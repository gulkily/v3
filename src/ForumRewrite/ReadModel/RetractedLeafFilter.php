<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

/**
 * Drops retracted replies that no shown reply answers, so a deleted dead end
 * leaves no "retracted" stub cluttering the thread. A retracted reply that
 * still has a shown reply under it keeps its stub, so that conversation keeps
 * its context. Repeats until stable, so a branch of retracted replies goes
 * whole. Display only: the retraction record and the post's own page remain.
 */
final class RetractedLeafFilter
{
    /**
     * @param list<array<string,mixed>> $replies replies only, root excluded
     * @return list<array<string,mixed>>
     */
    public static function apply(array $replies): array
    {
        do {
            $answered = [];
            foreach ($replies as $reply) {
                $parent = (string) ($reply['parent_id'] ?? '');
                if ($parent !== '') {
                    $answered[$parent] = true;
                }
            }
            $kept = array_values(array_filter($replies, static fn (array $reply): bool =>
                ($reply['retracted_at'] ?? null) === null || isset($answered[(string) $reply['post_id']])));
            $changed = count($kept) !== count($replies);
            $replies = $kept;
        } while ($changed);
        return $replies;
    }
}
