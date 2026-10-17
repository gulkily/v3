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
        foreach (self::history($root) as $identity => $records) {
            $latest[$identity] = $records[array_key_last($records)];
        }
        return $latest;
    }

    /** @return array<string, list<array<string, string>>> Verified updates per identity, oldest first by (Created-At, Record-ID). */
    public static function history(string $root): array
    {
        $history = [];
        foreach (glob($root . '/records/profile-names/*.txt') ?: [] as $file) {
            try {
                $contents = (string) file_get_contents($file);
                $record = (new ProfileNameRecordParser())->parse($contents);
                $path = 'records/profile-names/' . $record['Record-ID'] . '.txt';
                if (basename($file) !== $record['Record-ID'] . '.txt'
                    || ReactionSignature::status($root, $path, $contents, $record['Author-Identity-ID']) !== 'verified') continue;
                $history[$record['Author-Identity-ID']][] = $record;
            } catch (\RuntimeException) {
                // Unverified or malformed imported updates cannot change canonical identity labels.
            }
        }
        foreach ($history as &$records) {
            usort($records, static fn (array $a, array $b): int => ($a['Created-At'] . $a['Record-ID']) <=> ($b['Created-At'] . $b['Record-ID']));
        }
        return $history;
    }
}
