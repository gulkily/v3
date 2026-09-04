<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Analysis\ProviderRequestException;
use ForumRewrite\Llm\AnthropicStructuredChatProvider;
use ForumRewrite\Llm\LlmProviderConfig;
use ForumRewrite\Llm\OpenAiCompatibleStructuredChatProvider;
use ForumRewrite\Llm\StructuredChatProvider;
use ForumRewrite\Support\PrivateConfig;

$projectRoot = dirname(__DIR__);

try {
    $options = parseOptions(array_slice($argv, 1));
    if (($options['help'] ?? false) === true) {
        fwrite(STDOUT, usageText());
        exit(0);
    }

    $config = LlmProviderConfig::fromPrivateConfig(PrivateConfig::load($projectRoot));
    $timeoutSeconds = isset($options['timeout'])
        ? max(1, (int) $options['timeout'])
        : min(max(1, $config->timeoutSeconds), 30);
    $config = new LlmProviderConfig(
        $config->provider,
        $config->apiKey,
        $config->baseUrl,
        $config->model,
        $timeoutSeconds,
        $config->postAnalysisPromptPath,
        $config->extraHeaders,
    );

    fwrite(STDOUT, "Agent reply live provider test\n");
    fwrite(STDOUT, "Provider: {$config->provider}\n");
    fwrite(STDOUT, "Model: {$config->model}\n");
    fwrite(STDOUT, "Base URL: {$config->baseUrl}\n");
    fwrite(STDOUT, "Timeout: {$config->timeoutSeconds}s\n");
    fwrite(STDOUT, 'API key: ' . ($config->apiKey === '' ? 'missing' : 'present') . "\n\n");

    if ($config->provider === 'stub') {
        throw new RuntimeException('LLM_PROVIDER is stub; configure a live provider/API key to test the language model service.');
    }
    if ($config->apiKey === '') {
        throw new RuntimeException('LLM API key is not configured.');
    }

    $startedAt = microtime(true);
    $result = provider($config)->completeStructuredChat(
        'ForumAgentReplyLiveTest',
        [
            [
                'role' => 'system',
                'content' => 'Return only valid structured JSON for this connectivity test.',
            ],
            [
                'role' => 'user',
                'content' => 'Reply with ok=true and message="agent reply provider reachable".',
            ],
        ],
        [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'ok' => ['type' => 'boolean'],
                'message' => ['type' => 'string'],
            ],
            'required' => ['ok', 'message'],
        ],
        [
            'max_completion_tokens' => 2000,
            'max_tokens' => 2000,
        ]
    );
    $decoded = $result['decoded'];
    if (($decoded['ok'] ?? null) !== true) {
        throw new RuntimeException('Provider response decoded, but ok was not true.');
    }

    fwrite(STDOUT, "Status: ok\n");
    fwrite(STDOUT, 'Provider response model: ' . value($result['provider_model'] ?? null) . "\n");
    fwrite(STDOUT, 'Provider request id: ' . value($result['provider_request_id'] ?? null) . "\n");
    fwrite(STDOUT, 'Decoded message: ' . value($decoded['message'] ?? null) . "\n");
    fwrite(STDOUT, sprintf("Elapsed: %.3f seconds\n", microtime(true) - $startedAt));
} catch (ProviderRequestException $exception) {
    fwrite(STDERR, 'Status: failed' . "\n");
    fwrite(STDERR, 'Error: ' . $exception->getMessage() . "\n");
    printProviderDiagnostics($exception->diagnostics());
    fwrite(STDERR, "\n" . usageText());
    exit(1);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Status: failed' . "\n");
    fwrite(STDERR, 'Error: ' . $exception->getMessage() . "\n\n" . usageText());
    exit(1);
}

/**
 * @param list<string> $args
 * @return array<string, int|bool>
 */
function parseOptions(array $args): array
{
    $options = [];
    foreach ($args as $arg) {
        if ($arg === '-h' || $arg === '--help') {
            $options['help'] = true;
            continue;
        }
        if (str_starts_with($arg, '--timeout=')) {
            $timeout = filter_var(substr($arg, strlen('--timeout=')), FILTER_VALIDATE_INT);
            if ($timeout === false) {
                throw new RuntimeException('Timeout must be an integer number of seconds.');
            }
            $options['timeout'] = max(1, $timeout);
            continue;
        }

        throw new RuntimeException('Unknown option: ' . $arg);
    }

    return $options;
}

function provider(LlmProviderConfig $config): StructuredChatProvider
{
    if ($config->provider === 'anthropic') {
        return new AnthropicStructuredChatProvider(
            $config->apiKey,
            $config->baseUrl,
            $config->model,
            $config->timeoutSeconds,
            $config->extraHeaders,
        );
    }

    return new OpenAiCompatibleStructuredChatProvider(
        $config->provider,
        $config->apiKey,
        $config->baseUrl,
        $config->model,
        $config->timeoutSeconds,
        $config->extraHeaders,
    );
}

/**
 * @param array<string, mixed> $diagnostics
 */
function printProviderDiagnostics(array $diagnostics): void
{
    $request = is_array($diagnostics['request'] ?? null) ? $diagnostics['request'] : [];
    $response = is_array($diagnostics['response'] ?? null) ? $diagnostics['response'] : [];
    if ($request !== []) {
        fwrite(STDERR, 'Request URL: ' . value($request['url'] ?? null) . "\n");
        fwrite(STDERR, 'Request timeout: ' . value($request['timeout_seconds'] ?? null) . "\n");
    }
    if ($response !== []) {
        fwrite(STDERR, 'Response status: ' . value($response['status_code'] ?? null) . "\n");
        fwrite(STDERR, 'Response body: ' . truncate(value($response['body'] ?? null), 2000) . "\n");
    }
}

function value(mixed $value): string
{
    if ($value === null || $value === '') {
        return '(none)';
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if (is_scalar($value)) {
        return (string) $value;
    }

    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '(unprintable)';
}

function truncate(string $value, int $limit): string
{
    return strlen($value) > $limit ? substr($value, 0, $limit) . '...' : $value;
}

function usageText(): string
{
    return "Usage: php scripts/test_agent_reply_provider.php [--timeout=30]\n"
        . "       ./v3 agent-reply test [--timeout=30]\n"
        . "Sends one live structured prompt to the configured LLM provider to validate the API key and model service.\n";
}
