<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use ForumRewrite\Canonical\CanonicalRecordRepository;

/**
 * Bulk lookups of which posts/threads a given identity has tagged
 * (liked/flagged posts, liked/labeled threads) - one glob/scan of the
 * relevant canonical records covering many posts or threads at once,
 * instead of one scan per post/thread. Pure given the repository root; no
 * PDO, no session. Used by the board, Forte's board view, and single-thread
 * rendering alike.
 */
final class ViewerTagLookup
{
    /**
     * @param array<int, mixed> $postIds
     * @return array<string, true>
     */
    public static function postTags(string $repositoryRoot, array $postIds, string $tag, string $identityId): array
    {
        $postLookup = array_fill_keys(array_map(static fn (mixed $value): string => (string) $value, $postIds), true);
        if ($postLookup === []) {
            return [];
        }

        $repository = new CanonicalRecordRepository($repositoryRoot);
        $taggedPostIds = [];
        foreach (glob($repositoryRoot . '/records/post-reactions/*.txt') ?: [] as $path) {
            $record = $repository->loadPostReaction('records/post-reactions/' . basename($path));
            if (!isset($postLookup[$record->postId]) || $record->authorIdentityId !== $identityId) {
                continue;
            }

            if (in_array($tag, $record->tags, true)) {
                $taggedPostIds[$record->postId] = true;
            }
        }

        return $taggedPostIds;
    }

    /**
     * @param array<int, mixed> $threadIds
     * @return array<string, true>
     */
    public static function threadTags(string $repositoryRoot, array $threadIds, string $tag, string $identityId): array
    {
        $threadLookup = array_fill_keys(array_map(static fn (mixed $value): string => (string) $value, $threadIds), true);
        if ($threadLookup === []) {
            return [];
        }

        $repository = new CanonicalRecordRepository($repositoryRoot);
        $taggedThreadIds = [];
        foreach (glob($repositoryRoot . '/records/thread-labels/*.txt') ?: [] as $path) {
            $record = $repository->loadThreadLabel('records/thread-labels/' . basename($path));
            if (!isset($threadLookup[$record->threadId]) || $record->authorIdentityId !== $identityId) {
                continue;
            }

            if (in_array($tag, $record->labels, true)) {
                $taggedThreadIds[$record->threadId] = true;
            }
        }

        return $taggedThreadIds;
    }
}
