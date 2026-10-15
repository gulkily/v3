<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use ForumRewrite\Canonical\ProfileNameRecordParser;
use ForumRewrite\Security\ReactionSignature;

final class ProfileNames
{
    /** @return array<string, array<string, string>> Latest verified update per identity. */
    public static function latest(string $root): array
    {
        $latest = [];
        foreach (glob($root . '/records/profile-names/*.txt') ?: [] as $file) {
            try {
                $contents = (string) file_get_contents($file);
                $record = (new ProfileNameRecordParser())->parse($contents);
                $path = 'records/profile-names/' . $record['Record-ID'] . '.txt';
                if (basename($file) !== $record['Record-ID'] . '.txt'
                    || ReactionSignature::status($root, $path, $contents, $record['Author-Identity-ID']) !== 'verified') continue;
                $identity = $record['Author-Identity-ID'];
                $order = $record['Created-At'] . $record['Record-ID'];
                $previous = $latest[$identity] ?? null;
                if ($previous === null || $order > $previous['Created-At'] . $previous['Record-ID']) $latest[$identity] = $record;
            } catch (\RuntimeException) {
                // Unverified or malformed imported updates cannot change canonical identity labels.
            }
        }
        return $latest;
    }
}
