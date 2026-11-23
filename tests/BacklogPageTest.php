<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Feedback\FeedbackBacklog;
use ForumRewrite\Http\BacklogPageController;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;

/**
 * Tools → Backlog reads the checkout's own docs/feedback/items, so these tests
 * run against the real items and assert only what holds for any set of them.
 */
final class BacklogPageTest
{
    public function testFlagOffHidesThePageAndItsToolsEntry(): void
    {
        $this->withSite(false, function (Application $application): void {
            assertStringContains('Not Found', $this->render($application, '/backlog'));
            assertStringNotContains('href="/backlog"', $this->render($application, '/tools/'));
        });
    }

    public function testSignedOutVisitorSeesTheDefaultList(): void
    {
        $this->withSite(true, function (Application $application): void {
            assertStringContains('href="/backlog"', $this->render($application, '/tools/'), 'Tools links to the backlog.');
            $html = $this->render($application, '/backlog');
            $items = BacklogPageController::load(dirname(__DIR__));
            $shown = FeedbackBacklog::sort(FeedbackBacklog::select($items, 'can-start', 'all'), 'salient', 'newest');
            assertTrue(count($shown) > 0);
            assertStringContains(count($shown) . ' of ' . count(FeedbackBacklog::select($items, 'open', 'all')) . ' open', $html);
            assertSame(count($shown), substr_count($html, 'class="backlog-row"'));
            assertTrue(strpos($html, $shown[0]->id()) < strpos($html, $shown[1]->id()), 'Rows follow the shared order.');
            assertStringContains('/assets/backlog.', $html, 'The page carries its own stylesheet.');
            assertStringContains('aria-current="true">Salient<span class="visually-hidden">, newest first</span>', $html);
            assertStringContains('aria-current="true">Can start</a>', $html);
            assertStringNotContains('data-workflow="blocked"', $html);
            assertStringNotContains('data-workflow="done"', $html);
        });
    }

    public function testSortAndFilterLinksUseReadableWords(): void
    {
        $this->withSite(true, function (Application $application): void {
            $html = $this->render($application, '/backlog');
            assertStringContains('href="/backlog?order=oldest"', $html, 'The active sort flips its order.');
            assertStringContains('href="/backlog?sort=effort"', $html);
            assertStringContains('href="/backlog?show=waiting"', $html);
            assertStringContains('href="/backlog?kind=bug"', $html);
            assertStringContains('href="/backlog.txt"', $html);

            $bugs = $this->render($application, '/backlog?sort=effort&order=oldest&show=waiting&kind=bug');
            assertStringContains('href="/backlog?sort=effort&amp;show=waiting&amp;kind=bug"', $bugs, 'Effort flips back to newest; other words stay.');
            assertStringContains('href="/backlog?sort=impact&amp;show=waiting&amp;kind=bug"', $bugs, 'Another sort starts newest first.');
            assertStringContains('href="/backlog?sort=effort&amp;order=oldest&amp;kind=bug"', $bugs, 'Can start drops the show word.');
            assertStringContains('aria-current="true">Waiting on you</a>', $bugs);
            assertStringNotContains('data-workflow="untriaged"', $bugs);
            preg_match_all('/href="(\/backlog[^"]*)"/', $bugs, $links);
            foreach ($links[1] as $link) {
                assertSame(1, preg_match('/^\/backlog(\.txt)?(\?[a-z=&;-]+)?$/', $link), 'Readable words only: ' . $link);
            }
        });
    }

    public function testUnknownWordsFallBackWithANotice(): void
    {
        $this->withSite(true, function (Application $application): void {
            $html = $this->render($application, '/backlog?sort=sideways&show=<b>');
            assertStringContains('Not understood, so the default is shown: sort=sideways, show=&lt;b&gt;', $html);
            assertStringContains('aria-current="true">Salient', $html);
            assertStringNotContains('<b>', $html);

            $planned = $this->render($application, '/backlog?show=planned&kind=other');
            assertStringContains('aria-current="true">Planned</a>', $planned, 'A workflow word without a standing link shows while active.');
        });
    }

    public function testStartPageShowsThePromptForThePickedItems(): void
    {
        $this->withSite(true, function (Application $application): void {
            $items = BacklogPageController::load(dirname(__DIR__));
            $open = FeedbackBacklog::select($items, 'can-start', 'all');
            $closed = array_values(array_filter($items, static fn ($item): bool => $item->isClosed()));
            [$a, $b] = [$open[0]->id(), $open[1]->id()];

            $list = $this->render($application, '/backlog');
            assertStringContains('<form class="backlog-form" method="get" action="/backlog/start"', $list);
            assertStringContains('type="checkbox" name="items[]" value="' . $a . '"', $list);
            assertStringContains('<button type="submit">Start</button>', $list);

            $one = $this->render($application, '/backlog/start?items=' . $a);
            assertStringContains('Work on this v3 backlog item as one Railspike one-shot cycle.', $one);
            assertStringContains('- docs/feedback/items/' . $a . '.txt', $one);
            assertStringContains('aria-current="true">One-shot</a>', $one);
            assertStringContains('href="/backlog/start?items=' . $a . '&amp;mode=staged"', $one);
            assertStringContains('href="/backlog/start.txt?items=' . $a . '"', $one);
            assertStringContains('class="copy-prompt"', $one, 'The shared prompt partial with Copy.');

            $two = $this->render($application, '/backlog/start?items=' . $a . ',' . $b . '&mode=staged');
            assertStringContains('Work on these 2 v3 backlog items together as one staged Railspike cycle.', $two);
            assertStringContains('aria-current="true">Staged cycle</a>', $two);
            exec('cd ' . escapeshellarg(dirname(__DIR__)) . ' && ./v3 feedback start ' . $a . ' ' . $b . ' --mode=staged 2>/dev/null', $command);
            assertStringContains(htmlspecialchars(implode("\n", $command)), $two, 'The page and the command give the same prompt.');

            $mixed = $this->render($application, '/backlog/start?items=' . $a . ',' . $closed[0]->id() . ',PF-00000000-F99');
            assertStringContains('Left out, already closed: ' . $closed[0]->id(), $mixed);
            assertStringContains('Left out, no such item: PF-00000000-F99', $mixed);
            assertStringContains('Work on this v3 backlog item', $mixed);
            assertStringContains('href="/backlog/start.txt?items=' . $a . '"', $mixed, 'The text link names only what starts.');

            $none = $this->render($application, '/backlog/start?items=' . $closed[0]->id());
            assertStringContains('Nothing to start.', $none);
            assertStringNotContains('class="copy-prompt', $none);
        });
    }

    public function testStartRequestsAreRedirectedToTheTidyAddress(): void
    {
        $tidy = '/backlog/start?items=PF-20261101-F01,PF-20261101-B02';
        assertSame(null, BacklogPageController::startRedirect(['items' => 'PF-20261101-F01,PF-20261101-B02'], $tidy));
        assertSame($tidy, BacklogPageController::startRedirect(['items' => ['PF-20261101-F01', 'PF-20261101-B02']], '/backlog/start?items%5B%5D=PF-20261101-F01&items%5B%5D=PF-20261101-B02'));
        assertSame($tidy . '&mode=staged', BacklogPageController::startRedirect(['items' => 'PF-20261101-F01,PF-20261101-B02,PF-20261101-F01', 'mode' => 'staged'], '/x'));
        assertSame($tidy, BacklogPageController::startRedirect(['items' => 'PF-20261101-F01,PF-20261101-B02', 'mode' => 'fast'], '/x'), 'An unknown mode is dropped.');
        assertSame('/backlog', BacklogPageController::startRedirect([], '/backlog/start'), 'Nothing picked goes back to the list.');
        assertSame('/backlog', BacklogPageController::startRedirect(['items' => '<script>'], '/backlog/start?items=x'));

        $this->withSite(true, function (Application $application): void {
            $page = $this->render($application, '/backlog/start?items%5B%5D=PF-20261101-F01');
            assertStringContains('/backlog/start?items=PF-20261101-F01', $page, 'The redirect page names the tidy address.');
            assertStringNotContains('class="copy-prompt', $page);
            assertStringContains('Pick at least one item to start.', $this->render($application, '/backlog/start'));
        });
        $this->withSite(false, function (Application $application): void {
            assertStringContains('Not Found', $this->render($application, '/backlog/start?items=PF-20261101-F01'));
        });
    }

    public function testCheckoutWithoutItemsGivesAnEmptyList(): void
    {
        $root = sys_get_temp_dir() . '/v3-backlog-empty-' . bin2hex(random_bytes(4));
        mkdir($root);
        try {
            assertSame([], BacklogPageController::load($root));
        } finally {
            rmdir($root);
        }
    }

    /** @param callable(Application): void $test */
    private function withSite(bool $enabled, callable $test): void
    {
        $oldFlag = getenv(FeatureFlagRegistry::BACKLOG_ENABLED);
        putenv(FeatureFlagRegistry::BACKLOG_ENABLED . '=' . ($enabled ? 'true' : 'false'));
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-backlog-' . bin2hex(random_bytes(6)) . '/repo';
        try {
            mkdir($repositoryRoot, 0777, true);
            $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);
            foreach (['git init', 'git config user.name "Forum Rewrite"', 'git config user.email "forum-rewrite@example.invalid"', 'git add .', 'git commit -m "Test repository"'] as $command) {
                $this->runCommand($repositoryRoot, $command);
            }
            $test(new Application(dirname(__DIR__), $repositoryRoot, dirname($repositoryRoot) . '/read-model.sqlite3'));
        } finally {
            putenv($oldFlag === false ? FeatureFlagRegistry::BACKLOG_ENABLED : FeatureFlagRegistry::BACKLOG_ENABLED . '=' . $oldFlag);
            exec('rm -rf ' . escapeshellarg(dirname($repositoryRoot)));
        }
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
