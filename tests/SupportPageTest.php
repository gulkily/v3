<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Write\LocalWriteService;

final class SupportPageTest
{
    /** The fixture's committed root-approved operator. */
    private const MEMBER = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';

    public function testPageExistsOnlyWithPledgesOnAndANamedThread(): void
    {
        [$root, $database, $service] = $this->environment();
        $thread = $this->thread($service, 'Support ZenMemes', "Keep the work going.\ngoal: 50 squares");
        $page = fn (): string => $this->render(new Application(dirname(__DIR__), $root, $database), '/support/');

        $this->withProfile(null, function () use ($page): void {
            putenv('FORUM_PLEDGES_ENABLED=1');
            try {
                supportAssertContains('There is no page at this address.', $page());
            } finally {
                putenv('FORUM_PLEDGES_ENABLED');
            }
        });
        $this->withProfile($thread, function () use ($page): void {
            putenv('FORUM_PLEDGES_ENABLED=0');
            try {
                supportAssertContains('There is no page at this address.', $page());
            } finally {
                putenv('FORUM_PLEDGES_ENABLED');
            }
        });
    }

    public function testPageShowsThePitchAndTheThreadsTotals(): void
    {
        [$root, $database, $service] = $this->environment();
        $thread = $this->thread($service, 'Support ZenMemes', "Keep the work going.\ngoal: 50 squares");
        $this->reply($service, $thread, 'pledge: 3');
        $this->reply($service, $thread, 'pledge: 2.5');
        $app = fn (): Application => new Application(dirname(__DIR__), $root, $database);

        $this->withProfile($thread, function () use ($app, $thread): void {
            putenv('FORUM_PLEDGES_ENABLED=1');
            try {
                $support = $this->render($app(), '/support/');
                $threadPage = $this->render($app(), '/threads/' . rawurlencode($thread));
            } finally {
                putenv('FORUM_PLEDGES_ENABLED');
            }
            supportAssertContains('<h1 class="support__title" id="support-title">Support ZenMemes</h1>', $support);
            supportAssertContains('Keep the work going.', $support);
            // The goal line is in the totals, not the pitch.
            supportAssertNotContains('goal: 50', $support);
            $sum = '<strong>5.5</strong> of 50 squares pledged';
            supportAssertContains($sum, $support);
            supportAssertContains($sum, $threadPage);
            supportAssertContains('href="/threads/' . rawurlencode($thread) . '">Open as a thread</a>', $support);
            supportAssertContains('<a href="/support/">Open the wall</a>', $threadPage);
        });
    }

    public function testANamedThreadThatDoesNotExistShowsAnEmptyPage(): void
    {
        [$root, $database] = $this->environment();
        $this->withProfile('thread-not-yet', function () use ($root, $database): void {
            putenv('FORUM_PLEDGES_ENABLED=1');
            try {
                $support = $this->render(new Application(dirname(__DIR__), $root, $database), '/support/');
            } finally {
                putenv('FORUM_PLEDGES_ENABLED');
            }
            supportAssertContains('id="support-title"', $support);
            supportAssertNotContains('data-pledge-totals', $support);
        });
    }

    private function withProfile(?string $supportThread, callable $test): void
    {
        $profiles = SiteProfileRegistry::all();
        foreach ($profiles as $siteId => $profile) {
            $profiles[$siteId]['supportThread'] = $supportThread;
            $profiles[$siteId]['appName'] = 'ZenMemes';
        }
        SiteProfileRegistry::setProfilesForTesting($profiles);
        try {
            $test();
        } finally {
            SiteProfileRegistry::setProfilesForTesting(null);
        }
    }

    /** @return array{0: string, 1: string, 2: LocalWriteService} */
    private function environment(): array
    {
        $root = sys_get_temp_dir() . '/forum-rewrite-support-repo-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $root);
        foreach (['git init', 'git config user.name "Forum Rewrite"', 'git config user.email "forum-rewrite@example.invalid"', 'git add .', 'git -c commit.gpgsign=false commit -m init'] as $command) {
            $this->run($root, $command);
        }
        // The read model sits in its own directory so its rebuild lock is not shared with other tests.
        $databaseDirectory = sys_get_temp_dir() . '/forum-rewrite-support-db-' . bin2hex(random_bytes(6));
        mkdir($databaseDirectory, 0777, true);
        $database = $databaseDirectory . '/read-model.sqlite3';
        $artifacts = sys_get_temp_dir() . '/forum-rewrite-support-public-' . bin2hex(random_bytes(6));
        mkdir($artifacts, 0777, true);
        $this->render(new Application(dirname(__DIR__), $root, $database, $artifacts), '/');
        $service = new LocalWriteService($root, $database, $artifacts, new CanonicalRecordRepository($root), featureFlags: FeatureFlagEvaluator::forRepository($root));

        return [$root, $database, $service];
    }

    private function thread(LocalWriteService $service, string $subject, string $body): string
    {
        return (string) $service->createThread(['board_tags' => 'general', 'subject' => $subject, 'body' => $body, 'author_identity_id' => self::MEMBER])['thread_id'];
    }

    private function reply(LocalWriteService $service, string $threadId, string $body, ?string $author = self::MEMBER, ?string $parentId = null): string
    {
        $input = ['thread_id' => $threadId, 'parent_id' => $parentId ?? $threadId, 'body' => $body];
        if ($author !== null) {
            $input['author_identity_id'] = $author;
        }

        return (string) $service->createReply($input)['post_id'];
    }

    private function render(Application $application, string $path): string
    {
        ob_start();
        $application->handle('GET', $path);

        return (string) ob_get_clean();
    }

    private function run(string $directory, string $command): void
    {
        exec('cd ' . escapeshellarg($directory) . ' && ' . $command . ' 2>&1', $output, $status);
        if ($status !== 0) {
            throw new RuntimeException('Command failed: ' . $command . "\n" . implode("\n", $output));
        }
    }

    private function copyDirectory(string $source, string $destination): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $target = $destination . '/' . $iterator->getSubPathname();
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0777, true);
                }
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }
}

if (!function_exists('supportAssertSame')) {
    function supportAssertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException('Failed asserting identical values. Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
        }
    }

    function supportAssertContains(string $needle, string $haystack): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException('Failed asserting that output contains: ' . $needle);
        }
    }

    function supportAssertNotContains(string $needle, string $haystack): void
    {
        if (str_contains($haystack, $needle)) {
            throw new RuntimeException('Failed asserting that output does not contain: ' . $needle);
        }
    }
}
