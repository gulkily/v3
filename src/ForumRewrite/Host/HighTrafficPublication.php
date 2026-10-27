<?php

declare(strict_types=1);
namespace ForumRewrite\Host;

/** Rendering uses ordinary read-model freshness; retraction delays do not gate publication. */
final class HighTrafficPublication
{
    public static function identity(array $context): string
    {
        return hash('sha256', json_encode($context, JSON_THROW_ON_ERROR));
    }
    public static function capture(string $repository): array
    {
        return ['repository' => $repository, 'state' => ['ids' => [], 'absent' => [], 'evidence' => []], 'head' => null, 'fingerprint' => null];
    }
    public static function captureForRender(string $repository): array
    {
        return self::capture($repository) + ['scope' => 'prepared-render'];
    }
    public static function validate(array $context): void
    {
        if (!is_dir($context['repository'] . '/records')) throw new \RuntimeException('Canonical records directory is unavailable.');
    }
}
