<?php

declare(strict_types=1);

namespace ForumRewrite\Host;

use ForumRewrite\Llm\ExchangeRetractionState;
use ForumRewrite\ReadModel\ReadModelMetadata;
use RuntimeException;

/** HTML-only admission. Absence is a build exclusion, never deletion authority. */
final class HighTrafficPublication
{
    public static function identity(array $context): string
    {
        return hash('sha256', json_encode($context, JSON_THROW_ON_ERROR));
    }

    /** Full input identity is needed when deriving a new model, not when rendering one. */
    public static function capture(string $repository): array
    {
        return self::captureContext($repository, true);
    }

    public static function captureForRender(string $repository): array
    {
        return self::captureContext($repository, false) + ['scope' => 'prepared-render'];
    }

    private static function captureContext(string $repository, bool $fingerprintContents): array
    {
        $state = ExchangeRetractionState::snapshot($repository);
        if ($state === null) {
            throw new RuntimeException('Cannot validate high-traffic retractions; inspect canonical evidence and retry.');
        }

        return [
            'repository' => $repository,
            'state' => $state,
            'head' => $state['absent'] === [] ? null : ReadModelMetadata::repositoryHead($repository),
            // Preparation pins every input. Rendering uses an already validated SQLite snapshot.
            'fingerprint' => !$fingerprintContents || $state['absent'] === [] ? null : ReadModelMetadata::canonicalFingerprint($repository),
        ];
    }

    public static function validate(array $context): void
    {
        $current = ($context['scope'] ?? null) === 'prepared-render'
            ? self::captureForRender($context['repository']) : self::capture($context['repository']);
        if ($current !== $context) {
            throw new RuntimeException('High-traffic source changed during rendering; retry generation.');
        }
    }
}
