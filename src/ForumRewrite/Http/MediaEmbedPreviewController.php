<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use DateTimeImmutable;
use Exception;
use ForumRewrite\View\InstagramPagePreviewFetcher;
use ForumRewrite\View\MediaEmbedDetector;
use ForumRewrite\View\MediaEmbedPreviewCacheStore;
use ForumRewrite\View\MediaEmbedPreviewDatabaseConfig;
use PDO;
use RuntimeException;

/**
 * Backs the client-triggered beacon that warms the Instagram preview cache
 * (see MediaEmbedRenderer). Deliberately unauthenticated - any viewer's
 * browser is expected to hit this - but bounded: the fetch target is always
 * derived from a URL MediaEmbedDetector itself validates as a real Instagram
 * post/reel link, never an arbitrary input, and a cache hit (success or a
 * recent failure) short-circuits before any outbound fetch.
 */
final class MediaEmbedPreviewController
{
    private const RETRY_BACKOFF_SECONDS = 3600;

    private ?MediaEmbedPreviewCacheStore $cacheStore = null;

    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly string $projectRoot,
        private readonly MediaEmbedDetector $detector = new MediaEmbedDetector(),
        private readonly InstagramPagePreviewFetcher $fetcher = new InstagramPagePreviewFetcher(),
    ) {
    }

    /**
     * @param array<string, mixed> $query
     */
    public function warmPreview(string $method, array $query): void
    {
        if ($method !== 'GET') {
            $this->routeServices->sendText('', 405);
            return;
        }

        $provider = (string) ($query['provider'] ?? '');
        $url = (string) ($query['url'] ?? '');

        if ($provider === 'instagram') {
            $classified = $this->detector->classify($url);

            if ($classified !== null && $classified['provider'] === 'instagram') {
                $this->warmInstagramPreview($classified['embedId'], $url);
            }
        }

        $this->routeServices->sendText('', 204);
    }

    private function warmInstagramPreview(string $embedId, string $url): void
    {
        $store = $this->cacheStore();
        $existing = $store->get('instagram', $embedId);

        if ($existing !== null && !$this->shouldRetry($existing)) {
            return;
        }

        $result = $this->fetcher->fetch($url);
        $store->put('instagram', $embedId, $result['title'] ?? null, $result['thumbnailUrl'] ?? null);
    }

    /**
     * @param array{title: ?string, thumbnailUrl: ?string, fetchedAt: string} $cached
     */
    private function shouldRetry(array $cached): bool
    {
        if ($cached['title'] !== null) {
            return false;
        }

        try {
            $fetchedAt = new DateTimeImmutable($cached['fetchedAt']);
        } catch (Exception) {
            return true;
        }

        return (new DateTimeImmutable())->getTimestamp() - $fetchedAt->getTimestamp() > self::RETRY_BACKOFF_SECONDS;
    }

    private function cacheStore(): MediaEmbedPreviewCacheStore
    {
        if ($this->cacheStore !== null) {
            return $this->cacheStore;
        }

        $path = MediaEmbedPreviewDatabaseConfig::path($this->projectRoot);
        $directory = dirname($path);
        if ($directory !== '' && !is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Media embed preview database directory is not writable: ' . $directory);
        }

        return $this->cacheStore = new MediaEmbedPreviewCacheStore(new PDO('sqlite:' . $path));
    }
}
