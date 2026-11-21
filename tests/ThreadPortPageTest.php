<?php

declare(strict_types=1);

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Http\PortPageController;
use ForumRewrite\Http\RouteServices;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\View\TemplateRenderer;

require __DIR__ . '/../autoload.php';

/** The bookmarklet's form is held, the preview shows Port to approved members, and Port writes the thread. */
final class ThreadPortPageTest
{
    private const MEMBER = 'openpgp:abababababababababababababababababababab';
    private const PORT = "Port-Format: hn-v1\nThread-Title: Ask HN: Test\nThread-Link: https://news.ycombinator.com/item?id=500\n"
        . "%%\nItem: 500\nAuthor: asker\nCreated-At: 2026-01-01T00:00:00Z\nTitle: Ask HN: Test\n\nQuestion?\n"
        . "%%\nItem: 501\nParent: 500\nAuthor: one\nCreated-At: 2026-01-01T00:01:00Z\n\nAnswer\n"
        . "%%\nItem: 502\nParent: 501\nAuthor: two\nCreated-At: 2026-01-01T00:02:00Z\n\nFollow-up\n";

    private string $root = '';

    public function testCrossSitePostIsHeldThenAnApprovedMemberPorts(): void
    {
        $this->root = '';
        $this->site(true, false);
        $member = ['identity_id' => self::MEMBER, 'username_token' => 'ilyag', 'is_approved' => 1, '_authenticated_identity' => true];

        // The bookmarklet's form: from another site, no session.
        [$status, $html] = $this->call(null, 'receive', 'POST', ['port' => self::PORT], ['HTTP_SEC_FETCH_SITE' => 'cross-site']);
        threadPortPageAssertSame(303, $status);
        preg_match('#/port/([a-f0-9]{12})#', $html, $m);
        $id = $m[1] ?? '';
        threadPortPageAssertSame(12, strlen($id));

        [$status, $html] = $this->call($member, 'preview', 'GET', [], [], $id);
        threadPortPageAssertSame(200, $status);
        threadPortPageAssertContains('Ask HN: Test', $html);
        threadPortPageAssertContains('2 comments', $html);
        threadPortPageAssertContains('<form method="post" action="/port/' . $id . '">', $html);

        // Port from another site is refused; nothing is written.
        [$status, $html] = $this->call($member, 'preview', 'POST', [], ['HTTP_SEC_FETCH_SITE' => 'cross-site'], $id);
        threadPortPageAssertSame(409, $status);
        threadPortPageAssertContains('Nothing was posted', $html);
        threadPortPageAssertSame(0, $this->portedCount());

        [$status, $html] = $this->call($member, 'preview', 'POST', [], ['HTTP_SEC_FETCH_SITE' => 'same-origin'], $id);
        threadPortPageAssertSame(303, $status);
        threadPortPageAssertContains('/threads/thread-', $html);
        threadPortPageAssertSame(3, $this->portedCount());

        // The held port is gone; sending it again shows the existing thread instead of Port.
        threadPortPageAssertSame(404, $this->call($member, 'preview', 'GET', [], [], $id)[0]);
        [, $html] = $this->call(null, 'receive', 'POST', ['port' => self::PORT]);
        preg_match('#/port/([a-f0-9]{12})#', $html, $m);
        [, $html] = $this->call($member, 'preview', 'GET', [], [], $m[1]);
        threadPortPageAssertContains('Already ported.', $html);
    }

    public function testGuestsSeeSignInOrWaitingAndAFailedWriteKeepsTheRetry(): void
    {
        $this->root = '';
        $this->site(true, false);
        [, $html] = $this->call(null, 'receive', 'POST', ['port' => self::PORT]);
        preg_match('#/port/([a-f0-9]{12})#', $html, $m);
        $id = $m[1];
        [, $html] = $this->call(null, 'preview', 'GET', [], [], $id);
        threadPortPageAssertContains('as an approved member to port', $html);
        threadPortPageAssertSame(false, str_contains($html, '<form method="post"'));
        [$status] = $this->call(null, 'preview', 'POST', [], ['HTTP_SEC_FETCH_SITE' => 'same-origin'], $id);
        threadPortPageAssertSame(409, $status);
        threadPortPageAssertSame(0, $this->portedCount());

        $this->site(true, true);
        [, $html] = $this->call(null, 'receive', 'POST', ['port' => self::PORT]);
        preg_match('#/port/([a-f0-9]{12})#', $html, $m);
        [, $html] = $this->call(null, 'preview', 'GET', [], [], $m[1]);
        threadPortPageAssertContains('Waiting for a member to port it.', $html);

        // A write failure keeps the held port with the error and a retry button.
        $member = ['identity_id' => self::MEMBER, 'username_token' => 'ilyag', 'is_approved' => 1, '_authenticated_identity' => true];
        $hook = $this->root . '/.git/hooks/pre-commit';
        file_put_contents($hook, "#!/bin/sh\nexit 1\n");
        chmod($hook, 0755);
        try {
            [$status, $html] = $this->call($member, 'preview', 'POST', [], ['HTTP_SEC_FETCH_SITE' => 'same-origin'], $m[1]);
        } finally {
            unlink($hook);
        }
        threadPortPageAssertSame(409, $status);
        threadPortPageAssertContains('Try again', $html);
        threadPortPageAssertContains('you can try again', $this->call($member, 'preview', 'GET', [], [], $m[1])[1]);
        threadPortPageAssertSame('', trim((string) shell_exec('cd ' . escapeshellarg($this->root) . ' && git status --porcelain records')));
        threadPortPageAssertSame(0, $this->portedCount());

        // The retry works once the commit can go through.
        [$status] = $this->call($member, 'preview', 'POST', [], ['HTTP_SEC_FETCH_SITE' => 'same-origin'], $m[1]);
        threadPortPageAssertSame(303, $status);
        threadPortPageAssertSame(3, $this->portedCount());
    }

    public function testMalformedFormAndFlagOff(): void
    {
        $this->root = '';
        $this->site(true, false);
        [$status, $html] = $this->call(null, 'receive', 'POST', ['port' => "Port-Format: hn-v0\n%%\n"]);
        threadPortPageAssertSame(422, $status);
        threadPortPageAssertContains('Nothing was posted.', $html);

        $application = new Application(dirname(__DIR__), $this->root, $this->root . '.sqlite3', $this->root . '-public');
        $server = $_SERVER;
        $_SERVER['HTTP_SEC_FETCH_SITE'] = 'cross-site';
        $_SERVER['HTTP_ORIGIN'] = 'https://news.ycombinator.com';
        http_response_code(200);
        ob_start();
        $_POST = ['port' => self::PORT];
        try {
            $application->handle('POST', '/port');
        } finally {
            $routed = (string) ob_get_clean();
            $_POST = [];
            $_SERVER = $server;
        }
        threadPortPageAssertSame(303, http_response_code());
        threadPortPageAssertContains('/port/', $routed);

        $this->site(false, false);
        $application = new Application(dirname(__DIR__), $this->root, $this->root . '.sqlite3', $this->root . '-public');
        http_response_code(200);
        ob_start();
        $_POST = ['port' => self::PORT];
        try {
            $application->handle('POST', '/port');
        } finally {
            ob_end_clean();
            $_POST = [];
        }
        threadPortPageAssertSame(404, http_response_code());
    }

    public function testBookmarkletsPageListsPortHnOnlyWithTheFlag(): void
    {
        $this->root = '';
        foreach ([true => 2, false => 0] as $enabled => $expected) {
            $this->site((bool) $enabled, false);
            $application = new Application(dirname(__DIR__), $this->root, $this->root . '.sqlite3', $this->root . '-public');
            http_response_code(200);
            ob_start();
            try {
                $application->handle('GET', '/tools/bookmarklets/');
            } finally {
                $html = (string) ob_get_clean();
            }
            threadPortPageAssertSame($expected, substr_count($html, 'data-bookmarklet-kind="hn-port"'));
        }
    }

    public function testMembersSeePortsWaitingOnlyWhileGuestsMayPort(): void
    {
        $this->root = '';
        $member = ['identity_id' => self::MEMBER, 'username_token' => 'ilyag', 'is_approved' => 1, '_authenticated_identity' => true];
        $this->site(true, true);
        $this->call(null, 'receive', 'POST', ['port' => self::PORT]);
        [$status, $html] = $this->call($member, 'receive', 'GET');
        threadPortPageAssertSame(200, $status);
        threadPortPageAssertContains('Ports waiting', $html);
        threadPortPageAssertContains('Ask HN: Test</a>', $html);
        threadPortPageAssertContains('2 comments', $html);
        threadPortPageAssertSame(false, str_contains($this->call(null, 'receive', 'GET')[1], 'Ask HN: Test'));
        $tools = $this->page('/tools/bookmarklets/');
        threadPortPageAssertContains('<a href="/port/">Ports waiting</a>', $tools);

        $this->site(true, false);
        threadPortPageAssertSame(false, str_contains($this->call($member, 'receive', 'GET')[1], 'Ask HN: Test'));
        threadPortPageAssertSame(false, str_contains($this->page('/tools/bookmarklets/'), 'Ports waiting'));
    }

    private function page(string $path): string
    {
        $application = new Application(dirname(__DIR__), $this->root, $this->root . '.sqlite3', $this->root . '-public');
        http_response_code(200);
        ob_start();
        try {
            $application->handle('GET', $path);
        } finally {
            $html = (string) ob_get_clean();
        }
        return $html;
    }

    private function portedCount(): int
    {
        return (int) (new PDO('sqlite:' . $this->root . '.sqlite3'))->query('SELECT COUNT(*) FROM posts WHERE ported_from IS NOT NULL')->fetchColumn();
    }

    /**
     * @param array<string, mixed>|null $viewer
     * @param array<string, string> $input
     * @param array<string, string> $headers
     * @return array{0: int, 1: string}
     */
    private function call(?array $viewer, string $action, string $method, array $input = [], array $headers = [], string $id = ''): array
    {
        $services = new RouteServices(
            $this->root . '.sqlite3', new TemplateRenderer(__DIR__ . '/../templates', 'test'), 'test', false,
            static fn (): ?array => $viewer, $this->root, dirname(__DIR__), null, null,
            FeatureFlagEvaluator::forApplication($this->root, dirname(__DIR__)), static fn (): ?array => $viewer,
        );
        $flags = FeatureFlagEvaluator::forApplication($this->root, dirname(__DIR__));
        $controller = new PortPageController($services, static fn (): ?array => $viewer,
            static fn (string $flag): bool => $flags->isEnabled($flag), $services->writer()->heldPorts());
        $server = $_SERVER;
        foreach ($headers as $name => $value) $_SERVER[$name] = $value;
        $_POST = $input;
        http_response_code(200);
        ob_start();
        try {
            $action === 'receive' ? $controller->receive($method, []) : $controller->preview($method, $id, []);
        } finally {
            $html = (string) ob_get_clean();
            $_SERVER = $server;
            $_POST = [];
        }
        return [http_response_code(), $html];
    }

    private function site(bool $enabled, bool $guests): void
    {
        if ($this->root === '') {
            $this->root = sys_get_temp_dir() . '/forum-rewrite-thread-port-page-' . bin2hex(random_bytes(6));
            mkdir($this->root, 0777, true);
            $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/fixtures/parity_minimal_v1', FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
            foreach ($items as $item) {
                $target = $this->root . '/' . $items->getSubPathName();
                $item->isDir() ? (is_dir($target) || mkdir($target, 0777, true)) : copy($item->getPathname(), $target);
            }
            mkdir($this->root . '-public', 0777, true);
        }
        file_put_contents($this->root . '/records/instance/feature-flags.txt', "Schema: site-feature-flags-v1\n\n"
            . FeatureFlagRegistry::THREAD_PORT_ENABLED . ': ' . ($enabled ? 'true' : 'false') . "\n"
            . FeatureFlagRegistry::THREAD_PORT_GUESTS_ENABLED . ': ' . ($guests ? 'true' : 'false') . "\n");
        foreach (['git init -q', 'git config user.name "Forum Rewrite"', 'git config user.email "forum-rewrite@example.invalid"', 'git add .', 'git -c commit.gpgsign=false commit -q -m flags --allow-empty'] as $command) {
            exec('cd ' . escapeshellarg($this->root) . ' && ' . $command . ' 2>&1', $output, $status);
            if ($status !== 0) {
                throw new RuntimeException('Command failed: ' . $command . ' ' . implode("\n", $output));
            }
        }
        $application = new Application(dirname(__DIR__), $this->root, $this->root . '.sqlite3', $this->root . '-public');
        ob_start();
        try {
            $application->handle('GET', '/');
        } finally {
            ob_end_clean();
        }
        (new \ForumRewrite\ReadModel\ReadModelBuilder($this->root, $this->root . '.sqlite3', new CanonicalRecordRepository($this->root)))->rebuild();
    }
}

function threadPortPageAssertSame(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function threadPortPageAssertContains(string $needle, string $haystack): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException('Expected to find ' . var_export($needle, true) . ' in ' . substr($haystack, 0, 900));
    }
}
