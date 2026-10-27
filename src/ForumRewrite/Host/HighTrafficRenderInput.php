<?php

declare(strict_types=1);
namespace ForumRewrite\Host;

use ForumRewrite\ReadModel\{ReadModelCandidateBuilder, ReadModelCandidatePromoter, ReadModelStaleMarker};
use RuntimeException;

final class HighTrafficRenderInput
{
    /** Maintenance prepares the ordinary model once; rendering never rebuilds it. */
    public static function build(array $context, string $liveDatabase, ?\Closure $progressReporter = null): string
    {
        HighTrafficPublication::validate($context);
        try {
            if ((new ReadModelStaleMarker($liveDatabase))->exists()) throw new RuntimeException('Read model is stale.');
            ReadModelCandidateBuilder::assertValid($context['repository'], $liveDatabase);
            $progressReporter?->__invoke('Reusing current live read model');
        } catch (\Throwable) {
            $candidate = (new ReadModelCandidateBuilder($context['repository'], $liveDatabase, 'high_traffic_prepare', $progressReporter))->build();
            try { (new ReadModelCandidatePromoter($context['repository'], $liveDatabase, $progressReporter))->promote($candidate); }
            finally { if (is_file($candidate)) unlink($candidate); }
        }
        return $liveDatabase;
    }

    public static function prepare(array $context, string $liveDatabase, string $directory, ?\Closure $progressReporter = null): string
    {
        HighTrafficPublication::validate($context);
        if (!is_file($liveDatabase)) throw new RuntimeException('Read model missing; run ./v3 high-traffic prepare, then retry refresh. Page rendering does not rebuild read models.');
        return $liveDatabase;
    }

    /** Legacy private model location, retained only for upgrade cleanup. */
    public static function sharedPath(string $liveDatabase): string
    {
        return $liveDatabase . '.high-traffic.sqlite3';
    }
}
