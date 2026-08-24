<?php

declare(strict_types=1);

namespace ForumRewrite\Agent;

use ForumRewrite\Analysis\DedalusPostAnalyzer;
use ForumRewrite\Analysis\ProviderRequestException;
use ForumRewrite\Support\UnicodeTextPolicy;
use RuntimeException;

final class DedalusAgentReplyGenerator implements AgentReplyGenerator
{
    private const RESPONSE_STYLES = ['curious', 'clarifying', 'supportive', 'challenging', 'deescalating'];
    private const RESPONSE_INTENTS = ['answer', 'clarify', 'ask_followup', 'share_context', 'challenge_gently', 'deescalate'];

    private ?string $loadedSystemPrompt = null;
    /** @var array<string, mixed>|null */
    private ?array $lastProviderExchange = null;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.dedaluslabs.ai',
        private readonly string $model = 'openai/gpt-5-nano',
        private readonly int $timeoutSeconds = 60,
        private readonly ?string $systemPromptTemplatePath = null,
        private readonly int $maxCompletionTokens = 6000,
    ) {
    }

    public function generate(array $context): array
    {
        $providerStartedAt = hrtime(true);
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
                    'name' => 'ForumAgentReply',
                    'schema' => $this->responseSchema(),
                ],
            ],
            'max_completion_tokens' => max(1200, $this->maxCompletionTokens),
        ]);
        $externalProviderTiming = $this->elapsedMilliseconds($providerStartedAt);

        try {
            $decoded = self::decodeCompletionPayload($response);
        } catch (\Throwable $throwable) {
            throw new ProviderRequestException(
                $throwable->getMessage(),
                $this->diagnosticsWithError($this->lastProviderExchange ?? [], $throwable),
                $throwable
            );
        }
        $text = self::normalizeGeneratedReplyText((string) ($decoded['response_text'] ?? ''));
        if ($text === '') {
            throw new RuntimeException('Dedalus reply response did not include response_text.');
        }

        return [
            'provider' => 'dedalus',
            'provider_model' => (string) ($response['model'] ?? $this->model),
            'provider_request_id' => isset($response['id']) ? (string) $response['id'] : null,
            'response_text' => $text,
            'response_style' => (string) ($decoded['response_style'] ?? 'curious'),
            'response_intent' => (string) ($decoded['response_intent'] ?? 'answer'),
            'raw_response' => $response,
            'timings' => [
                'external_provider' => $externalProviderTiming,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    public static function decodeCompletionPayload(array $response): array
    {
        return DedalusPostAnalyzer::decodeCompletionPayload($response);
    }

    public static function normalizeGeneratedReplyText(
        string $text,
        bool $allowUnicodeAuthoredText = false,
        bool $allowEmojiAuthoredText = false,
    ): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", trim($text));
        $normalized = strtr($normalized, [
            "\u{2018}" => "'",
            "\u{2019}" => "'",
            "\u{201C}" => '"',
            "\u{201D}" => '"',
            "\u{2013}" => '-',
            "\u{2014}" => '-',
            "\u{2026}" => '...',
            "\u{00A0}" => ' ',
        ]);

        if ($allowUnicodeAuthoredText) {
            return trim((new UnicodeTextPolicy($allowEmojiAuthoredText))->normalizeBody($normalized, 'response_text'));
        }

        $normalized = preg_replace('/[^\x0A\x20-\x7E]/u', '', $normalized);

        return trim($normalized ?? '');
    }

    public function systemPrompt(): string
    {
        if ($this->loadedSystemPrompt !== null) {
            return $this->loadedSystemPrompt;
        }

        $path = $this->systemPromptTemplatePath
            ?? dirname(__DIR__, 3) . '/prompts/dedalus_agent_reply_system.txt';
        $prompt = @file_get_contents($path);
        if ($prompt === false) {
            throw new RuntimeException('Dedalus agent reply prompt template could not be read: ' . $path);
        }

        $prompt = trim($prompt);
        if ($prompt === '') {
            throw new RuntimeException('Dedalus agent reply prompt template is empty: ' . $path);
        }

        $this->loadedSystemPrompt = strtr($prompt, [
            '{{response_styles}}' => implode(', ', self::RESPONSE_STYLES),
            '{{response_intents}}' => implode(', ', self::RESPONSE_INTENTS),
        ]);

        return $this->loadedSystemPrompt;
    }

    private function elapsedMilliseconds(int $startedAt): float
    {
        return round((hrtime(true) - $startedAt) / 1000000, 1);
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
                'response_text' => [
                    'type' => 'string',
                    'minLength' => 1,
                    'maxLength' => 1200,
                ],
                'response_style' => [
                    'type' => 'string',
                    'enum' => self::RESPONSE_STYLES,
                ],
                'response_intent' => [
                    'type' => 'string',
                    'enum' => self::RESPONSE_INTENTS,
                ],
            ],
            'required' => ['response_text', 'response_style', 'response_intent'],
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
        $requestDiagnostics = [
            'method' => 'POST',
            'url' => $url,
            'path' => $path,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer <redacted>',
            ],
            'payload' => $payload,
            'body' => $body,
            'timeout_seconds' => max(1, $this->timeoutSeconds),
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
        $responseHeaders = $http_response_header ?? [];
        $this->lastProviderExchange = [
            'request' => $requestDiagnostics,
            'response' => [
                'status_code' => $statusCode,
                'headers' => $responseHeaders,
                'body' => $raw === false ? null : $raw,
            ],
        ];
        if ($raw === false) {
            $error = error_get_last();
            $diagnostics = $this->diagnosticsWithError($this->lastProviderExchange, new RuntimeException(
                isset($error['message']) ? (string) $error['message'] : 'Dedalus agent reply request failed before receiving a response.'
            ));
            throw new ProviderRequestException('Dedalus agent reply request failed before receiving a response.', $diagnostics);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $diagnostics = $this->diagnosticsWithError(
                $this->lastProviderExchange,
                new RuntimeException('Dedalus agent reply response was not valid JSON.')
            );
            throw new ProviderRequestException('Dedalus agent reply response was not valid JSON.', $diagnostics);
        }
        $this->lastProviderExchange['response']['decoded'] = $decoded;

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = $this->errorMessageFromResponse($decoded);
            $diagnostics = $this->diagnosticsWithError($this->lastProviderExchange, new RuntimeException($message));
            throw new ProviderRequestException($message, $diagnostics);
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function errorMessageFromResponse(array $decoded): string
    {
        foreach ([
            $decoded['error']['message'] ?? null,
            $decoded['detail']['error']['message'] ?? null,
            $decoded['detail']['message'] ?? null,
            $decoded['message'] ?? null,
        ] as $message) {
            $message = trim((string) $message);
            if ($message !== '') {
                return $message;
            }
        }

        return 'Dedalus agent reply request failed.';
    }

    /**
     * @param array<string, mixed> $diagnostics
     * @return array<string, mixed>
     */
    private function diagnosticsWithError(array $diagnostics, \Throwable $throwable): array
    {
        $diagnostics['error'] = [
            'class' => $throwable::class,
            'message' => $throwable->getMessage(),
        ];

        return $diagnostics;
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
}
