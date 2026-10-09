<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use DateTimeImmutable;
use Exception;
use ForumRewrite\View\InstagramPagePreviewFetcher;
use ForumRewrite\View\MediaEmbedDetector;
use ForumRewrite\View\MediaEmbedPreviewCacheStore;
use ForumRewrite\View\YoutubeOembedTitleFetcher;

/**
 * Backs the client-triggered beacon that warms the Instagram/YouTube preview
 * cache (see MediaEmbedRenderer) and, when a thread_id is supplied and a
 * fetch succeeds, back-fills that thread's still-empty subject with the
 * fetched title (see ThreadTitle::bareMediaEmbedMatch(),
 * LocalWriteService::setThreadSubjectIfEmpty()). Deliberately
 * unauthenticated - any viewer's browser is expected to hit this - but
 * bounded: the fetch target is always derived from a URL MediaEmbedDetector
 * itself validates as a real post/reel/video link, never an arbitrary
 * input; thread_id only ever reaches the already-safe-by-construction
 * (empty-subject-only) write path, never arbitrary data. A cache hit
 * (success or a recent failure) short-circuits before any outbound fetch.
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
        private readonly YoutubeOembedTitleFetcher $youtubeFetcher = new YoutubeOembedTitleFetcher(),
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
        $threadId = trim((string) ($query['thread_id'] ?? ''));
        $threadId = $threadId !== '' ? $threadId : null;

        if ($provider === 'instagram') {
            $classified = $this->detector->classify($url);

            if ($classified !== null && $classified['provider'] === 'instagram') {
                $this->warmInstagramPreview($classified['embedId'], $url, $threadId);
            }
        } elseif ($provider === 'youtube') {
            $classified = $this->detector->classify($url);

            if ($classified !== null && $classified['provider'] === 'youtube') {
                $this->warmYoutubePreview($classified['embedId'], $url, $threadId);
            }
        }

        $this->routeServices->sendText('', 204);
    }

    private function warmInstagramPreview(string $embedId, string $url, ?string $threadId): void
    {
        $store = $this->cacheStore();
        $existing = $store->get('instagram', $embedId);

        if ($existing !== null && !$this->shouldRetry($existing)) {
            $this->backfillThreadSubject($threadId, $existing['title']);
            return;
        }

        $result = $this->fetcher->fetch($url);
        $store->put('instagram', $embedId, $result['title'] ?? null, $result['thumbnailUrl'] ?? null);
        $this->backfillThreadSubject($threadId, $result['title'] ?? null);
    }

    private function warmYoutubePreview(string $embedId, string $url, ?string $threadId): void
    {
        $store = $this->cacheStore();
        $existing = $store->get('youtube', $embedId);

        if ($existing !== null && !$this->shouldRetry($existing)) {
            $this->backfillThreadSubject($threadId, $existing['title']);
            return;
        }

        $result = $this->youtubeFetcher->fetch($url);
        $store->put('youtube', $embedId, $result['title'] ?? null, $result['thumbnailUrl'] ?? null);
        $this->backfillThreadSubject($threadId, $result['title'] ?? null);
    }

    private function backfillThreadSubject(?string $threadId, ?string $title): void
    {
        if ($threadId === null || $title === null) {
            return;
        }

        $this->routeServices->writer()->setThreadSubjectIfEmpty($threadId, $title);
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
        return $this->cacheStore ??= MediaEmbedPreviewCacheStore::openAt($this->projectRoot);
    }
}
