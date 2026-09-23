<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ViewerTagLookup;
use ForumRewrite\Support\ThreadTitle;

/**
 * Twenty-eighth Phase 2 slice (see docs/plans/codebase_cleanup_audit_plan_v1.md):
 * the single-thread view - /threads/{id} (+ /threads/{id}?format=rss) and
 * /posts/{id}. Earlier passes flagged this as harder tier (13+
 * collaborators spanning agent-reply, Codex handoff, LLM-exchange, and
 * post-analysis subsystems), but by the time this slice landed, every one
 * of those subsystems had already been extracted into ActivityService/
 * PostWorkflowService - so what's left here are calls to already-cheap
 * delegating wrappers, not core logic. This controller builds its own
 * PostWorkflowService directly (same three closures
 * PostWorkflowApiController needs) rather than taking six more closures
 * for methods that just forward to it.
 *
 * viewerCanInspectLlmExchanges()/fetchLlmExchangesForPosts() stay on
 * Application (llmExchangeStore() has an external consumer,
 * LlmExchangesController, beyond this cluster) - passed in as closures,
 * same as the PostWorkflowApiController slice.
 */
final class ThreadAndPostPageController
{
    /**
     * @param \Closure(string): (array<string, mixed>|null) $fetchThread
     * @param \Closure(string): array<int, array<string, mixed>> $fetchThreadPosts
     * @param \Closure(string, bool): (array<string, mixed>|null) $fetchPost
     * @param \Closure(array<string, mixed>): string $displayThreadTitle
     * @param \Closure(): (array<string, mixed>|null) $resolveViewerProfileFromIdentityHint
     * @param \Closure(): (\ForumRewrite\Llm\LlmExchangeRecorder|null) $llmExchangeRecorderFactory
     * @param \Closure(): bool $viewerCanInspectLlmExchanges
     * @param \Closure(array<int, array<string, mixed>>): array<string, list<array<string, mixed>>> $fetchLlmExchangesForPosts
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly string $repositoryRoot,
        private readonly \Closure $fetchThread,
        private readonly \Closure $fetchThreadPosts,
        private readonly \Closure $fetchPost,
        private readonly \Closure $displayThreadTitle,
        private readonly \Closure $resolveViewerProfileFromIdentityHint,
        private readonly \Closure $llmExchangeRecorderFactory,
        private readonly \Closure $viewerCanInspectLlmExchanges,
        private readonly \Closure $fetchLlmExchangesForPosts,
    ) {
    }

    private function postWorkflowService(): \ForumRewrite\Agent\PostWorkflowService
    {
        return $this->routeServices->postWorkflowService(
            $this->fetchPost,
            $this->fetchThreadPosts,
            $this->llmExchangeRecorderFactory,
        );
    }

    /**
     * @return string[]
     */
    private function identityScripts(array $extra = []): array
    {
        return array_merge([
            '/assets/openpgp_loader.js',
            '/assets/browser_signing.js',
        ], $extra);
    }

    public function thread(string $threadId, string $createdPostId = ''): ?string
    {
        $threadRow = ($this->fetchThread)($threadId);
        if ($threadRow === null) {
            return null;
        }

        $service = $this->postWorkflowService();
        $title = ($this->displayThreadTitle)($threadRow);
        $viewerProfile = ($this->resolveViewerProfileFromIdentityHint)();
        $viewerHasLiked = $viewerProfile !== null
            && $this->viewerHasThreadTag($threadId, 'like', (string) $viewerProfile['identity_id']);
        $posts = ($this->fetchThreadPosts)($threadId);
        $viewerPostFlags = $viewerProfile !== null
            ? $this->viewerPostTagsForPosts(array_column($posts, 'post_id'), 'flag', (string) $viewerProfile['identity_id'])
            : [];
        $viewerPostLikes = $viewerProfile !== null
            ? $this->viewerPostTagsForPosts(array_column($posts, 'post_id'), 'like', (string) $viewerProfile['identity_id'])
            : [];
        $viewerCanSeePostAnalysis = $viewerProfile !== null && ((int) ($viewerProfile['is_approved'] ?? 0)) === 1;
        $viewerCanUseCodexHandoff = $service->viewerCanUseCodexHandoff($viewerProfile);
        $createdPostId = $this->createdPostIdForThread($threadId, $createdPostId);
        $postAnalysesForWork = $service->fetchPostAnalysesForPosts($posts);
        $agentRepliesByPostId = $service->fetchAgentReplyGenerationsForPosts($posts);
        $llmExchangesByPostId = ($this->viewerCanInspectLlmExchanges)() ? ($this->fetchLlmExchangesForPosts)($posts) : [];
        $codexHandoffsByPostId = $viewerCanUseCodexHandoff ? $service->fetchCodexHandoffsForPosts($posts) : [];
        $codexHandoffEligiblePostIds = $viewerCanUseCodexHandoff ? $service->codexHandoffEligiblePostIds($posts, $threadRow) : [];

        return $this->routeServices->renderPageTemplate(
            'thread.php',
            [
                'thread' => $threadRow,
                'posts' => $posts,
                'title' => $title,
                'viewerProfile' => $viewerProfile,
                'viewerHasLiked' => $viewerHasLiked,
                'viewerPostFlags' => $viewerPostFlags,
                'viewerPostLikes' => $viewerPostLikes,
                'createdPostId' => $createdPostId,
                'viewerCanSeePostAnalysis' => $viewerCanSeePostAnalysis,
                'viewerCanUseCodexHandoff' => $viewerCanUseCodexHandoff,
                'postAnalysesByPostId' => $viewerCanSeePostAnalysis ? $postAnalysesForWork : [],
                'agentRepliesByPostId' => $agentRepliesByPostId,
                'llmExchangesByPostId' => $llmExchangesByPostId,
                'codexHandoffsByPostId' => $codexHandoffsByPostId,
                'codexHandoffEligiblePostIds' => $codexHandoffEligiblePostIds,
                'agentReplyWorkByPostId' => $service->agentReplyWorkByPostId(
                    $posts,
                    $createdPostId,
                    $postAnalysesForWork,
                    $agentRepliesByPostId
                ),
            ],
            $title,
            'board',
            $this->identityScripts([
                '/assets/inline_reply_form.js',
                '/assets/thread_reactions.js',
                '/assets/post_analysis.js',
            ]),
        );
    }

    public function threadRss(string $threadId): ?string
    {
        $thread = ($this->fetchThread)($threadId);
        if ($thread === null) {
            return null;
        }

        $items = [];
        foreach (($this->fetchThreadPosts)($threadId) as $post) {
            $items[] = RssFeed::item(
                $post['post_id'],
                '/posts/' . $post['post_id'],
                trim($post['body']),
                (string) $post['created_at']
            );
        }

        return RssFeed::feed(($this->displayThreadTitle)($thread), '/threads/' . $threadId . '?format=rss', $items);
    }

    public function post(string $postId): ?string
    {
        $post = ($this->fetchPost)($postId, true);
        if ($post === null) {
            return null;
        }

        if (((int) ($post['is_hidden'] ?? 0)) === 1) {
            return $this->routeServices->renderMessagePage('Post Hidden', 'Post Hidden', 'This post has been hidden.', 'board');
        }

        $service = $this->postWorkflowService();
        $viewerProfile = ($this->resolveViewerProfileFromIdentityHint)();
        $viewerPostFlags = $viewerProfile !== null
            ? $this->viewerPostTagsForPosts([$post['post_id']], 'flag', (string) $viewerProfile['identity_id'])
            : [];
        $viewerPostLikes = $viewerProfile !== null
            ? $this->viewerPostTagsForPosts([$post['post_id']], 'like', (string) $viewerProfile['identity_id'])
            : [];
        $viewerCanSeePostAnalysis = $viewerProfile !== null && ((int) ($viewerProfile['is_approved'] ?? 0)) === 1;
        $viewerCanUseCodexHandoff = $service->viewerCanUseCodexHandoff($viewerProfile);
        $posts = [$post];
        $postAnalysesForWork = $service->fetchPostAnalysesForPosts($posts);
        $agentRepliesByPostId = $service->fetchAgentReplyGenerationsForPosts($posts);
        $llmExchangesByPostId = ($this->viewerCanInspectLlmExchanges)() ? ($this->fetchLlmExchangesForPosts)($posts) : [];
        $codexHandoffsByPostId = $viewerCanUseCodexHandoff ? $service->fetchCodexHandoffsForPosts($posts) : [];
        $threadRow = ($this->fetchThread)((string) $post['thread_id']);
        $codexHandoffEligiblePostIds = $viewerCanUseCodexHandoff ? $service->codexHandoffEligiblePostIds($posts, $threadRow) : [];

        return $this->routeServices->renderPageTemplate(
            'post.php',
            [
                'post' => $post,
                'viewerPostFlags' => $viewerPostFlags,
                'viewerPostLikes' => $viewerPostLikes,
                'viewerCanSeePostAnalysis' => $viewerCanSeePostAnalysis,
                'viewerCanUseCodexHandoff' => $viewerCanUseCodexHandoff,
                'postAnalysesByPostId' => $viewerCanSeePostAnalysis ? $postAnalysesForWork : [],
                'agentRepliesByPostId' => $agentRepliesByPostId,
                'llmExchangesByPostId' => $llmExchangesByPostId,
                'codexHandoffsByPostId' => $codexHandoffsByPostId,
                'codexHandoffEligiblePostIds' => $codexHandoffEligiblePostIds,
                'agentReplyWorkByPostId' => $service->agentReplyWorkByPostId(
                    $posts,
                    '',
                    $postAnalysesForWork,
                    $agentRepliesByPostId
                ),
            ],
            'Post ' . $post['post_id'],
            'board',
            $this->identityScripts([
                '/assets/thread_reactions.js',
                '/assets/post_analysis.js',
            ]),
        );
    }

    private function viewerHasThreadTag(string $threadId, string $tag, string $identityId): bool
    {
        $repository = new CanonicalRecordRepository($this->repositoryRoot);
        foreach (glob($this->repositoryRoot . '/records/thread-labels/*.txt') ?: [] as $path) {
            $record = $repository->loadThreadLabel('records/thread-labels/' . basename($path));
            if ($record->threadId !== $threadId || $record->authorIdentityId !== $identityId) {
                continue;
            }

            if (in_array($tag, $record->labels, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, mixed> $postIds
     * @return array<string, true>
     */
    private function viewerPostTagsForPosts(array $postIds, string $tag, string $identityId): array
    {
        return ViewerTagLookup::postTags($this->repositoryRoot, $postIds, $tag, $identityId);
    }

    private function createdPostIdForThread(string $threadId, string $createdPostId): string
    {
        $createdPostId = trim($createdPostId);
        if ($createdPostId === '') {
            return '';
        }

        $post = ($this->fetchPost)($createdPostId);
        if ($post === null || (string) $post['thread_id'] !== $threadId) {
            return '';
        }

        return $createdPostId;
    }
}
