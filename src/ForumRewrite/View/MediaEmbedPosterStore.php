<?php

declare(strict_types=1);

namespace ForumRewrite\View;

/**
 * Disposable copies of YouTube poster images, kept beside the media embed
 * preview cache and served from our own address, so a page view never
 * contacts YouTube. A poster is fetched once, by video ID, from YouTube's
 * image host only, and only from the background poster request
 * (MediaEmbedPreviewController), never while a page renders. A failed fetch
 * leaves a marker so it is not retried for an hour. Deleting the folder is
 * always safe: posters come back on the next view.
 */
final class MediaEmbedPosterStore
{
    private const VIDEO_ID = '/^[A-Za-z0-9_-]{11}$/';
    private const SOURCE = 'https://i.ytimg.com/vi/%s/hqdefault.jpg';
    private const MAX_BYTES = 512 * 1024;
    private const TIMEOUT_SECONDS = 4;
    private const RETRY_BACKOFF_SECONDS = 3600;

    /** @var (callable(string): (string|false))|null */
    private $transport;

    public function __construct(private readonly string $directory, ?callable $transport = null)
    {
        $this->transport = $transport;
    }

    public static function openAt(string $projectRoot, ?callable $transport = null): self
    {
        return new self(dirname(MediaEmbedPreviewDatabaseConfig::path($projectRoot)) . '/posters', $transport);
    }

    public static function isVideoId(string $videoId): bool
    {
        return preg_match(self::VIDEO_ID, $videoId) === 1;
    }

    /** The stored poster's file, or null when there is none yet. */
    public function path(string $videoId): ?string
    {
        if (!self::isVideoId($videoId)) {
            return null;
        }
        $path = $this->directory . '/' . $videoId . '.jpg';

        return is_file($path) ? $path : null;
    }

    /** Fetch and keep the poster unless it is stored or failed within the hour; true when stored. */
    public function warm(string $videoId): bool
    {
        if (!self::isVideoId($videoId)) {
            return false;
        }
        if ($this->path($videoId) !== null) {
            return true;
        }
        $failed = $this->directory . '/' . $videoId . '.failed';
        clearstatcache(true, $failed);
        if (is_file($failed) && time() - (int) filemtime($failed) < self::RETRY_BACKOFF_SECONDS) {
            return false;
        }
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0777, true) && !is_dir($this->directory)) {
            return false;
        }

        $bytes = $this->fetch(sprintf(self::SOURCE, $videoId));
        // Only a JPEG of a sane size is kept; anything else counts as a failure.
        if (!is_string($bytes) || strlen($bytes) > self::MAX_BYTES || !str_starts_with($bytes, "\xFF\xD8\xFF")) {
            @touch($failed);
            return false;
        }

        $temporary = $this->directory . '/' . $videoId . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($temporary, $bytes) !== strlen($bytes) || !@rename($temporary, $this->directory . '/' . $videoId . '.jpg')) {
            @unlink($temporary);
            return false;
        }
        @unlink($failed);

        return true;
    }

    private function fetch(string $url): string|false
    {
        if ($this->transport !== null) {
            return ($this->transport)($url);
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => self::TIMEOUT_SECONDS,
                'header' => "User-Agent: Mozilla/5.0 (compatible)\r\n",
                'ignore_errors' => false,
            ],
        ]);

        return @file_get_contents($url, false, $context, 0, self::MAX_BYTES + 1);
    }
}
