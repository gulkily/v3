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
    public function __construct(private readonly RouteServices $services, private readonly string $repositoryRoot, private readonly FeatureFlagEvaluator $flags) {}

    public function serve(string $method, string $name): void
    {
        $headers = ['Cache-Control: private, no-store', 'X-Content-Type-Options: nosniff'];
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            $this->services->sendText("Method not allowed\n", 405, $headers);
            return;
        }
        if ($this->flags->isEnabled(FeatureFlagRegistry::IMAGE_CONTENT_ENABLED)
            && preg_match('/^thumbs\/([a-f0-9]{64})-(1x|2x|tile)\.(png|jpg)$/D', $name, $thumb)) {
            $this->serveThumbnail($method, $headers, $thumb[1] . '.' . $thumb[3], $thumb[2]);
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
            foreach ($headers as $header) { header($header); }
            header('Content-Type: image/' . ($ref['type'] === 'jpg' ? 'jpeg' : 'png'));
            header('Content-Length: ' . $ref['bytes']);
            header('Content-Disposition: inline; filename="' . $name . '"');
        }
        if ($method === 'GET') { readfile($path); }
    }

    /**
     * A list-row or gallery thumbnail: the cached small copy, generated from the verified
     * original on first request. Served only while the original is present;
     * if resizing is unavailable or fails, the original is served instead.
     */
    private function serveThumbnail(string $method, array $headers, string $original, string $size): void
    {
        try {
            $store = new ImageStore($this->repositoryRoot);
            $originalPath = $store->root . '/' . $original;
            if (is_link($originalPath) || !is_file($originalPath)) {
                throw new RuntimeException('Image unavailable.');
            }
            $thumbnails = new \ForumRewrite\Images\ThumbnailStore($store);
            $cached = $thumbnails->root . '/' . substr($original, 0, 64) . '-' . $size . '.' . substr($original, 65);
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
        if (!headers_sent()) {
            http_response_code(200);
            foreach ($headers as $header) { header($header); }
            header('Content-Type: image/' . (str_ends_with($cached, '.jpg') ? 'jpeg' : 'png'));
            header('Content-Length: ' . filesize($cached));
            header('Content-Disposition: inline; filename="' . basename($cached) . '"');
        }
        if ($method === 'GET') { readfile($cached); }
    }
}
