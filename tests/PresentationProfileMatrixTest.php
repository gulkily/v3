<?php

declare(strict_types=1);

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;

require __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ProfileRegressionContract.php';
require_once __DIR__ . '/Support/ProfileRegressionFixture.php';

final class PresentationProfileMatrixTest
{
    public function testProfilesRenderTheirRegisteredPresentationSelections(): void
    {
        $application = $this->application();
        foreach (ProfileRegressionContract::all() as $profileId => $contract) {
            $expected = $contract['presentation'];
            putenv('FORUM_SITE_ID=' . $profileId);
            try {
                $board = $this->render($application, $expected['boardPath']);
                $about = $this->render($application, '/about/');
                $compose = $this->render($application, $expected['composePath']);

                $this->assertContains($profileId, 'board card', $expected['boardMarker'], $board);
                $this->assertContains($profileId, 'navigation', $expected['navigationMarker'], $board);
                $this->assertContains($profileId, 'compose surface', $expected['composeMarker'], $compose);
                assertSame($expected['aboutHackable'], str_contains($about, 'Hackable by design'));
                assertSame($expected['brandedTheme'] !== null, str_contains($board, 'data-theme-option="' . ($expected['brandedTheme'] ?? 'no-branded-theme') . '"'));
            } finally {
                putenv('FORUM_SITE_ID');
            }
        }
    }

    public function testOnlyZenmemesAboutLeadsWithSiteDescriptionAndBuildExample(): void
    {
        $application = $this->application();
        foreach (array_keys(ProfileRegressionContract::all()) as $profileId) {
            putenv('FORUM_SITE_ID=' . $profileId);
            try {
                $about = $this->render($application, '/about/');
                $isZenmemes = $profileId === 'zenmemes';
                assertSame($isZenmemes, str_contains($about, 'data-site-description'));
                assertSame($isZenmemes, str_contains($about, 'data-build-example'));
                if ($isZenmemes) {
                    assertStringContains('zenmemes is a garage where people and agents build this site together, starting from threads.', $about);
                    assertStringContains('href="/threads/thread-20261103060933-51ef9554"', $about);
                    assertStringContains('href="https://github.com/gulkily/v3/pull/26"', $about);
                    assertTrue(strpos($about, 'data-site-description') < strpos($about, 'data-build-example'));
                }
            } finally {
                putenv('FORUM_SITE_ID');
            }
        }
    }

    public function testOnlyZenmemesBoardCarriesTheFirstVisitIntro(): void
    {
        $application = $this->application();
        foreach (array_keys(ProfileRegressionContract::all()) as $profileId) {
            putenv('FORUM_SITE_ID=' . $profileId);
            try {
                $board = $this->render($application, '/');
                $isZenmemes = $profileId === 'zenmemes';
                assertSame($isZenmemes, str_contains($board, 'class="card visitor-intro" data-visitor-intro'));
                assertStringContains("localStorage.getItem('forum_visitor_intro_hidden')", $board);
                if ($isZenmemes) {
                    assertTrue(strpos($board, 'class="card visitor-intro"') < strpos($board, 'data-compose-root'));
                    assertStringContains('<a href="/about/">About</a>', $board);
                    assertStringContains('data-visitor-intro-hide aria-label="Hide"', $board);
                    assertStringContains("localStorage.setItem('forum_visitor_intro_hidden', '1')", $board);
                }
            } finally {
                putenv('FORUM_SITE_ID');
            }
        }
    }

    public function testOnlyZenmemesOffersTheAgentPromptOnAboutWithALinkFromTheIntro(): void
    {
        $application = $this->application();
        $previousHost = $_SERVER['HTTP_HOST'] ?? null;
        $_SERVER['HTTP_HOST'] = 'zenmemes.example';
        foreach (array_keys(ProfileRegressionContract::all()) as $profileId) {
            putenv('FORUM_SITE_ID=' . $profileId);
            try {
                $isZenmemes = $profileId === 'zenmemes';
                $board = $this->render($application, '/');
                $about = $this->render($application, '/about/');
                assertSame($isZenmemes, str_contains($board, '<a href="/about/#agent">Agent prompt</a>'));
                assertTrue(!str_contains($board, 'data-agent-prompt '));
                assertSame($isZenmemes, str_contains($about, 'data-agent-prompt '));
                if ($isZenmemes) {
                    assertStringContains('<details class="agent-prompt" id="agent"', $about);
                    assertStringContains('<summary>Use with your agent</summary>', $about);
                    assertStringContains('Please visit http://zenmemes.example and read http://zenmemes.example/llms.txt.', $about);
                    assertStringContains('Ask me before you post anything.', $about);
                    assertStringContains('data-agent-prompt-origin="http://zenmemes.example"', $about);
                    assertStringContains('data-agent-prompt-copy hidden>Copy</button>', $about);
                    assertTrue(strpos($about, 'data-site-description') < strpos($about, 'id="agent"'));
                }
            } finally {
                putenv('FORUM_SITE_ID');
            }
        }
        if ($previousHost === null) {
            unset($_SERVER['HTTP_HOST']);
        } else {
            $_SERVER['HTTP_HOST'] = $previousHost;
        }
    }

    public function testAgentPromptFlagAndProfileGateEveryEntryPoint(): void
    {
        $application = $this->application();
        $flag = \ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry::AGENT_PROMPT_ENABLED;
        $previousFlag = getenv($flag);
        try {
            foreach (array_keys(ProfileRegressionContract::all()) as $profileId) {
                putenv('FORUM_SITE_ID=' . $profileId);
                foreach (['1', '0'] as $value) {
                    putenv($flag . '=' . $value);
                    $shown = $profileId === 'zenmemes' && $value === '1';
                    $board = $this->render($application, '/');
                    $about = $this->render($application, '/about/');
                    $tools = $this->render($application, '/tools/');
                    $context = $profileId . ' ' . $flag . '=' . $value;
                    assertSame($shown, str_contains($board, 'href="/about/#agent"'), $context);
                    assertSame($shown, str_contains($about, 'data-agent-prompt '), $context);
                    assertSame($shown, str_contains($tools, 'href="/about/#agent"'), $context);
                }
            }
        } finally {
            putenv('FORUM_SITE_ID');
            putenv($previousFlag === false ? $flag : $flag . '=' . $previousFlag);
        }
    }

    public function testAgentPromptUsesAPlaceholderOriginWithoutARequestHost(): void
    {
        $profile = \ForumRewrite\SiteProfileRegistry::all()['zenmemes'];
        assertStringContains('Please visit {origin} and read {origin}/llms.txt.', \ForumRewrite\ProfilePresentationContent::agentPrompt($profile, '{origin}'));
        assertSame('', \ForumRewrite\ProfilePresentationContent::agentPrompt(\ForumRewrite\SiteProfileRegistry::all()['qdb'], 'https://x.example'));
    }

    public function testFourthProfileFixtureUsesTheSamePresentationMatrix(): void
    {
        ProfileRegressionFixture::withFourthProfile(fn (): mixed => $this->testProfilesRenderTheirRegisteredPresentationSelections());
    }

    public function testListingQueryRoutesAndCodebaseCoverage(): void
    {
        $application = $this->application();
        foreach (ProfileRegressionContract::all() as $profileId => $contract) {
            putenv('FORUM_SITE_ID=' . $profileId);
            try {
                foreach (['/', '/threads', '/threads/'] as $path) {
                    foreach (['all', 'liked'] as $view) {
                        foreach (['newest', 'oldest', 'top'] as $sort) {
                            $html = $this->render($application, $path . '?view=' . $view . '&sort=' . $sort);
                            if ($contract['experience'] === 'forum') {
                                assertStringContains('data-query-key="board-' . $view . '-' . $sort . '"', $html);
                                assertSame(1, substr_count($html, 'data-query-dialog '));
                                assertStringContains('sqlite_dialog.', $html);
                                $footer = strpos($html, '<footer class="listing-query-footer">');
                                assertTrue($footer !== false && $footer < strpos($html, 'data-query-key="board-'));
                                assertTrue($footer > strrpos($html, 'data-compose-root'));
                                if (str_contains($html, 'class="card thread-card"')) {
                                    assertTrue($footer > strrpos($html, 'class="card thread-card"'));
                                }
                            }
                        }
                    }
                }
                $codebase = $this->render($application, '/tools/codebase/');
                preg_match_all('/data-query-sql="([^"]+)"/', $codebase, $queries);
                assertTrue(count($queries[1]) >= 16);
                assertStringContains('<code>SELECT ', $codebase);
                assertStringContains('class="codebase-query-action"', $codebase);
                assertStringContains('aria-label="View query: Instance admins"', $codebase);
                foreach ($queries[1] as $sql) {
                    assertTrue(str_starts_with(html_entity_decode($sql, ENT_QUOTES), 'SELECT '));
                }
                assertStringContains('git -C state/local_repository', $codebase);
                assertStringNotContains('data-query-dialog', $this->render($application, '/forte/'));
            } finally { putenv('FORUM_SITE_ID'); }
        }
    }

    private function application(): Application
    {
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-presentation-matrix-repo-' . bin2hex(random_bytes(6));
        mkdir($repositoryRoot, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);
        $this->runCommand($repositoryRoot, 'git init');
        $this->runCommand($repositoryRoot, 'git config user.name "Forum Rewrite"');
        $this->runCommand($repositoryRoot, 'git config user.email "forum-rewrite@example.invalid"');
        $this->runCommand($repositoryRoot, 'git add .');
        $this->runCommand($repositoryRoot, 'git commit -m "Initialize test repository"');
        file_put_contents(
            $repositoryRoot . '/records/posts/thread-presentation-matrix.txt',
            "Post-ID: thread-presentation-matrix\nCreated-At: 2026-10-06T00:00:00Z\nBoard-Tags: general\nSubject: Presentation matrix\n\nVisible matrix thread.\n",
        );
        file_put_contents(
            $repositoryRoot . '/records/posts/thread-20261006000001-qdb-42.txt',
            "Post-ID: thread-20261006000001-qdb-42\nCreated-At: 2026-10-06T00:00:01Z\nBoard-Tags: general\nSubject: Presentation matrix quote\n\nVisible matrix quote.\n",
        );
        $this->runCommand($repositoryRoot, 'git add .');
        $this->runCommand($repositoryRoot, 'git commit -m "Add presentation matrix thread"');

        $databasePath = sys_get_temp_dir() . '/forum-rewrite-presentation-matrix-db-' . bin2hex(random_bytes(6)) . '.sqlite3';
        (new ReadModelBuilder($repositoryRoot, $databasePath, new CanonicalRecordRepository($repositoryRoot)))->rebuild();
        $threadCount = (int) (new \PDO('sqlite:' . $databasePath))->query('SELECT COUNT(*) FROM threads')->fetchColumn();
        if ($threadCount === 0) {
            throw new RuntimeException('Presentation matrix read model has no threads.');
        }

        return new Application(
            dirname(__DIR__),
            $repositoryRoot,
            $databasePath,
        );
    }

    private function render(Application $application, string $requestUri): string
    {
        ob_start();
        $application->handle('GET', $requestUri);

        return (string) ob_get_clean();
    }

    private function assertContains(string $profileId, string $surface, string $expected, string $html): void
    {
        if (!str_contains($html, $expected)) {
            throw new RuntimeException("{$profileId} {$surface} did not contain {$expected}.");
        }
    }

    private function copyDirectory(string $source, string $target): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) {
            $destination = $target . '/' . substr($item->getPathname(), strlen($source) + 1);
            if ($item->isDir()) {
                mkdir($destination, 0777, true);
            } else {
                copy($item->getPathname(), $destination);
            }
        }
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
        assertSame(0, proc_close($process));
    }
}
