<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Http\MediaEmbedPreviewController;
use ForumRewrite\Http\RouteServices;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\View\InstagramPagePreviewFetcher;
use ForumRewrite\View\MediaEmbedDetector;
use ForumRewrite\View\MediaEmbedPreviewCacheStore;
use ForumRewrite\View\MediaEmbedPreviewDatabaseConfig;
use ForumRewrite\View\TemplateRenderer;

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

    private function controller(string $projectRoot, InstagramPagePreviewFetcher $fetcher): MediaEmbedPreviewController
    {
        $routeServices = new RouteServices(
            $projectRoot . '/read_model.sqlite3',
            new TemplateRenderer(dirname(__DIR__) . '/templates'),
            'php-fallback',
            false,
            static fn (): ?array => null,
            $projectRoot,
            $projectRoot,
            null,
            null,
            FeatureFlagEvaluator::forApplication($projectRoot, $projectRoot),
            static fn (): ?array => null,
        );

        return new MediaEmbedPreviewController($routeServices, $projectRoot, new MediaEmbedDetector(), $fetcher);
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
    private function readCache(string $projectRoot, string $embedId): ?array
    {
        $path = MediaEmbedPreviewDatabaseConfig::path($projectRoot);
        if (!is_file($path)) {
            return null;
        }

        return (new MediaEmbedPreviewCacheStore(new PDO('sqlite:' . $path)))->get('instagram', $embedId);
    }

    private function seedCache(string $projectRoot, string $embedId, ?string $title, ?string $thumbnailUrl, ?string $fetchedAtModifier): void
    {
        $path = MediaEmbedPreviewDatabaseConfig::path($projectRoot);
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $pdo = new PDO('sqlite:' . $path);
        $store = new MediaEmbedPreviewCacheStore($pdo);
        $store->put('instagram', $embedId, $title, $thumbnailUrl);

        if ($fetchedAtModifier !== null) {
            $fetchedAt = (new DateTimeImmutable($fetchedAtModifier))->format('c');
            $pdo->prepare('UPDATE media_embed_previews SET fetched_at = :fetched_at WHERE provider = :provider AND embed_id = :embed_id')
                ->execute(['fetched_at' => $fetchedAt, 'provider' => 'instagram', 'embed_id' => $embedId]);
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
