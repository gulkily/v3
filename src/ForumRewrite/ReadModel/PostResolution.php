<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use ForumRewrite\Canonical\PostReactionRecord;
use ForumRewrite\Support\Tagsets;

/**
 * A reply's state from its exclusive tagset marks (resolved, closed, open):
 * the latest mark by an approved key wins, ordered by Action-At (the tap time,
 * which offline sends keep) and then Created-At and record ID. Marks from
 * unapproved keys are stored but never counted. "open" and no mark both mean open.
 */
final class PostResolution
{
    public const OPEN = 'open';

    /**
     * @param iterable<PostReactionRecord> $records reactions on one post
     * @param array<string, mixed> $approvedIdentityIds keys are approved identity IDs
     * @return array{state:?string,set_at:?string,set_by:?string} state null means open
     */
    public static function derive(iterable $records, array $approvedIdentityIds, ?Tagsets $tagsets = null): array
    {
        $exclusive = array_flip(($tagsets ?? Tagsets::forApplication())->exclusiveTags());
        $best = null;
        $bestKey = null;
        foreach ($records as $record) {
            if ($record->authorIdentityId === null || !isset($approvedIdentityIds[$record->authorIdentityId])) {
                continue;
            }
            foreach ($record->tags as $tag) {
                if (!isset($exclusive[$tag])) {
                    continue;
                }
                $at = $record->actionAt ?? $record->createdAt;
                $key = [$at, $record->createdAt, $record->recordId];
                if ($bestKey === null || $key > $bestKey) {
                    $bestKey = $key;
                    $best = ['state' => $tag, 'set_at' => $at, 'set_by' => $record->authorIdentityId];
                }
            }
        }
        if ($best === null || $best['state'] === self::OPEN) {
            return ['state' => null, 'set_at' => $best['set_at'] ?? null, 'set_by' => $best['set_by'] ?? null];
        }

        return $best;
    }
}
