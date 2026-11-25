<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;

final class MusicPageTest
{
    public function testMusicPageListsTheMusicTagOnASiteWithTheSection(): void
    {
        $this->withSite('mitrapclub', function (string $repositoryRoot, string $databasePath): void {
            $this->writePost($repositoryRoot, 'thread-music-old', "Subject: DC3 - SEEK\n\nhttps://www.youtube.com/watch?v=GQLciY_ityU", '2026-07-01T00:00:00Z', 'music');
            $this->writePost($repositoryRoot, 'thread-music-new', "Subject: KOAD - dirty\n\nhttps://www.youtube.com/watch?v=B7Gj3YodcWU", '2026-07-02T00:00:00Z', 'music');
            $this->writePost($repositoryRoot, 'thread-music-hashtag', "Subject: Lupe Fiasco - Sailing Flavor\n\nhttps://www.youtube.com/watch?v=mVslaF2r0Zk", '2026-07-03T00:00:00Z', 'general music');
            $this->writePost($repositoryRoot, 'thread-lecture', "Subject: Where Do Minds Come From?\n\nhttps://www.youtube.com/watch?v=CGeaFnDZPNY", '2026-07-04T00:00:00Z');
            $this->commit($repositoryRoot);
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);

            $page = $this->markup($this->render($application, '/music/'));
            $tag = $this->markup($this->render($application, '/tags/music'));
            preg_match_all('#id="post-(thread-[a-z-]+)"#', $page, $listed);
            preg_match_all('#id="post-(thread-[a-z-]+)"#', $tag, $tagged);
            assertSame(['thread-music-hashtag', 'thread-music-new', 'thread-music-old'], $listed[1], 'Threads carrying the music tag, newest first, alongside other tags.');
            assertSame($tagged[1], $listed[1], 'The Music page lists exactly what /tags/music lists.');
            assertStringNotContains('Where Do Minds Come From?', $page, 'An untagged video stays off the page.');
            assertStringContains('<title>Music', $page);
            assertStringContains('No tracks on this page', $this->markup($this->render($application, '/music/?page=2')));
        });
    }

    public function testEachRowPlaysItsFirstLinkWithoutRepeatingIt(): void
    {
        putenv('FORUM_MEDIA_EMBEDS_ENABLED=true');
        putenv('FORUM_MEDIA_EMBEDS_INLINE_PLAYER_ENABLED=true');
        try {
            $this->withSite('mitrapclub', function (string $repositoryRoot, string $databasePath): void {
                $this->writePost($repositoryRoot, 'thread-music-note', "Subject: Deante' Hitchcock - I Done Changed A Lot\n\nhttps://open.spotify.com/album/3LVR5at2HIWpbqFfV7Zzdj\nthe whole album", '2026-07-01T00:00:00Z', 'music');
                $this->writePost($repositoryRoot, 'thread-music-text', "Subject: What are you listening to?\n\nTell us.", '2026-07-02T00:00:00Z', 'music');
                $this->commit($repositoryRoot);
                $page = $this->markup($this->render(new Application(dirname(__DIR__), $repositoryRoot, $databasePath), '/music/'));
                $note = substr($page, strpos($page, 'id="post-thread-music-note"'));
                $text = substr($page, strpos($page, 'id="post-thread-music-text"'), strpos($page, 'id="post-thread-music-note"') - strpos($page, 'id="post-thread-music-text"'));
                assertStringContains('<div class="thread-row__player">', $note);
                assertStringContains('data-provider="spotify"', $note);
                assertStringContains('<details', $note, 'The player starts collapsed.');
                assertStringNotContains('thread-row__excerpt', substr($note, 0, strpos($note, '</article>')), 'The row does not repeat the played link as its excerpt.');
                assertStringNotContains('thread-row__player', $text, 'A track without a recognized link shows no player.');
                assertStringContains('Tell us.', $text);
            });
        } finally {
            putenv('FORUM_MEDIA_EMBEDS_ENABLED');
            putenv('FORUM_MEDIA_EMBEDS_INLINE_PLAYER_ENABLED');
        }
    }

    public function testEmptyMusicPageStillRenders(): void
    {
        $this->withSite('mitrapclub', function (string $repositoryRoot, string $databasePath): void {
            $page = $this->markup($this->render(new Application(dirname(__DIR__), $repositoryRoot, $databasePath), '/music/'));
            assertStringContains('No tracks yet', $page);
        });
    }

    public function testMusicPageIsNotFoundWithoutTheSection(): void
    {
        foreach (['zenmemes', 'chouse', 'qdb'] as $site) {
            $this->withSite($site, function (string $repositoryRoot, string $databasePath) use ($site): void {
                $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
                $this->render($application, '/music/');
                assertSame(404, http_response_code(), $site . ' has no Music page');
            });
        }
    }

    private function withSite(string $site, callable $test): void
    {
        $old = getenv('FORUM_SITE_ID');
        putenv('FORUM_SITE_ID=' . $site);
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-music-' . bin2hex(random_bytes(6)) . '/repo';
        try {
            mkdir($repositoryRoot, 0777, true);
            $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);
            foreach (['git init', 'git config user.name "Forum Rewrite"', 'git config user.email "forum-rewrite@example.invalid"'] as $command) {
                $this->runCommand($repositoryRoot, $command);
            }
            $this->commit($repositoryRoot);
            $test($repositoryRoot, dirname($repositoryRoot) . '/read-model.sqlite3');
        } finally {
            $old === false ? putenv('FORUM_SITE_ID') : putenv('FORUM_SITE_ID=' . $old);
            exec('rm -rf ' . escapeshellarg(dirname($repositoryRoot)));
        }
    }

    private function writePost(string $repositoryRoot, string $postId, string $rest, string $createdAt, string $tags = 'general'): void
    {
        file_put_contents($repositoryRoot . '/records/posts/' . $postId . '.txt', "Post-ID: {$postId}\nCreated-At: {$createdAt}\nBoard-Tags: {$tags}\n{$rest}\n");
    }

    private function commit(string $repositoryRoot): void
    {
        $this->runCommand($repositoryRoot, 'git add .');
        $this->runCommand($repositoryRoot, 'git commit -q --allow-empty -m "Update test repository"');
    }

    /** The page without its inline critical CSS, which names every class. */
    private function markup(string $html): string
    {
        return (string) preg_replace('/<style data-role="critical-css">.*?<\/style>/s', '', $html);
    }

    private function render(Application $application, string $path): string
    {
        http_response_code(200);
        ob_start();
        $application->handle('GET', $path);

        return (string) ob_get_clean();
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
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0777, true);
                }
                continue;
            }
            copy($item->getPathname(), $targetPath);
        }
    }
}
