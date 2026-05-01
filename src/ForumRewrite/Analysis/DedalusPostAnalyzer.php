<?php

declare(strict_types=1);

namespace ForumRewrite\Analysis;

use RuntimeException;

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

    private string $apiKey;
    private string $baseUrl;
    private string $model;
    private int $timeoutSeconds;
    private string $systemPromptTemplatePath;
    private ?string $loadedSystemPrompt = null;

    public function __construct(
        string $apiKey,
        string $baseUrl = 'https://api.dedaluslabs.ai',
        string $model = 'openai/gpt-5-nano',
        int $timeoutSeconds = 60,
        ?string $systemPromptTemplatePath = null,
    ) {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
        $this->model = $model;
        $this->timeoutSeconds = $timeoutSeconds;
        $this->systemPromptTemplatePath = $systemPromptTemplatePath
            ?? dirname(__DIR__, 3) . '/prompts/dedalus_post_analysis_system.txt';
    }

    public function analyze(array $context): array
    {
        $response = $this->postJson('/v1/chat/completions', [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->systemPrompt(),
                ],
                [
                    'role' => 'user',
                    'content' => json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                ],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'ForumPostAnalysis',
                    'schema' => $this->responseSchema(),
                ],
            ],
            'max_completion_tokens' => 4000,
        ]);

        $decoded = self::decodeCompletionPayload($response);

        return [
            'provider' => 'dedalus',
            'provider_model' => (string) ($response['model'] ?? $this->model),
            'provider_request_id' => isset($response['id']) ? (string) $response['id'] : null,
            'moderation' => $this->objectOrEmpty($decoded['moderation'] ?? null),
            'engagement' => $this->objectOrEmpty($decoded['engagement'] ?? null),
            'quality' => $this->objectOrEmpty($decoded['quality'] ?? null),
            'respondability' => $this->objectOrEmpty($decoded['respondability'] ?? null),
            'raw_response' => $response,
        ];
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    public static function decodeCompletionPayload(array $response): array
    {
        $message = $response['choices'][0]['message'] ?? null;
        if (is_array($message)) {
            $parsed = $message['parsed'] ?? null;
            if (is_array($parsed)) {
                return $parsed;
            }

            $content = $message['content'] ?? null;
            if (is_array($content) && !array_is_list($content)) {
                return $content;
            }

            $contentText = self::contentToText($content);
            if ($contentText !== '') {
                $decoded = json_decode($contentText, true);
                if (is_array($decoded)) {
                    return $decoded;
                }

                throw new RuntimeException('Dedalus response content was not valid JSON.');
            }
        }

        $outputText = self::contentToText($response['output_text'] ?? null);
        if ($outputText !== '') {
            $decoded = json_decode($outputText, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $finishReason = (string) ($response['choices'][0]['finish_reason'] ?? 'unknown');
        $completionTokens = (string) ($response['usage']['completion_tokens'] ?? 'unknown');
        $reasoningTokens = (string) ($response['usage']['completion_tokens_details']['reasoning_tokens'] ?? 'unknown');

        throw new RuntimeException(
            'Dedalus response did not include parseable message content.'
            . ' finish_reason=' . $finishReason
            . ' completion_tokens=' . $completionTokens
            . ' reasoning_tokens=' . $reasoningTokens
        );
    }

    private static function contentToText(mixed $content): string
    {
        if (is_string($content)) {
            return trim($content);
        }

        if (!is_array($content)) {
            return '';
        }

        $parts = [];
        foreach ($content as $block) {
            if (is_string($block)) {
                $parts[] = $block;
                continue;
            }

            if (!is_array($block)) {
                continue;
            }

            foreach (['text', 'content', 'output_text'] as $key) {
                if (isset($block[$key]) && is_string($block[$key])) {
                    $parts[] = $block[$key];
                    break;
                }
            }
        }

        return trim(implode('', $parts));
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
    private function responseSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
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
            ],
            'required' => ['engagement', 'moderation', 'quality', 'respondability'],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function postJson(string $path, array $payload): array
    {
        $url = rtrim($this->baseUrl, '/') . $path;
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => max(1, $this->timeoutSeconds),
                'ignore_errors' => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        $statusCode = $this->statusCode($http_response_header ?? []);
        if ($raw === false) {
            throw new RuntimeException('Dedalus request failed before receiving a response.');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Dedalus response was not valid JSON.');
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = (string) ($decoded['error']['message'] ?? 'Dedalus request failed.');
            throw new RuntimeException($message);
        }

        return $decoded;
    }

    /**
     * @param string[] $headers
     */
    private function statusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function objectOrEmpty(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
