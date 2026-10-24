<?php

declare(strict_types=1);
namespace ForumRewrite\Host;

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelCandidateBuilder;
use ForumRewrite\Retraction\PublicationGuard;

final class HighTrafficRenderInput
{
    public static function prepare(array $context, string $liveDatabase, string $directory, ?\Closure $progressReporter = null): string
    {
        HighTrafficPublication::validate($context);
        $excluded = $context['state']['absent'];
        if ($excluded === []) return $liveDatabase;
        $path = $directory . '/read.sqlite3';
        if (file_exists($path)) throw new \RuntimeException('High-traffic candidate already exists; retry with a fresh workspace.');
        (new ReadModelBuilder(
            $context['repository'], $path, new CanonicalRecordRepository($context['repository']),
            'high_traffic_absent_targets', $progressReporter, publicationExclusions: $excluded,
        ))->rebuild();
        $progressReporter?->__invoke('validating isolated model metadata and integrity');
        ReadModelCandidateBuilder::assertValid($context['repository'], $path);
        $progressReporter?->__invoke('validating candidate retractions');
        PublicationGuard::beginSnapshot($path);
        $progressReporter?->__invoke('rechecking canonical inputs');
        HighTrafficPublication::validate($context);
        return $path;
    }
}
