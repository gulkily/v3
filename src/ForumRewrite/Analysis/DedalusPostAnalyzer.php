<?php

declare(strict_types=1);

namespace ForumRewrite\Analysis;

use ForumRewrite\Llm\OpenAiCompatibleStructuredChatProvider;
use ForumRewrite\Llm\LlmExchangeRecorder;
use ForumRewrite\Llm\StructuredChatCompletionDecoder;
use ForumRewrite\Llm\StructuredChatProvider;

final class DedalusPostAnalyzer implements PostAnalyzer
{
    private const RESPONSE_STYLES = ['curious', 'clarifying', 'supportive', 'challenging', 'deescalating'];
    private const MODERATION_LABELS = ['trolling', 'bad_faith', 'aggression', 'harassment', 'threat', 'spam', 'low_effort', 'off_topic', 'escalation_risk'];
    private const MODERATION_SEVERITIES = ['none', 'low', 'medium', 'high', 'critical'];
    private const RECOMMENDED_ACTIONS = ['none', 'watch', 'review', 'hide_pending_review', 'escalate'];
    private const DISCUSSION_VALUES = ['low', 'medium', 'high'];
    private const QUESTION_TYPES = ['none', 'factual', 'opinion', 'advice', 'clarification', 'challenge', 'rhetorical'];
    private const BENEFIT_LEVELS = ['low', 'medium', 'high'];
    private const EFFORT_LEVELS = ['low', 'medium', 'high'];
    private const RESPONSE_RISK_LEVELS = ['low', 'medium', 'high'];
    private const RESPONSE_MODES = ['none', 'answer', 'clarify', 'ask_followup', 'share_context', 'challenge_gently', 'deescalate'];
    private const RELATED_CONTENT_RELATIONSHIPS = ['none', 'same_topic', 'same_question', 'direct_answer', 'duplicate_request', 'background_context', 'counterexample'];
    private const UNICODE_REVIEW_PRIORITIES = ['none', 'low', 'medium', 'high'];
    private const UNICODE_RECOMMENDED_ACTIONS = ['none', 'watch', 'human_review'];

    private string $systemPromptTemplatePath;
    private ?string $loadedSystemPrompt = null;
    private StructuredChatProvider $provider;

    public function __construct(
        string $apiKey,
        string $baseUrl = 'https://api.dedaluslabs.ai',
        string $model = 'openai/gpt-5-nano',
        int $timeoutSeconds = 60,
        ?string $systemPromptTemplatePath = null,
        ?StructuredChatProvider $provider = null,
        ?LlmExchangeRecorder $exchangeRecorder = null,
    ) {
        $this->systemPromptTemplatePath = $systemPromptTemplatePath
            ?? dirname(__DIR__, 3) . '/prompts/dedalus_post_analysis_system.txt';
        $this->provider = $provider ?? new OpenAiCompatibleStructuredChatProvider(
            'dedalus',
            $apiKey,
            $baseUrl,
            $model,
            $timeoutSeconds,
            exchangeRecorder: $exchangeRecorder,
        );
    }

    public function analyze(array $context): array
    {
        $completion = $this->provider->completeStructuredChat(
            'ForumPostAnalysis',
            [
                [
                    'role' => 'system',
                    'content' => $this->systemPrompt(),
                ],
                [
                    'role' => 'user',
                    'content' => json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                ],
            ],
            $this->responseSchema(),
            [
                'max_completion_tokens' => 8000,
                'exchange_context' => [
                    'call_type' => 'post_analysis',
                    'post_id' => $context['post_id'] ?? null,
                    'content_hash' => $context['content_hash'] ?? null,
                ],
            ]
        );
        $decoded = $completion['decoded'];

        return [
            'provider' => (string) $completion['provider'],
            'provider_model' => (string) $completion['provider_model'],
            'provider_request_id' => isset($completion['provider_request_id']) ? (string) $completion['provider_request_id'] : null,
            'post_summary' => (string) ($decoded['post_summary'] ?? ''),
            'moderation' => $this->objectOrEmpty($decoded['moderation'] ?? null),
            'engagement' => $this->objectOrEmpty($decoded['engagement'] ?? null),
            'quality' => $this->objectOrEmpty($decoded['quality'] ?? null),
            'respondability' => $this->objectOrEmpty($decoded['respondability'] ?? null),
            'related_content_assessment' => $this->objectOrEmpty($decoded['related_content_assessment'] ?? null),
            'unicode_risk_review' => $this->unicodeRiskReview($decoded['unicode_risk_review'] ?? null),
            'raw_response' => $completion['raw_response'],
            'timings' => $completion['timings'] ?? [],
        ];
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    public static function decodeCompletionPayload(array $response): array
    {
        return StructuredChatCompletionDecoder::decodeOpenAiCompatiblePayload($response);
    }

    private function systemPrompt(): string
    {
        if ($this->loadedSystemPrompt !== null) {
            return $this->loadedSystemPrompt;
        }

        $prompt = @file_get_contents($this->systemPromptTemplatePath);
        if ($prompt === false) {
            throw new RuntimeException('Dedalus prompt template could not be read: ' . $this->systemPromptTemplatePath);
        }

        $prompt = trim($prompt);
        if ($prompt === '') {
            throw new RuntimeException('Dedalus prompt template is empty: ' . $this->systemPromptTemplatePath);
        }

        $this->loadedSystemPrompt = $this->renderSystemPromptTemplate($prompt);

        return $this->loadedSystemPrompt;
    }

    private function renderSystemPromptTemplate(string $template): string
    {
        return strtr($template, [
            '{{response_styles}}' => implode(', ', self::RESPONSE_STYLES),
            '{{moderation_labels}}' => implode(', ', self::MODERATION_LABELS),
            '{{moderation_severities}}' => implode(', ', self::MODERATION_SEVERITIES),
            '{{recommended_actions}}' => implode(', ', self::RECOMMENDED_ACTIONS),
            '{{discussion_values}}' => implode(', ', self::DISCUSSION_VALUES),
            '{{question_types}}' => implode(', ', self::QUESTION_TYPES),
            '{{benefit_levels}}' => implode(', ', self::BENEFIT_LEVELS),
            '{{effort_levels}}' => implode(', ', self::EFFORT_LEVELS),
            '{{response_risk_levels}}' => implode(', ', self::RESPONSE_RISK_LEVELS),
            '{{response_modes}}' => implode(', ', self::RESPONSE_MODES),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function unicodeRiskReview(mixed $review): array
    {
        if (!is_array($review)) {
            return $this->emptyUnicodeRiskReview();
        }

        $priority = (string) ($review['review_priority'] ?? 'none');
        if (!in_array($priority, self::UNICODE_REVIEW_PRIORITIES, true)) {
            $priority = 'none';
        }

        $action = (string) ($review['recommended_action'] ?? 'none');
        if (!in_array($action, self::UNICODE_RECOMMENDED_ACTIONS, true)) {
            $action = 'none';
        }

        $concerns = $review['concerns'] ?? [];
        if (!is_array($concerns) || !array_is_list($concerns)) {
            $concerns = [];
        }

        return [
            'review_priority' => $priority,
            'summary' => substr((string) ($review['summary'] ?? ''), 0, 500),
            'concerns' => array_values(array_filter(array_map(
                static fn (mixed $concern): string => substr((string) $concern, 0, 80),
                $concerns
            ), static fn (string $concern): bool => $concern !== '')),
            'recommended_action' => $action,
            'confidence' => $this->boundedScore($review['confidence'] ?? 0.0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyUnicodeRiskReview(): array
    {
        return [
            'review_priority' => 'none',
            'summary' => '',
            'concerns' => [],
            'recommended_action' => 'none',
            'confidence' => 0.0,
        ];
    }

    private function boundedScore(mixed $value): float
    {
        return max(0.0, min(1.0, (float) $value));
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'post_summary' => [
                    'type' => 'string',
                    'minLength' => 1,
                    'maxLength' => 500,
                ],
                'engagement' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'suggested_response' => ['type' => 'string'],
                        'response_style' => [
                            'type' => 'string',
                            'enum' => self::RESPONSE_STYLES,
                        ],
                        'response_should_be_public' => ['type' => 'boolean'],
                    ],
                    'required' => ['suggested_response', 'response_style', 'response_should_be_public'],
                ],
                'moderation' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'severity' => [
                            'type' => 'string',
                            'enum' => self::MODERATION_SEVERITIES,
                        ],
                        'labels' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'string',
                                'enum' => self::MODERATION_LABELS,
                            ],
                        ],
                        'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                        'summary' => ['type' => 'string'],
                        'recommended_action' => [
                            'type' => 'string',
                            'enum' => self::RECOMMENDED_ACTIONS,
                        ],
                    ],
                    'required' => ['severity', 'labels', 'confidence', 'summary', 'recommended_action'],
                ],
                'quality' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'discussion_value' => [
                            'type' => 'string',
                            'enum' => self::DISCUSSION_VALUES,
                        ],
                        'good_faith_likelihood' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                        'needs_human_review' => ['type' => 'boolean'],
                    ],
                    'required' => ['discussion_value', 'good_faith_likelihood', 'needs_human_review'],
                ],
                'respondability' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'overall_score' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                        'asks_question' => ['type' => 'boolean'],
                        'question_type' => [
                            'type' => 'string',
                            'enum' => self::QUESTION_TYPES,
                        ],
                        'invites_response' => ['type' => 'boolean'],
                        'author_benefit' => [
                            'type' => 'string',
                            'enum' => self::BENEFIT_LEVELS,
                        ],
                        'audience_benefit' => [
                            'type' => 'string',
                            'enum' => self::BENEFIT_LEVELS,
                        ],
                        'response_effort_required' => [
                            'type' => 'string',
                            'enum' => self::EFFORT_LEVELS,
                        ],
                        'response_risk' => [
                            'type' => 'string',
                            'enum' => self::RESPONSE_RISK_LEVELS,
                        ],
                        'best_response_mode' => [
                            'type' => 'string',
                            'enum' => self::RESPONSE_MODES,
                        ],
                        'should_generate_response' => ['type' => 'boolean'],
                        'reason' => ['type' => 'string'],
                    ],
                    'required' => [
                        'overall_score',
                        'asks_question',
                        'question_type',
                        'invites_response',
                        'author_benefit',
                        'audience_benefit',
                        'response_effort_required',
                        'response_risk',
                        'best_response_mode',
                        'should_generate_response',
                        'reason',
                    ],
                ],
                'related_content_assessment' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'related_results_appropriate' => ['type' => 'boolean'],
                        'solicitation_score' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                        'solicitation_reason' => ['type' => 'string'],
                        'candidate_reviews' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'properties' => [
                                    'post_id' => ['type' => 'string'],
                                    'relationship' => [
                                        'type' => 'string',
                                        'enum' => self::RELATED_CONTENT_RELATIONSHIPS,
                                    ],
                                    'relevance_score' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                                    'appropriate_to_show' => ['type' => 'boolean'],
                                    'reason' => ['type' => 'string'],
                                ],
                                'required' => ['post_id', 'relationship', 'relevance_score', 'appropriate_to_show', 'reason'],
                            ],
                        ],
                    ],
                    'required' => ['related_results_appropriate', 'solicitation_score', 'solicitation_reason', 'candidate_reviews'],
                ],
                'unicode_risk_review' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'review_priority' => [
                            'type' => 'string',
                            'enum' => self::UNICODE_REVIEW_PRIORITIES,
                        ],
                        'summary' => [
                            'type' => 'string',
                            'maxLength' => 500,
                        ],
                        'concerns' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'maxItems' => 10,
                        ],
                        'recommended_action' => [
                            'type' => 'string',
                            'enum' => self::UNICODE_RECOMMENDED_ACTIONS,
                        ],
                        'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                    ],
                    'required' => ['review_priority', 'summary', 'concerns', 'recommended_action', 'confidence'],
                ],
            ],
            'required' => ['post_summary', 'engagement', 'moderation', 'quality', 'respondability', 'related_content_assessment', 'unicode_risk_review'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function objectOrEmpty(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
