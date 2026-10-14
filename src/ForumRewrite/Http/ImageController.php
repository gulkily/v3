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
}
