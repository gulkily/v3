<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use RuntimeException;

/**
 * Twenty-seventh Phase 2 slice (see docs/plans/codebase_cleanup_audit_plan_v1.md):
 * the post-analysis/agent-reply/Codex-handoff API group -
 * /api/analyze_post, /api/generate_agent_reply, /api/codex_handoff,
 * /api/codex_handoff_approval. A dedicated dependency-graph investigation
 * (same treatment the activity subsystem got) found this cluster has the
 * same shape: a large pure data/orchestration layer (now
 * ForumRewrite\Agent\PostWorkflowService) plus a small config-touching
 * core whose every real dependency was already on RouteServices.
 *
 * The single-thread view (renderThread()/renderPost(), for /threads/{id}
 * and /posts/{id}) shares this same service but stays on Application for
 * now - confirmed genuinely entangled with several other subsystems
 * (LLM-exchange inspection, viewer tag lookups) beyond just this one,
 * a separate slice.
 */
final class PostWorkflowApiController
{
    /**
     * @param \Closure(string): (array<string, mixed>|null) $fetchPost
     * @param \Closure(string): array<int, array<string, mixed>> $fetchThreadPosts
     * @param \Closure(): (\ForumRewrite\Llm\LlmExchangeRecorder|null) $llmExchangeRecorderFactory
     * @param \Closure(): (array<string, mixed>|null) $resolveViewerProfileFromIdentityHint
     * @param \Closure(string): (array<string, mixed>|null) $fetchThread
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly \Closure $fetchPost,
        private readonly \Closure $fetchThreadPosts,
        private readonly \Closure $llmExchangeRecorderFactory,
        private readonly \Closure $resolveViewerProfileFromIdentityHint,
        private readonly \Closure $fetchThread,
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
     * @param array<string, mixed> $query
     */
    public function analyzePost(string $method, array $query): void
    {
        $service = $this->postWorkflowService();
        $totalStartedAt = hrtime(true);
        $timings = [];
        $headersWithTimings = function () use (&$timings, $totalStartedAt): array {
            $timings['total'] = $this->routeServices->elapsedMilliseconds($totalStartedAt);
            return array_merge($this->routeServices->noStoreHeaders(), $this->routeServices->serverTimingHeaders(['timings' => $timings]));
        };

        if ($method !== 'POST') {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405, $headersWithTimings());
            return;
        }

        $phaseStartedAt = hrtime(true);
        $input = $this->routeServices->requestData($query);
        $timings['request_data'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);
        $postId = trim((string) ($input['post_id'] ?? ''));
        if ($postId === '') {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'Missing post_id.'], 400, $headersWithTimings());
            return;
        }

        $phaseStartedAt = hrtime(true);
        $post = ($this->fetchPost)($postId);
        $timings['fetch_post'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);
        if ($post === null) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'post not found'], 404, $headersWithTimings());
            return;
        }

        $phaseStartedAt = hrtime(true);
        $context = $service->postAnalysisContext($post);
        $timings['analysis_context'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);

        $phaseStartedAt = hrtime(true);
        $result = $service->analyze($context);
        $timings['post_analysis'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);
        foreach ($this->timingMetricsFrom($result['timings'] ?? null) as $name => $duration) {
            $timings[$name] = $duration;
        }

        $phaseStartedAt = hrtime(true);
        $viewerProfile = ($this->resolveViewerProfileFromIdentityHint)();
        $viewerCanSeePostAnalysis = $viewerProfile !== null && ((int) ($viewerProfile['is_approved'] ?? 0)) === 1;
        $timings['viewer_profile'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);

        $phaseStartedAt = hrtime(true);
        $response = $service->postAnalysisResponse($result, $viewerCanSeePostAnalysis);
        $agentReplyEnabled = $service->agentRepliesEnabled();
        $analysisComplete = ($result['status'] ?? null) === 'complete';
        $gateFailure = $analysisComplete ? $service->agentReplyGateFailure($post, $result) : null;
        $agentReplyAllowed = $agentReplyEnabled && $analysisComplete && $gateFailure === null;
        $timings['analysis_response'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);

        $phaseStartedAt = hrtime(true);
        if (!$agentReplyEnabled) {
            $agentReplyResult = $service->agentReplyStatusResponse('not_recommended', $postId, [
                'reason' => 'config_disabled',
            ]);
        } elseif (!$analysisComplete) {
            $agentReplyResult = $service->agentReplyStatusResponse('analysis_required', $postId, [
                'reason' => array_key_exists('status', $result) ? 'analysis_not_complete' : 'missing_analysis',
            ]);
        } elseif ($gateFailure !== null) {
            $agentReplyResult = $service->agentReplyStatusResponse('not_recommended', $postId, [
                'reason' => $viewerCanSeePostAnalysis ? ($gateFailure['reason'] ?? 'not_recommended') : 'not_recommended',
            ]);
        } else {
            $agentReplyResult = $service->agentReplyResultForPost($post);
        }
        $timings['agent_reply'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);

        $phaseStartedAt = hrtime(true);
        $response['agent_reply_generation_allowed'] = $agentReplyAllowed;
        $response = array_merge($response, $service->agentReplySummaryForAnalysisResponse($agentReplyResult));
        $timings['response_summary'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);

        $this->routeServices->sendJson($response, 200, $headersWithTimings());
    }

    /**
     * @param array<string, mixed> $query
     */
    public function generateAgentReply(string $method, array $query): void
    {
        $service = $this->postWorkflowService();
        $totalStartedAt = hrtime(true);
        $timings = [];
        $headersWithTimings = function () use (&$timings, $totalStartedAt): array {
            $withTotal = $timings;
            $withTotal['total'] = $this->routeServices->elapsedMilliseconds($totalStartedAt);
            return array_merge($this->routeServices->noStoreHeaders(), $this->routeServices->serverTimingHeaders(['timings' => $withTotal]));
        };

        if ($method !== 'POST') {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405, $headersWithTimings());
            return;
        }

        $phaseStartedAt = hrtime(true);
        $input = $this->routeServices->requestData($query);
        $timings['request_data'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);
        $postId = trim((string) ($input['post_id'] ?? ''));
        if ($postId === '') {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'Missing post_id.'], 400, $headersWithTimings());
            return;
        }

        $phaseStartedAt = hrtime(true);
        $post = ($this->fetchPost)($postId);
        $timings['fetch_post'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);
        if ($post === null) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'post not found'], 404, $headersWithTimings());
            return;
        }

        $phaseStartedAt = hrtime(true);
        $viewerProfile = ($this->resolveViewerProfileFromIdentityHint)();
        $timings['viewer_profile'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);
        if ($viewerProfile === null || ((int) ($viewerProfile['is_approved'] ?? 0)) !== 1) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'forbidden'], 403, $headersWithTimings());
            return;
        }

        $phaseStartedAt = hrtime(true);
        $response = $service->agentReplyRequestResultForPost($post, $viewerProfile);
        $timings['agent_reply'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);
        foreach ($this->timingMetricsFrom($response['timings'] ?? null) as $name => $duration) {
            $timings[$name] = $duration;
        }

        $this->routeServices->sendJson($response, 200, $headersWithTimings());
    }

    /**
     * @param array<string, mixed> $query
     */
    public function codexHandoff(string $method, array $query): void
    {
        $service = $this->postWorkflowService();
        $totalStartedAt = hrtime(true);
        $timings = [];
        $headersWithTimings = function () use (&$timings, $totalStartedAt): array {
            $withTotal = $timings;
            $withTotal['total'] = $this->routeServices->elapsedMilliseconds($totalStartedAt);
            return array_merge($this->routeServices->noStoreHeaders(), $this->routeServices->serverTimingHeaders(['timings' => $withTotal]));
        };

        if (!in_array($method, ['GET', 'POST'], true)) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405, $headersWithTimings());
            return;
        }

        $phaseStartedAt = hrtime(true);
        $viewerProfile = ($this->resolveViewerProfileFromIdentityHint)();
        $timings['viewer_profile'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);
        if (!$service->viewerCanUseCodexHandoff($viewerProfile)) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'forbidden'], 403, $headersWithTimings());
            return;
        }

        $phaseStartedAt = hrtime(true);
        $input = $this->routeServices->requestData($query);
        $timings['request_data'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);

        try {
            if ($method === 'GET') {
                $handoff = $service->findCodexHandoffFromInput($input);
                if ($handoff === null) {
                    $this->routeServices->sendJson(['status' => 'error', 'error' => 'handoff not found'], 404, $headersWithTimings());
                    return;
                }

                $this->routeServices->sendJson($service->codexHandoffResponse($handoff), 200, $headersWithTimings());
                return;
            }

            $postId = trim((string) ($input['post_id'] ?? ''));
            if ($postId === '') {
                $this->routeServices->sendJson(['status' => 'error', 'error' => 'Missing post_id.'], 400, $headersWithTimings());
                return;
            }

            $phaseStartedAt = hrtime(true);
            $post = ($this->fetchPost)($postId);
            $timings['fetch_post'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);
            if ($post === null) {
                $this->routeServices->sendJson(['status' => 'error', 'error' => 'post not found'], 404, $headersWithTimings());
                return;
            }

            if ((string) ($post['author_label'] ?? '') === \ForumRewrite\Agent\AgentIdentityService::USERNAME) {
                $this->routeServices->sendJson(['status' => 'error', 'error' => 'codex handoff target is not eligible'], 400, $headersWithTimings());
                return;
            }
            $thread = ($this->fetchThread)((string) ($post['thread_id'] ?? ''));
            if (!$service->postCanUseCodexHandoffTarget($post, $thread)) {
                $this->routeServices->sendJson(['status' => 'error', 'error' => 'codex handoff target requires a development tag or thread label'], 400, $headersWithTimings());
                return;
            }

            $phaseStartedAt = hrtime(true);
            $store = $service->codexHandoffStore();
            $handoff = $store->requestForPost($post, $viewerProfile ?? []);
            if ((string) ($handoff['status'] ?? '') === 'requested') {
                $draft = $service->codexHandoffDraftService()->prepare($handoff, $post);
                $handoff = $store->updateStatus((string) $handoff['handoff_id'], 'draft_ready', $draft);
            }
            $timings['codex_handoff'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);

            $this->routeServices->sendJson($service->codexHandoffResponse($handoff), 200, $headersWithTimings());
        } catch (RuntimeException $exception) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => $exception->getMessage()], 400, $headersWithTimings());
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    public function codexHandoffApproval(string $method, array $query): void
    {
        $service = $this->postWorkflowService();
        $totalStartedAt = hrtime(true);
        $timings = [];
        $headersWithTimings = function () use (&$timings, $totalStartedAt): array {
            $withTotal = $timings;
            $withTotal['total'] = $this->routeServices->elapsedMilliseconds($totalStartedAt);
            return array_merge($this->routeServices->noStoreHeaders(), $this->routeServices->serverTimingHeaders(['timings' => $withTotal]));
        };

        if ($method !== 'POST') {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405, $headersWithTimings());
            return;
        }

        $phaseStartedAt = hrtime(true);
        $viewerProfile = ($this->resolveViewerProfileFromIdentityHint)();
        $timings['viewer_profile'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);
        if (!$service->viewerCanUseCodexHandoff($viewerProfile)) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'forbidden'], 403, $headersWithTimings());
            return;
        }

        $phaseStartedAt = hrtime(true);
        $input = $this->routeServices->requestData($query);
        $timings['request_data'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);
        $handoffId = trim((string) ($input['handoff_id'] ?? ''));
        $decision = trim((string) ($input['decision'] ?? ''));
        if ($handoffId === '') {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'Missing handoff_id.'], 400, $headersWithTimings());
            return;
        }

        try {
            $store = $service->codexHandoffStore();
            $handoff = $store->findByHandoffId($handoffId);
            if ($handoff === null) {
                $this->routeServices->sendJson(['status' => 'error', 'error' => 'handoff not found'], 404, $headersWithTimings());
                return;
            }

            if ($decision === 'approve') {
                if ((string) ($handoff['status'] ?? '') !== 'draft_ready') {
                    $this->routeServices->sendJson(['status' => 'error', 'error' => 'Codex handoff approval requires draft_ready status.'], 400, $headersWithTimings());
                    return;
                }

                $handoff = $store->updateStatus($handoffId, 'approved', [
                    'approved_by_identity_id' => (string) ($viewerProfile['identity_id'] ?? ''),
                    'approved_by_profile_slug' => (string) ($viewerProfile['profile_slug'] ?? ''),
                    'approved_by_username' => (string) ($viewerProfile['username'] ?? ''),
                ]);
            } elseif ($decision === 'reject') {
                $handoff = $store->updateStatus($handoffId, 'rejected', [
                    'rejected_by_identity_id' => (string) ($viewerProfile['identity_id'] ?? ''),
                    'rejected_by_profile_slug' => (string) ($viewerProfile['profile_slug'] ?? ''),
                    'rejected_by_username' => (string) ($viewerProfile['username'] ?? ''),
                ]);
            } else {
                $this->routeServices->sendJson(['status' => 'error', 'error' => 'decision must be approve or reject'], 400, $headersWithTimings());
                return;
            }

            $this->routeServices->sendJson($service->codexHandoffResponse($handoff), 200, $headersWithTimings());
        } catch (RuntimeException $exception) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => $exception->getMessage()], 400, $headersWithTimings());
        }
    }

    /**
     * @return array<string, float>
     */
    private function timingMetricsFrom(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $timings = [];
        foreach ($value as $name => $duration) {
            if (!is_string($name) || !preg_match('/^[a-z_][a-z0-9_]*$/', $name)) {
                continue;
            }

            if (!is_int($duration) && !is_float($duration)) {
                continue;
            }

            $timings[$name] = (float) $duration;
        }

        return $timings;
    }
}
