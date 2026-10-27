<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\PresentationSlotRegistry;
use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\View\TemplateRenderer;

final class BoardListTest
{
    public function testZenmemesUsesTheListBoardAndOtherSitesKeepCards(): void
    {
        $profiles = SiteProfileRegistry::all();
        assertSame('list', PresentationSlotRegistry::resolve($profiles['zenmemes'], 'boardCard'));
        assertSame('thread', PresentationSlotRegistry::resolve($profiles['chouse'], 'boardCard'));
        assertSame('thread', PresentationSlotRegistry::resolve($profiles['mitrapclub'], 'boardCard'));
        assertSame('quote', PresentationSlotRegistry::resolve($profiles['qdb'], 'boardCard'));

        [$repositoryRoot, $databasePath] = $this->createTempEnvironment(0);
        foreach (['chouse', 'mitrapclub'] as $site) {
            putenv('FORUM_SITE_ID=' . $site);
            try {
                $board = $this->render(new Application(dirname(__DIR__), $repositoryRoot, $databasePath), '/threads/?view=all&sort=newest');
                assertStringContains('class="nav board-controls-nav"', $board);
                assertStringContains('class="card thread-card"', $board);
                assertStringNotContains('class="thread-row"', $board);
            } finally {
                putenv('FORUM_SITE_ID');
            }
        }
    }

    public function testPinnedThreadsComeFirstAndLatestThreadsArePaged(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment(60);
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);

        $first = $this->render($application, '/threads/?view=all&sort=newest');
        assertSame(1, substr_count($first, 'class="thread-rows pinned-list"'));
        assertStringContains('href="/threads/thread-zenmemes-rules"', $this->section($first, 'pinned-list'));
        assertStringContains('<h2 class="section-label">Latest</h2>', $first);
        assertSame(51, substr_count($first, 'class="thread-row"'));
        assertStringContains('href="/threads/board-list-060"', $first);
        assertStringNotContains('href="/threads/board-list-010"', $first);
        assertStringNotContains('New Post', $this->markup($first));
        assertStringNotContains('rel="prev"', $first);
        preg_match('/rel="next" href="([^"]+)">Show older threads</', $first, $next);
        assertTrue(isset($next[1]), 'Expected a Show older threads link.');

        $second = $this->render($application, html_entity_decode($next[1]));
        assertStringNotContains('pinned-list', $this->markup($second));
        assertStringContains('href="/threads/board-list-010"', $second);
        assertStringContains('href="/threads/root-001"', $second);
        assertStringNotContains('href="/threads/board-list-060"', $second);
        assertStringNotContains('rel="next"', $second);
        preg_match('/rel="prev" href="([^"]+)">Newer threads</', $second, $previous);
        assertTrue(isset($previous[1]), 'Expected a Newer threads link.');

        $back = $this->render($application, html_entity_decode($previous[1]));
        assertStringContains('href="/threads/board-list-060"', $back);
        assertStringContains('href="/threads/board-list-011"', $back);
        assertStringNotContains('href="/threads/board-list-010"', $back);
    }

    public function testExpiredOrForeignPositionsOfferTheFirstPage(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment(0);
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);

        $gone = \ForumRewrite\Pagination\PageNavigation::encode('board:all:newest', 'next', ['thread' => 'no-such-thread']);
        $expired = $this->render($application, '/threads/?view=all&sort=newest&position=' . $gone);
        assertStringContains('This page position has expired.', $expired);
        assertStringContains('href="/threads/?view=all&amp;sort=newest">First page</a>', $expired);

        // A position from another sort is a different context.
        $other = \ForumRewrite\Pagination\PageNavigation::encode('board:all:top', 'next', ['thread' => 'root-001']);
        $foreign = $this->render($application, '/threads/?view=all&sort=newest&position=' . $other);
        assertStringContains('This page position is invalid.', $foreign);
        assertStringContains('>First page</a>', $foreign);
    }

    public function testEmptyLikedViewOffersAllThreads(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment(0);
        $liked = $this->render(new Application(dirname(__DIR__), $repositoryRoot, $databasePath), '/threads/?view=liked&sort=top');

        assertStringContains('<h2>No liked threads yet</h2>', $liked);
        assertStringContains('href="/threads/?view=all&amp;sort=top">Show all threads</a>', $liked);
        assertStringContains('data-nav-key="liked" aria-current="page"><span class="like-mark" aria-hidden="true"></span>Liked</a>', $liked);
    }

    public function testSortMenuMarksTheActiveSort(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment(0);
        $board = $this->render(new Application(dirname(__DIR__), $repositoryRoot, $databasePath), '/threads/?view=all&sort=oldest');

        assertStringContains('<summary class="nav-link"><span class="visually-hidden">Sort by </span>Oldest</summary>', $board);
        assertStringContains('href="/threads/?view=all&amp;sort=oldest" data-nav-key="oldest" aria-current="true">Oldest</a>', $board);
        assertStringContains('href="/threads/?view=all&amp;sort=top" data-nav-key="top">Top</a>', $board);
    }

    public function testRowExcerptSkipsImageReferences(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment(0);
        file_put_contents(
            $repositoryRoot . '/records/posts/board-list-image.txt',
            "Post-ID: board-list-image\nCreated-At: 2026-07-01T00:00:00Z\nBoard-Tags: general\nSubject: Image\n\nImage-v1: " . str_repeat('a', 64) . " png 10 20 30\n"
        );
        $this->runCommand($repositoryRoot, 'git add .');
        $this->runCommand($repositoryRoot, 'git commit -m "Add image post"');
        $board = $this->render(new Application(dirname(__DIR__), $repositoryRoot, $databasePath), '/threads/?view=all&sort=newest');

        assertStringContains('href="/threads/board-list-image">Image</a>', $board);
        assertStringNotContains('Image-v1:', $board);
    }

    public function testRowMetaShowsAPendingChipAndTheFullTimestampAsTooltip(): void
    {
        $renderer = new TemplateRenderer(dirname(__DIR__) . '/templates');
        $method = new ReflectionMethod($renderer, 'renderRowMeta');
        $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $meta = $method->invoke($renderer, [
            'author_label' => 'mouse',
            'author_profile_slug' => 'mouse',
            'author_is_approved' => 0,
            'root_post_created_at' => '2025-10-06T12:37:00Z',
        ], 'root_post_created_at', $escape);

        assertSame('<a href="/profiles/mouse">mouse</a> · <time datetime="2025-10-06T12:37:00Z" title="Oct 6, 2025 at 12:37 UTC">Oct 6, 2025</time> <span class="chip chip--pending">Pending</span>', $meta);
    }

    /** Text of the rows container with the given class. */
    private function section(string $html, string $class): string
    {
        preg_match('/<div class="thread-rows ' . $class . '">.*?<\/div>/s', $html, $match);

        return $match[0] ?? '';
    }

    /** The page without its inline critical CSS, which names every board class. */
    private function markup(string $html): string
    {
        return (string) preg_replace('/<style data-role="critical-css">.*?<\/style>/s', '', $html);
    }

    private function render(Application $application, string $path): string
    {
        ob_start();
        $application->handle('GET', $path);

        return (string) ob_get_clean();
    }

    /** @return array{string, string} */
    private function createTempEnvironment(int $extraThreads): array
    {
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-board-list-repo-' . bin2hex(random_bytes(6));
        mkdir($repositoryRoot, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);
        for ($i = 1; $i <= $extraThreads; $i++) {
            $postId = sprintf('board-list-%03d', $i);
            file_put_contents(
                $repositoryRoot . '/records/posts/' . $postId . '.txt',
                "Post-ID: {$postId}\nCreated-At: " . gmdate('Y-m-d\TH:i:s\Z', strtotime('2026-06-01T00:00:00Z') + $i * 60) . "\nBoard-Tags: general\nSubject: Thread {$i}\n\nBody {$i}.\n"
            );
        }
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-board-list-db-' . bin2hex(random_bytes(6)) . '.sqlite3';
        foreach (['git init', 'git config user.name "Forum Rewrite"', 'git config user.email "forum-rewrite@example.invalid"', 'git add .', 'git commit -m "Initialize test repository"'] as $command) {
            $this->runCommand($repositoryRoot, $command);
        }

        return [$repositoryRoot, $databasePath];
    }

    private function runCommand(string $cwd, string $command): void
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to run command: ' . $command);
        }
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException('Command failed: ' . $command);
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
