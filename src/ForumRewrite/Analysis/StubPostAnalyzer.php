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

        return [
            'provider' => 'stub',
            'provider_model' => $this->model,
            'provider_request_id' => null,
            'moderation' => [
                'severity' => $severity,
                'labels' => $labels,
                'confidence' => $labels === [] ? 0.25 : 0.8,
                'summary' => $labels === [] ? 'No obvious moderation concern in stub analysis.' : 'Stub analysis detected aggressive wording.',
                'recommended_action' => $labels === [] ? 'none' : 'review',
            ],
            'engagement' => [
                'suggested_response' => $shouldGenerateResponse ? 'Thanks for starting this. What is the strongest reason someone might disagree?' : '',
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
            'raw_response' => [
                'stub' => true,
            ],
        ];
    }
}
