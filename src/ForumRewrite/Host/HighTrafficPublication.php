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

    public static function capture(string $repository): array
    {
        $state = ExchangeRetractionState::snapshot($repository);
        if ($state === null) {
            throw new RuntimeException('Cannot validate high-traffic retractions; inspect canonical evidence and retry.');
        }

        return [
            'repository' => $repository,
            'state' => $state,
            'head' => ReadModelMetadata::repositoryHead($repository),
            // Include uncommitted edits, source reappearance and derived-input changes.
            'fingerprint' => ReadModelMetadata::canonicalFingerprint($repository),
        ];
    }

    public static function validate(array $context): void
    {
        if (self::capture($context['repository']) !== $context) {
            throw new RuntimeException('High-traffic source changed during rendering; retry generation.');
        }
    }
}
