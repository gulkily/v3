<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use ForumRewrite\Support\ThreadLabels;

/**
 * Takes replies that are only hashtags out of the conversation when no shown reply answers them,
 * so a label does not take a full reply card. A tag-only reply that someone answered stays, so that
 * conversation keeps its context. Repeats until stable, so a chain of tag-only replies goes whole.
 * Display only, like RetractedLeafFilter: the reply, its labels and its own page remain.
 */
final class TagOnlyLeafFilter
{
    /**
     * @param list<array<string,mixed>> $replies replies only, root excluded, in thread order
     * @return array{kept: list<array<string,mixed>>, moved: list<array<string,mixed>>} both in thread order
     */
    public static function split(array $replies): array
    {
        $movedIds = [];
        do {
            $answered = [];
            foreach ($replies as $reply) {
                if (!isset($movedIds[(string) $reply['post_id']]) && (string) ($reply['parent_id'] ?? '') !== '') {
                    $answered[(string) $reply['parent_id']] = true;
                }
            }
            $changed = false;
            foreach ($replies as $reply) {
                $id = (string) $reply['post_id'];
                if (!isset($movedIds[$id]) && !isset($answered[$id]) && ThreadLabels::isTagOnly($reply)) {
                    $movedIds[$id] = true;
                    $changed = true;
                }
            }
        } while ($changed);

        $kept = [];
        $moved = [];
        foreach ($replies as $reply) {
            if (isset($movedIds[(string) $reply['post_id']])) {
                $moved[] = $reply;
            } else {
                $kept[] = $reply;
            }
        }

        return ['kept' => $kept, 'moved' => $moved];
    }
}
