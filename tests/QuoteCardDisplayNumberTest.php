<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Host\StaticArtifactBuilder;

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
        assertStringContains(
            'data-role="thread-score" data-score-format="bare-ratio">(<span class="quote-card-score-value quote-card-score-positive" data-role="thread-score-value">5</span>/<span data-role="thread-vote-count">7</span>)</span>',
            $board,
        );
        assertStringNotContains('>#thread-20030613104735-qdb-42</a>', $board);
    }

    public function testQdbScoreMarkupHandlesPositiveNegativeAndNeutralScores(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment();
        $this->writeImportedQuote($repositoryRoot, 'thread-20030613104735-qdb-42', 'Positive quote.', 5, 7);
        $this->writeImportedQuote($repositoryRoot, 'thread-20030613104736-qdb-43', 'Negative quote.', -5, 7);
        $this->writeImportedQuote($repositoryRoot, 'thread-20030613104737-qdb-44', 'Neutral quote.', 0, 7);

        putenv('FORUM_SITE_ID=qdb');
        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
            $board = $this->render($application, '/latest');
        } finally {
            putenv('FORUM_SITE_ID');
        }

        assertStringContains('class="quote-card-score-value quote-card-score-positive" data-role="thread-score-value">5</span>', $board);
        assertStringContains('class="quote-card-score-value quote-card-score-negative" data-role="thread-score-value">-5</span>', $board);
        assertStringContains('class="quote-card-score-value" data-role="thread-score-value">0</span>', $board);
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

    public function testQuoteCardLinksToTheShortNumericPermalinkOnLatest(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment();
        $this->writeImportedQuote($repositoryRoot, 'thread-20030613104735-qdb-42', 'The quoted body.');

        putenv('FORUM_SITE_ID=qdb');
        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
            $board = $this->render($application, '/latest');
        } finally {
            putenv('FORUM_SITE_ID');
        }

        assertStringContains('href="/42">#42</a>', $board);
        assertStringNotContains('href="/threads/thread-20030613104735-qdb-42"', $board);
    }

    public function testQuoteCardLinksToTheShortNumericPermalinkOnTopSearchAndRandom(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment();
        $this->writeImportedQuote($repositoryRoot, 'thread-20030613104735-qdb-42', 'The quoted body.');

        putenv('FORUM_SITE_ID=qdb');
        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
            $top = $this->render($application, '/top');
            $search = $this->render($application, '/search?search=quoted');
            $random = $this->render($application, '/random');
        } finally {
            putenv('FORUM_SITE_ID');
        }

        assertStringContains('href="/42">#42</a>', $top);
        assertStringContains('href="/42">#42</a>', $search);
        assertStringContains('href="/42">#42</a>', $random);
    }

    public function testQdbListingExcludesThreadsWithoutQuoteIds(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment();
        $this->writeImportedQuote($repositoryRoot, 'thread-20030613104735-qdb-42', 'The quoted body.');

        putenv('FORUM_SITE_ID=qdb');
        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
            $board = $this->render($application, '/latest');
        } finally {
            putenv('FORUM_SITE_ID');
        }

        assertStringContains('href="/42">#42</a>', $board);
        assertStringNotContains('root-001', $board);
        assertStringContains('1 quote', $board);
    }

    public function testQdbCollectionSurfacesExcludeThreadsWithoutQuoteIds(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment();
        $this->writeImportedQuote($repositoryRoot, 'thread-20030613104735-qdb-42', 'The quoted body.');

        putenv('FORUM_SITE_ID=qdb');
        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
            $surfaces = [
                $this->render($application, '/latest'),
                $this->render($application, '/top'),
                $this->render($application, '/leetness'),
                $this->render($application, '/random'),
                $this->render($application, '/search?search=body'),
                $this->render($application, '/?format=rss'),
            ];
        } finally {
            putenv('FORUM_SITE_ID');
        }

        foreach ($surfaces as $surface) {
            assertStringContains('42', $surface);
            assertStringNotContains('root-001', $surface);
        }
    }

    public function testQdbWelcomeDisplaysThreeNewestNewsItemsAndLinksToAllNews(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment();
        $this->writeNews($repositoryRoot, [
            ['news-oldest', '2026-10-01T12:00:00Z', 'Oldest news', 'Oldest body.'],
            ['news-older', '2026-10-02T12:00:00Z', 'Older news', 'Older body.'],
            ['news-titleless', '2026-10-03T12:00:00Z', '', "Titleless news headline\nMore detail."],
            ['news-newest', '2026-10-04T12:00:00Z', 'Newest news', 'Newest body.'],
        ]);

        putenv('FORUM_SITE_ID=qdb');
        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
            $welcome = $this->render($application, '/');
        } finally {
            putenv('FORUM_SITE_ID');
        }

        assertStringContains('<h2>Site News</h2>', $welcome);
        assertStringNotContains('Recent activity', $welcome);
        assertSame(3, substr_count($welcome, 'href="/threads/news-'));
        assertStringContains('href="/threads/news-newest">Newest news</a>', $welcome);
        assertStringContains('href="/threads/news-titleless">Titleless news headline</a>', $welcome);
        assertStringContains('href="/threads/news-older">Older news</a>', $welcome);
        assertStringNotContains('news-oldest', $welcome);
        assertStringContains('<time datetime="2026-10-04T12:00:00Z">Oct 4, 2026 at 12:00 UTC</time>', $welcome);
        assertStringContains('href="/tags/news">All news</a>', $welcome);
        assertTrue(
            strpos($welcome, 'Newest news') < strpos($welcome, 'Titleless news headline')
            && strpos($welcome, 'Titleless news headline') < strpos($welcome, 'Older news')
        );
    }

    public function testQdbWelcomeShowsAnEmptySiteNewsState(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment();

        putenv('FORUM_SITE_ID=qdb');
        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
            $welcome = $this->render($application, '/');
        } finally {
            putenv('FORUM_SITE_ID');
        }

        assertStringContains('<h2>Site News</h2>', $welcome);
        assertStringContains('No site news yet.', $welcome);
        assertStringNotContains('href="/tags/news">All news</a>', $welcome);
    }

    public function testQdbStaticWelcomeAndAllNewsArtifactsAreGenerated(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment();
        $artifactRoot = sys_get_temp_dir() . '/forum-rewrite-qdb-news-static-' . bin2hex(random_bytes(6));
        $this->writeNews($repositoryRoot, [
            ['news-one', '2026-10-01T12:00:00Z', 'First news', 'First body.'],
            ['news-two', '2026-10-02T12:00:00Z', 'Second news', 'Second body.'],
            ['news-three', '2026-10-03T12:00:00Z', 'Third news', 'Third body.'],
            ['news-four', '2026-10-04T12:00:00Z', 'Fourth news', 'Fourth body.'],
        ]);

        putenv('FORUM_SITE_ID=qdb');
        try {
            (new StaticArtifactBuilder(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot))->build();
        } finally {
            putenv('FORUM_SITE_ID');
        }

        $welcome = (string) file_get_contents($artifactRoot . '/index.html');

        assertStringContains('<h2>Site News</h2>', $welcome);
        assertStringContains('href="/tags/news">All news</a>', $welcome);
        assertTrue(is_file($artifactRoot . '/tags/news.html'));
    }

    public function testQdbDirectLegacyThreadPermalinkRemainsAvailable(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment();

        putenv('FORUM_SITE_ID=qdb');
        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
            $legacyThread = $this->render($application, '/threads/root-001');
        } finally {
            putenv('FORUM_SITE_ID');
        }

        assertSame(200, http_response_code());
        assertStringContains('Hello world', $legacyThread);
    }

    public function testGenericBoardStillListsLegacyAndQuoteRoots(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment();
        $this->writeImportedQuote($repositoryRoot, 'thread-20030613104735-qdb-42', 'The quoted body.');

        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
        $board = $this->render($application, '/?view=all&sort=newest');

        assertStringContains('root-001', $board);
        assertStringContains('thread-20030613104735-qdb-42', $board);
    }

    public function testFollowingTheShortNumericPermalinkReachesTheUnchangedQuotePage(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment();
        $this->writeImportedQuote($repositoryRoot, 'thread-20030613104735-qdb-42', 'The quoted body.');

        putenv('FORUM_SITE_ID=qdb');
        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
            $directThreadPage = $this->render($application, '/threads/thread-20030613104735-qdb-42');
            $viaShortLink = $this->render($application, '/42');
            $shortLinkStatus = http_response_code();
            $resolvedPage = $this->render($application, '/threads/thread-20030613104735-qdb-42');
        } finally {
            putenv('FORUM_SITE_ID');
        }

        assertSame(302, $shortLinkStatus);
        assertStringContains('href="/threads/thread-20030613104735-qdb-42"', $viaShortLink);
        assertSame($directThreadPage, $resolvedPage);
    }

    public function testQdbStaticReleaseIncludesPublicListingsAndNumericQuoteAlias(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment();
        $this->writeImportedQuote($repositoryRoot, 'thread-20030613104735-qdb-42', 'The quoted body.');
        $artifactRoot = sys_get_temp_dir() . '/forum-rewrite-qdb-static-' . bin2hex(random_bytes(6));

        putenv('FORUM_SITE_ID=qdb');
        try {
            (new StaticArtifactBuilder(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot))->build();
        } finally {
            putenv('FORUM_SITE_ID');
        }

        assertTrue(is_file($artifactRoot . '/latest.html'));
        assertTrue(is_file($artifactRoot . '/latest/index.html'));
        assertTrue(is_file($artifactRoot . '/top.html'));
        assertTrue(is_file($artifactRoot . '/top/index.html'));
        assertTrue(is_file($artifactRoot . '/leetness.html'));
        assertTrue(is_file($artifactRoot . '/leetness/index.html'));
        assertTrue(is_file($artifactRoot . '/threads/thread-20030613104735-qdb-42.html'));
        assertTrue(is_file($artifactRoot . '/qdb/quotes/42.html'));
        assertSame(
            (string) file_get_contents($artifactRoot . '/threads/thread-20030613104735-qdb-42.html'),
            (string) file_get_contents($artifactRoot . '/qdb/quotes/42.html')
        );
    }

    public function testQdbStaticReleaseCanSkipIndividualDetailPages(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment();
        $this->writeImportedQuote($repositoryRoot, 'thread-20030613104735-qdb-42', 'The quoted body.');
        $artifactRoot = sys_get_temp_dir() . '/forum-rewrite-qdb-static-no-details-' . bin2hex(random_bytes(6));

        $previousStaticDetailPages = getenv('FORUM_STATIC_DETAIL_PAGES_ENABLED');
        putenv('FORUM_SITE_ID=qdb');
        putenv('FORUM_STATIC_DETAIL_PAGES_ENABLED=false');
        try {
            (new StaticArtifactBuilder(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot))->build();
        } finally {
            if ($previousStaticDetailPages === false) {
                putenv('FORUM_STATIC_DETAIL_PAGES_ENABLED');
            } else {
                putenv('FORUM_STATIC_DETAIL_PAGES_ENABLED=' . $previousStaticDetailPages);
            }
            putenv('FORUM_SITE_ID');
        }

        assertTrue(is_file($artifactRoot . '/latest.html'));
        assertTrue(is_file($artifactRoot . '/top.html'));
        assertTrue(is_file($artifactRoot . '/leetness.html'));
        assertFalse(is_file($artifactRoot . '/threads/thread-20030613104735-qdb-42.html'));
        assertFalse(is_file($artifactRoot . '/posts/thread-20030613104735-qdb-42.html'));
        assertFalse(is_file($artifactRoot . '/qdb/quotes/42.html'));
    }

    public function testQdbListingPagesLoadTheVoteButtonAndIdentityLoaderScripts(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment();
        $this->writeImportedQuote($repositoryRoot, 'thread-20030613104735-qdb-42', 'The quoted body.');

        putenv('FORUM_SITE_ID=qdb');
        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
            $latest = $this->render($application, '/latest');
            $top = $this->render($application, '/top');
            $leetness = $this->render($application, '/leetness');
            $random = $this->render($application, '/random');
            $search = $this->render($application, '/search?search=quoted');
        } finally {
            putenv('FORUM_SITE_ID');
        }

        assertStringContains('thread_reactions', $latest);
        assertStringContains('thread_reactions', $top);
        assertStringContains('thread_reactions', $leetness);
        assertStringContains('thread_reactions', $random);
        assertStringContains('thread_reactions', $search);
        assertStringContains('lazy_compose_signing', $latest);
        assertStringContains('lazy_compose_signing', $top);
        assertStringContains('lazy_compose_signing', $leetness);
        assertStringContains('lazy_compose_signing', $random);
        assertStringContains('lazy_compose_signing', $search);
        assertStringContains('class="nav-link" href="/leetness">1337</a>', $latest);
        assertStringContains('class="nav-link is-active" href="/leetness">1337</a>', $leetness);
    }

    public function testNonQdbBoardDoesNotLoadTheVoteButtonScript(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment();
        $this->writeImportedQuote($repositoryRoot, 'thread-20030613104735-qdb-42', 'The quoted body.');

        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
        $board = $this->render($application, '/?view=all&sort=newest');

        assertStringNotContains('thread_reactions', $board);
    }

    public function testQdbPermalinkRootCardMatchesListingCardWithoutLike(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment();
        $this->writeImportedQuote($repositoryRoot, 'thread-20030613104735-qdb-42', 'The quoted body.');

        putenv('FORUM_SITE_ID=qdb');
        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
            $permalink = $this->render($application, '/threads/thread-20030613104735-qdb-42');
        } finally {
            putenv('FORUM_SITE_ID');
        }

        assertStringContains('class="quote-card-permalink" href="/42">#42</a>', $permalink);
        assertStringContains('data-role="thread-score" data-score-format="bare-ratio">(<span class="quote-card-score-value quote-card-score-positive" data-role="thread-score-value">5</span>/<span data-role="thread-vote-count">7</span>)</span>', $permalink);
        assertStringContains('<p class="quote-card-body">The quoted body.<br />', $permalink);
        assertStringNotContains('<p class="meta">', $permalink);
        assertStringNotContains('>Reply</a>', $permalink);
        assertStringContains('data-tag="upvote"', $permalink);
        assertStringContains('data-tag="downvote"', $permalink);
        assertStringContains('data-tag="flag"', $permalink);
        assertStringNotContains('data-tag="like"', $permalink);
    }

    public function testNonQdbPermalinkRootCardKeepsLikeAndHasNoQuoteHeader(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment();
        $this->writeImportedQuote($repositoryRoot, 'thread-20030613104735-qdb-42', 'The quoted body.');

        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
        $permalink = $this->render($application, '/threads/thread-20030613104735-qdb-42');

        assertStringContains('data-tag="like"', $permalink);
        assertStringNotContains('quote-card-permalink', $permalink);
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

    private function writeImportedQuote(
        string $repositoryRoot,
        string $postId,
        string $body,
        int $scoreSeed = 5,
        int $voteCountSeed = 7,
    ): void
    {
        file_put_contents(
            $repositoryRoot . '/records/posts/' . $postId . '.txt',
            "Post-ID: {$postId}\n"
            . "Created-At: 2003-06-13T10:47:35Z\n"
            . "Board-Tags: general\n"
            . "Imported-Score-Seed: {$scoreSeed}\n"
            . "Imported-Vote-Count-Seed: {$voteCountSeed}\n"
            . "\n{$body}\n"
        );
        $this->runCommand($repositoryRoot, 'git add .');
        $this->runCommand($repositoryRoot, 'git commit -m "Add imported quote fixture"');
    }

    /** @param array<int, array{string,string,string,string}> $news */
    private function writeNews(string $repositoryRoot, array $news): void
    {
        foreach ($news as [$postId, $createdAt, $subject, $body]) {
            file_put_contents(
                $repositoryRoot . '/records/posts/' . $postId . '.txt',
                "Post-ID: {$postId}\n"
                . "Created-At: {$createdAt}\n"
                . "Board-Tags: news\n"
                . ($subject === '' ? '' : "Subject: {$subject}\n")
                . "\n{$body}\n"
            );
        }

        $this->runCommand($repositoryRoot, 'git add .');
        $this->runCommand($repositoryRoot, 'git commit -m "Add news fixtures"');
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
