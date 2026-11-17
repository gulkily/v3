<?php

declare(strict_types=1);

namespace ForumRewrite\View;

use ForumRewrite\Canonical\PostRecordList;
use ForumRewrite\ReadModel\TagOnlyLeafFilter;
use ForumRewrite\Support\ThreadLabels;

/**
 * Entries for a thread page's Activity drawer: one "tagged" entry per tag-only reply that left the
 * conversation, then one group per post that has likes or flags. Built only from data the thread
 * page already loaded; the actors are the same SignalActors lists the signal lines show.
 */
final class ThreadActivity
{
    /**
     * Splits a thread's replies for the drawer and builds its entries. If anything fails, the
     * thread renders as it did before the drawer: every reply in place and no entries.
     *
     * @param array<string,mixed>|null $rootPost
     * @param list<array<string,mixed>> $replies replies after RetractedLeafFilter, in thread order
     * @param array<string,mixed>|null $signalActors
     * @param array<string,string> $recordIds PostRecordList::reactionRecordIds() result; names with a record link to it
     * @return array{shown: list<array<string,mixed>>, moved: list<array<string,mixed>>, entries: list<array<string,mixed>>}
     */
    public static function partition(?array $rootPost, array $replies, ?array $signalActors, bool $recordsEnabled, array $recordIds = []): array
    {
        try {
            ['kept' => $shown, 'moved' => $moved] = TagOnlyLeafFilter::split($replies);

            return ['shown' => $shown, 'moved' => $moved, 'entries' => self::build($rootPost, $moved, $shown, $signalActors, $recordsEnabled, $recordIds)];
        } catch (\Throwable) {
            return ['shown' => $replies, 'moved' => [], 'entries' => []];
        }
    }

    /**
     * @param array<string,mixed>|null $rootPost
     * @param list<array<string,mixed>> $movedReplies tag-only replies taken out of the conversation
     * @param list<array<string,mixed>> $shownReplies replies still in the conversation
     * @param array<string,mixed>|null $signalActors SignalActors::load() result
     * @param array<string,string> $recordIds PostRecordList::reactionRecordIds() result
     * @return list<array<string,mixed>>
     */
    public static function build(?array $rootPost, array $movedReplies, array $shownReplies, ?array $signalActors, bool $recordsEnabled, array $recordIds = []): array
    {
        $entries = [];
        foreach ($movedReplies as $reply) {
            $entries[] = [
                'kind' => 'tagged',
                'post_id' => (string) $reply['post_id'],
                'author' => self::author($reply),
                'labels' => ThreadLabels::fromBody((string) ($reply['body'] ?? '')),
                'created_at' => (string) ($reply['created_at'] ?? ''),
                'href' => self::postHref((string) $reply['post_id'], false),
            ];
        }

        $posts = $signalActors['posts'] ?? [];
        if ($rootPost !== null) {
            $rootId = (string) $rootPost['post_id'];
            $entries = self::addReactions($entries, $rootId, 'Root post', $signalActors['thread_likes'] ?? [], $posts[$rootId]['flag'] ?? [], $recordsEnabled, $recordIds);
        }
        $replies = [...$shownReplies, ...$movedReplies];
        usort($replies, static fn (array $a, array $b): int => [(int) ($a['sequence_number'] ?? 0), (string) $a['post_id']] <=> [(int) ($b['sequence_number'] ?? 0), (string) $b['post_id']]);
        foreach ($replies as $reply) {
            $id = (string) $reply['post_id'];
            $label = 'Reply by ' . self::author($reply)['username'];
            $entries = self::addReactions($entries, $id, $label, $posts[$id]['like'] ?? [], $posts[$id]['flag'] ?? [], $recordsEnabled, $recordIds);
        }

        return $entries;
    }

    /**
     * @param list<array<string,mixed>> $entries
     * @param list<array<string,mixed>> $likes @param list<array<string,mixed>> $flags
     * @return list<array<string,mixed>>
     */
    private static function addReactions(array $entries, string $postId, string $label, array $likes, array $flags, bool $recordsEnabled, array $recordIds): array
    {
        if ($likes !== [] || $flags !== []) {
            $withRecord = static fn (array $actors, string $tag): array => array_map(static function (array $actor) use ($postId, $tag, $recordIds): array {
                $recordId = $recordIds[PostRecordList::reactionKey($postId, $tag, (string) ($actor['identity_id'] ?? ''))] ?? null;
                return $recordId !== null ? $actor + ['record_id' => $recordId] : $actor;
            }, $actors);
            [$likes, $flags] = [$withRecord($likes, 'like'), $withRecord($flags, 'flag')];
            $entries[] = ['kind' => 'reactions', 'post_id' => $postId, 'label' => $label, 'like' => $likes, 'flag' => $flags,
                'href' => self::postHref($postId, $recordsEnabled)];
        }

        return $entries;
    }

    /** @param array<string,mixed> $post @return array{username: string, profile_slug: ?string} */
    private static function author(array $post): array
    {
        $slug = (string) ($post['author_profile_slug'] ?? '');

        return ['username' => (string) ($post['author_label'] ?? 'guest'), 'profile_slug' => $slug !== '' ? $slug : null];
    }

    private static function postHref(string $postId, bool $records): string
    {
        return '/posts/' . rawurlencode($postId) . ($records ? '#records' : '');
    }
}
