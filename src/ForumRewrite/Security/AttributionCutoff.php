<?php

declare(strict_types=1);

namespace ForumRewrite\Security;

use ForumRewrite\Canonical\CanonicalRecordParseException;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;

/**
 * Unsigned items created before the cutoff keep counting under verified attribution.
 * The cutoff is the latest operator-signed move, else the first operator-signed switch-on
 * of the flag, else none (everything counts).
 */
final class AttributionCutoff
{
    /**
     * @return array{cutoff_at:string,source:'moved'|'switch-on',source_path:string}|null
     */
    public static function resolve(string $repositoryRoot): ?array
    {
        $repository = new CanonicalRecordRepository($repositoryRoot);
        $moved = null;
        foreach (self::paths($repositoryRoot, 'records/attribution-cutoffs') as $path) {
            try {
                $record = $repository->loadAttributionCutoff($path);
            } catch (CanonicalRecordParseException) {
                continue;
            }
            if (!self::signedByOperator($repositoryRoot, $path, $record->operatorIdentityId)) {
                continue;
            }
            $candidate = ['cutoff_at' => $record->cutoffAt, 'source' => 'moved', 'source_path' => $path, 'created_at' => $record->createdAt];
            if ($moved === null || [$record->createdAt, $path] > [$moved['created_at'], $moved['source_path']]) {
                $moved = $candidate;
            }
        }
        if ($moved !== null) {
            unset($moved['created_at']);
            return $moved;
        }

        $switchOn = FeatureFlagSwitchOn::earliest($repositoryRoot, FeatureFlagRegistry::VERIFIED_ATTRIBUTION);

        return $switchOn === null ? null : ['cutoff_at' => $switchOn['at'], 'source' => 'switch-on', 'source_path' => $switchOn['source_path']];
    }

    private static function signedByOperator(string $repositoryRoot, string $path, string $operatorIdentityId): bool
    {
        return FeatureFlagSwitchOn::signedByOperator($repositoryRoot, $path, $operatorIdentityId);
    }

    /** @return list<string> */
    private static function paths(string $repositoryRoot, string $directory): array
    {
        return FeatureFlagSwitchOn::paths($repositoryRoot, $directory);
    }
}
