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
        assertStringNotContains('rel="prev"', $this->markup($first)); // inline CSS selects .nav-link[rel="prev"]
        preg_match('/rel="next" href="([^"]+)">Show older threads</', $first, $next);
        assertTrue(isset($next[1]), 'Expected a Show older threads link.');

        $second = $this->render($application, html_entity_decode($next[1]));
        assertStringNotContains('pinned-list', $this->markup($second));
        assertStringContains('href="/threads/board-list-010"', $second);
        assertStringContains('href="/threads/root-001"', $second);
        assertStringNotContains('href="/threads/board-list-060"', $second);
        assertStringNotContains('rel="next"', $this->markup($second));
        preg_match('/rel="prev" href="([^"]+)">Newer threads</', $second, $previous);
        assertTrue(isset($previous[1]), 'Expected a Newer threads link.');

        $back = $this->render($application, html_entity_decode($previous[1]));
        assertStringContains('href="/threads/board-list-060"', $back);
        assertStringContains('href="/threads/board-list-011"', $back);
        assertStringNotContains('href="/threads/board-list-010"', $back);
    }

    public function testPageNumbersWithAndDefaultsInBoardLinks(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment(60);
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);

        $first = $this->render($application, '/threads/?view=all&sort=newest');
        assertStringContains('rel="next" href="/threads/?view=all&amp;page=2">Show older threads</a>', $first, 'The default sort is left out.');
        assertStringContains('id="post-board-list-060"', $first);
        assertStringNotContains('?position=', $first);
        assertStringNotContains('&amp;position=', $first);

        $past = $this->render($application, '/threads/?view=all&page=9');
        assertStringContains('href="/threads/?view=all">First page</a>', $past);
        assertStringNotContains('pinned-list', $this->markup($past));

        assertSame(51, substr_count($this->render($application, '/threads/?view=all&page=abc'), 'class="thread-row"'), 'A malformed page shows page 1.');

        $older = $this->render($application, '/threads/?view=all&sort=newest&with=root-001');
        assertStringContains('/threads/?view=all&amp;page=2#post-root-001', $older);
        $pinned = $this->render($application, '/threads/?view=all&with=thread-zenmemes-rules');
        assertStringContains('/threads/?view=all#post-thread-zenmemes-rules', $pinned);
        assertSame(51, substr_count($this->render($application, '/threads/?view=all&with=no-such-thread'), 'class="thread-row"'));
    }

    public function testEmptyLikedViewOffersAllThreads(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment(0);
        $liked = $this->render(new Application(dirname(__DIR__), $repositoryRoot, $databasePath), '/threads/?view=liked&sort=top');

        assertStringContains('<h2>No liked threads yet</h2>', $liked);
        assertStringContains('href="/threads/?view=all&amp;sort=top">Show all threads</a>', $liked);
        assertStringContains('data-nav-key="liked" aria-current="page"><span class="like-mark" aria-hidden="true"></span>Liked</a>', $liked);
    }

    public function testDefaultBoardShowsAllUntilSomethingIsLiked(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment(0);
        $board = $this->render(new Application(dirname(__DIR__), $repositoryRoot, $databasePath), '/threads/');
        assertStringContains('data-nav-key="all" aria-current="page">All</a>', $board, 'Nothing liked yet: the default board shows All.');
        assertStringContains('href="/threads/root-001"', $board);
        assertStringContains('<a class="nav-link" href="/start/">Getting started</a>', $board);
        assertStringNotContains('No liked threads yet', $board);

        file_put_contents($repositoryRoot . '/records/thread-labels/thread-label-20260601000000-likeroot.txt',
            "Record-ID: thread-label-20260601000000-likeroot\nCreated-At: 2026-06-01T00:00:00Z\nThread-ID: root-001\nOperation: add\nLabels: like\nAuthor-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954\n\n");
        $this->runCommand($repositoryRoot, 'git add .');
        $this->runCommand($repositoryRoot, 'git commit -m "Like a thread"');
        $liked = $this->render(new Application(dirname(__DIR__), $repositoryRoot, $databasePath . '-liked'), '/threads/');
        assertStringContains('data-nav-key="liked" aria-current="page">', $liked, 'Once something is liked, the default is Liked again.');
        assertStringContains('href="/threads/root-001"', $liked);
        assertStringNotContains('href="/start/"', $liked);
    }

    public function testStartPageOffersBrowsingAndImportChoices(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment(0);
        $oldHost = $_SERVER['HTTP_HOST'] ?? null;
        try {
            $_SERVER['HTTP_HOST'] = '127.0.0.1:8000';
            $page = $this->render(new Application(dirname(__DIR__), $repositoryRoot, $databasePath), '/start/');
            assertStringContains('<h1>Getting started</h1>', $page);
            assertStringContains('<a href="/threads/?view=all">Browse threads</a>', $page);
            assertStringContains('<code>./v3 import-instance zenmemes --dry-run</code>', $page);
            assertStringContains('<code>./v3 import-instance forum.example --dry-run</code>', $page);
            assertStringContains('<a href="/compose/thread">Start a thread</a>', $page);

            $_SERVER['HTTP_HOST'] = 'zenmemes.com';
            $own = $this->render(new Application(dirname(__DIR__), $repositoryRoot, $databasePath), '/start/');
            assertStringNotContains('import-instance zenmemes ', $own, 'An instance does not offer its own address.');
            assertStringContains('import-instance forum.example', $own);
        } finally {
            if ($oldHost === null) unset($_SERVER['HTTP_HOST']); else $_SERVER['HTTP_HOST'] = $oldHost;
        }
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

    public function testImagePostsShowAThumbnailOnTheBoardAndRecent(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment(0);
        $imageRoot = $repositoryRoot . '-images';
        $flag = \ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry::IMAGE_CONTENT_ENABLED;
        $old = getenv($flag);
        $thumbnailFlag = \ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry::LIST_THUMBNAILS_ENABLED;
        $oldThumbnail = getenv($thumbnailFlag);
        try {
            $source = $repositoryRoot . '-pixel.png';
            file_put_contents($source, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgYGAAAAAEAAGjChXjAAAAAElFTkSuQmCC'));
            $reference = \ForumRewrite\Images\ImageStore::inspect($source, '');
            (new \ForumRewrite\Images\ImageStore($repositoryRoot))->put($source, $reference);
            $missing = ['sha256' => str_repeat('b', 64), 'type' => 'png', 'bytes' => 10, 'width' => 20, 'height' => 30, 'description' => ''];
            foreach ([
                'thumb-present' => \ForumRewrite\Images\ImageReference::line($reference),
                'thumb-missing' => \ForumRewrite\Images\ImageReference::line($missing),
                'thumb-none' => 'Just words.',
            ] as $postId => $body) {
                file_put_contents($repositoryRoot . '/records/posts/' . $postId . '.txt', "Post-ID: {$postId}\nCreated-At: 2026-07-01T00:00:00Z\nBoard-Tags: general\nSubject: {$postId}\n\nWords first.\n{$body}\n");
            }
            $this->runCommand($repositoryRoot, 'git add .');
            $this->runCommand($repositoryRoot, 'git commit -m "Add image posts"');
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
            $thumb = '<a class="thread-row__thumb" href="/threads/thread-present" tabindex="-1" aria-hidden="true"><img src="/images/' . \ForumRewrite\Images\ImageReference::filename($reference) . '" alt="" width="1" height="1" loading="lazy" decoding="async"></a>';

            putenv($flag . '=true');
            putenv($thumbnailFlag . '=true');
            $board = $this->markup($this->render($application, '/threads/?view=all&sort=newest'));
            assertStringContains(str_replace('thread-present', 'thumb-present', $thumb), $board);
            assertSame(1, substr_count($board, 'class="thread-row__thumb"'), 'Only the post with a stored image gets a thumbnail.');
            assertSame(1, substr_count($board, 'thread-row--thumb'));

            $recent = $this->markup($this->render($application, '/recent/'));
            assertStringContains(str_replace('thread-present', 'thumb-present', $thumb), $recent);
            assertSame(1, substr_count($recent, 'class="thread-row__thumb"'));

            putenv($thumbnailFlag . '=false');
            assertStringNotContains('thread-row__thumb', $this->markup($this->render($application, '/threads/?view=all&sort=newest')));
            assertStringNotContains('thread-row__thumb', $this->markup($this->render($application, '/recent/')));
            putenv($thumbnailFlag . '=true');
            putenv($flag . '=false');
            assertStringNotContains('thread-row__thumb', $this->markup($this->render($application, '/threads/?view=all&sort=newest')));
            assertStringNotContains('thread-row__thumb', $this->markup($this->render($application, '/recent/')));
        } finally {
            putenv($oldThumbnail === false ? $thumbnailFlag : $thumbnailFlag . '=' . $oldThumbnail);
            putenv($old === false ? $flag : $flag . '=' . $old);
            exec('rm -rf ' . escapeshellarg($imageRoot) . ' ' . escapeshellarg($repositoryRoot . '-pixel.png'));
        }
    }

    public function testBoardRowUsesTheThreadsFirstImageEvenFromAReply(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment(0);
        $imageRoot = $repositoryRoot . '-images';
        $flag = \ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry::IMAGE_CONTENT_ENABLED;
        $old = getenv($flag);
        $thumbnailFlag = \ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry::LIST_THUMBNAILS_ENABLED;
        $oldThumbnail = getenv($thumbnailFlag);
        try {
            $store = new \ForumRewrite\Images\ImageStore($repositoryRoot);
            $references = [];
            foreach (['first' => [10, 20, 200], 'second' => [10, 20, 30]] as $name => [$red, $green, $blue]) {
                $source = $repositoryRoot . '-' . $name . '.png';
                $canvas = imagecreatetruecolor(2, 2);
                imagefill($canvas, 0, 0, imagecolorallocate($canvas, $red, $green, $blue));
                imagepng($canvas, $source);
                $references[$name] = \ForumRewrite\Images\ImageStore::inspect($source, '');
                $store->put($source, $references[$name]);
            }
            $missing = ['sha256' => str_repeat('b', 64), 'type' => 'png', 'bytes' => 10, 'width' => 20, 'height' => 30, 'description' => ''];
            $posts = [
                'text-root' => ["Subject: text-root", 'Just words.', '00'],
                'reply-missing' => ["Thread-ID: text-root\nParent-ID: text-root", \ForumRewrite\Images\ImageReference::line($missing), '01'],
                'reply-first' => ["Thread-ID: text-root\nParent-ID: text-root", \ForumRewrite\Images\ImageReference::line($references['first']), '02'],
                'reply-second' => ["Thread-ID: text-root\nParent-ID: reply-first", \ForumRewrite\Images\ImageReference::line($references['second']), '03'],
                'image-root' => ["Subject: image-root", \ForumRewrite\Images\ImageReference::line($references['second']), '04'],
                'image-reply' => ["Thread-ID: image-root\nParent-ID: image-root", \ForumRewrite\Images\ImageReference::line($references['first']), '05'],
            ];
            foreach ($posts as $postId => [$headers, $body, $minute]) {
                file_put_contents($repositoryRoot . '/records/posts/' . $postId . '.txt', "Post-ID: {$postId}\nCreated-At: 2026-07-01T00:{$minute}:00Z\nBoard-Tags: general\n{$headers}\n\n{$body}\n");
            }
            $this->runCommand($repositoryRoot, 'git add .');
            $this->runCommand($repositoryRoot, 'git commit -m "Add threads with images in replies"');
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
            putenv($flag . '=true');
            putenv($thumbnailFlag . '=true');
            $thumb = static fn (string $thread, array $reference): string => '<a class="thread-row__thumb" href="/threads/' . $thread . '" tabindex="-1" aria-hidden="true"><img src="/images/' . \ForumRewrite\Images\ImageReference::filename($reference) . '"';

            $board = $this->markup($this->render($application, '/threads/?view=all&sort=newest'));
            assertStringContains($thumb('text-root', $references['first']), $board, 'A text-only root shows the first stored image from its replies.');
            assertStringContains($thumb('image-root', $references['second']), $board, 'A root with its own image keeps it.');
            assertSame(2, substr_count($board, 'class="thread-row__thumb"'));

            $recent = $this->markup($this->render($application, '/recent/'));
            assertStringNotContains('href="/threads/text-root" tabindex="-1"', $recent, 'Recent rows still show only their own post\'s image.');
        } finally {
            putenv($oldThumbnail === false ? $thumbnailFlag : $thumbnailFlag . '=' . $oldThumbnail);
            putenv($old === false ? $flag : $flag . '=' . $old);
            exec('rm -rf ' . escapeshellarg($imageRoot) . ' ' . escapeshellarg($repositoryRoot . '-first.png') . ' ' . escapeshellarg($repositoryRoot . '-second.png'));
        }
    }

    public function testLargeImagesGetSmallCachedThumbnailCopies(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment(0);
        $imageRoot = $repositoryRoot . '-images';
        $flag = \ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry::IMAGE_CONTENT_ENABLED;
        $old = getenv($flag);
        try {
            $source = $repositoryRoot . '-wide.png';
            $canvas = imagecreatetruecolor(400, 100);
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 200, 40, 40));
            // Blue ends that a centred square crop leaves out.
            imagefilledrectangle($canvas, 0, 0, 99, 99, imagecolorallocate($canvas, 20, 40, 200));
            imagefilledrectangle($canvas, 300, 0, 399, 99, imagecolorallocate($canvas, 20, 40, 200));
            imagepng($canvas, $source);
            $reference = \ForumRewrite\Images\ImageStore::inspect($source, '');
            $store = new \ForumRewrite\Images\ImageStore($repositoryRoot);
            $store->put($source, $reference);
            file_put_contents($repositoryRoot . '/records/posts/thumb-wide.txt', "Post-ID: thumb-wide\nCreated-At: 2026-07-01T00:00:00Z\nBoard-Tags: general\nSubject: thumb-wide\n\n" . \ForumRewrite\Images\ImageReference::line($reference) . "\n");
            $this->runCommand($repositoryRoot, 'git add .');
            $this->runCommand($repositoryRoot, 'git commit -m "Add wide image post"');
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
            putenv($flag . '=true');

            $one = '/images/thumbs/' . \ForumRewrite\Images\ThumbnailStore::filename($reference, 'sq');
            $two = '/images/thumbs/' . \ForumRewrite\Images\ThumbnailStore::filename($reference, 'sq2x');
            $board = $this->markup($this->render($application, '/threads/?view=all&sort=newest'));
            assertStringContains('<img src="' . $one . '" srcset="' . $one . ' 1x, ' . $two . ' 2x" alt="" width="400" height="100"', $board);
            assertStringNotContains('src="/images/' . \ForumRewrite\Images\ImageReference::filename($reference) . '"', $board, 'The full-size original is not linked from the list.');

            foreach ([[$one, 72], [$two, 100]] as [$url, $side]) {
                $bytes = $this->render($application, $url);
                $size = getimagesizefromstring($bytes);
                assertSame([$side, $side], [$size[0], $size[1]], $url . ' is a square crop for the row slot, never larger than the original.');
                assertTrue(strlen($bytes) < $reference['bytes'] || $reference['bytes'] < 1000, 'The thumbnail is not larger than needed.');
                $pixels = imagecreatefromstring($bytes);
                foreach ([0, $side - 1] as $x) {
                    $color = imagecolorsforindex($pixels, imagecolorat($pixels, $x, intdiv($side, 2)));
                    assertTrue($color['red'] > $color['blue'], $url . ' is cropped from the centre.');
                }
            }
            $legacy = getimagesizefromstring($this->render($application, '/images/thumbs/' . $reference['sha256'] . '-1x.png'));
            assertSame([72, 72], [$legacy[0], $legacy[1]], 'A list-row URL from an older page gets the square crop.');
            $cached = $store->root . '-thumbs/' . \ForumRewrite\Images\ThumbnailStore::filename($reference, 'sq');
            assertTrue(is_file($cached), 'The thumbnail is cached beside the image root.');
            assertSame($this->render($application, $one), file_get_contents($cached));
            $store->inventory();

            unlink($store->root . '/' . \ForumRewrite\Images\ImageReference::filename($reference));
            assertStringContains('Image unavailable', $this->render($application, $one), 'A thumbnail is not served once its original is gone.');
            assertStringContains('Image unavailable', $this->render($application, '/images/thumbs/' . str_repeat('c', 64) . '-3x.png'));
        } finally {
            putenv($old === false ? $flag : $flag . '=' . $old);
            exec('rm -rf ' . escapeshellarg($imageRoot) . ' ' . escapeshellarg($imageRoot . '-thumbs') . ' ' . escapeshellarg($repositoryRoot . '-wide.png'));
        }
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

        assertSame('<a href="/profiles/mouse">mouse</a> · <time datetime="2025-10-06T12:37:00Z" title="Oct 6, 2025 at 12:37 UTC">Oct 6, 2025</time> <a class="pending-chip" href="/profiles/mouse" data-pending-profile="mouse" data-pending-username="mouse"><span class="chip chip--pending">Pending</span></a>', $meta);
    }

    public function testPendingChipIsAPlainLabelWhenItsFlagIsOff(): void
    {
        $flag = 'FORUM_PENDING_CHIP_APPROVAL_ENABLED';
        $old = getenv($flag);
        putenv($flag . '=0');
        try {
            $renderer = new TemplateRenderer(dirname(__DIR__) . '/templates');
            $method = new ReflectionMethod($renderer, 'renderRowMeta');
            $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES);
            $meta = $method->invoke($renderer, [
                'author_label' => 'mouse',
                'author_profile_slug' => 'mouse',
                'author_is_approved' => 0,
                'root_post_created_at' => '2025-10-06T12:37:00Z',
            ], 'root_post_created_at', $escape);

            assertStringContains('</time> <span class="chip chip--pending">Pending</span>', $meta);
            assertStringNotContains('data-pending-profile', $meta);
        } finally {
            putenv($old === false ? $flag : $flag . '=' . $old);
        }
    }

    /** Text of the rows container with the given class. */
    public function testZenMemesOpenTheHomePageOnly(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment(60);
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
        assertStringNotContains('class="zen-strip"', $this->markup($this->render($application, '/')));

        // Make one thread's signed author an approved member who tagged it #zen #meme.
        $pdo = new PDO('sqlite:' . $databasePath);
        $pdo->exec("INSERT INTO profiles (identity_id,profile_slug,username,username_token,fallback_label,signer_fingerprint,bootstrap_post_id,bootstrap_thread_id,public_key,is_approved,was_approved) VALUES ('id-zen','zen','zen','zen','zen','fp','b','b','k',1,1)");
        $pdo->exec("UPDATE posts SET author_identity_id = 'id-zen', attribution_counted = 1 WHERE post_id = 'board-list-030'");
        $pdo->exec("UPDATE threads SET board_tags_json = '[\"zen\",\"meme\"]' WHERE root_post_id = 'board-list-030'");
        $pdo = null;

        $first = $this->markup($this->render($application, '/'));
        assertSame(1, substr_count($first, 'class="zen-strip"'));
        assertTrue(strpos($first, 'class="zen-strip"') < strpos($first, 'data-visitor-intro'), 'The strip should come before the visitor intro.');
        assertStringContains('<a class="zen-strip__card zen-strip__card--text" href="/threads/board-list-030"><span>Thread 30</span></a>', $first);
        // One Zen meme: no thumbnail row, and never a separate All link.
        assertStringNotContains('zen-strip__row', $first);
        assertStringNotContains('zen-strip__more', $first);

        // A second, better-liked meme takes the top spot; the first drops into the row, which is one link to All.
        $pdo = new PDO('sqlite:' . $databasePath);
        $pdo->exec("UPDATE posts SET author_identity_id = 'id-zen', attribution_counted = 1 WHERE post_id = 'board-list-010'");
        $pdo->exec("UPDATE threads SET board_tags_json = '[\"zen\",\"meme\"]', score_total = 3 WHERE root_post_id = 'board-list-010'");
        $pdo = null;
        $first = $this->markup($this->render($application, '/'));
        $strip = substr($first, strpos($first, '<section class="zen-strip"'), 1200);
        $strip = substr($strip, 0, strpos($strip, '</section>'));
        assertStringContains('<a class="zen-strip__card zen-strip__card--text" href="/threads/board-list-010"><span>Thread 10</span></a>', $strip);
        assertStringContains('<a class="zen-strip__row" href="/tags/zen+meme" aria-label="All Zen memes">', $strip);
        assertStringContains('<span class="zen-strip__thumb zen-strip__thumb--text" aria-hidden="true">Thread 30</span>', $strip);
        assertSame(2, substr_count($strip, '<a '));
        assertStringNotContains('zen-strip__more', $first);
        preg_match('/rel="next" href="([^"]+)">Show older threads</', $first, $next);
        // Not on the threads page, a chosen view or sort, or a later page.
        foreach (['/threads/', '/?view=all', '/?view=liked', '/?sort=top', '/threads/?view=all&sort=newest', html_entity_decode($next[1])] as $elsewhere) {
            assertStringNotContains('class="zen-strip"', $this->markup($this->render($application, $elsewhere)), $elsewhere);
        }

        putenv('FORUM_ZEN_STRIP_ENABLED=false');
        try {
            $off = $this->markup($this->render(new Application(dirname(__DIR__), $repositoryRoot, $databasePath), '/'));
        } finally {
            putenv('FORUM_ZEN_STRIP_ENABLED');
        }
        assertStringNotContains('class="zen-strip"', $off);
    }

    public function testZenStripImageCardsShowTheWholeImageWithTheOriginalForSharpScreens(): void
    {
        [$repositoryRoot, $databasePath] = $this->createTempEnvironment(0);
        $imageRoot = $repositoryRoot . '-images';
        $flag = \ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry::IMAGE_CONTENT_ENABLED;
        $old = getenv($flag);
        try {
            $source = $repositoryRoot . '-meme.png';
            $canvas = imagecreatetruecolor(900, 600);
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 240, 235, 225));
            imagepng($canvas, $source);
            $reference = \ForumRewrite\Images\ImageStore::inspect($source, 'A calm pond');
            (new \ForumRewrite\Images\ImageStore($repositoryRoot))->put($source, $reference);
            file_put_contents($repositoryRoot . '/records/posts/zen-image.txt', "Post-ID: zen-image\nCreated-At: 2026-07-01T00:00:00Z\nBoard-Tags: zen meme\nSubject: Pond\n\n" . \ForumRewrite\Images\ImageReference::line($reference) . "\n");
            $this->runCommand($repositoryRoot, 'git add .');
            $this->runCommand($repositoryRoot, 'git commit -m "Add a Zen meme"');
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
            putenv($flag . '=true');
            $this->render($application, '/threads/?view=all&sort=newest');
            $pdo = new PDO('sqlite:' . $databasePath);
            $pdo->exec("INSERT INTO profiles (identity_id,profile_slug,username,username_token,fallback_label,signer_fingerprint,bootstrap_post_id,bootstrap_thread_id,public_key,is_approved,was_approved) VALUES ('id-zen','zen','zen','zen','zen','fp','b','b','k',1,1)");
            $pdo->exec("UPDATE posts SET author_identity_id = 'id-zen', attribution_counted = 1 WHERE post_id = 'zen-image'");
            $pdo = null;

            $tile = '/images/thumbs/' . \ForumRewrite\Images\ThumbnailStore::filename($reference, 'tile');
            $full = '/images/' . \ForumRewrite\Images\ImageReference::filename($reference);
            $board = $this->markup($this->render($application, '/'));
            assertStringContains('<a class="zen-strip__card zen-strip__card--image" href="/threads/zen-image"><img src="' . $tile . '" srcset="' . $tile . ' 480w, ' . $full . ' 900w" sizes="(min-width: 48rem) 24rem, 100vw" alt="A calm pond" width="900" height="600"', $board);
        } finally {
            putenv($old === false ? $flag : $flag . '=' . $old);
            exec('rm -rf ' . escapeshellarg($imageRoot) . ' ' . escapeshellarg($imageRoot . '-thumbs') . ' ' . escapeshellarg($repositoryRoot . '-meme.png'));
        }
    }

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
