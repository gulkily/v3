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
        $labels = [];
        $severity = 'none';
        if (str_contains($body, 'idiot') || str_contains($body, 'stupid')) {
            $labels[] = 'aggression';
            $severity = 'medium';
        }

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
                'suggested_response' => 'Thanks for starting this. What is the strongest reason someone might disagree?',
                'response_style' => 'curious',
                'response_should_be_public' => true,
            ],
            'quality' => [
                'discussion_value' => 'medium',
                'good_faith_likelihood' => $labels === [] ? 0.75 : 0.45,
                'needs_human_review' => $labels !== [],
            ],
            'raw_response' => [
                'stub' => true,
            ],
        ];
    }
}
