<?php

declare(strict_types=1);

namespace ForumRewrite\Llm;

use ForumRewrite\Analysis\ProviderRequestException;
use RuntimeException;

final class AnthropicStructuredChatProvider implements StructuredChatProvider
{
    /**
     * @param array<string, string> $extraHeaders
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int $timeoutSeconds = 60,
        private readonly array $extraHeaders = [],
        private readonly ?LlmExchangeRecorder $exchangeRecorder = null,
    ) {
    }

    public function completeStructuredChat(string $schemaName, array $messages, array $jsonSchema, array $options = []): array
    {
        $startedAt = hrtime(true);
        $payload = $this->payloadFor($schemaName, $messages, $jsonSchema, $options);
        $response = $this->postJson('/v1/messages', $payload, $this->exchangeContext($options));
        $decoded = $this->decodeResponse($response);

        return [
            'provider' => 'anthropic',
            'provider_model' => (string) ($response['model'] ?? $this->model),
            'provider_request_id' => isset($response['id']) ? (string) $response['id'] : null,
            'decoded' => $decoded,
            'raw_response' => $response,
            'timings' => [
                'external_provider' => $this->elapsedMilliseconds($startedAt),
            ],
        ];
    }

    /**
     * @param list<array{role:string, content:string}> $messages
     * @param array<string, mixed> $jsonSchema
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function payloadFor(string $schemaName, array $messages, array $jsonSchema, array $options): array
    {
        $system = [];
        $anthropicMessages = [];
        foreach ($messages as $message) {
            $role = (string) ($message['role'] ?? '');
            $content = (string) ($message['content'] ?? '');
            if ($role === 'system') {
                $system[] = $content;
                continue;
            }

            $anthropicMessages[] = [
                'role' => $role === 'assistant' ? 'assistant' : 'user',
                'content' => $content,
            ];
        }

        $payload = [
            'model' => $this->model,
            'max_tokens' => max(1, (int) ($options['max_tokens'] ?? $options['max_completion_tokens'] ?? 8000)),
            'messages' => $anthropicMessages,
            'output_config' => [
                'format' => [
                    'type' => 'json_schema',
                    'schema' => $this->anthropicSchema($jsonSchema),
                ],
            ],
        ];
        if ($system !== []) {
            $payload['system'] = implode("\n\n", $system);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function anthropicSchema(array $schema): array
    {
        foreach (['minimum', 'maximum', 'minLength', 'maxLength', 'pattern', 'format'] as $unsupported) {
            unset($schema[$unsupported]);
        }

        foreach ($schema as $key => $value) {
            if (is_array($value)) {
                $schema[$key] = $this->arraySchemaValue($value);
            }
        }

        return $schema;
    }

    private function arraySchemaValue(array $value): array
    {
        if ($value === []) {
            return [];
        }

        if (array_is_list($value)) {
            $items = [];
            foreach ($value as $item) {
                $items[] = is_array($item) ? $this->arraySchemaValue($item) : $item;
            }

            return $items;
        }

        return $this->anthropicSchema($value);
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function decodeResponse(array $response): array
    {
        if (is_array($response['parsed_output'] ?? null)) {
            return $response['parsed_output'];
        }

        $text = '';
        foreach (($response['content'] ?? []) as $block) {
            if (is_array($block) && (string) ($block['type'] ?? '') === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
        }

        $decoded = json_decode(trim($text), true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $stopReason = (string) ($response['stop_reason'] ?? 'unknown');
        throw new RuntimeException('Anthropic response did not include parseable structured content; stop_reason=' . $stopReason);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function postJson(string $path, array $payload, array $exchangeContext = []): array
    {
        $startedAt = hrtime(true);
        $url = rtrim($this->baseUrl, '/') . $path;
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $headers = array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'anthropic-version' => '2023-06-01',
            'x-api-key' => $this->apiKey,
        ], $this->extraHeaders);
        $diagnostics = [
            'request' => [
                'method' => 'POST',
                'url' => $url,
                'path' => $path,
                'headers' => $this->redactedHeaders($headers),
                'payload' => $payload,
                'body' => $body,
                'timeout_seconds' => max(1, $this->timeoutSeconds),
            ],
            'response' => [
                'status_code' => 0,
                'headers' => [],
                'body' => null,
            ],
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $this->headerLines($headers),
                'content' => $body,
                'timeout' => max(1, $this->timeoutSeconds),
                'ignore_errors' => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        $diagnostics['response']['status_code'] = $this->statusCode($http_response_header ?? []);
        $diagnostics['response']['headers'] = $http_response_header ?? [];
        $diagnostics['response']['body'] = $raw === false ? null : $raw;
        $this->recordExchange($exchangeContext, $diagnostics, 'transport_error', $startedAt);

        if ($raw === false) {
            $error = error_get_last();
            throw new ProviderRequestException(
                'Anthropic provider request failed before receiving a response.',
                $this->diagnosticsWithError($diagnostics, new RuntimeException(
                    isset($error['message']) ? (string) $error['message'] : 'Provider request failed before receiving a response.'
                ))
            );
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new ProviderRequestException(
                'Anthropic provider response was not valid JSON.',
                $this->diagnosticsWithError($diagnostics, new RuntimeException('Provider response was not valid JSON.'))
            );
        }
        $diagnostics['response']['decoded'] = $decoded;
        $this->recordExchange(
            $exchangeContext,
            $diagnostics,
            $diagnostics['response']['status_code'] >= 200 && $diagnostics['response']['status_code'] < 300 ? 'completed' : 'provider_error',
            $startedAt
        );

        if ($diagnostics['response']['status_code'] < 200 || $diagnostics['response']['status_code'] >= 300) {
            $message = $this->errorMessageFromResponse($decoded);
            throw new ProviderRequestException($message, $this->diagnosticsWithError($diagnostics, new RuntimeException($message)));
        }

        return $decoded;
    }

    /** @param array<string, mixed> $options @return array<string, mixed> */
    private function exchangeContext(array $options): array
    {
        return is_array($options['exchange_context'] ?? null) ? $options['exchange_context'] : [];
    }

    /** @param array<string, mixed> $context @param array<string, mixed> $diagnostics */
    private function recordExchange(array $context, array $diagnostics, string $status, int $startedAt): void
    {
        if ($this->exchangeRecorder === null) {
            return;
        }

        $response = is_array($diagnostics['response'] ?? null) ? $diagnostics['response'] : [];
        $request = is_array($diagnostics['request'] ?? null) ? $diagnostics['request'] : [];
        $context['provider'] = $context['provider'] ?? 'anthropic';
        $context['provider_model'] = $context['provider_model'] ?? $this->model;
        $context['provider_request_id'] = $context['provider_request_id'] ?? ($response['decoded']['id'] ?? null);
        $this->exchangeRecorder->record(
            $context,
            $request,
            $response,
            $status,
            $this->elapsedMilliseconds($startedAt),
            is_array($diagnostics['error'] ?? null) ? $diagnostics['error'] : []
        );
    }

    /**
     * @param array<string, string> $headers
     */
    private function headerLines(array $headers): string
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        return implode("\r\n", $lines);
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function redactedHeaders(array $headers): array
    {
        $redacted = [];
        foreach ($headers as $name => $value) {
            $redacted[$name] = preg_match('/(authorization|api-key|x-api-key|token|secret|password)/i', $name) === 1
                ? '<redacted>'
                : $value;
        }

        return $redacted;
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
     * @param array<string, mixed> $decoded
     */
    private function errorMessageFromResponse(array $decoded): string
    {
        foreach ([
            $decoded['error']['message'] ?? null,
            $decoded['message'] ?? null,
        ] as $message) {
            $message = trim((string) $message);
            if ($message !== '') {
                return $message;
            }
        }

        return 'Anthropic provider request failed.';
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

    private function elapsedMilliseconds(int $startedAt): float
    {
        return round((hrtime(true) - $startedAt) / 1000000, 1);
    }
}
