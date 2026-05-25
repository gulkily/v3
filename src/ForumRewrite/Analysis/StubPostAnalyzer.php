<?php

declare(strict_types=1);

namespace ForumRewrite\Analysis;

final class StubPostAnalyzer implements PostAnalyzer
{
    public function __construct(
        private readonly string $model = 'stub/post-analysis',
    ) {
    }

    public function analyze(array $context): array
    {
        $body = strtolower((string) ($context['body'] ?? ''));
        $asksQuestion = str_contains($body, '?');
        $labels = [];
        $severity = 'none';
        if (str_contains($body, 'idiot') || str_contains($body, 'stupid')) {
            $labels[] = 'aggression';
            $severity = 'medium';
        }

        $responseRisk = $labels === [] ? 'low' : 'medium';
        $overallScore = $asksQuestion ? 0.78 : 0.58;
        $shouldGenerateResponse = $overallScore >= 0.65 && $responseRisk !== 'high';
        $relatedContent = is_array($context['related_content'] ?? null) ? $context['related_content'] : [];

        return [
            'provider' => 'stub',
            'provider_model' => $this->model,
            'provider_request_id' => null,
            'post_summary' => $this->summary((string) ($context['body'] ?? '')),
            'moderation' => [
                'severity' => $severity,
                'labels' => $labels,
                'confidence' => $labels === [] ? 0.25 : 0.8,
                'summary' => $labels === [] ? 'No obvious moderation concern in stub analysis.' : 'Stub analysis detected aggressive wording.',
                'recommended_action' => $labels === [] ? 'none' : 'review',
            ],
            'engagement' => [
                'suggested_response' => $this->suggestedResponse($shouldGenerateResponse, $relatedContent),
                'response_style' => 'curious',
                'response_should_be_public' => $shouldGenerateResponse,
            ],
            'quality' => [
                'discussion_value' => 'medium',
                'good_faith_likelihood' => $labels === [] ? 0.75 : 0.45,
                'needs_human_review' => $labels !== [],
            ],
            'respondability' => [
                'overall_score' => $overallScore,
                'asks_question' => $asksQuestion,
                'question_type' => $asksQuestion ? 'opinion' : 'none',
                'invites_response' => true,
                'author_benefit' => $asksQuestion ? 'high' : 'medium',
                'audience_benefit' => 'medium',
                'response_effort_required' => 'medium',
                'response_risk' => $responseRisk,
                'best_response_mode' => $asksQuestion ? 'answer' : 'ask_followup',
                'should_generate_response' => $shouldGenerateResponse,
                'reason' => $asksQuestion
                    ? 'Stub analysis treats explicit questions as respondable.'
                    : 'Stub analysis sees a possible follow-up but no explicit question.',
            ],
            'related_content_assessment' => $this->relatedContentAssessment($relatedContent),
            'unicode_risk_review' => $this->unicodeRiskReview($context),
            'raw_response' => [
                'stub' => true,
                'related_content' => $relatedContent,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function unicodeRiskReview(array $context): array
    {
        $facts = $context['unicode_risk_deterministic_facts'] ?? null;
        if (!is_array($facts)) {
            return [
                'review_priority' => 'none',
                'summary' => '',
                'concerns' => [],
                'recommended_action' => 'none',
                'confidence' => 1.0,
            ];
        }

        $labels = [];
        foreach (($facts['fields'] ?? []) as $fieldFacts) {
            if (!is_array($fieldFacts)) {
                continue;
            }
            foreach (($fieldFacts['risk_labels'] ?? []) as $label) {
                $labels[(string) $label] = true;
            }
        }

        return [
            'review_priority' => $labels === [] ? 'none' : 'low',
            'summary' => $labels === [] ? '' : 'Stub review saw deterministic Unicode risk labels.',
            'concerns' => array_keys($labels),
            'recommended_action' => $labels === [] ? 'none' : 'watch',
            'confidence' => 0.8,
        ];
    }

    /**
     * @param list<array<string, mixed>> $relatedContent
     */
    private function relatedContentAssessment(array $relatedContent): array
    {
        $candidateReviews = [];
        foreach ($relatedContent as $candidate) {
            $postId = (string) ($candidate['post_id'] ?? '');
            if ($postId === '') {
                continue;
            }

            $candidateReviews[] = [
                'post_id' => $postId,
                'relationship' => 'same_topic',
                'relevance_score' => 0.8,
                'appropriate_to_show' => true,
                'reason' => 'Stub analysis treats supplied related content as displayable.',
            ];
        }

        return [
            'related_results_appropriate' => $candidateReviews !== [],
            'solicitation_score' => $candidateReviews !== [] ? 0.8 : 0.0,
            'solicitation_reason' => $candidateReviews !== []
                ? 'Stub analysis received related content candidates.'
                : 'Stub analysis received no related content candidates.',
            'candidate_reviews' => $candidateReviews,
        ];
    }

    /**
     * @param list<array<string, mixed>> $relatedContent
     */
    private function suggestedResponse(bool $shouldGenerateResponse, array $relatedContent): string
    {
        if (!$shouldGenerateResponse) {
            return '';
        }

        $firstMatch = $relatedContent[0] ?? null;
        if (is_array($firstMatch) && trim((string) ($firstMatch['post_url'] ?? '')) !== '') {
            $subject = trim((string) ($firstMatch['subject'] ?? ''));
            $label = $subject !== '' ? $subject : (string) ($firstMatch['post_id'] ?? 'a related post');

            return 'This looks connected to ' . (string) $firstMatch['post_url'] . ' (' . $label . '). A useful next step is to compare what changed since that earlier answer.';
        }

        return 'Thanks for starting this. What is the strongest reason someone might disagree?';
    }

    private function summary(string $body): string
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', $body));
        if ($normalized === '') {
            return 'The post has no body text.';
        }

        if (strlen($normalized) > 120) {
            $normalized = rtrim(substr($normalized, 0, 117)) . '...';
        }

        return 'The post says: ' . $normalized;
    }
}
