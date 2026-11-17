<?php

declare(strict_types=1);

namespace ForumRewrite\Security;

use ForumRewrite\Canonical\CanonicalRecordParseException;
use ForumRewrite\Canonical\CanonicalRecordRepository;

/** The first operator-signed change that switched a site flag on: a cutoff no one else can move. */
final class FeatureFlagSwitchOn
{
    /** @return array{at:string,source_path:string}|null */
    public static function earliest(string $repositoryRoot, string $flagKey): ?array
    {
        $repository = new CanonicalRecordRepository($repositoryRoot);
        $first = null;
        foreach (self::paths($repositoryRoot, 'records/feature-flag-changes') as $path) {
            try {
                $record = $repository->loadFeatureFlagChange($path);
            } catch (CanonicalRecordParseException) {
                continue;
            }
            if ($record->flagKey !== $flagKey || !$record->value
                || !self::signedByOperator($repositoryRoot, $path, $record->operatorIdentityId)) {
                continue;
            }
            if ($first === null || [$record->createdAt, $path] < [$first['at'], $first['source_path']]) {
                $first = ['at' => $record->createdAt, 'source_path' => $path];
            }
        }

        return $first;
    }

    public static function signedByOperator(string $repositoryRoot, string $path, string $operatorIdentityId): bool
    {
        $contents = @file_get_contents($repositoryRoot . '/' . $path);

        return is_string($contents)
            && ApprovalPolicy::isOperator($repositoryRoot, $operatorIdentityId)
            && ReactionSignature::status($repositoryRoot, $path, $contents, $operatorIdentityId) === 'verified';
    }

    /** @return list<string> */
    public static function paths(string $repositoryRoot, string $directory): array
    {
        $files = glob($repositoryRoot . '/' . $directory . '/*.txt') ?: [];
        sort($files);

        return array_map(static fn (string $file): string => $directory . '/' . basename($file), $files);
    }
}
