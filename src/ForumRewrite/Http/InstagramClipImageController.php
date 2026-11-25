<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\View\InstagramPagePreviewFetcher;
use ForumRewrite\View\MediaEmbedDetector;

/**
 * Hands the composer the image of an Instagram post, so the Instagram clip
 * bookmarklet can open a new post with that image attached. The browser
 * cannot fetch it itself: Instagram's image host does not allow other sites
 * to read its images.
 *
 * Bounded like the preview beacon (MediaEmbedPreviewController): the post
 * must be a link MediaEmbedDetector recognizes as an Instagram post or reel,
 * and the image address is fetched only from Instagram's image hosts, with
 * no redirects followed. Without a usable image address from the page the
 * bookmarklet ran on, the post page's og:image is used. The answer is the
 * image bytes when they are a JPEG, PNG, WebP or GIF under the size cap;
 * the composer then prepares and uploads it like any attached image.
 */
final class InstagramClipImageController
{
    public const MAX_BYTES = 15 * 1024 * 1024;
    private const TIMEOUT_SECONDS = 10;

    /** @var (callable(string): (string|false))|null */
    private $transport;

    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly MediaEmbedDetector $detector = new MediaEmbedDetector(),
        private readonly InstagramPagePreviewFetcher $pageFetcher = new InstagramPagePreviewFetcher(),
        ?callable $transport = null,
    ) {
        $this->transport = $transport;
    }

    /** @param array<string, mixed> $query */
    public function image(string $method, array $query): void
    {
        $headers = ['Cache-Control: no-store'];
        if ($method !== 'GET') {
            $this->routeServices->sendText("Use GET.\n", 405, $headers);
            return;
        }

        $post = trim((string) ($query['post'] ?? ''));
        $classified = $this->detector->classify($post);
        if ($classified === null || $classified['provider'] !== 'instagram') {
            $this->routeServices->sendText("Not an Instagram post link.\n", 400, $headers);
            return;
        }

        $candidates = [];
        $given = trim((string) ($query['image'] ?? ''));
        if (self::isInstagramImageUrl($given)) {
            $candidates[] = $given;
        }
        $candidates[] = null; // the post page's og:image, looked up only if needed

        foreach ($candidates as $candidate) {
            if ($candidate === null) {
                $preview = $this->pageFetcher->fetch('https://www.instagram.com/p/' . $classified['embedId'] . '/');
                $candidate = $preview['thumbnailUrl'] ?? '';
                if (!self::isInstagramImageUrl($candidate)) {
                    break;
                }
            }
            $bytes = $this->download($candidate);
            $type = $bytes === false ? null : self::imageType($bytes);
            if ($type !== null) {
                $this->routeServices->sendBinary($bytes, 'image/' . $type, $headers);
                return;
            }
        }

        $this->routeServices->sendText("Couldn't get the image from Instagram.\n", 502, $headers);
    }

    public static function isInstagramImageUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['port'])) {
            return false;
        }
        $host = strtolower((string) ($parts['host'] ?? ''));

        return preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)*\.(cdninstagram\.com|fbcdn\.net)$/D', $host) === 1;
    }

    /** jpeg, png, webp or gif, read from the bytes; null for anything else. */
    public static function imageType(string $bytes): ?string
    {
        return match (true) {
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'jpeg',
            str_starts_with($bytes, "\x89PNG\r\n\x1A\n") => 'png',
            strlen($bytes) >= 12 && substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP' => 'webp',
            str_starts_with($bytes, 'GIF87a') || str_starts_with($bytes, 'GIF89a') => 'gif',
            default => null,
        };
    }

    private function download(string $url): string|false
    {
        if ($this->transport !== null) {
            $bytes = ($this->transport)($url);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => self::TIMEOUT_SECONDS,
                    'follow_location' => 0,
                    'header' => "User-Agent: Mozilla/5.0 (compatible)\r\n",
                    'ignore_errors' => false,
                ],
            ]);
            $bytes = @file_get_contents($url, false, $context, 0, self::MAX_BYTES + 1);
        }

        return is_string($bytes) && $bytes !== '' && strlen($bytes) <= self::MAX_BYTES ? $bytes : false;
    }
}
