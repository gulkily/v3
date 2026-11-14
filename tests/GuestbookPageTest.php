<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;

final class GuestbookPageTest
{
    private const BOOK = 'guestbook-root';

    public function testBookShowsTopLevelRepliesOnStablePages(): void
    {
        $this->withBook(function (string $repositoryRoot, Application $application): void {
            for ($i = 1; $i <= 7; $i++) {
                $this->writeEntry($repositoryRoot, 'entry-' . $i, 'Note number ' . $i . '.', sprintf('2026-11-01T00:%02d:00Z', $i));
            }
            $this->writePost($repositoryRoot, 'entry-2-reply', "Thread-ID: " . self::BOOK . "\nParent-ID: entry-2\n\nA reply under an entry.", '2026-11-01T01:00:00Z');
            $this->writePost($repositoryRoot, 'entry-2-reply-2', "Thread-ID: " . self::BOOK . "\nParent-ID: entry-2-reply\n\nAnd one under that.", '2026-11-01T01:01:00Z');
            $this->commit($repositoryRoot);

            // Seven entries fill pages 1-3; the newest spread is pages 3 and 4.
            $latest = $this->render($application, '/guestbook/');
            assertStringContains('id="page-3"', $latest);
            assertStringContains('id="page-4"', $latest);
            assertStringContains('Note number 7.', $latest);
            assertStringNotContains('Note number 6.', $latest);
            assertStringContains('href="/guestbook/?page=1"', $latest, 'Earlier pages go to the first spread.');

            $first = $this->render($application, '/guestbook/?page=1');
            foreach ([1, 2, 3, 4, 5, 6] as $i) assertStringContains('Note number ' . $i . '.', $first);
            assertTrue(strpos($first, 'Note number 1.') < strpos($first, 'Note number 6.'), 'Oldest first.');
            assertStringNotContains('A reply under an entry.', $first, 'Replies to an entry stay in the thread.');
            assertSame($first, $this->render($application, '/guestbook/?page=2'), 'Any page of a spread shows that spread.');

            // New entries never move old ones.
            $this->writeEntry($repositoryRoot, 'entry-8', 'Note number 8.', '2026-11-02T00:00:00Z');
            $this->commit($repositoryRoot);
            $again = $this->render($application, '/guestbook/?page=1');
            foreach ([1, 2, 3, 4, 5, 6] as $i) assertStringContains('Note number ' . $i . '.', $again);
            assertStringNotContains('Note number 8.', $again);
        });
    }

    public function testRetractedEntriesLeaveTheBook(): void
    {
        $this->withBook(function (string $repositoryRoot, Application $application): void {
            $this->writeEntry($repositoryRoot, 'entry-kept', 'Still here.', '2026-11-01T00:01:00Z');
            $this->writeEntry($repositoryRoot, 'entry-gone', 'Taken back.', '2026-11-01T00:02:00Z');
            $this->commit($repositoryRoot);
            assertStringContains('Taken back.', $this->render($application, '/guestbook/'));
            // The read model marks a retracted post; the book follows it.
            $pdo = new PDO('sqlite:' . dirname($repositoryRoot) . '/read-model.sqlite3');
            $pdo->exec("UPDATE posts SET retracted_at = '2026-11-02T00:00:00Z' WHERE post_id = 'entry-gone'");
            $html = $this->render($application, '/guestbook/');
            assertStringContains('Still here.', $html);
            assertStringNotContains('Taken back.', $html);
        });
    }

    public function testSigningTheBookFromTheLastPageReturnsToIt(): void
    {
        $this->withBook(function (string $repositoryRoot, Application $application): void {
            for ($i = 1; $i <= 4; $i++) {
                $this->writeEntry($repositoryRoot, 'entry-' . $i, 'Note number ' . $i . '.', sprintf('2026-11-01T00:%02d:00Z', $i));
            }
            $this->commit($repositoryRoot);
            $latest = $this->render($application, '/guestbook/');
            assertStringContains('Leave a note for whoever reads this next', $latest);
            assertStringContains('name="return_to" value="/guestbook/"', $latest);
            assertStringContains('name="parent_id" value="' . self::BOOK . '"', $latest);
            assertStringContains('Sign the book', $latest);
            assertStringContains('/assets/browser_signing.', $latest);
            assertStringContains('/assets/guestbook.', $latest, 'The book carries its own stylesheet.');
            $this->writeEntry($repositoryRoot, 'entry-5', 'Note number 5.', '2026-11-01T00:05:00Z');
            $this->writeEntry($repositoryRoot, 'entry-6', 'Note number 6.', '2026-11-01T00:06:00Z');
            $this->writeEntry($repositoryRoot, 'entry-7', 'Note number 7.', '2026-11-01T00:07:00Z');
            $this->commit($repositoryRoot);
            $first = $this->render($application, '/guestbook/?page=1');
            assertStringNotContains('name="return_to"', $first, 'Only the newest spread carries the form.');
            assertStringContains('href="/guestbook/#sign"', $first);

            // The form as a browser without JavaScript sends it.
            $previous = $_POST;
            $_POST = ['thread_id' => self::BOOK, 'parent_id' => self::BOOK, 'board_tags' => 'general', 'author_identity_id' => '',
                'body' => 'Signed without a script.', 'return_to' => '/guestbook/', 'body_format' => 'plain'];
            try {
                ob_start();
                $application->handle('POST', '/compose/reply');
                $redirect = (string) ob_get_clean();
            } finally {
                $_POST = $previous;
            }
            assertStringContains('/guestbook/?created_post_id=', $redirect);
            assertStringContains('Signed without a script.', $this->render($application, '/guestbook/'));

            // Elsewhere, return_to cannot send a reply to the book.
            $_POST = ['thread_id' => 'root-001', 'parent_id' => 'root-001', 'board_tags' => 'general', 'author_identity_id' => '',
                'body' => 'An ordinary reply.', 'return_to' => '/guestbook/', 'body_format' => 'plain'];
            try {
                ob_start();
                $application->handle('POST', '/compose/reply');
                $other = (string) ob_get_clean();
            } finally {
                $_POST = $previous;
            }
            assertStringContains('/threads/root-001?created_post_id=', $other);
        });
    }

    public function testEmptyMissingAndOutOfRangeBooks(): void
    {
        $this->withBook(function (string $repositoryRoot, Application $application): void {
            $empty = $this->render($application, '/guestbook/');
            assertStringContains('id="page-1"', $empty, 'An empty book still opens.');
            assertStringContains('id="page-2"', $empty);
            assertStringNotContains('guestbook__entry"', $empty);
            assertStringContains('Not Found', $this->render($application, '/guestbook/?page=9'));
            assertStringContains('Not Found', $this->render($application, '/guestbook/?page=abc'));
        });
        $this->withBook(function (string $repositoryRoot, Application $application): void {
            assertStringContains('Not Found', $this->render($application, '/guestbook/'));
        }, null);
        $this->withBook(function (string $repositoryRoot, Application $application): void {
            putenv(FeatureFlagRegistry::GUESTBOOK_ENABLED . '=false');
            assertStringContains('Not Found', $this->render($application, '/guestbook/'));
        });
        $this->withBook(function (string $repositoryRoot, Application $application): void {
            $html = $this->render($application, '/guestbook/');
            assertStringContains('id="page-1"', $html, 'A named thread that does not exist yet shows an empty book.');
        }, 'thread-not-yet-written');
    }

    /** @param callable(string, Application): void $test */
    private function withBook(callable $test, ?string $guestbookThread = self::BOOK): void
    {
        $oldFlag = getenv(FeatureFlagRegistry::GUESTBOOK_ENABLED);
        putenv(FeatureFlagRegistry::GUESTBOOK_ENABLED . '=true');
        $profiles = SiteProfileRegistry::all();
        foreach ($profiles as $siteId => $profile) $profiles[$siteId]['guestbookThread'] = $guestbookThread;
        SiteProfileRegistry::setProfilesForTesting($profiles);
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-guestbook-' . bin2hex(random_bytes(6)) . '/repo';
        try {
            mkdir($repositoryRoot, 0777, true);
            $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);
            foreach (['git init', 'git config user.name "Forum Rewrite"', 'git config user.email "forum-rewrite@example.invalid"'] as $command) {
                $this->runCommand($repositoryRoot, $command);
            }
            $this->writePost($repositoryRoot, self::BOOK, "Subject: Guestbook\n\nSign below.", '2026-11-01T00:00:00Z');
            $this->commit($repositoryRoot);
            $test($repositoryRoot, new Application(dirname(__DIR__), $repositoryRoot, dirname($repositoryRoot) . '/read-model.sqlite3'));
        } finally {
            SiteProfileRegistry::setProfilesForTesting(null);
            putenv($oldFlag === false ? FeatureFlagRegistry::GUESTBOOK_ENABLED : FeatureFlagRegistry::GUESTBOOK_ENABLED . '=' . $oldFlag);
            exec('rm -rf ' . escapeshellarg(dirname($repositoryRoot)));
        }
    }

    private function writeEntry(string $repositoryRoot, string $postId, string $text, string $createdAt): void
    {
        $this->writePost($repositoryRoot, $postId, "Thread-ID: " . self::BOOK . "\nParent-ID: " . self::BOOK . "\n\n" . $text, $createdAt);
    }

    private function writePost(string $repositoryRoot, string $postId, string $rest, string $createdAt): void
    {
        file_put_contents($repositoryRoot . '/records/posts/' . $postId . '.txt', "Post-ID: {$postId}\nCreated-At: {$createdAt}\nBoard-Tags: general\n{$rest}\n");
    }

    private function commit(string $repositoryRoot): void
    {
        $this->runCommand($repositoryRoot, 'git add .');
        $this->runCommand($repositoryRoot, 'git commit -m "Update test repository"');
    }

    /** The page without its inline critical CSS, which names every class. */
    private function render(Application $application, string $path): string
    {
        ob_start();
        $application->handle('GET', $path);

        return (string) preg_replace('/<style data-role="critical-css">.*?<\/style>/s', '', (string) ob_get_clean());
    }

    private function runCommand(string $cwd, string $command): void
    {
        exec('cd ' . escapeshellarg($cwd) . ' && ' . $command . ' 2>&1', $output, $status);
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
            $targetPath = $destination . '/' . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($targetPath)) mkdir($targetPath, 0777, true);
                continue;
            }
            copy($item->getPathname(), $targetPath);
        }
    }
}
