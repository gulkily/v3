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
    /** Compute once per aggregation, never cache across canonical writes. */
    public static function inactive(string $root): array
    {
        $removed = RetractedPosts::effectivePostIds($root);
        $evidence = AcceptedRetraction::removals($root) + LabelRemoval::all($root);
        foreach ($evidence as $id => $entry) if ($entry['operation'] === 'delete') $removed[$id] = 'operator';
        $inactive = [];
        foreach (glob($root . '/records/thread-labels/*.txt') ?: [] as $path) {
            $relative = 'records/thread-labels/' . basename($path);
            try { $label = (new CanonicalRecordRepository($root))->loadThreadLabel($relative); }
            catch (CanonicalRecordParseException) { continue; }
            $source = $label->sourcePostId;
            $deleted = $source === null ? null : ($evidence[$source] ?? null);
            $deletionBound = ($deleted['operation'] ?? null) === 'delete'
                && $label->sourcePostSha256 !== null && $label->sourcePostSha256 === ($deleted['source_hash'] ?? null)
                && $label->threadId === $deleted['post']['thread_id']
                && $label->authorIdentityId === $deleted['post']['author_identity_id'];
            if ($source !== null && isset($removed[$source]) && ($deletionBound || self::inspect($label, $root)['status'] === 'proven')) {
                $inactive[$label->recordId] = true;
            }
            foreach ($evidence as $id => $entry) {
                if (!isset($removed[$id]) && self::hasSource($root, $id)) continue;
                if (isset($entry['labels'][$relative]) && hash_equals($entry['labels'][$relative], hash_file('sha256', $path))) {
                    $inactive[$label->recordId] = true;
                }
            }
        }
        return $inactive;
    }

    public static function hasSource(string $root, string $id): bool
    {
        foreach (CanonicalPathResolver::postCandidates($id) as $path) if (is_file($root . '/' . $path)) return true;
        return false;
    }

}
