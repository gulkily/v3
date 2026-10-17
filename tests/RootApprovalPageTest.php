<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ProfileRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\Write\LocalWriteService;

final class RootApprovalPageTest
{
    private const KEY = 'openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
    private const ID = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
    private const SOURCE = 'records/approval-seeds/' . self::KEY . '.txt';
    private const URL = '/approvals/root/' . self::KEY;

    public function testReadableSeedLinksFromEveryHistoryVariantAndBackToSource(): void
    {
        $this->fixture(function ($workspace, $root, $db): void {
            $profile = ProfileRepository::byIdentityId(new PDO('sqlite:' . $db), self::ID);
            foreach (['/user/' . $profile['username_token'], '/profiles/' . self::KEY,
                '/forte/user/' . $profile['username_token'], '/forte/profiles/' . self::KEY] as $path) {
                $html = $this->render($root, $db, $path);
                assertStringContains('href="' . self::URL . '">Approval evidence</a>', $html);
            }
            $html = $this->render($root, $db, self::URL);
            assertSame(200, http_response_code());
            assertStringContains('<h1>Root approval</h1>', $html);
            assertStringContains('initial approved fixture user', $html);
            assertStringContains('Seed this identity as approved', $html);
            assertStringContains('Authorizer: not recorded. Acceptance time: not recorded.', $html);
            assertStringContains('Repository history is unavailable for this seed.', $html);
            assertStringContains('href="/profiles/' . self::KEY . '"', $html);
            assertStringContains('href="/user/' . $profile['username_token'] . '#account-keys"', $html);
            assertStringContains('href="/source/current/' . self::SOURCE . '"', $html);
            assertStringContains('Approved-Identity-ID: ' . self::ID, $this->render($root, $db, '/source/current/' . self::SOURCE));
        });
    }

    public function testMissingMalformedAndMismatchedSeedsHaveSafeErrors(): void
    {
        $this->fixture(function ($workspace, $root, $db): void {
            foreach (['/approvals/root/openpgp-' . str_repeat('a', 40), '/approvals/root/not-a-key', '/approvals/root/openpgp-%2e%2e'] as $url) {
                assertStringContains('Root approval unavailable', $this->render($root, $db, $url));
                assertSame(404, http_response_code());
            }
            foreach (["not a canonical record\n", "Approved-Identity-ID: openpgp:" . str_repeat('b', 40) . "\nSeed-Reason: mismatch\n\n"] as $contents) {
                $workspace->put($root . '/' . self::SOURCE, $contents);
                $html = $this->render($root, $db, self::URL);
                assertSame(404, http_response_code());
                assertStringNotContains('CanonicalRecordParseException', $html);
                assertStringNotContains('mismatch', $html);
            }
            unlink($root . '/' . self::SOURCE);
            $workspace->put($workspace->root . '/outside.txt', "Approved-Identity-ID: " . self::ID . "\nSeed-Reason: outside private record\n\n");
            symlink($workspace->root . '/outside.txt', $root . '/' . self::SOURCE);
            assertStringNotContains('outside private record', $this->render($root, $db, self::URL));
            assertSame(404, http_response_code());
        });
    }

    public function testEscapedSeedFactsAndOptionalCurrentProfile(): void
    {
        $this->fixture(function ($workspace, $root, $db): void {
            $workspace->put($root . '/' . self::SOURCE, "Approved-Identity-ID: " . self::ID . "\nSeed-Reason: <script>alert(1)</script>\n\n<img src=x onerror=alert(2)>\n");
            $pdo = new PDO('sqlite:' . $db);
            $pdo->exec("UPDATE profiles SET username='renamed', username_token='renamed'");
            $html = $this->render($root, $db, self::URL);
            assertStringContains('Current account: <a href="/user/renamed#account-keys">renamed</a>', $html);
            assertStringContains('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
            assertStringNotContains('<script>alert(1)', $html);
            assertStringNotContains('<img src=x', $html);
            $pdo->exec('DELETE FROM profiles');
            $html = $this->render($root, $db, self::URL);
            assertSame(200, http_response_code());
            assertStringContains(self::KEY, $html);
            assertStringNotContains('Current account:', $html);
            assertStringNotContains('href="/profiles/' . self::KEY . '"', $html);
        });
    }

    public function testPrivateSeedPageUsesTheSameAccessBoundaryAsRawSource(): void
    {
        $this->fixture(function ($workspace, $root, $db): void {
            putenv('FORUM_APPROVED_MEMBERS_ONLY=true');
            foreach ([self::URL, '/source/current/' . self::SOURCE] as $url) {
                $_SESSION = [];
                $_COOKIE = ['identity_hint' => self::ID];
                $denied = $this->render($root, $db, $url);
                assertSame(401, http_response_code());
                assertStringNotContains('initial approved fixture user', $denied);
                $_SESSION = ['authenticated_identity_id' => self::ID];
                $_COOKIE = [];
                $allowed = $this->render($root, $db, $url);
                assertSame(200, http_response_code());
                assertStringContains('initial approved fixture user', $allowed);
            }
        });
    }

    public function testOrdinaryApprovalEvidenceStillOpensItsPost(): void
    {
        $this->fixture(function ($workspace, $root, $db): void {
            $workspace->git($root);
            (new ReadModelBuilder($root, $db, new CanonicalRecordRepository($root)))->rebuild();
            $writer = new LocalWriteService($root, $db, $workspace->root . '/artifacts', new CanonicalRecordRepository($root));
            $target = $writer->linkIdentity(['public_key' => file_get_contents(__DIR__ . '/fixtures/openpgp_signature/reaction-public-key.asc')]);
            putenv('FORUM_APPROVED_MEMBERS_ONLY=true');
            $_SESSION = ['authenticated_identity_id' => $target['identity_id']];
            $this->render($root, $db, self::URL);
            assertSame(403, http_response_code());
            putenv('FORUM_APPROVED_MEMBERS_ONLY=false');
            $_SESSION = [];
            $grant = $writer->approveUser(['approver_identity_id' => self::ID, 'target_identity_id' => $target['identity_id'],
                'target_profile_slug' => $target['profile_slug'], 'thread_id' => $target['bootstrap_thread_id'], 'parent_id' => $target['bootstrap_post_id']]);
            $html = $this->render($root, $db, '/profiles/' . $target['profile_slug']);
            assertStringContains('href="/posts/' . $grant['post_id'] . '">Approval evidence</a>', $html);
            putenv('FORUM_APPROVED_MEMBERS_ONLY=true');
            $_SESSION = ['authenticated_identity_id' => $target['identity_id']];
            assertStringContains('initial approved fixture user', $this->render($root, $db, self::URL));
            assertSame(200, http_response_code());
        });
    }

    public function testRepositoryEvidenceBindsCurrentBytesWithoutInventingAcceptanceFacts(): void
    {
        $this->fixture(function ($workspace, $root, $db): void {
            $workspace->git($root);
            [, $sha] = $workspace->command(['git', '-C', $root, 'rev-parse', 'HEAD']);
            $sha = trim($sha);
            $html = $this->render($root, $db, self::URL);
            assertStringContains('href="/source/blob/' . $sha . '/' . self::SOURCE . '"', $html);
            assertStringContains('href="/source/commits/' . $sha . '"', $html);
            assertStringContains('Repository commit dates describe file history.', $html);
            assertStringContains('Authorizer: not recorded. Acceptance time: not recorded.', $html);
            assertStringContains('initial approved fixture user', $this->render($root, $db, '/source/blob/' . $sha . '/' . self::SOURCE));
            assertStringContains(self::SOURCE, $this->render($root, $db, '/source/commits/' . $sha));
            $workspace->put($root . '/' . self::SOURCE, "Approved-Identity-ID: " . self::ID . "\nSeed-Reason: changed current reason\n\n");
            $html = $this->render($root, $db, self::URL);
            assertStringContains('changed current reason', $html);
            assertStringContains('current seed differs from the latest repository version', $html);
            assertStringNotContains('href="/source/blob/' . $sha, $html);
            assertStringContains('href="/source/commits/' . $sha . '"', $html);
            foreach ([['add', self::SOURCE], ['commit', '-qm', 'Update root seed reason']] as $args) {
                [$code, $output] = $workspace->command(['git', '-C', $root, ...$args]);
                assertSame(0, $code, $output);
            }
            [, $updated] = $workspace->command(['git', '-C', $root, 'rev-parse', 'HEAD']);
            $html = $this->render($root, $db, self::URL);
            assertStringContains('href="/source/blob/' . trim($updated) . '/' . self::SOURCE . '"', $html);
            assertStringContains('href="/source/commits/' . $sha . '"', $html);
            assertStringContains('Update root seed reason', $html);
            assertStringNotContains('current seed differs', $html);
        });
    }

    public function testSeedHistoryIsBoundedAndExcludesOtherFiles(): void
    {
        $this->fixture(function ($workspace, $root, $db): void {
            $workspace->git($root);
            for ($index = 0; $index < 11; $index++) {
                $workspace->put($root . '/' . self::SOURCE, "Approved-Identity-ID: " . self::ID . "\nSeed-Reason: revision {$index}\n\n");
                foreach ([['add', self::SOURCE], ['commit', '-qm', 'Seed revision ' . $index]] as $args) {
                    [$code, $output] = $workspace->command(['git', '-C', $root, ...$args]);
                    assertSame(0, $code, $output);
                }
            }
            $workspace->put($root . '/unrelated.txt', "Unrelated\n");
            $workspace->command(['git', '-C', $root, 'add', 'unrelated.txt']);
            $workspace->command(['git', '-C', $root, 'commit', '-qm', 'Unrelated history event']);
            $html = $this->render($root, $db, self::URL);
            assertSame(10, substr_count($html, '<li><a href="/source/commits/'));
            assertStringNotContains('Unrelated history event', $html);
            assertStringNotContains('Seed revision 0', $html);
            assertStringContains('Seed revision 10', $html);
        });
    }

    private function fixture(callable $test): void
    {
        $workspace = new ImportTestWorkspace();
        $root = $workspace->repository('repository', true);
        $db = $workspace->root . '/read.sqlite3';
        $flag = getenv('FORUM_APPROVED_MEMBERS_ONLY');
        $session = $_SESSION ?? [];
        $cookies = $_COOKIE;
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION = []; $_COOKIE = [];
        putenv('FORUM_APPROVED_MEMBERS_ONLY=false');
        try {
            (new ReadModelBuilder($root, $db, new CanonicalRecordRepository($root)))->rebuild();
            $test($workspace, $root, $db);
        } finally {
            $_SESSION = $session; $_COOKIE = $cookies;
            putenv($flag === false ? 'FORUM_APPROVED_MEMBERS_ONLY' : 'FORUM_APPROVED_MEMBERS_ONLY=' . $flag);
            if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        }
    }

    private function render(string $root, string $db, string $url): string
    {
        http_response_code(200);
        ob_start();
        try {
            (new Application(dirname(__DIR__), $root, $db))->handle('GET', $url);
            return (string) ob_get_contents();
        } finally { ob_end_clean(); }
    }
}
