<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Http\MediaEmbedPreviewController;
use ForumRewrite\Http\RouteServices;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\View\InstagramPagePreviewFetcher;
use ForumRewrite\View\MediaEmbedDetector;
use ForumRewrite\View\MediaEmbedPreviewCacheStore;
use ForumRewrite\View\MediaEmbedPreviewDatabaseConfig;
use ForumRewrite\View\TemplateRenderer;
use ForumRewrite\View\YoutubeOembedTitleFetcher;

final class MediaEmbedPreviewControllerTest
{
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
            $this->seedCache($projectRoot, 'Cabc123XYZ', null, null, '-5 minutes');

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

            $this->seedCache($projectRoot, 'Cabc123XYZ', null, null, '-2 hours');

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
            $controller->warmPreview('GET', ['provider' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=abc1234567']);
            ob_end_clean();

            assertSame(1, $callCount);
            $row = $this->readCache($projectRoot, 'abc1234567', 'youtube');
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
            $this->seedCache($projectRoot, 'abc1234567', 'Already cached', 'https://example.com/thumb.jpg', null, 'youtube');

            $callCount = 0;
            $youtubeFetcher = new YoutubeOembedTitleFetcher(function () use (&$callCount): string {
                $callCount++;
                return '';
            });
            $controller = $this->controller($projectRoot, new InstagramPagePreviewFetcher(), $youtubeFetcher);

            ob_start();
            $controller->warmPreview('GET', ['provider' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=abc1234567']);
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
            $this->seedCache($projectRoot, 'abc1234567', null, null, '-5 minutes', 'youtube');

            $callCount = 0;
            $youtubeFetcher = new YoutubeOembedTitleFetcher(function () use (&$callCount): string {
                $callCount++;
                return '';
            });
            $controller = $this->controller($projectRoot, new InstagramPagePreviewFetcher(), $youtubeFetcher);

            ob_start();
            $controller->warmPreview('GET', ['provider' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=abc1234567']);
            ob_end_clean();

            assertSame(0, $callCount);

            $this->seedCache($projectRoot, 'abc1234567', null, null, '-2 hours', 'youtube');

            ob_start();
            $controller->warmPreview('GET', ['provider' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=abc1234567']);
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
        ?string $repositoryRoot = null
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
            $youtubeFetcher ?? new YoutubeOembedTitleFetcher()
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
            $fetchedAt = (new DateTimeImmutable($fetchedAtModifier))->format('c');
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
