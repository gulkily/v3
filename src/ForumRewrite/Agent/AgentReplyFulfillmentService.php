<?php

declare(strict_types=1);

namespace ForumRewrite\Agent;

use ForumRewrite\Analysis\PostAnalysisStore;
use ForumRewrite\Analysis\PostAnalysisService;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Write\LocalWriteService;
use RuntimeException;

final class AgentReplyFulfillmentService
{
    /**
     * @param callable(string): array<string, mixed>|null $postForId
     * @param callable(array<string, mixed>): array<string, mixed> $contextForPost
     * @param callable(array<string, mixed>, array<string, mixed>): array<string, mixed>|null $gateFailureForPost
     */
    public function __construct(
        private readonly AgentReplyGenerationStore $generationStore,
        private readonly PostAnalysisStore $analysisStore,
        private readonly PostAnalysisService $analysisService,
        private readonly AgentIdentityService $identityService,
        private readonly LocalWriteService $writer,
        private readonly FeatureFlagEvaluator $featureFlags,
        private readonly mixed $postForId,
        private readonly mixed $contextForPost,
        private readonly mixed $gateFailureForPost,
    ) {
    }

    /**
     * @param array<string, mixed> $requestRow
     * @return array<string, mixed>
     */
    public function fulfillRequest(array $requestRow): array
    {
        $postId = (string) ($requestRow['target_post_id'] ?? '');
        $contentHash = (string) ($requestRow['target_content_hash'] ?? '');
        $post = ($this->postForId)($postId);
        if ($post === null) {
            return $this->markSkippedResponse($postId, $contentHash, 'target_post_missing');
        }

        if ((string) ($post['author_label'] ?? '') === AgentIdentityService::USERNAME) {
            return $this->markSkippedResponse($postId, $contentHash, 'agent_loop_prevention');
        }

        $context = ($this->contextForPost)($post);
        if ((string) ($context['content_hash'] ?? '') !== $contentHash) {
            return $this->markSkippedResponse($postId, $contentHash, 'target_content_changed');
        }

        $analysis = $this->analysisStore->find($postId, $contentHash);
        if ($analysis === null || ($analysis['status'] ?? null) !== 'complete') {
            $analysis = $this->analysisService->analyze($context);
        }

        if (($analysis['status'] ?? null) !== 'complete') {
            return $this->markSkippedResponse($postId, $contentHash, [
                'reason' => array_key_exists('status', $analysis) ? 'analysis_not_complete' : 'missing_analysis',
                'analysis_status' => $analysis['status'] ?? null,
                'failure_code' => $analysis['failure_code'] ?? null,
            ]);
        }

        $gateFailure = ($this->gateFailureForPost)($post, $analysis);
        if ($gateFailure !== null) {
            return $this->markSkippedResponse($postId, $contentHash, $gateFailure);
        }

        return $this->publishForPost($post);
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public function publishForPost(array $post): array
    {
        $postId = (string) ($post['post_id'] ?? '');
        $context = ($this->contextForPost)($post);
        $existing = $this->generationStore->findByTarget((string) $context['post_id'], (string) $context['content_hash']);
        $existingIsClaimedRequest = $existing !== null
            && $existing['status'] === 'pending'
            && is_array($existing['request_context'] ?? null)
            && is_array($existing['request_context']['agent_reply_request'] ?? null);
        if ($existing !== null && $existing['agent_post_id'] !== null) {
            return $this->statusResponse('already_posted', $postId, [
                'agent_post_id' => $existing['agent_post_id'],
                'agent_post_url' => '/posts/' . $existing['agent_post_id'],
            ]);
        }
        if ($existing !== null && $existing['status'] === 'failed') {
            return $this->failedResponse($existing);
        }
        if ($existing !== null && in_array($existing['status'], ['pending', 'posting'], true) && !$existingIsClaimedRequest) {
            return $this->statusResponse('in_progress', $postId);
        }

        $analysis = $this->analysisStore->find(
            (string) $context['post_id'],
            (string) $context['content_hash']
        );
        if ($analysis === null || ($analysis['status'] ?? null) !== 'complete') {
            return $this->statusResponse('analysis_required', $postId, [
                'reason' => $analysis === null ? 'missing_analysis' : 'analysis_not_complete',
                'analysis_status' => $analysis['status'] ?? null,
                'failure_code' => $analysis['failure_code'] ?? null,
            ]);
        }

        $gateFailure = ($this->gateFailureForPost)($post, $analysis);
        if ($gateFailure !== null) {
            return $this->statusResponse('not_recommended', $postId, $gateFailure);
        }

        $generationContext = $context;
        $generationContext['analysis'] = [
            'moderation' => $analysis['moderation'] ?? [],
            'engagement' => $analysis['engagement'] ?? [],
            'quality' => $analysis['quality'] ?? [],
            'respondability' => $analysis['respondability'] ?? [],
        ];
        $generationContext['analysis_hash'] = $this->analysisHash($analysis);

        $stored = $existing !== null && $existing['status'] === 'complete' ? $existing : null;
        if ($stored === null && !$existingIsClaimedRequest) {
            $reservation = $this->generationStore->reserveGeneration($generationContext);
            if (($reservation['reserved'] ?? false) !== true) {
                if ($reservation['agent_post_id'] !== null) {
                    return $this->statusResponse('already_posted', $postId, [
                        'agent_post_id' => $reservation['agent_post_id'],
                        'agent_post_url' => '/posts/' . $reservation['agent_post_id'],
                    ]);
                }
                if ($reservation['status'] === 'complete') {
                    $stored = $reservation;
                } elseif ($reservation['status'] === 'failed') {
                    return $this->failedResponse($reservation);
                } else {
                    return $this->statusResponse('in_progress', $postId);
                }
            }
        }

        if ($stored === null) {
            try {
                $generation = $this->generationFromAnalysis($analysis);
                $stored = $this->generationStore->saveComplete($generationContext, $generation);
            } catch (\Throwable $throwable) {
                $failed = $this->generationStore->saveFailed(
                    (string) $context['post_id'],
                    (string) $context['content_hash'],
                    (string) $generationContext['analysis_hash'],
                    'analysis_suggestion_error',
                    $throwable->getMessage()
                );
                return $this->failedResponse($failed);
            }
        }

        $latest = $this->generationStore->findByTarget((string) $context['post_id'], (string) $context['content_hash']);
        if ($latest !== null && $latest['agent_post_id'] !== null) {
            return $this->statusResponse('already_posted', $postId, [
                'agent_post_id' => $latest['agent_post_id'],
                'agent_post_url' => '/posts/' . $latest['agent_post_id'],
            ]);
        }
        if ($latest !== null && $latest['status'] === 'failed') {
            return $this->failedResponse($latest);
        }

        $posting = $this->generationStore->reservePosting((string) $context['post_id'], (string) $context['content_hash']);
        if ($posting === null || ($posting['reserved'] ?? false) !== true) {
            if ($posting !== null && $posting['agent_post_id'] !== null) {
                return $this->statusResponse('already_posted', $postId, [
                    'agent_post_id' => $posting['agent_post_id'],
                    'agent_post_url' => '/posts/' . $posting['agent_post_id'],
                ]);
            }
            if ($posting !== null && $posting['status'] === 'failed') {
                return $this->failedResponse($posting);
            }

            return $this->statusResponse('in_progress', $postId);
        }
        $stored = $posting;

        try {
            $identity = $this->identityService->ensureReplyAgentIdentity();
            $writeResult = $this->writer->createReply([
                'thread_id' => (string) $post['thread_id'],
                'parent_id' => (string) $post['post_id'],
                'board_tags' => 'general',
                'body' => (string) ($stored['response_text'] ?? ''),
                'author_identity_id' => (string) $identity['identity_id'],
            ]);
            $posted = $this->generationStore->markPosted(
                (string) $context['post_id'],
                (string) $context['content_hash'],
                (string) $writeResult['post_id'],
                (string) $identity['identity_id'],
                (string) $identity['profile_slug']
            );

            return $this->generatedResponse($posted, $existing !== null);
        } catch (\Throwable $throwable) {
            $failed = $this->generationStore->saveFailed(
                (string) $context['post_id'],
                (string) $context['content_hash'],
                (string) $generationContext['analysis_hash'],
                'posting_error',
                $throwable->getMessage()
            );
            return $this->failedResponse($failed);
        }
    }

    /**
     * @param array<string, mixed> $analysis
     * @return array<string, mixed>
     */
    private function generationFromAnalysis(array $analysis): array
    {
        $engagement = is_array($analysis['engagement'] ?? null) ? $analysis['engagement'] : [];
        $respondability = is_array($analysis['respondability'] ?? null) ? $analysis['respondability'] : [];
        $text = DedalusAgentReplyGenerator::normalizeGeneratedReplyText(
            (string) ($engagement['suggested_response'] ?? ''),
            $this->featureFlags->isEnabled(FeatureFlagRegistry::UNICODE_AUTHORED_TEXT),
            $this->featureFlags->isEnabled(FeatureFlagRegistry::EMOJI_AUTHORED_TEXT),
        );
        if ($text === '') {
            throw new RuntimeException('Completed analysis did not include a suggested_response.');
        }

        return [
            'provider' => (string) ($analysis['provider'] ?? 'analysis'),
            'provider_model' => (string) ($analysis['provider_model'] ?? 'analysis'),
            'provider_request_id' => isset($analysis['provider_request_id']) ? (string) $analysis['provider_request_id'] : null,
            'response_text' => $text,
            'response_style' => (string) ($engagement['response_style'] ?? 'curious'),
            'response_intent' => (string) ($respondability['best_response_mode'] ?? 'answer'),
            'raw_response' => [
                'source' => 'analysis_suggested_response',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $analysis
     */
    private function analysisHash(array $analysis): string
    {
        return hash('sha256', json_encode([
            'status' => $analysis['status'] ?? null,
            'moderation' => $analysis['moderation'] ?? [],
            'engagement' => $analysis['engagement'] ?? [],
            'quality' => $analysis['quality'] ?? [],
            'respondability' => $analysis['respondability'] ?? [],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function statusResponse(string $generationStatus, string $postId, array $extra = []): array
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
    private function generatedResponse(array $row, bool $cached): array
    {
        return [
            'status' => 'ok',
            'post_id' => (string) ($row['target_post_id'] ?? ''),
            'generation_status' => 'generated',
            'cached' => $cached,
            'provider' => $row['provider'] ?? null,
            'provider_model' => $row['provider_model'] ?? null,
            'response_text' => $row['response_text'] ?? null,
            'response_style' => $row['response_style'] ?? null,
            'response_intent' => $row['response_intent'] ?? null,
            'agent_post_id' => $row['agent_post_id'] ?? null,
            'agent_post_url' => isset($row['agent_post_id']) ? '/posts/' . $row['agent_post_id'] : null,
            'posted' => isset($row['agent_post_id']),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function failedResponse(array $row): array
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
     * @param string|array<string, mixed> $reason
     * @return array<string, mixed>
     */
    private function markSkippedResponse(string $postId, string $contentHash, string|array $reason): array
    {
        $details = is_array($reason) ? $reason : ['reason' => $reason];
        $stored = $this->generationStore->markSkipped(
            $postId,
            $contentHash,
            (string) ($details['reason'] ?? 'not_recommended')
        );

        return $this->statusResponse('not_recommended', $postId, array_merge([
            'reason' => (string) ($stored['failure_code'] ?? 'not_recommended'),
        ], $details));
    }
}
