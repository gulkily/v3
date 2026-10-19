<?php

declare(strict_types=1);

namespace ForumRewrite\Agent;

use ForumRewrite\Analysis\ProviderRequestException;
use ForumRewrite\Llm\CompletionLimitException;
use InvalidArgumentException;
use Throwable;

final class AgentReplyFailure
{
    public static function code(Throwable $error): string
    {
        if ($error instanceof ScriptureVerificationException) {
            return 'scripture_verification_failed';
        }
        if ($error instanceof CompletionLimitException) {
            return 'completion_limit_exhausted';
        }
        if ($error instanceof InvalidArgumentException) {
            return 'provider_configuration_error';
        }
        if ($error instanceof ProviderRequestException
            && in_array((int) ($error->diagnostics()['response']['status_code'] ?? 0), [400, 401, 403, 404, 422], true)) {
            return 'provider_configuration_error';
        }
        return 'analysis_suggestion_error';
    }

    public static function message(string $code): string
    {
        return match ($code) {
            'scripture_verification_failed' => 'Scripture source verification failed. No partial reply was published. An operator can inspect the source or output and retry.',
            'completion_limit_exhausted' => 'Completion limit reached. Ask an operator to adjust the allowance and retry.',
            'provider_configuration_error' => 'Provider configuration needs correction before an operator retries.',
            default => 'Reply generation failed. An operator can inspect the failure and retry.',
        };
    }
}
