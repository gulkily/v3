<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\ReadModel\AuthoredContentRepository;
use ForumRewrite\ReadModel\ProfileRepository;
use ForumRewrite\Support\ThreadTitle;

/**
 * Twenty-third Phase 2 slice (see docs/plans/codebase_cleanup_audit_plan_v1.md):
 * two of the four `/api/forte_*`/`/api/get_forte_*` AJAX endpoints -
 * /api/get_forte_content_summary and /api/forte_user_detail. The other two
 * (/api/forte_activity_page, /api/forte_commit_detail) stay on
 * Application - both are directly coupled to fetchActivity() /
 * activityCommitManifest(), the same harder-tier activity/commit-manifest
 * subsystem already deferred for /activity itself.
 *
 * fetchVisibleAuthoredThreads()/fetchVisibleAuthoredPosts() and
 * fetchProfilesByUsernameToken() were one-line repository wrappers with
 * exactly one caller each (this cluster), so this controller calls
 * AuthoredContentRepository/ProfileRepository directly instead of taking
 * closures - same pattern as AuthApiController's fetchProfileByIdentityId().
 * Only fetchPost() (shared elsewhere) stayed a bound closure.
 */
final class ForteContentAndUserDetailApiController
{
    /**
     * @param \Closure(string): (array<string, mixed>|null) $fetchPost
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly \Closure $fetchPost,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     */
    public function contentSummary(array $query): void
    {
        $summary = $this->forteContentSummary((string) ($query['post_id'] ?? ''));
        if ($summary === null) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'not found'], 404);
            return;
        }

        $this->routeServices->sendJson(array_merge(['status' => 'ok'], $summary), 200);
    }

    /**
     * @return array{post_id:string,thread_id:string,is_reply:bool,title:string,author_label:string,created_at:string,body_preview:string,reply_count:int}|null
     */
    private function forteContentSummary(string $postId): ?array
    {
        $post = ($this->fetchPost)($postId);
        if ($post === null) {
            return null;
        }

        $isReply = trim((string) ($post['parent_id'] ?? '')) !== '';
        $threadId = (string) $post['thread_id'];

        $stmt = $this->routeServices->pdo()->prepare('SELECT reply_count FROM threads WHERE root_post_id = :root_post_id');
        $stmt->execute(['root_post_id' => $threadId]);
        $replyCount = $stmt->fetchColumn();

        return [
            'post_id' => (string) $post['post_id'],
            'thread_id' => $threadId,
            'is_reply' => $isReply,
            'title' => ThreadTitle::displayTitle(
                (string) ($post['subject'] ?? ''),
                (string) ($post['body'] ?? ''),
                (string) $post['post_id'],
            ),
            'author_label' => (string) ($post['author_label'] ?? ''),
            'created_at' => (string) ($post['created_at'] ?? ''),
            'body_preview' => (string) ($post['body'] ?? ''),
            'reply_count' => $replyCount !== false ? (int) $replyCount : 0,
        ];
    }

    /**
     * @param array<string, mixed> $query
     */
    public function userDetail(array $query): void
    {
        $usernameToken = strtolower(trim((string) ($query['username_token'] ?? '')));
        $profiles = $usernameToken === '' ? [] : ProfileRepository::byUsernameToken($this->routeServices->pdo(), $usernameToken);
        $approvedProfiles = array_values(array_filter(
            $profiles,
            static fn (array $profile): bool => ((int) $profile['is_approved']) === 1
        ));
        if ($approvedProfiles === []) {
            $this->userDetailPending($usernameToken, $profiles);
            return;
        }

        $approvedIdentityIds = array_values(array_map(
            static fn (array $profile): string => (string) $profile['identity_id'],
            $approvedProfiles
        ));

        $approvedThreads = AuthoredContentRepository::visibleThreads($this->routeServices->pdo(), $approvedIdentityIds);
        $approvedPosts = AuthoredContentRepository::visiblePosts($this->routeServices->pdo(), $approvedIdentityIds);
        $activityBounds = $this->userDirectoryActivityBounds($approvedThreads, $approvedPosts);

        $html = $this->routeServices->renderFragment('partials/paned_user_detail_pane.php', [
            'usernameToken' => $usernameToken,
            'approvedThreadCount' => count($approvedThreads),
            'approvedPostCount' => count($approvedPosts),
            'approvedThreads' => $approvedThreads,
            'approvedPosts' => $approvedPosts,
            'activeAt' => $activityBounds['latest'],
            'memberSince' => $activityBounds['earliest'],
        ]);

        $this->routeServices->sendJson(['status' => 'ok', 'html' => $html], 200);
    }

    /**
     * @param array<int, array<string, mixed>> $threads
     * @param array<int, array<string, mixed>> $posts
     * @return array{earliest: string, latest: string}
     */
    private function userDirectoryActivityBounds(array $threads, array $posts): array
    {
        $timestamps = [];
        foreach ($threads as $thread) {
            $timestamps[] = (string) ($thread['root_post_created_at'] ?? '');
        }
        foreach ($posts as $post) {
            $timestamps[] = (string) ($post['created_at'] ?? '');
        }
        $timestamps = array_values(array_filter($timestamps, static fn (string $timestamp): bool => $timestamp !== ''));

        if ($timestamps === []) {
            return ['earliest' => '', 'latest' => ''];
        }

        return ['earliest' => min($timestamps), 'latest' => max($timestamps)];
    }

    /**
     * @param array<int, array<string, mixed>> $profiles every profile (any approval status) for this token
     */
    private function userDetailPending(string $usernameToken, array $profiles): void
    {
        if ($profiles === []) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'user not found'], 404);
            return;
        }

        $pendingThreadCount = array_sum(array_map(static fn (array $p): int => (int) $p['thread_count'], $profiles));
        $pendingPostCount = array_sum(array_map(static fn (array $p): int => (int) $p['post_count'], $profiles));

        $html = $this->routeServices->renderFragment('partials/paned_user_pending_detail_pane.php', [
            'usernameToken' => $usernameToken,
            'pendingProfileCount' => count($profiles),
            'pendingThreadCount' => $pendingThreadCount,
            'pendingPostCount' => $pendingPostCount,
        ]);

        $this->routeServices->sendJson(['status' => 'ok', 'html' => $html], 200);
    }
}
