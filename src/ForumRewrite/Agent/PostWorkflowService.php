<?php

declare(strict_types=1);

namespace ForumRewrite\Agent;

use ForumRewrite\Analysis\PostAnalysisService;
use ForumRewrite\Analysis\PostAnalyzerFactory;
use ForumRewrite\Analysis\RelatedContentSearchService;
use ForumRewrite\Analysis\SqlitePostAnalysisStore;
use ForumRewrite\Analysis\SqliteUnicodeRiskStore;
use ForumRewrite\Analysis\UnicodeRiskInspector;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Codex\CodexHandoffDraftService;
use ForumRewrite\Codex\CodexHandoffStore;
use ForumRewrite\ReadModel\ThreadRowSupport;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\PrivateConfig;
use ForumRewrite\Write\LocalWriteService;
use PDO;
use RuntimeException;

/**
 * The post-analysis/agent-reply/Codex-handoff data and orchestration
 * layer, extracted from Application.php (see
 * docs/plans/codebase_cleanup_audit_plan_v1.md and the dedicated
 * dependency-graph investigation that preceded this slice - same
 * treatment ActivityService got). Moved wholesale, mirroring
 * ActivityService: nearly everything here only needs
 * (pdo, projectRoot, repositoryRoot, databasePath, artifactRoot,
 * staticHtmlRoot, featureFlags, writer()) - already exactly what
 * RouteServices carries - plus a handful of closures for methods that
 * stay genuinely shared elsewhere (fetchPost, fetchThreadPosts,
 * llmExchangeRecorder - the latter stays on Application since
 * llmExchangeStore()/viewerCanInspectLlmExchanges() are also still used
 * directly by LlmExchangesController there).
 *
 * PDO is lazy (a factory closure, memoized on first real use) rather than
 * built eagerly at construction - the same fix ActivityService needed:
 * some callers (e.g. viewerCanUseCodexHandoff(), which a test reflects
 * into directly without going through ensureReadModel()) never touch the
 * main read-model at all, and eagerly opening it would break that case
 * again.
 */
final class PostWorkflowService
{
    private const ANALYSIS_SCHEMA_VERSION = 5;
    private const THREAD_CONTEXT_COMMENT_BODY_LIMIT = 3000;
    private const THREAD_CONTEXT_TOTAL_BODY_LIMIT = 18000;
    private const CODEX_HANDOFF_DEVELOPMENT_TAGS = ['feature', 'bug', 'task', 'dev', 'development', 'codex', 'implementation', 'fdp'];

    private ?PDO $pdo = null;

    /**
     * @param \Closure(): PDO $pdoFactory
     * @param \Closure(): LocalWriteService $writerFactory
     * @param \Closure(string): (array<string, mixed>|null) $fetchPost
     * @param \Closure(string): array<int, array<string, mixed>> $fetchThreadPosts
     * @param \Closure(): (\ForumRewrite\Llm\LlmExchangeRecorder|null) $llmExchangeRecorderFactory
     */
    public function __construct(
        private readonly \Closure $pdoFactory,
        private readonly string $projectRoot,
        private readonly string $repositoryRoot,
        private readonly string $databasePath,
        private readonly ?string $artifactRoot,
        private readonly ?string $staticHtmlRoot,
        private readonly FeatureFlagEvaluator $featureFlags,
        private readonly \Closure $writerFactory,
        private readonly \Closure $fetchPost,
        private readonly \Closure $fetchThreadPosts,
        private readonly \Closure $llmExchangeRecorderFactory,
    ) {
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= ($this->pdoFactory)();
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public function postAnalysisContext(array $post, bool $includeThreadComments = true): array
    {
        $thread = ($this->fetchPost)((string) $post['thread_id']);
        $parent = trim((string) ($post['parent_id'] ?? '')) !== '' ? ($this->fetchPost)((string) $post['parent_id']) : null;
        $body = (string) $post['body'];

        $context = [
            'post_id' => (string) $post['post_id'],
            'content_hash' => $this->postAnalysisContentHash($post),
            'analysis_schema_version' => self::ANALYSIS_SCHEMA_VERSION,
            'post_kind' => (string) $post['post_id'] === (string) $post['thread_id'] ? 'thread' : 'reply',
            'thread_id' => (string) $post['thread_id'],
            'parent_id' => isset($post['parent_id']) ? (string) $post['parent_id'] : null,
            'subject' => isset($post['subject']) ? (string) $post['subject'] : '',
            'body' => $this->limitAnalysisText($body, 6000),
            'board_tags' => ThreadRowSupport::decodeStringList((string) ($post['board_tags_json'] ?? '[]')),
            'author_label' => (string) ($post['author_label'] ?? 'guest'),
            'thread_subject' => $thread !== null ? (string) ($thread['subject'] ?? '') : '',
            'thread_body_preview' => $thread !== null ? $this->limitAnalysisText((string) ($thread['body'] ?? ''), 1200) : '',
            'parent_body_preview' => $parent !== null ? $this->limitAnalysisText((string) ($parent['body'] ?? ''), 1200) : '',
        ];

        if ($includeThreadComments) {
            $context['thread_comments'] = $this->threadCommentsContext($post);
            $relatedContent = $this->relatedContentContext($post);
            if ($relatedContent !== []) {
                $context['related_content'] = $relatedContent;
            }
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $post
     */
    private function postAnalysisContentHash(array $post): string
    {
        return hash('sha256', json_encode([
            'analysis_schema_version' => self::ANALYSIS_SCHEMA_VERSION,
            'post_id' => (string) $post['post_id'],
            'subject' => (string) ($post['subject'] ?? ''),
            'body' => (string) $post['body'],
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $targetPost
     * @return array<int, array<string, mixed>>
     */
    private function threadCommentsContext(array $targetPost): array
    {
        $posts = ($this->fetchThreadPosts)((string) $targetPost['thread_id']);
        if ($posts === []) {
            return [];
        }

        $store = new SqlitePostAnalysisStore($this->pdo());
        $targetPostId = (string) $targetPost['post_id'];
        $parentPostId = isset($targetPost['parent_id']) ? (string) $targetPost['parent_id'] : '';
        $threadPostId = (string) $targetPost['thread_id'];
        $remainingBodyBudget = self::THREAD_CONTEXT_TOTAL_BODY_LIMIT;
        $comments = [];

        foreach ($posts as $post) {
            $postId = (string) $post['post_id'];
            $isRequired = $postId === $targetPostId || $postId === $parentPostId || $postId === $threadPostId;
            if ($remainingBodyBudget <= 0 && !$isRequired) {
                continue;
            }

            $bodyLimit = $isRequired
                ? self::THREAD_CONTEXT_COMMENT_BODY_LIMIT
                : min(self::THREAD_CONTEXT_COMMENT_BODY_LIMIT, $remainingBodyBudget);
            $body = $this->limitThreadCommentBody((string) $post['body'], max(0, $bodyLimit));
            $remainingBodyBudget -= strlen($body);
            $analysis = $store->find($postId, $this->postAnalysisContentHash($post));

            $comments[] = [
                'post_id' => $postId,
                'parent_id' => isset($post['parent_id']) ? (string) $post['parent_id'] : null,
                'author_label' => (string) ($post['author_label'] ?? 'guest'),
                'created_at' => (string) ($post['created_at'] ?? ''),
                'post_kind' => $postId === $threadPostId ? 'thread' : 'reply',
                'is_thread_root' => $postId === $threadPostId,
                'is_parent' => $postId === $parentPostId,
                'is_target' => $postId === $targetPostId,
                'is_agent_authored' => (string) ($post['author_label'] ?? '') === 'reply-agent',
                'post_summary' => is_array($analysis) && ($analysis['status'] ?? null) === 'complete'
                    ? (string) ($analysis['post_summary'] ?? '')
                    : '',
                'body' => $body,
                'body_truncated' => strlen((string) $post['body']) > strlen($body),
            ];
        }

        return $comments;
    }

    /**
     * @param array<string, mixed> $targetPost
     * @return list<array<string, mixed>>
     */
    private function relatedContentContext(array $targetPost): array
    {
        return (new RelatedContentSearchService($this->pdo()))->findRelatedContent($targetPost, 5);
    }

    private function limitThreadCommentBody(string $value, int $maxLength): string
    {
        if ($maxLength <= 0) {
            return '';
        }

        if (strlen($value) <= $maxLength) {
            return $value;
        }

        return substr($value, 0, max(0, $maxLength - 12)) . "\n[truncated]";
    }

    private function limitAnalysisText(string $value, int $maxLength): string
    {
        if (strlen($value) <= $maxLength) {
            return $value;
        }

        return substr($value, 0, $maxLength) . "\n[truncated]";
    }

    /**
     * @param array<string, mixed> $analysis
     * @return array<string, mixed>
     */
    public function postAnalysisResponse(array $analysis, bool $includeDetails): array
    {
        $response = [
            'status' => 'ok',
            'post_id' => (string) ($analysis['post_id'] ?? ''),
            'analysis_status' => (string) ($analysis['status'] ?? 'unknown'),
            'cached' => (bool) ($analysis['cached'] ?? false),
            'failure_code' => $analysis['failure_code'] ?? null,
            'failure_message' => $analysis['failure_message'] ?? ($analysis['message'] ?? null),
            'retry_after' => $analysis['retry_after'] ?? null,
            'viewer_can_see_analysis' => $includeDetails,
        ];

        if (!$includeDetails) {
            return $response;
        }

        $response['provider'] = $analysis['provider'] ?? null;
        $response['provider_model'] = $analysis['provider_model'] ?? null;
        $response['post_summary'] = $analysis['post_summary'] ?? '';
        $response['moderation'] = $analysis['moderation'] ?? null;
        $response['engagement'] = $analysis['engagement'] ?? null;
        $response['quality'] = $analysis['quality'] ?? null;
        $response['respondability'] = $analysis['respondability'] ?? null;
        $response['related_content'] = $analysis['related_content'] ?? [];
        $response['related_content_assessment'] = $analysis['related_content_assessment'] ?? [];
        $response['unicode_risk'] = $analysis['unicode_risk'] ?? null;

        return $response;
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $analysis
     * @return array<string, mixed>|null
     */
    public function agentReplyGateFailure(array $post, array $analysis): ?array
    {
        if ((string) ($post['author_label'] ?? '') === AgentIdentityService::USERNAME) {
            return ['reason' => 'agent_loop_prevention'];
        }

        $respondability = is_array($analysis['respondability'] ?? null) ? $analysis['respondability'] : [];
        $moderation = is_array($analysis['moderation'] ?? null) ? $analysis['moderation'] : [];
        $engagement = is_array($analysis['engagement'] ?? null) ? $analysis['engagement'] : [];

        if (($respondability['should_generate_response'] ?? false) !== true) {
            return ['reason' => 'respondability_not_recommended'];
        }

        if (($engagement['response_should_be_public'] ?? false) !== true) {
            return ['reason' => 'response_not_public'];
        }

        if ((float) ($respondability['overall_score'] ?? 0) < 0.65) {
            return ['reason' => 'respondability_score_low'];
        }

        if ((string) ($respondability['response_risk'] ?? '') === 'high') {
            return ['reason' => 'response_risk_high'];
        }

        if (in_array((string) ($moderation['severity'] ?? ''), ['high', 'critical'], true)) {
            return ['reason' => 'moderation_severity_high'];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public function agentReplyStatusResponse(string $generationStatus, string $postId, array $extra = []): array
    {
        return array_merge([
            'status' => 'ok',
            'post_id' => $postId,
            'generation_status' => $generationStatus,
        ], $extra);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function failedAgentReplyResponse(array $row): array
    {
        return [
            'status' => 'ok',
            'post_id' => (string) ($row['target_post_id'] ?? ''),
            'generation_status' => 'failed',
            'failure_code' => $row['failure_code'] ?? null,
            'failure_message' => $row['failure_message'] ?? null,
            'retry_after' => $row['retry_after'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $replyResult
     * @return array<string, mixed>
     */
    public function agentReplySummaryForAnalysisResponse(array $replyResult): array
    {
        $generationStatus = (string) ($replyResult['generation_status'] ?? 'failed');
        $agentPostId = isset($replyResult['agent_post_id']) ? (string) $replyResult['agent_post_id'] : null;
        $agentPostUrl = isset($replyResult['agent_post_url']) ? (string) $replyResult['agent_post_url'] : null;
        $reason = isset($replyResult['reason']) ? (string) $replyResult['reason'] : null;
        $failureCode = isset($replyResult['failure_code']) ? (string) $replyResult['failure_code'] : null;

        $summary = [
            'agent_reply_generation_status' => $generationStatus,
            'agent_reply_posted' => false,
            'agent_reply_post_id' => null,
            'agent_reply_post_url' => null,
            'agent_reply_reason' => null,
            'agent_reply_failure_code' => null,
        ];

        if ($generationStatus === 'generated') {
            $posted = ($replyResult['posted'] ?? false) === true;
            $summary['agent_reply_posted'] = $posted;
            $summary['agent_reply_post_id'] = $posted ? $agentPostId : null;
            $summary['agent_reply_post_url'] = $posted ? $agentPostUrl : null;
            $summary['agent_reply_reason'] = $reason;
            $summary['agent_reply_failure_code'] = $failureCode;

            return $summary;
        }

        if ($generationStatus === 'already_posted') {
            $summary['agent_reply_posted'] = true;
            $summary['agent_reply_post_id'] = $agentPostId;
            $summary['agent_reply_post_url'] = $agentPostUrl;

            return $summary;
        }

        if ($generationStatus === 'not_recommended') {
            $summary['agent_reply_reason'] = $reason;

            return $summary;
        }

        if ($generationStatus === 'analysis_required') {
            $summary['agent_reply_reason'] = $reason;

            return $summary;
        }

        if ($generationStatus === 'failed') {
            $summary['agent_reply_post_id'] = $agentPostId;
            $summary['agent_reply_post_url'] = $agentPostUrl;
            $summary['agent_reply_failure_code'] = $failureCode;

            return $summary;
        }

        if ($generationStatus === 'in_progress') {
            return $summary;
        }

        $summary['agent_reply_generation_status'] = 'failed';
        $summary['agent_reply_failure_code'] = 'unexpected_agent_reply_status';

        return $summary;
    }

    /**
     * @param array<int, array<string, mixed>> $posts
     * @return array<string, array<string, mixed>>
     */
    public function fetchPostAnalysesForPosts(array $posts): array
    {
        if ($posts === []) {
            return [];
        }

        try {
            $store = new SqlitePostAnalysisStore($this->pdo());
            $unicodeRiskStore = new SqliteUnicodeRiskStore($this->pdo());
        } catch (\Throwable) {
            return [];
        }

        $analyses = [];
        foreach ($posts as $post) {
            $context = $this->postAnalysisContext($post, false);
            $analysis = $store->find((string) $context['post_id'], (string) $context['content_hash']);
            if ($analysis === null) {
                continue;
            }
            $unicodeRisk = $unicodeRiskStore->find((string) $context['post_id'], (string) $context['content_hash']);
            if ($unicodeRisk !== null) {
                $analysis['unicode_risk'] = $unicodeRisk;
            }

            $analyses[(string) $context['post_id']] = $analysis;
        }

        return $analyses;
    }

    /**
     * @param array<int, array<string, mixed>> $posts
     * @return array<string, array<string, mixed>>
     */
    public function fetchAgentReplyGenerationsForPosts(array $posts): array
    {
        if ($posts === []) {
            return [];
        }

        try {
            $store = new SqliteAgentReplyGenerationStore($this->pdo());
        } catch (\Throwable) {
            return [];
        }

        $generations = [];
        foreach ($posts as $post) {
            $context = $this->postAnalysisContext($post, false);
            $generation = $store->findByTarget((string) $context['post_id'], (string) $context['content_hash']);
            if ($generation === null) {
                continue;
            }

            $generations[(string) $context['post_id']] = $generation;
        }

        return $generations;
    }

    /**
     * @param array<int, array<string, mixed>> $posts
     * @return array<string, array<string, mixed>>
     */
    public function fetchCodexHandoffsForPosts(array $posts): array
    {
        if ($posts === []) {
            return [];
        }

        try {
            $store = $this->codexHandoffStore();
        } catch (\Throwable) {
            return [];
        }

        $handoffs = [];
        foreach ($posts as $post) {
            $handoff = $store->findByPost($post);
            if ($handoff === null) {
                continue;
            }

            $handoffs[(string) $post['post_id']] = $handoff;
        }

        return $handoffs;
    }

    /**
     * @param array<int, array<string, mixed>> $posts
     * @param array<string, array<string, mixed>> $analysesByPostId
     * @param array<string, array<string, mixed>> $agentRepliesByPostId
     * @return array<string, string>
     */
    public function agentReplyWorkByPostId(
        array $posts,
        string $createdPostId,
        array $analysesByPostId,
        array $agentRepliesByPostId
    ): array {
        if ($createdPostId === '' || !$this->agentRepliesAutomaticEnabled()) {
            return [];
        }

        foreach ($posts as $post) {
            if ((string) ($post['post_id'] ?? '') !== $createdPostId) {
                continue;
            }

            $work = $this->agentReplyWorkForPost(
                $post,
                $analysesByPostId[$createdPostId] ?? null,
                $agentRepliesByPostId[$createdPostId] ?? null
            );

            return $work === 'none' ? [] : [$createdPostId => $work];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed>|null $analysis
     * @param array<string, mixed>|null $agentReply
     */
    private function agentReplyWorkForPost(array $post, ?array $analysis, ?array $agentReply): string
    {
        if ((string) ($post['author_label'] ?? '') === AgentIdentityService::USERNAME) {
            return 'none';
        }

        if ($agentReply !== null) {
            if ($agentReply['agent_post_id'] !== null) {
                return 'none';
            }

            if ((string) ($agentReply['status'] ?? '') !== 'complete') {
                return 'none';
            }
        }

        if ($analysis === null) {
            return 'analyze';
        }

        if (($analysis['status'] ?? null) !== 'complete') {
            return 'none';
        }

        return $this->agentReplyGateFailure($post, $analysis) === null ? 'publish' : 'none';
    }

    /**
     * @param array<string, mixed>|null $viewerProfile
     */
    public function viewerCanUseCodexHandoff(?array $viewerProfile): bool
    {
        return $viewerProfile !== null
            && ((int) ($viewerProfile['is_approved'] ?? 0)) === 1
            && $this->requestIsLocalhost();
    }

    /**
     * @param array<int, array<string, mixed>> $posts
     * @param array<string, mixed>|null $thread
     * @return array<string, bool>
     */
    public function codexHandoffEligiblePostIds(array $posts, ?array $thread = null): array
    {
        $eligible = [];
        foreach ($posts as $post) {
            if ($this->postCanUseCodexHandoffTarget($post, $thread)) {
                $eligible[(string) ($post['post_id'] ?? '')] = true;
            }
        }

        return $eligible;
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed>|null $thread
     */
    public function postCanUseCodexHandoffTarget(array $post, ?array $thread = null): bool
    {
        if ((string) ($post['author_label'] ?? '') === AgentIdentityService::USERNAME) {
            return false;
        }

        if ($this->hasCodexHandoffTag(ThreadRowSupport::decodeStringList((string) ($post['board_tags_json'] ?? '[]')))) {
            return true;
        }

        if ((string) ($post['post_id'] ?? '') !== (string) ($post['thread_id'] ?? '')) {
            return false;
        }

        $threadLabels = [];
        if ($thread !== null) {
            $threadLabels = is_array($thread['thread_labels'] ?? null)
                ? array_map('strval', $thread['thread_labels'])
                : ThreadRowSupport::decodeStringList((string) ($thread['thread_labels_json'] ?? '[]'));
        }

        return $this->hasCodexHandoffTag($threadLabels);
    }

    /**
     * @param list<string> $tags
     */
    private function hasCodexHandoffTag(array $tags): bool
    {
        foreach ($tags as $tag) {
            if (in_array(strtolower($tag), self::CODEX_HANDOFF_DEVELOPMENT_TAGS, true)) {
                return true;
            }
        }

        return false;
    }

    private function requestIsLocalhost(): bool
    {
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
        if ($host !== '') {
            $host = strtolower($host);
            if (str_starts_with($host, '[')) {
                $closingBracket = strpos($host, ']');
                $host = $closingBracket === false ? $host : substr($host, 1, $closingBracket - 1);
            } elseif (str_contains($host, ':')) {
                $host = explode(':', $host, 2)[0];
            }

            return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
                || str_ends_with($host, '.localhost');
        }

        $remoteAddress = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($remoteAddress === '') {
            return PHP_SAPI === 'cli';
        }

        return in_array($remoteAddress, ['127.0.0.1', '::1'], true);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|null
     */
    public function findCodexHandoffFromInput(array $input): ?array
    {
        $store = $this->codexHandoffStore();
        $handoffId = trim((string) ($input['handoff_id'] ?? ''));
        if ($handoffId !== '') {
            return $store->findByHandoffId($handoffId);
        }

        $postId = trim((string) ($input['post_id'] ?? ''));
        if ($postId === '') {
            return null;
        }

        $post = ($this->fetchPost)($postId);
        if ($post === null) {
            return null;
        }

        return $store->findByPost($post);
    }

    /**
     * @param array<string, mixed> $handoff
     * @return array<string, mixed>
     */
    public function codexHandoffResponse(array $handoff): array
    {
        return [
            'status' => 'ok',
            'handoff_id' => (string) ($handoff['handoff_id'] ?? ''),
            'handoff_status' => (string) ($handoff['status'] ?? ''),
            'origin_post_id' => (string) ($handoff['origin_post_id'] ?? ''),
            'origin_thread_id' => (string) ($handoff['origin_thread_id'] ?? ''),
            'user_story' => $handoff['user_story'] ?? null,
            'fdp_step1' => $handoff['fdp_step1'] ?? null,
            'confidence_summary' => $handoff['confidence_summary'] ?? null,
            'draft_text' => $handoff['draft_text'] ?? null,
            'requested_at' => $handoff['requested_at'] ?? null,
            'draft_ready_at' => $handoff['draft_ready_at'] ?? null,
            'approved_at' => $handoff['approved_at'] ?? null,
            'rejected_at' => $handoff['rejected_at'] ?? null,
            'running_at' => $handoff['running_at'] ?? null,
            'completed_at' => $handoff['completed_at'] ?? null,
            'failed_at' => $handoff['failed_at'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $viewerProfile
     * @return array<string, mixed>
     */
    public function agentReplyRequestResultForPost(array $post, array $viewerProfile): array
    {
        $postId = (string) ($post['post_id'] ?? '');
        if ((string) ($post['author_label'] ?? '') === AgentIdentityService::USERNAME) {
            return $this->agentReplyStatusResponse('not_recommended', $postId, [
                'reason' => 'agent_loop_prevention',
            ]);
        }

        $context = $this->postAnalysisContext($post, false);
        $store = new SqliteAgentReplyGenerationStore($this->pdo());
        $row = $store->requestForTarget($context, [
            'requested_by_identity_id' => (string) ($viewerProfile['identity_id'] ?? ''),
            'requested_by_profile_slug' => (string) ($viewerProfile['profile_slug'] ?? ''),
            'requested_by_username' => (string) ($viewerProfile['username'] ?? ''),
        ]);

        return $this->agentReplyResponseForStoredRequest($row, $postId);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function agentReplyResponseForStoredRequest(array $row, string $postId): array
    {
        if ($row['agent_post_id'] !== null) {
            return $this->agentReplyStatusResponse('already_posted', $postId, [
                'agent_post_id' => $row['agent_post_id'],
                'agent_post_url' => '/posts/' . $row['agent_post_id'],
            ]);
        }

        $status = (string) ($row['status'] ?? '');
        if ($status === 'requested') {
            return $this->agentReplyStatusResponse('requested', $postId);
        }

        if (in_array($status, ['pending', 'complete', 'posting'], true)) {
            return $this->agentReplyStatusResponse('in_progress', $postId);
        }

        if ($status === 'skipped') {
            return $this->agentReplyStatusResponse('not_recommended', $postId, [
                'reason' => (string) ($row['failure_code'] ?? 'not_recommended'),
            ]);
        }

        if ($status === 'failed') {
            return $this->failedAgentReplyResponse($row);
        }

        return $this->agentReplyStatusResponse('in_progress', $postId);
    }

    /**
     * @param array<string, mixed> $requestRow
     * @return array<string, mixed>
     */
    public function fulfillAgentReplyRequest(array $requestRow): array
    {
        return $this->agentReplyFulfillmentService()->fulfillRequest($requestRow);
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public function agentReplyResultForPost(array $post): array
    {
        $postId = (string) ($post['post_id'] ?? '');

        if (!$this->agentRepliesEnabled()) {
            return $this->agentReplyStatusResponse('not_recommended', $postId, [
                'reason' => 'config_disabled',
            ]);
        }

        return $this->agentReplyFulfillmentService()->publishForPost($post);
    }

    public function codexHandoffStore(): CodexHandoffStore
    {
        return new CodexHandoffStore($this->pdo());
    }

    public function codexHandoffDraftService(): CodexHandoffDraftService
    {
        return new CodexHandoffDraftService();
    }

    private function agentIdentityService(): AgentIdentityService
    {
        return new AgentIdentityService(
            $this->repositoryRoot,
            $this->databasePath,
            $this->artifactRoot ?? ($this->projectRoot . '/public'),
            $this->projectRoot . '/state/private/agent-reply',
            new CanonicalRecordRepository($this->repositoryRoot),
            staticHtmlRoot: $this->staticHtmlRoot,
        );
    }

    private function agentReplyFulfillmentService(): AgentReplyFulfillmentService
    {
        return new AgentReplyFulfillmentService(
            new SqliteAgentReplyGenerationStore($this->pdo()),
            new SqlitePostAnalysisStore($this->pdo()),
            $this->postAnalysisService(),
            $this->agentIdentityService(),
            ($this->writerFactory)(),
            $this->featureFlags,
            $this->fetchPost,
            fn (array $post): array => $this->postAnalysisContext($post),
            fn (array $post, array $analysis): ?array => $this->agentReplyGateFailure($post, $analysis),
        );
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function analyze(array $context): array
    {
        return $this->postAnalysisService()->analyze($context);
    }

    private function postAnalysisService(): PostAnalysisService
    {
        $config = PrivateConfig::load($this->projectRoot);
        $analyzer = PostAnalyzerFactory::fromPrivateConfig($config, $this->projectRoot, ($this->llmExchangeRecorderFactory)());

        return new PostAnalysisService(
            new SqlitePostAnalysisStore($this->pdo()),
            $analyzer,
            new UnicodeRiskInspector(),
            new SqliteUnicodeRiskStore($this->pdo())
        );
    }

    public function agentRepliesEnabled(): bool
    {
        $config = PrivateConfig::load($this->projectRoot);

        return $this->configFlagEnabled($config, 'DEDALUS_AGENT_REPLIES_ENABLED', true);
    }

    private function agentRepliesAutomaticEnabled(): bool
    {
        if (!$this->agentRepliesEnabled()) {
            return false;
        }

        $config = PrivateConfig::load($this->projectRoot);

        return $this->configFlagEnabled($config, 'DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED', true);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configFlagEnabled(array $config, string $key, bool $default): bool
    {
        if (!array_key_exists($key, $config)) {
            return $default;
        }

        $value = $config[$key];
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value !== 0.0;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return $default;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        return $default;
    }
}
