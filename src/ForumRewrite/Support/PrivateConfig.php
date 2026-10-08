<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

final class PrivateConfig
{
    /**
     * @return array<string, mixed>
     */
    public static function load(string $projectRoot): array
    {
        $config = [];
        foreach (self::candidatePaths($projectRoot) as $path) {
            if ($path === '' || !is_file($path)) {
                continue;
            }

            $loaded = require $path;
            if (is_array($loaded)) {
                foreach ($loaded as $key => $value) {
                    if (is_string($key)) {
                        $config[$key] = $value;
                    }
                }
            }
        }

        foreach ([
            'LLM_PROVIDER',
            'LLM_API_KEY',
            'LLM_API_BASE_URL',
            'LLM_MODEL',
            'LLM_TIMEOUT_SECONDS',
            'LLM_EXTRA_HEADERS',
            'LLM_POST_ANALYSIS_PROMPT_PATH',
            'FAST_SCORING_ENABLED',
            'FAST_SCORING_AUTOMATIC_ENQUEUE_ENABLED',
            'FAST_SCORING_LLM_PROVIDER',
            'FAST_SCORING_LLM_API_KEY',
            'FAST_SCORING_LLM_API_BASE_URL',
            'FAST_SCORING_LLM_MODEL',
            'FAST_SCORING_LLM_TIMEOUT_SECONDS',
            'FAST_SCORING_LLM_EXTRA_HEADERS',
            'FAST_SCORING_PROMPT_PATH',
            'FAST_SCORING_DATABASE_PATH',
            'FAST_SCORING_INPUT_USD_PER_MILLION',
            'FAST_SCORING_OUTPUT_USD_PER_MILLION',
            'DEDALUS_API_KEY',
            'DEDALUS_API_BASE_URL',
            'DEDALUS_MODEL',
            'DEDALUS_TIMEOUT_SECONDS',
            'DEDALUS_POST_ANALYSIS_PROMPT_PATH',
            'DEDALUS_ANALYSIS_MODE',
            'DEDALUS_AGENT_REPLIES_ENABLED',
            'DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED',
            'AGENT_RESPONSE_REQUESTS_ENABLED',
            'LLM_CONVERSATION_RECORDING_ENABLED',
            'LLM_CONVERSATION_UI_ENABLED',
            'LLM_EXCHANGE_DATABASE_PATH',
            'VISITOR_STATISTICS_DATABASE_PATH',
        ] as $key) {
            $value = getenv($key);
            if ($value !== false) {
                $config[$key] = $value;
            }
        }

        return $config;
    }

    /**
     * @return string[]
     */
    private static function candidatePaths(string $projectRoot): array
    {
        $paths = [];
        $explicitPath = getenv('FORUM_SECRETS_PATH');
        if ($explicitPath !== false && trim($explicitPath) !== '') {
            return [$explicitPath];
        }

        $paths[] = dirname($projectRoot) . '/forum-private/secrets.php';

        return array_values(array_unique($paths));
    }
}
