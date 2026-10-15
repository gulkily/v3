<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

/**
 * Single definition of when an author retraction applies, shared by the read-model builder
 * and the raw-record routes so they cannot disagree about which posts are suppressed.
 */
final class RetractedPosts
{
    /** Authority posts are never retractable: trust state is derived from their bodies. */
    private const AUTHORITY_TAGS = ['identity', 'approval', 'invitation'];

    public static function applies(PostRecord $post, ?PostRetractionRecord $retraction): bool
    {
        if ($retraction === null || $post->authorIdentityId === null) {
            return false;
        }
        if ($retraction->postId !== $post->postId || $retraction->authorIdentityId !== $post->authorIdentityId) {
            return false;
        }

        return ($retraction->classification !== 'operator' || $retraction->operatorAccepted)
            && array_intersect($post->boardTags, self::AUTHORITY_TAGS) === [];
    }

    public static function isAuthorityPost(PostRecord $post): bool
    {
        return array_intersect($post->boardTags, self::AUTHORITY_TAGS) !== [];
    }

    /**
     * Ids of posts whose retraction is effective in the repository's current files.
     *
     * @return array<string, string>
     */
    public static function effectivePostIds(string $repositoryRoot): array
    {
        return self::effectiveValues($repositoryRoot, false);
    }

    /** @return array<string, string> Display attribution, never used to grant suppression authority. */
    public static function effectiveAttributions(string $repositoryRoot): array
    {
        return self::effectiveValues($repositoryRoot, true);
    }

    private static function effectiveValues(string $repositoryRoot, bool $attribution): array
    {
        $repository = new CanonicalRecordRepository($repositoryRoot);
        $ids = [];
        foreach (glob($repositoryRoot . '/records/post-retractions/*.txt') ?: [] as $path) {
            try {
                $retraction = $repository->loadPostRetraction('records/post-retractions/' . basename($path));
                foreach (CanonicalPathResolver::postCandidates($retraction->postId) as $candidate) {
                    if (!is_file($repositoryRoot . '/' . $candidate)) {
                        continue;
                    }
                    if (self::applies($repository->loadPost($candidate), $retraction)) {
                        $ids[$retraction->postId] = $attribution ? ($retraction->attribution ?? $retraction->classification) : $retraction->classification;
                    }
                    break;
                }
            } catch (CanonicalRecordParseException) {
                continue;
            }
        }

        return $ids;
    }

    /** True for a post record file (flat or dated path) of an effectively retracted post. */
    public static function isRetractedPostPath(string $relativePath, array $effectivePostIds): bool
    {
        if (preg_match('#^records/posts/(?:\d{4}/\d{2}/\d{2}/)?([A-Za-z0-9][A-Za-z0-9._-]*)\.txt$#', $relativePath, $matches) !== 1) {
            return false;
        }

        return isset($effectivePostIds[$matches[1]]);
    }
}
