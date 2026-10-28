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

        $switchOn = null;
        foreach (self::paths($repositoryRoot, 'records/feature-flag-changes') as $path) {
            try {
                $record = $repository->loadFeatureFlagChange($path);
            } catch (CanonicalRecordParseException) {
                continue;
            }
            if ($record->flagKey !== FeatureFlagRegistry::VERIFIED_ATTRIBUTION || !$record->value
                || !self::signedByOperator($repositoryRoot, $path, $record->operatorIdentityId)) {
                continue;
            }
            if ($switchOn === null || [$record->createdAt, $path] < [$switchOn['cutoff_at'], $switchOn['source_path']]) {
                $switchOn = ['cutoff_at' => $record->createdAt, 'source' => 'switch-on', 'source_path' => $path];
            }
        }

        return $switchOn;
    }

    private static function signedByOperator(string $repositoryRoot, string $path, string $operatorIdentityId): bool
    {
        $contents = @file_get_contents($repositoryRoot . '/' . $path);

        return is_string($contents)
            && ApprovalPolicy::isOperator($repositoryRoot, $operatorIdentityId)
            && ReactionSignature::status($repositoryRoot, $path, $contents, $operatorIdentityId) === 'verified';
    }

    /** @return list<string> */
    private static function paths(string $repositoryRoot, string $directory): array
    {
        $files = glob($repositoryRoot . '/' . $directory . '/*.txt') ?: [];
        sort($files);

        return array_map(static fn (string $file): string => $directory . '/' . basename($file), $files);
    }
}
