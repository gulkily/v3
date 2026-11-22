<?php

declare(strict_types=1);
namespace ForumRewrite\Http;

use ForumRewrite\Images\ImageStore;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use RuntimeException;

/** Dispatched only after the application's instance access gate. */
final class ImageController
{
    /**
     * A served image may be kept by that browser for a year: its name is the
     * hash of its bytes, so an address can never show anything else. Private,
     * because the access gate decided this visitor may see it. A removed image
     * stops being served at once; a browser that already holds a copy may show
     * it until it lets go.
     */
    public const KEEP = 'Cache-Control: private, max-age=31536000, immutable';

    public function __construct(private readonly RouteServices $services, private readonly string $repositoryRoot, private readonly FeatureFlagEvaluator $flags) {}

    /** With direct serving on, the next request for this address is answered by the web server. */
    private function publish(string $name, string $path): void
    {
        if (\ForumRewrite\Images\PublishedImages::allowed($this->flags)) {
            \ForumRewrite\Images\PublishedImages::forProject()->publish($name, $path);
        }
    }

    public function serve(string $method, string $name): void
    {
        // Error answers are never kept; a served image replaces the first header with KEEP.
        $headers = ['Cache-Control: private, no-store', 'X-Content-Type-Options: nosniff'];
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            $this->services->sendText("Method not allowed\n", 405, $headers);
            return;
        }
        if ($this->flags->isEnabled(FeatureFlagRegistry::IMAGE_CONTENT_ENABLED)
            && preg_match('/^thumbs\/(([a-f0-9]{64})-(1x|2x|sq|sq2x|tile|wide|large)\.(png|jpg)(?:\.webp)?)$/D', $name, $thumb)) {
            $size = \ForumRewrite\Images\ThumbnailStore::LEGACY[$thumb[3]] ?? $thumb[3];
            $this->serveThumbnail($method, $headers, $thumb[2] . '.' . $thumb[4], $size, $thumb[1]);
            return;
        }
        if (!$this->flags->isEnabled(FeatureFlagRegistry::IMAGE_CONTENT_ENABLED) || !preg_match('/^[a-f0-9]{64}\.(png|jpg)$/D', $name)) {
            $this->services->sendText("Image unavailable\n", 404, $headers);
            return;
        }
        try {
            $store = new ImageStore($this->repositoryRoot);
            $ref = ImageStore::inspect($store->root . '/' . $name, 'Image');
            if (\ForumRewrite\Images\ImageReference::filename($ref) !== $name) { throw new RuntimeException('Image checksum mismatch.'); }
            $path = $store->resolve($ref);
        } catch (RuntimeException $e) {
            $this->services->sendText("Image unavailable; restore a verified image backup.\n", 404, $headers);
            return;
        }
        if (!headers_sent()) {
            http_response_code(200);
            header(self::KEEP);
            header($headers[1]);
            header('Content-Type: image/' . ($ref['type'] === 'jpg' ? 'jpeg' : 'png'));
            header('Content-Length: ' . $ref['bytes']);
            header('Content-Disposition: inline; filename="' . $name . '"');
        }
        if ($method === 'GET') { readfile($path); }
        $this->publish($name, $path);
    }

    /**
     * A list-row or gallery thumbnail: the cached small copy, generated from the verified
     * original on first request. Served only while the original is present;
     * if resizing is unavailable or fails, the original is served instead.
     */
    private function serveThumbnail(string $method, array $headers, string $original, string $size, string $requested): void
    {
        try {
            $store = new ImageStore($this->repositoryRoot);
            $originalPath = $store->root . '/' . $original;
            if (is_link($originalPath) || !is_file($originalPath)) {
                throw new RuntimeException('Image unavailable.');
            }
            $thumbnails = new \ForumRewrite\Images\ThumbnailStore($store);
            // Older pages may still ask for a pre-WebP name; those regenerate in the current format.
            $cached = $thumbnails->root . '/' . $requested;
            if (!is_file($cached) || is_link($cached)) {
                $ref = ImageStore::inspect($originalPath, 'Image');
                if (\ForumRewrite\Images\ImageReference::filename($ref) !== $original) { throw new RuntimeException('Image checksum mismatch.'); }
                try {
                    $cached = $thumbnails->path($ref, $size);
                } catch (RuntimeException) {
                    $this->serve($method, $original);
                    return;
                }
            }
        } catch (RuntimeException) {
            $this->services->sendText("Image unavailable; restore a verified image backup.\n", 404, $headers);
            return;
        }
        clearstatcache(true, $cached);
        // A display copy re-encodes an original that already fits; when that came out no smaller, answer with the original.
        if (in_array($size, \ForumRewrite\Images\ThumbnailStore::DISPLAY, true) && filesize($cached) >= filesize($originalPath)) {
            $this->serve($method, $original);
            return;
        }
        if (!headers_sent()) {
            http_response_code(200);
            header(self::KEEP);
            header($headers[1]);
            header('Content-Type: image/' . match (pathinfo($cached, PATHINFO_EXTENSION)) { 'webp' => 'webp', 'jpg' => 'jpeg', default => 'png' });
            header('Content-Length: ' . filesize($cached));
            header('Content-Disposition: inline; filename="' . basename($cached) . '"');
        }
        if ($method === 'GET') { readfile($cached); }
        // Published only under the address it was asked for; a legacy name that was answered in a newer format is not.
        if (basename($cached) === $requested) { $this->publish('thumbs/' . $requested, $cached); }
    }
}
