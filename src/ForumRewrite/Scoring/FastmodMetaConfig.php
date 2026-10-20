<?php

declare(strict_types=1);
namespace ForumRewrite\Scoring;

final class FastmodMetaConfig
{
    public static function threshold(array $config): float
    {
        $value = $config['FASTMOD_META_THRESHOLD'] ?? 0.90;
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < 0 || (float) $value > 1) {
            throw new \InvalidArgumentException('FASTMOD_META_THRESHOLD must be between 0 and 1.');
        }
        return (float) $value;
    }

    public static function identity(FastScoringConfig $config): string
    {
        return hash('sha256', json_encode([$config->provider->provider, $config->provider->baseUrl, $config->provider->model], JSON_THROW_ON_ERROR));
    }
}
