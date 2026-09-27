<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

final class FastScoreFailure
{
    /** @return array{failure_code:string,failure_message:string} */
    public static function fromThrowable(\Throwable $error): array
    {
        $message = strtolower($error->getMessage());
        if (str_contains($message, 'timeout')) {
            return ['failure_code' => 'provider_timeout', 'failure_message' => 'Provider request timed out.'];
        }
        if (str_contains($message, 'rate limit') || str_contains($message, '429')) {
            return ['failure_code' => 'provider_rate_limited', 'failure_message' => 'Provider rate limited the request.'];
        }
        if (str_contains($message, 'authentication') || str_contains($message, 'api key') || str_contains($message, '401') || str_contains($message, '403')) {
            return ['failure_code' => 'provider_authentication_failed', 'failure_message' => 'Provider authentication failed.'];
        }
        return ['failure_code' => 'provider_request_failed', 'failure_message' => 'Provider request failed.'];
    }

    public static function safeMessage(?string $code, string $status): ?string
    {
        return match ($code) {
            'provider_timeout' => 'Provider request timed out.',
            'provider_rate_limited' => 'Provider rate limited the request.',
            'provider_authentication_failed' => 'Provider authentication failed.',
            'provider_request_failed' => 'Provider request failed.',
            'invalid_response' => 'Provider returned an invalid structured response.',
            'config_missing' => 'Fastmod provider configuration is unavailable.',
            'worker_error' => 'Unexpected scoring worker error.',
            default => match ($status) {
                'provider_error' => 'Provider request failed.',
                'invalid_response' => 'Provider returned an invalid structured response.',
                'config_missing' => 'Fastmod provider configuration is unavailable.',
                default => null,
            },
        };
    }
}
