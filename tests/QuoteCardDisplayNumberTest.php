<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;

final class QuoteCardDisplayNumberTest
{
    public function testQdbInstanceShowsTheImportedQuoteNumberNotTheInternalPostId(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment();
        $this->writeImportedQuote($repositoryRoot, 'thread-20030613104735-qdb-42', 'The quoted body.');

        putenv('FORUM_SITE_ID=qdb');
        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
            // Plain "/" renders the qdb welcome page, not the quote list -
            // "/latest" is the real board route, same as a visitor browsing.
            $board = $this->render($application, '/latest');
        } finally {
            putenv('FORUM_SITE_ID');
        }

        assertStringContains('>#42</a>', $board);
        assertStringNotContains('>#thread-20030613104735-qdb-42</a>', $board);
    }

    public function testNonQdbInstanceStillShowsTheFullPostIdUnchanged(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment();
        $this->writeImportedQuote($repositoryRoot, 'thread-20030613104735-qdb-42', 'The quoted body.');

        // No FORUM_SITE_ID override: default (zenmemes) rendering path.
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
        $board = $this->render($application, '/?view=all&sort=newest');

        // zenmemes/chouse boards render thread_card.php, not quote_card.php,
        // so there is no bare "#<id>" permalink text to regress at all -
        // confirming this stage's change is scoped to the qdb card only.
        assertStringNotContains('quote-card', $board);
    }

    public function testBareNumericPathRedirectsToTheQuoteWithThatNumber(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment();
        $this->writeImportedQuote($repositoryRoot, 'thread-20030613104735-qdb-42', 'The quoted body.');

        putenv('FORUM_SITE_ID=qdb');
        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
            $body = $this->render($application, '/42');
        } finally {
            putenv('FORUM_SITE_ID');
        }

        assertSame(302, http_response_code());
        assertStringContains('href="/threads/thread-20030613104735-qdb-42"', $body);
    }

    public function testBareNumericPathWithNoMatchingQuoteStill404s(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment();
        $this->writeImportedQuote($repositoryRoot, 'thread-20030613104735-qdb-42', 'The quoted body.');

        putenv('FORUM_SITE_ID=qdb');
        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
            $this->render($application, '/999999');
        } finally {
            putenv('FORUM_SITE_ID');
        }

        assertSame(404, http_response_code());
    }

    public function testBareNumericPathOnNonQdbProfileDoesNotRedirect(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment();
        $this->writeImportedQuote($repositoryRoot, 'thread-20030613104735-qdb-42', 'The quoted body.');

        // No FORUM_SITE_ID override: default (zenmemes) profile - the bare-path
        // block this feature extends is qdb-only, so /42 should 404 like any
        // other unmatched path, not resolve by quote number.
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
        $this->render($application, '/42');

        assertSame(404, http_response_code());
    }

    private function render(Application $application, string $path): string
    {
        ob_start();
        $application->handle('GET', $path);

        return (string) ob_get_clean();
    }

    private function writeImportedQuote(string $repositoryRoot, string $postId, string $body): void
    {
        file_put_contents(
            $repositoryRoot . '/records/posts/' . $postId . '.txt',
            "Post-ID: {$postId}\n"
            . "Created-At: 2003-06-13T10:47:35Z\n"
            . "Board-Tags: general\n"
            . "Imported-Score-Seed: 5\n"
            . "Imported-Vote-Count-Seed: 7\n"
            . "\n{$body}\n"
        );
        $this->runCommand($repositoryRoot, 'git add .');
        $this->runCommand($repositoryRoot, 'git commit -m "Add imported quote fixture"');
    }

    /**
     * @return array{string,string}
     */
    private function createTempEnvironment(): array
    {
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-quote-card-repo-' . bin2hex(random_bytes(6));
        mkdir($repositoryRoot, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-quote-card-db-' . bin2hex(random_bytes(6)) . '.sqlite3';

        $this->runCommand($repositoryRoot, 'git init');
        $this->runCommand($repositoryRoot, 'git config user.name "Forum Rewrite"');
        $this->runCommand($repositoryRoot, 'git config user.email "forum-rewrite@example.invalid"');
        $this->runCommand($repositoryRoot, 'git add .');
        $this->runCommand($repositoryRoot, 'git commit -m "Initialize test repository"');

        return [$repositoryRoot, $databasePath];
    }

    private function runCommand(string $cwd, string $command): void
    {
        $descriptor = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
        if (!is_resource($descriptor)) {
            throw new RuntimeException('Unable to run command: ' . $command);
        }
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($descriptor);
        if ($exitCode !== 0) {
            throw new RuntimeException('Command failed (' . $exitCode . '): ' . $command);
        }
    }

    private function copyDirectory(string $source, string $destination): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $targetPath = $destination . '/' . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0777, true);
                }

                continue;
            }

            copy($item->getPathname(), $targetPath);
        }
    }
}

if (!function_exists('assertStringContains')) {
    function assertStringContains(string $needle, string $haystack): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException('Failed asserting that string contains: ' . $needle);
        }
    }
}

if (!function_exists('assertStringNotContains')) {
    function assertStringNotContains(string $needle, string $haystack): void
    {
        if (str_contains($haystack, $needle)) {
            throw new RuntimeException('Failed asserting that string does not contain: ' . $needle);
        }
    }
}
