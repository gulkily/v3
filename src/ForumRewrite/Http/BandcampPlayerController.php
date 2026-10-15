<?php

declare(strict_types=1);
namespace ForumRewrite\Http;

use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\View\BandcampPlayerResolver;
use ForumRewrite\View\BandcampReleaseUrl;

final class BandcampPlayerController
{
    public function __construct(private readonly RouteServices $services, private readonly BandcampPlayerResolver $resolver) {}

    public function resolve(string $method, array $query): void
    {
        if ($method !== 'GET') {
            $this->services->sendJson(['status' => 'unavailable'], 405, ['Allow: GET', 'Cache-Control: no-store']);
            return;
        }
        $flags = $this->services->featureFlags();
        if (!$flags->isEnabled(FeatureFlagRegistry::MEDIA_EMBEDS_ENABLED) || !$flags->isEnabled(FeatureFlagRegistry::MEDIA_EMBEDS_INLINE_PLAYER_ENABLED)) {
            $this->services->sendJson(['status' => 'unavailable'], 403, ['Cache-Control: no-store']);
            return;
        }
        $url = $query['url'] ?? null;
        $release = is_string($url) && strlen($url) <= 2048 ? BandcampReleaseUrl::parse($url) : null;
        if ($release === null) {
            $this->services->sendJson(['status' => 'unavailable'], 400, ['Cache-Control: no-store']);
            return;
        }
        $src = $this->resolver->resolve($release['url']);
        $this->services->sendJson($src === null ? ['status' => 'unavailable'] : ['status' => 'ready', 'embedSrc' => $src], 200, ['Cache-Control: no-store']);
    }
}
