<?php

declare(strict_types=1);

namespace ForumRewrite\Llm;

use ForumRewrite\Analysis\ProviderRequestException;
use RuntimeException;

final class OpenAiCompatibleStructuredChatProvider implements StructuredChatProvider
{
    /**
     * @param array<string, string> $extraHeaders
     */
    public function __construct(
        private readonly string $providerName,
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int $timeoutSeconds = 60,
        private readonly array $extraHeaders = [],
    ) {
    }

    public function completeStructuredChat(string $schemaName, array $messages, array $jsonSchema, array $options = []): array
    {
        $startedAt = hrtime(true);
        $response = $this->postJson('/v1/chat/completions', $this->payloadFor($schemaName, $messages, $jsonSchema, $options));
        $decoded = StructuredChatCompletionDecoder::decodeOpenAiCompatiblePayload($response);

        return [
            'provider' => $this->providerName,
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
        return [
            'model' => $this->model,
            'messages' => $messages,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $schemaName,
                    'schema' => $jsonSchema,
                ],
            ],
            'max_completion_tokens' => max(1, (int) ($options['max_completion_tokens'] ?? 8000)),
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
        $headers = array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiKey,
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

        if ($raw === false) {
            $error = error_get_last();
            throw new ProviderRequestException(
                'OpenAI-compatible provider request failed before receiving a response.',
                $this->diagnosticsWithError($diagnostics, new RuntimeException(
                    isset($error['message']) ? (string) $error['message'] : 'Provider request failed before receiving a response.'
                ))
            );
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new ProviderRequestException(
                'OpenAI-compatible provider response was not valid JSON.',
                $this->diagnosticsWithError($diagnostics, new RuntimeException('Provider response was not valid JSON.'))
            );
        }
        $diagnostics['response']['decoded'] = $decoded;

        if ($diagnostics['response']['status_code'] < 200 || $diagnostics['response']['status_code'] >= 300) {
            $message = $this->errorMessageFromResponse($decoded);
            throw new ProviderRequestException($message, $this->diagnosticsWithError($diagnostics, new RuntimeException($message)));
        }

        return $decoded;
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
            $redacted[$name] = preg_match('/(authorization|api-key|token|secret|password)/i', $name) === 1
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
            $decoded['detail']['error']['message'] ?? null,
            $decoded['detail']['message'] ?? null,
            $decoded['message'] ?? null,
        ] as $message) {
            $message = trim((string) $message);
            if ($message !== '') {
                return $message;
            }
        }

        return 'OpenAI-compatible provider request failed.';
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
