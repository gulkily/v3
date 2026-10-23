<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

/** Ownership is explicit and content-bound; legacy proximity is never proof. */
final class LabelOwnership
{
    public static function inspect(ThreadLabelRecord $label, string $repositoryRoot): array
    {
        if ($label->sourcePostId === null) {
            return ['status' => 'unresolved', 'source_post_id' => null, 'reason' => 'No source provenance; may be an independent action.'];
        }
        foreach (CanonicalPathResolver::postCandidates($label->sourcePostId) as $relative) {
            $path = $repositoryRoot . '/' . $relative;
            if (!is_file($path)) continue;
            $bytes = (string) file_get_contents($path);
            try { $post = (new PostRecordParser())->parse($bytes); }
            catch (CanonicalRecordParseException) {
                return ['status' => 'unresolved', 'source_post_id' => $label->sourcePostId, 'reason' => 'Invalid source record.'];
            }
            if ($post->postId !== $label->sourcePostId
                || ($post->threadId ?? $post->postId) !== $label->threadId
                || $post->authorIdentityId !== $label->authorIdentityId
                || !hash_equals($label->sourcePostSha256 ?? '', hash('sha256', $bytes))) {
                return ['status' => 'unresolved', 'source_post_id' => $label->sourcePostId, 'reason' => 'Source content or attribution differs.'];
            }
            return ['status' => 'proven', 'source_post_id' => $post->postId, 'reason' => 'Explicit content-bound source.'];
        }
        return ['status' => 'unresolved', 'source_post_id' => $label->sourcePostId, 'reason' => 'Source absent; inspect retained removal evidence or history.'];
    }
}
