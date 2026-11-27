<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Http\MediaEmbedPreviewController;
use ForumRewrite\Http\RouteServices;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\View\BandcampPagePlayerFetcher;
use ForumRewrite\View\InstagramPagePreviewFetcher;
use ForumRewrite\View\MediaEmbedDetector;
use ForumRewrite\View\MediaEmbedPreviewCacheStore;
use ForumRewrite\View\MediaEmbedPreviewDatabaseConfig;
use ForumRewrite\View\OembedTitleFetcher;
use ForumRewrite\View\TemplateRenderer;
use ForumRewrite\View\YoutubeOembedTitleFetcher;

final class MediaEmbedPreviewControllerTest
{
    public function testShortsFetchTitlesThroughACanonicalWatchUrl(): void
    {
        $root = $this->tempProjectRoot();
        try {
            $requested = '';
            $fetcher = new YoutubeOembedTitleFetcher(function (string $url) use (&$requested): string {
                $requested = $url;
                return '{"title":"A short clip"}';
            });
            $controller = $this->controller($root, new InstagramPagePreviewFetcher(), $fetcher);
            ob_start();
            $controller->warmPreview('GET', ['provider' => 'youtube', 'url' => 'http://youtube.com/shorts/M7lc1UVf-VE?si=tracking']);
            ob_end_clean();
            assertSame('https://www.youtube.com/oembed?format=json&url=' . rawurlencode('https://www.youtube.com/watch?v=M7lc1UVf-VE'), $requested);
            assertSame('A short clip', $this->readCache($root, 'M7lc1UVf-VE', 'youtube')['title']);
        } finally {
            $this->cleanup($root);
        }
    }

    public function testPosterModeCopiesAYoutubePosterAndServesItFromHere(): void
    {
        $root = $this->tempProjectRoot();
        $posters = $root . '/posters';
        try {
            $requested = [];
            $store = new \ForumRewrite\View\MediaEmbedPosterStore($posters, function (string $url) use (&$requested): string {
                $requested[] = $url;
                return "\xFF\xD8\xFFposter";
            });
            $controller = $this->controller($root, new InstagramPagePreviewFetcher(), posterStore: $store);
            ob_start();
            // Only a link the detector accepts as YouTube is fetched.
            $controller->warmPreview('GET', ['provider' => 'youtube', 'kind' => 'poster', 'url' => 'https://evil.test/watch?v=M7lc1UVf-VE']);
            $controller->warmPreview('GET', ['provider' => 'youtube', 'kind' => 'poster', 'url' => 'https://youtu.be/M7lc1UVf-VE']);
            ob_end_clean();
            assertSame(['https://i.ytimg.com/vi/M7lc1UVf-VE/hqdefault.jpg'], $requested);
            ob_start();
            $controller->poster('GET', 'M7lc1UVf-VE');
            assertSame("\xFF\xD8\xFFposter", ob_get_clean());
            // Posters off: no fetch, and nothing served.
            $off = $this->controller($root . '-off', new InstagramPagePreviewFetcher());
            ob_start();
            $off->warmPreview('GET', ['provider' => 'youtube', 'kind' => 'poster', 'url' => 'https://youtu.be/dQw4w9WgXcQ']);
            $off->poster('GET', 'M7lc1UVf-VE');
            assertSame("Not found.\n", ob_get_clean());
            assertSame(1, count($requested));
        } finally {
            @unlink($posters . '/M7lc1UVf-VE.jpg');
            @rmdir($posters);
            $this->cleanup($root);
        }
    }

    public function testMusicProvidersFetchTitlesThroughTheirOwnEndpoints(): void
    {
        $cases = [
            ['spotify', 'https://open.spotify.com/album/3LVR5at2HIWpbqFfV7Zzdj?si=x', 'https://open.spotify.com/oembed?url=' . rawurlencode('https://open.spotify.com/album/3LVR5at2HIWpbqFfV7Zzdj'), 'album:3LVR5at2HIWpbqFfV7Zzdj'],
            ['soundcloud', 'https://soundcloud.com/forss/flickermood?si=x', 'https://soundcloud.com/oembed?format=json&url=' . rawurlencode('https://soundcloud.com/forss/flickermood'), 'forss/flickermood'],
            ['tiktok', 'https://www.tiktok.com/@scout2015/video/6718335390845095173', 'https://www.tiktok.com/oembed?url=' . rawurlencode('https://www.tiktok.com/@scout2015/video/6718335390845095173'), '6718335390845095173'],
        ];
        foreach ($cases as [$provider, $url, $expectedRequest, $cacheId]) {
            $root = $this->tempProjectRoot();
            try {
                $requested = [];
                $oembed = new OembedTitleFetcher(function (string $request) use (&$requested): string {
                    $requested[] = $request;
                    return '{"title":"  A track  "}';
                });
                $controller = $this->controller($root, new InstagramPagePreviewFetcher(), null, null, null, $oembed);
                ob_start();
                $controller->warmPreview('GET', ['provider' => $provider, 'url' => $url]);
                $controller->warmPreview('GET', ['provider' => $provider, 'url' => $url]);
                ob_end_clean();
                assertSame([$expectedRequest], $requested, $provider . ': one request; the second is a cache hit');
                assertSame('A track', $this->readCache($root, $cacheId, $provider)['title']);
            } finally {
                $this->cleanup($root);
            }
        }
    }

    public function testBandcampTitleComesFromTheReleasePage(): void
    {
        $root = $this->tempProjectRoot();
        try {
            $bandcamp = new BandcampPagePlayerFetcher(fn (string $url): array => [
                'status' => 200, 'type' => 'text/html; charset=utf-8',
                'body' => '<html><head><meta charset="utf-8"><meta property="og:title" content="Take My Hand, by Dark Time Sunshine"></head></html>',
            ]);
            $url = 'https://darktimesunshine.bandcamp.com/track/take-my-hand';
            $controller = $this->controller($root, new InstagramPagePreviewFetcher(), null, null, null, null, $bandcamp);
            ob_start();
            $controller->warmPreview('GET', ['provider' => 'bandcamp', 'url' => $url]);
            ob_end_clean();
            assertSame('Take My Hand, by Dark Time Sunshine', $this->readCache($root, $url, 'bandcamp')['title']);
        } finally {
            $this->cleanup($root);
        }
    }

    public function testMusicFetchFailureIsCachedAndNeverWritesASubject(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createWritableEnvironment('root-no-subject');
        try {
            $calls = 0;
            $oembed = new OembedTitleFetcher(function () use (&$calls) { $calls++; return '{"html":"<iframe>"}'; });
            $controller = $this->controller($repositoryRoot, new InstagramPagePreviewFetcher(), null, $databasePath, $repositoryRoot, $oembed);
            ob_start();
            $controller->warmPreview('GET', ['provider' => 'tiktok', 'url' => 'https://www.tiktok.com/@scout2015/video/6718335390845095173', 'thread_id' => 'root-no-subject']);
            $controller->warmPreview('GET', ['provider' => 'tiktok', 'url' => 'https://www.tiktok.com/@scout2015/video/6718335390845095173', 'thread_id' => 'root-no-subject']);
            ob_end_clean();
            assertSame(1, $calls, 'a recent failure is not retried');
            assertSame(null, $this->readCache($repositoryRoot, '6718335390845095173', 'tiktok')['title']);
            assertSame('', (string) $this->currentSubject($databasePath, 'root-no-subject'));
        } finally {
            $this->cleanup($repositoryRoot);
        }
    }

    public function testMusicTitleBackfillsAnEmptyThreadSubject(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createWritableEnvironment('root-no-subject');
        try {
            $oembed = new OembedTitleFetcher(fn (string $url): string => '{"title":"I Done Changed A lot"}');
            $controller = $this->controller($repositoryRoot, new InstagramPagePreviewFetcher(), null, $databasePath, $repositoryRoot, $oembed);
            ob_start();
            $controller->warmPreview('GET', ['provider' => 'spotify', 'url' => 'https://open.spotify.com/album/3LVR5at2HIWpbqFfV7Zzdj', 'thread_id' => 'root-no-subject']);
            ob_end_clean();
            assertSame('I Done Changed A lot', $this->currentSubject($databasePath, 'root-no-subject'));
        } finally {
            $this->cleanup($repositoryRoot);
        }
    }

    public function testProviderMismatchIsRejectedWithoutFetch(): void
    {
        $root = $this->tempProjectRoot();
        try {
            $calls = 0;
            $oembed = new OembedTitleFetcher(function () use (&$calls) { $calls++; return '{"title":"x"}'; });
            $controller = $this->controller($root, new InstagramPagePreviewFetcher(), null, null, null, $oembed);
            ob_start();
            $controller->warmPreview('GET', ['provider' => 'spotify', 'url' => 'https://soundcloud.com/forss/flickermood']);
            $controller->warmPreview('GET', ['provider' => 'soundcloud', 'url' => 'https://example.com/forss/flickermood']);
            ob_end_clean();
            assertSame(0, $calls);
        } finally {
            $this->cleanup($root);
        }
    }

    public function testValidInstagramUrlWithColdCacheTriggersFetchAndWritesCache(): void
    {
        $projectRoot = $this->tempProjectRoot();

        try {
            $callCount = 0;
            $fetcher = new InstagramPagePreviewFetcher(function (string $url) use (&$callCount): string {
                $callCount++;
                return '<meta property="og:title" content="A great clip">'
                    . '<meta property="og:image" content="https://example.com/thumb.jpg">';
            });
            $controller = $this->controller($projectRoot, $fetcher);

            ob_start();
            $controller->warmPreview('GET', ['provider' => 'instagram', 'url' => 'https://www.instagram.com/p/Cabc123XYZ/']);
            ob_end_clean();

            assertSame(1, $callCount);
            $row = $this->readCache($projectRoot, 'Cabc123XYZ');
            assertSame('A great clip', $row['title']);
            assertSame('https://example.com/thumb.jpg', $row['thumbnailUrl']);
        } finally {
            $this->cleanup($projectRoot);
        }
    }

    public function testInvalidProviderIsRejectedWithoutFetch(): void
    {
        $projectRoot = $this->tempProjectRoot();

        try {
            $callCount = 0;
            $fetcher = new InstagramPagePreviewFetcher(function () use (&$callCount): string {
                $callCount++;
                return '';
            });
            $controller = $this->controller($projectRoot, $fetcher);

            ob_start();
            $controller->warmPreview('GET', ['provider' => 'youtube', 'url' => 'https://www.instagram.com/p/Cabc123XYZ/']);
            ob_end_clean();

            assertSame(0, $callCount);
            assertSame(null, $this->readCache($projectRoot, 'Cabc123XYZ'));
        } finally {
            $this->cleanup($projectRoot);
        }
    }

    public function testUrlThatDoesNotClassifyAsInstagramIsRejectedWithoutFetch(): void
    {
        $projectRoot = $this->tempProjectRoot();

        try {
            $callCount = 0;
            $fetcher = new InstagramPagePreviewFetcher(function () use (&$callCount): string {
                $callCount++;
                return '';
            });
            $controller = $this->controller($projectRoot, $fetcher);

            ob_start();
            $controller->warmPreview('GET', ['provider' => 'instagram', 'url' => 'https://vimeo.com/12345678']);
            ob_end_clean();

            assertSame(0, $callCount);
        } finally {
            $this->cleanup($projectRoot);
        }
    }

    public function testWarmCacheShortCircuitsWithoutFetch(): void
    {
        $projectRoot = $this->tempProjectRoot();

        try {
            $this->seedCache($projectRoot, 'Cabc123XYZ', 'Already cached', 'https://example.com/thumb.jpg', null);

            $callCount = 0;
            $fetcher = new InstagramPagePreviewFetcher(function () use (&$callCount): string {
                $callCount++;
                return '';
            });
            $controller = $this->controller($projectRoot, $fetcher);

            ob_start();
            $controller->warmPreview('GET', ['provider' => 'instagram', 'url' => 'https://www.instagram.com/p/Cabc123XYZ/']);
            ob_end_clean();

            assertSame(0, $callCount);
        } finally {
            $this->cleanup($projectRoot);
        }
    }

    public function testRecentFailureShortCircuitsButAnOldFailureRetries(): void
    {
        $projectRoot = $this->tempProjectRoot();

        try {
            $this->seedCache($projectRoot, 'Cabc123XYZ', null, null, '-1 minute');

            $callCount = 0;
            $fetcher = new InstagramPagePreviewFetcher(function () use (&$callCount): string {
                $callCount++;
                return '';
            });
            $controller = $this->controller($projectRoot, $fetcher);

            ob_start();
            $controller->warmPreview('GET', ['provider' => 'instagram', 'url' => 'https://www.instagram.com/p/Cabc123XYZ/']);
            ob_end_clean();

            assertSame(0, $callCount);

            $this->seedCache($projectRoot, 'Cabc123XYZ', null, null, '-3 hours');

            ob_start();
            $controller->warmPreview('GET', ['provider' => 'instagram', 'url' => 'https://www.instagram.com/p/Cabc123XYZ/']);
            ob_end_clean();

            assertSame(1, $callCount);
        } finally {
            $this->cleanup($projectRoot);
        }
    }

    public function testValidYoutubeUrlWithColdCacheTriggersFetchAndWritesCache(): void
    {
        $projectRoot = $this->tempProjectRoot();

        try {
            $callCount = 0;
            $youtubeFetcher = new YoutubeOembedTitleFetcher(function (string $url) use (&$callCount): string {
                $callCount++;
                return json_encode(['title' => 'A great video', 'thumbnail_url' => 'https://i.ytimg.com/vi/abc123/hqdefault.jpg'], JSON_THROW_ON_ERROR);
            });
            $controller = $this->controller($projectRoot, new InstagramPagePreviewFetcher(), $youtubeFetcher);

            ob_start();
            $controller->warmPreview('GET', ['provider' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=abc12345678']);
            ob_end_clean();

            assertSame(1, $callCount);
            $row = $this->readCache($projectRoot, 'abc12345678', 'youtube');
            assertSame('A great video', $row['title']);
            assertSame('https://i.ytimg.com/vi/abc123/hqdefault.jpg', $row['thumbnailUrl']);
        } finally {
            $this->cleanup($projectRoot);
        }
    }

    public function testYoutubeWarmCacheShortCircuitsWithoutFetch(): void
    {
        $projectRoot = $this->tempProjectRoot();

        try {
            $this->seedCache($projectRoot, 'abc12345678', 'Already cached', 'https://example.com/thumb.jpg', null, 'youtube');

            $callCount = 0;
            $youtubeFetcher = new YoutubeOembedTitleFetcher(function () use (&$callCount): string {
                $callCount++;
                return '';
            });
            $controller = $this->controller($projectRoot, new InstagramPagePreviewFetcher(), $youtubeFetcher);

            ob_start();
            $controller->warmPreview('GET', ['provider' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=abc12345678']);
            ob_end_clean();

            assertSame(0, $callCount);
        } finally {
            $this->cleanup($projectRoot);
        }
    }

    public function testYoutubeRecentFailureShortCircuitsButAnOldFailureRetries(): void
    {
        $projectRoot = $this->tempProjectRoot();

        try {
            $this->seedCache($projectRoot, 'abc12345678', null, null, '-1 minute', 'youtube');

            $callCount = 0;
            $youtubeFetcher = new YoutubeOembedTitleFetcher(function () use (&$callCount): string {
                $callCount++;
                return '';
            });
            $controller = $this->controller($projectRoot, new InstagramPagePreviewFetcher(), $youtubeFetcher);

            ob_start();
            $controller->warmPreview('GET', ['provider' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=abc12345678']);
            ob_end_clean();

            assertSame(0, $callCount);

            $this->seedCache($projectRoot, 'abc12345678', null, null, '-3 hours', 'youtube');

            ob_start();
            $controller->warmPreview('GET', ['provider' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=abc12345678']);
            ob_end_clean();

            assertSame(1, $callCount);
        } finally {
            $this->cleanup($projectRoot);
        }
    }

    public function testInstagramAndYoutubeCachesAreKeyedIndependentlyByProvider(): void
    {
        $projectRoot = $this->tempProjectRoot();

        try {
            $this->seedCache($projectRoot, 'sharedid123', 'Instagram title', 'https://example.com/ig.jpg', null, 'instagram');

            $youtubeFetcher = new YoutubeOembedTitleFetcher(
                fn (string $url): string => json_encode(['title' => 'YouTube title', 'thumbnail_url' => 'https://example.com/yt.jpg'], JSON_THROW_ON_ERROR)
            );
            $controller = $this->controller($projectRoot, new InstagramPagePreviewFetcher(), $youtubeFetcher);

            ob_start();
            $controller->warmPreview('GET', ['provider' => 'youtube', 'url' => 'https://youtu.be/sharedid123']);
            ob_end_clean();

            $instagramRow = $this->readCache($projectRoot, 'sharedid123', 'instagram');
            $youtubeRow = $this->readCache($projectRoot, 'sharedid123', 'youtube');
            assertSame('Instagram title', $instagramRow['title']);
            assertSame('YouTube title', $youtubeRow['title']);
        } finally {
            $this->cleanup($projectRoot);
        }
    }

    public function testThreadIdWithSuccessfulFetchBackfillsEmptyThreadSubject(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createWritableEnvironment('root-no-subject');

        try {
            $youtubeFetcher = new YoutubeOembedTitleFetcher(
                fn (string $url): string => json_encode(['title' => 'Rick Astley - Never Gonna Give You Up'], JSON_THROW_ON_ERROR)
            );
            $controller = $this->controller($repositoryRoot, new InstagramPagePreviewFetcher(), $youtubeFetcher, $databasePath, $repositoryRoot);

            ob_start();
            $controller->warmPreview('GET', [
                'provider' => 'youtube',
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'thread_id' => 'root-no-subject',
            ]);
            ob_end_clean();

            assertSame('Rick Astley - Never Gonna Give You Up', $this->currentSubject($databasePath, 'root-no-subject'));
        } finally {
            $this->cleanup($repositoryRoot);
        }
    }

    public function testThreadIdIsIgnoredWhenFetchFails(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createWritableEnvironment('root-no-subject');

        try {
            $youtubeFetcher = new YoutubeOembedTitleFetcher(fn (string $url) => false);
            $controller = $this->controller($repositoryRoot, new InstagramPagePreviewFetcher(), $youtubeFetcher, $databasePath, $repositoryRoot);

            ob_start();
            $controller->warmPreview('GET', [
                'provider' => 'youtube',
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'thread_id' => 'root-no-subject',
            ]);
            ob_end_clean();

            assertSame(null, $this->currentSubject($databasePath, 'root-no-subject'));
        } finally {
            $this->cleanup($repositoryRoot);
        }
    }

    public function testThreadIdIsIgnoredWhenAbsentFromRequest(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createWritableEnvironment('root-no-subject');

        try {
            $youtubeFetcher = new YoutubeOembedTitleFetcher(
                fn (string $url): string => json_encode(['title' => 'Should Not Be Written Without thread_id'], JSON_THROW_ON_ERROR)
            );
            $controller = $this->controller($repositoryRoot, new InstagramPagePreviewFetcher(), $youtubeFetcher, $databasePath, $repositoryRoot);

            ob_start();
            $controller->warmPreview('GET', [
                'provider' => 'youtube',
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ]);
            ob_end_clean();

            assertSame(null, $this->currentSubject($databasePath, 'root-no-subject'));
        } finally {
            $this->cleanup($repositoryRoot);
        }
    }

    private function controller(
        string $projectRoot,
        InstagramPagePreviewFetcher $fetcher,
        ?YoutubeOembedTitleFetcher $youtubeFetcher = null,
        ?string $databasePath = null,
        ?string $repositoryRoot = null,
        ?OembedTitleFetcher $oembedFetcher = null,
        ?BandcampPagePlayerFetcher $bandcampFetcher = null,
        ?\ForumRewrite\View\MediaEmbedPosterStore $posterStore = null
    ): MediaEmbedPreviewController {
        $routeServices = new RouteServices(
            $databasePath ?? $projectRoot . '/read_model.sqlite3',
            new TemplateRenderer(dirname(__DIR__) . '/templates'),
            'php-fallback',
            false,
            static fn (): ?array => null,
            $repositoryRoot ?? $projectRoot,
            $projectRoot,
            null,
            null,
            FeatureFlagEvaluator::forApplication($projectRoot, $projectRoot),
            static fn (): ?array => null,
        );

        return new MediaEmbedPreviewController(
            $routeServices,
            $projectRoot,
            new MediaEmbedDetector(),
            $fetcher,
            $youtubeFetcher ?? new YoutubeOembedTitleFetcher(),
            $oembedFetcher ?? new OembedTitleFetcher(fn (string $url) => false),
            $bandcampFetcher ?? new BandcampPagePlayerFetcher(fn (string $url) => null),
            $posterStore,
        );
    }

    private function tempProjectRoot(): string
    {
        $path = sys_get_temp_dir() . '/media-embed-preview-controller-' . bin2hex(random_bytes(6));
        mkdir($path, 0777, true);

        return $path;
    }

    private function cleanup(string $projectRoot): void
    {
        $cachePath = MediaEmbedPreviewDatabaseConfig::path($projectRoot);
        @unlink($cachePath);
        @rmdir(dirname($cachePath));
        @rmdir(dirname($cachePath, 2));
        @rmdir($projectRoot);
    }

    /**
     * @return ?array{title: ?string, thumbnailUrl: ?string, fetchedAt: string}
     */
    private function readCache(string $projectRoot, string $embedId, string $provider = 'instagram'): ?array
    {
        $path = MediaEmbedPreviewDatabaseConfig::path($projectRoot);
        if (!is_file($path)) {
            return null;
        }

        return (new MediaEmbedPreviewCacheStore(new PDO('sqlite:' . $path)))->get($provider, $embedId);
    }

    private function seedCache(string $projectRoot, string $embedId, ?string $title, ?string $thumbnailUrl, ?string $fetchedAtModifier, string $provider = 'instagram'): void
    {
        $path = MediaEmbedPreviewDatabaseConfig::path($projectRoot);
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $pdo = new PDO('sqlite:' . $path);
        $store = new MediaEmbedPreviewCacheStore($pdo);
        $store->put($provider, $embedId, $title, $thumbnailUrl);

        if ($fetchedAtModifier !== null) {
            $fetchedAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify($fetchedAtModifier)->format('c');
            $pdo->prepare('UPDATE media_embed_previews SET fetched_at = :fetched_at WHERE provider = :provider AND embed_id = :embed_id')
                ->execute(['fetched_at' => $fetchedAt, 'provider' => $provider, 'embed_id' => $embedId]);
        }
    }

    /**
     * @return array{string,string,string}
     */
    private function createWritableEnvironment(string $noSubjectRootPostId): array
    {
        $repositoryRoot = sys_get_temp_dir() . '/media-embed-preview-write-repo-' . bin2hex(random_bytes(6));
        mkdir($repositoryRoot, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);

        file_put_contents(
            $repositoryRoot . '/records/posts/' . $noSubjectRootPostId . '.txt',
            "Post-ID: {$noSubjectRootPostId}\nCreated-At: 2026-04-11T12:00:00Z\nBoard-Tags: general\n\nhttps://www.youtube.com/watch?v=dQw4w9WgXcQ\n"
        );

        $databasePath = sys_get_temp_dir() . '/media-embed-preview-write-db-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $artifactRoot = sys_get_temp_dir() . '/media-embed-preview-write-public-' . bin2hex(random_bytes(6));
        mkdir($artifactRoot, 0777, true);

        $this->runCommand($repositoryRoot, 'git init');
        $this->runCommand($repositoryRoot, 'git config user.name "Forum Rewrite"');
        $this->runCommand($repositoryRoot, 'git config user.email "forum-rewrite@example.invalid"');
        $this->runCommand($repositoryRoot, 'git add .');
        $this->runCommand($repositoryRoot, 'git commit -m "Initialize test repository"');

        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        ob_start();
        $application->handle('GET', '/');
        ob_end_clean();

        return [$repositoryRoot, $databasePath, $artifactRoot];
    }

    private function currentSubject(string $databasePath, string $threadId): ?string
    {
        $pdo = new PDO('sqlite:' . $databasePath);
        $stmt = $pdo->prepare('SELECT subject FROM threads WHERE root_post_id = :thread_id');
        $stmt->execute(['thread_id' => $threadId]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : ($value === null ? null : (string) $value);
    }

    private function runCommand(string $workdir, string $command): string
    {
        $output = [];
        $exitCode = 0;
        exec('cd ' . escapeshellarg($workdir) . ' && ' . $command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Command failed: ' . $command . "\n" . implode("\n", $output));
        }

        return implode("\n", $output);
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

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(
                'Failed asserting that values are identical. Expected '
                . var_export($expected, true)
                . ' but got '
                . var_export($actual, true)
            );
        }
    }
}
