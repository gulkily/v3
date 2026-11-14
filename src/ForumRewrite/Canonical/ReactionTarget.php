<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

use RuntimeException;

/** Where a reaction record sits, for retraction and removal receipts. */
final class ReactionTarget
{
    /**
     * @return array{record_id: string, thread_id: string, target_post_id: string, created_at: string,
     *     author_identity_id: string, is_like: bool, path: string, bytes: string}
     */
    public static function describe(string $root, string $recordId): array
    {
        if (RemovalTarget::family($recordId) === 'post') throw new RuntimeException('Not a reaction record ID.');
        $path = RemovalTarget::sourceCandidates($recordId)[0];
        $absolute = $root . '/' . $path;
        if (!is_file($absolute) || is_link($absolute)) throw new RuntimeException('Reaction to retract was not found.');
        $bytes = (string) RecordLimits::read($absolute);
        if (RemovalTarget::family($recordId) === 'thread-label') {
            $label = (new ThreadLabelRecordParser())->parse($bytes);
            return ['record_id' => $label->recordId, 'thread_id' => $label->threadId, 'target_post_id' => $label->threadId,
                'created_at' => $label->createdAt, 'author_identity_id' => (string) $label->authorIdentityId,
                // A label owned by a post's hashtag is removed with that post, never on its own.
                'is_like' => $label->labels === ['like'] && $label->operation === 'add' && $label->sourcePostId === null,
                'path' => $path, 'bytes' => $bytes];
        }
        $reaction = (new PostReactionRecordParser())->parse($bytes);
        $threadId = $reaction->postId;
        $repository = new CanonicalRecordRepository($root);
        foreach (CanonicalPathResolver::postCandidates($reaction->postId) as $candidate) {
            if (is_file($root . '/' . $candidate)) { $threadId = $repository->loadPost($candidate)->threadId ?? $reaction->postId; break; }
        }
        return ['record_id' => $reaction->recordId, 'thread_id' => $threadId, 'target_post_id' => $reaction->postId,
            'created_at' => $reaction->createdAt, 'author_identity_id' => (string) $reaction->authorIdentityId,
            'is_like' => $reaction->tags === ['like'] && $reaction->operation === 'add', 'path' => $path, 'bytes' => $bytes];
    }
}
