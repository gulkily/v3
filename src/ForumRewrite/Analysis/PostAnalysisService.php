<?php

declare(strict_types=1);

namespace ForumRewrite\Analysis;

use RuntimeException;

final class PostAnalysisService
{
    private const MIN_RELATED_RELEVANCE_SCORE = 0.75;
    private const MIN_SOLICITATION_SCORE = 0.35;

    public function __construct(
        private readonly PostAnalysisStore $store,
        private readonly ?PostAnalyzer $analyzer,
        private readonly ?UnicodeRiskInspector $unicodeRiskInspector = null,
        private readonly ?UnicodeRiskStore $unicodeRiskStore = null,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function analyze(array $context): array
    {
        $postId = (string) ($context['post_id'] ?? '');
        $contentHash = (string) ($context['content_hash'] ?? '');
        if ($postId === '' || $contentHash === '') {
            throw new RuntimeException('Post analysis requires post_id and content_hash.');
        }

        $unicodeRisk = $this->unicodeRisk($context, $postId, $contentHash);

        $existing = $this->store->find($postId, $contentHash);
        if ($existing !== null && $existing['status'] === 'complete') {
            $existing['cached'] = true;
            if ($unicodeRisk !== null) {
                $existing['unicode_risk'] = $unicodeRisk;
            }
            return $existing;
        }

        if ($existing !== null && $existing['status'] === 'failed') {
            $retryAfter = strtotime((string) ($existing['retry_after'] ?? ''));
            if ($retryAfter !== false && $retryAfter > time()) {
                $existing['cached'] = true;
                if ($unicodeRisk !== null) {
                    $existing['unicode_risk'] = $unicodeRisk;
                }
                return $existing;
            }
        }

        if ($this->analyzer === null) {
            return [
                'post_id' => $postId,
                'content_hash' => $contentHash,
                'status' => 'config_missing',
                'cached' => false,
                'message' => 'Dedalus API key is not configured.',
                'unicode_risk' => $unicodeRisk,
            ];
        }

        try {
            $analysisContext = $this->contextWithUnicodeRisk($context, $unicodeRisk);
            $analysis = $this->analyzer->analyze($analysisContext);
            $analysis['related_content_assessment'] = $this->relatedContentAssessment($analysis);
            $analysis['related_content'] = $this->approvedRelatedContent($context, $analysis['related_content_assessment']);
            $analysis['unicode_risk_review'] = $this->unicodeRiskReview($analysis);
            if ($unicodeRisk !== null) {
                $unicodeRisk = $this->storeUnicodeRiskReview($postId, $contentHash, $unicodeRisk, $analysis['unicode_risk_review']);
                $analysis['unicode_risk'] = $unicodeRisk;
            }
            $stored = $this->store->saveComplete($postId, $contentHash, $analysis);
            if ($unicodeRisk !== null) {
                $stored['unicode_risk'] = $unicodeRisk;
            }
            $stored['cached'] = false;
            return $stored;
        } catch (\Throwable $throwable) {
            $stored = $this->store->saveFailed($postId, $contentHash, 'provider_error', $throwable->getMessage());
            if ($unicodeRisk !== null) {
                $unicodeRisk = $this->storeUnicodeRiskFailure($postId, $contentHash, $unicodeRisk, $throwable->getMessage());
                $stored['unicode_risk'] = $unicodeRisk;
            }
            $stored['cached'] = false;
            return $stored;
        }
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed>|null $unicodeRisk
     * @return array<string, mixed>
     */
    private function contextWithUnicodeRisk(array $context, ?array $unicodeRisk): array
    {
        if ($unicodeRisk === null || !$this->unicodeRiskHasSignals($unicodeRisk)) {
            return $context;
        }

        $context['unicode_risk_deterministic_facts'] = $unicodeRisk['deterministic_facts'] ?? [];
        return $context;
    }

    /**
     * @param array<string, mixed> $unicodeRisk
     */
    private function unicodeRiskHasSignals(array $unicodeRisk): bool
    {
        $facts = $unicodeRisk['deterministic_facts'] ?? [];
        if (!is_array($facts)) {
            return false;
        }

        foreach (($facts['fields'] ?? []) as $fieldFacts) {
            if (!is_array($fieldFacts)) {
                continue;
            }
            $labels = $fieldFacts['risk_labels'] ?? [];
            if (is_array($labels) && $labels !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $analysis
     * @return array<string, mixed>
     */
    private function unicodeRiskReview(array $analysis): array
    {
        $review = $analysis['unicode_risk_review'] ?? null;
        if (!is_array($review)) {
            return [
                'review_priority' => 'none',
                'summary' => '',
                'concerns' => [],
                'recommended_action' => 'none',
                'confidence' => 0.0,
            ];
        }

        $concerns = $review['concerns'] ?? [];
        if (!is_array($concerns) || !array_is_list($concerns)) {
            $concerns = [];
        }

        return [
            'review_priority' => in_array((string) ($review['review_priority'] ?? ''), ['none', 'low', 'medium', 'high'], true)
                ? (string) $review['review_priority']
                : 'none',
            'summary' => substr((string) ($review['summary'] ?? ''), 0, 500),
            'concerns' => array_values(array_filter(array_map(static fn (mixed $concern): string => substr((string) $concern, 0, 80), $concerns))),
            'recommended_action' => in_array((string) ($review['recommended_action'] ?? ''), ['none', 'watch', 'human_review'], true)
                ? (string) $review['recommended_action']
                : 'none',
            'confidence' => max(0.0, min(1.0, (float) ($review['confidence'] ?? 0.0))),
        ];
    }

    /**
     * @param array<string, mixed> $unicodeRisk
     * @param array<string, mixed> $review
     * @return array<string, mixed>
     */
    private function storeUnicodeRiskReview(string $postId, string $contentHash, array $unicodeRisk, array $review): array
    {
        if ($this->unicodeRiskStore === null || !$this->unicodeRiskHasSignals($unicodeRisk)) {
            return $unicodeRisk;
        }

        return $this->unicodeRiskStore->saveLlmReview($postId, $contentHash, $review);
    }

    /**
     * @param array<string, mixed> $unicodeRisk
     * @return array<string, mixed>
     */
    private function storeUnicodeRiskFailure(string $postId, string $contentHash, array $unicodeRisk, string $failureMessage): array
    {
        if ($this->unicodeRiskStore === null || !$this->unicodeRiskHasSignals($unicodeRisk)) {
            return $unicodeRisk;
        }

        return $this->unicodeRiskStore->saveLlmFailure($postId, $contentHash, $failureMessage);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>|null
     */
    private function unicodeRisk(array $context, string $postId, string $contentHash): ?array
    {
        if ($this->unicodeRiskInspector === null || $this->unicodeRiskStore === null) {
            return null;
        }

        $existing = $this->unicodeRiskStore->find($postId, $contentHash);
        if ($existing !== null && (int) ($existing['schema_version'] ?? 0) === 1) {
            return $existing;
        }

        $facts = $this->unicodeRiskInspector->inspectPost(
            (string) ($context['subject'] ?? ''),
            (string) ($context['body'] ?? '')
        );

        return $this->unicodeRiskStore->saveDeterministic($postId, $contentHash, 1, $facts);
    }

    /**
     * @param array<string, mixed> $analysis
     * @return array<string, mixed>
     */
    private function relatedContentAssessment(array $analysis): array
    {
        $assessment = $analysis['related_content_assessment'] ?? null;
        if (!is_array($assessment)) {
            return $this->emptyRelatedContentAssessment();
        }

        $candidateReviews = $assessment['candidate_reviews'] ?? [];
        if (!is_array($candidateReviews) || !array_is_list($candidateReviews)) {
            $candidateReviews = [];
        }

        return [
            'related_results_appropriate' => (bool) ($assessment['related_results_appropriate'] ?? false),
            'solicitation_score' => $this->boundedScore($assessment['solicitation_score'] ?? 0.0),
            'solicitation_reason' => (string) ($assessment['solicitation_reason'] ?? ''),
            'candidate_reviews' => array_values(array_filter($candidateReviews, static fn (mixed $review): bool => is_array($review))),
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $assessment
     * @return list<array<string, mixed>>
     */
    private function approvedRelatedContent(array $context, array $assessment): array
    {
        $candidates = $context['related_content'] ?? [];
        if (!is_array($candidates) || !array_is_list($candidates) || !(bool) ($assessment['related_results_appropriate'] ?? false)) {
            return [];
        }

        $targetPostId = (string) ($context['post_id'] ?? '');
        $targetThreadId = (string) ($context['thread_id'] ?? '');
        $reviewsByPostId = [];
        foreach ($assessment['candidate_reviews'] ?? [] as $review) {
            if (!is_array($review)) {
                continue;
            }

            $postId = (string) ($review['post_id'] ?? '');
            if ($postId !== '') {
                $reviewsByPostId[$postId] = $review;
            }
        }

        $approved = [];
        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $postId = (string) ($candidate['post_id'] ?? '');
            $threadId = (string) ($candidate['thread_id'] ?? '');
            if ($postId === '' || $postId === $targetPostId || ($targetThreadId !== '' && $threadId === $targetThreadId)) {
                continue;
            }

            $review = $reviewsByPostId[$postId] ?? null;
            if (!is_array($review) || !$this->isApprovedRelatedReview($review, $assessment)) {
                continue;
            }

            $approved[] = $candidate;
            if (count($approved) >= 3) {
                break;
            }
        }

        return $approved;
    }

    /**
     * @param array<string, mixed> $review
     * @param array<string, mixed> $assessment
     */
    private function isApprovedRelatedReview(array $review, array $assessment): bool
    {
        $relationship = (string) ($review['relationship'] ?? 'none');
        if ($relationship === 'none' || !(bool) ($review['appropriate_to_show'] ?? false)) {
            return false;
        }

        if ($this->boundedScore($review['relevance_score'] ?? 0.0) < self::MIN_RELATED_RELEVANCE_SCORE) {
            return false;
        }

        $solicitationScore = $this->boundedScore($assessment['solicitation_score'] ?? 0.0);
        if ($solicitationScore < self::MIN_SOLICITATION_SCORE && !in_array($relationship, ['direct_answer', 'duplicate_request'], true)) {
            return false;
        }

        return true;
    }

    private function boundedScore(mixed $value): float
    {
        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            return 0.0;
        }

        return max(0.0, min(1.0, (float) $value));
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyRelatedContentAssessment(): array
    {
        return [
            'related_results_appropriate' => false,
            'solicitation_score' => 0.0,
            'solicitation_reason' => '',
            'candidate_reviews' => [],
        ];
    }
}
