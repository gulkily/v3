<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

final class PrivateConfigSchema
{
    /**
     * @return array<string, array{default:mixed,type:string,secret:bool,template:bool,required:bool}>
     */
    public static function definitions(): array
    {
        return [
            'LLM_PROVIDER' => self::definition('', 'string', template: true, required: true),
            'LLM_API_KEY' => self::definition('replace-with-real-key', 'string', secret: true, template: true),
            'LLM_API_BASE_URL' => self::definition('', 'string', template: true),
            'LLM_MODEL' => self::definition('', 'string', template: true),
            'LLM_TIMEOUT_SECONDS' => self::definition(60, 'integer', template: true),
            'LLM_EXTRA_HEADERS' => self::definition([], 'map', template: true),
            'LLM_POST_ANALYSIS_PROMPT_PATH' => self::definition('prompts/dedalus_post_analysis_system.txt', 'path', template: true),
            'FAST_SCORING_ENABLED' => self::definition(false, 'boolean', template: true),
            'FAST_SCORING_AUTOMATIC_ENQUEUE_ENABLED' => self::definition(false, 'boolean', template: true),
            'FAST_SCORING_LLM_PROVIDER' => self::definition(null, 'string'),
            'FAST_SCORING_LLM_API_KEY' => self::definition(null, 'string', secret: true),
            'FAST_SCORING_LLM_API_BASE_URL' => self::definition(null, 'string'),
            'FAST_SCORING_LLM_MODEL' => self::definition('openai/gpt-5-nano', 'string', template: true),
            'FAST_SCORING_LLM_TIMEOUT_SECONDS' => self::definition(null, 'integer'),
            'FAST_SCORING_LLM_EXTRA_HEADERS' => self::definition(null, 'map'),
            'FAST_SCORING_PROMPT_PATH' => self::definition('prompts/fast_post_scoring_system.txt', 'path', template: true),
            'FAST_SCORING_DATABASE_PATH' => self::definition('', 'path', template: true),
            'FAST_SCORING_INPUT_USD_PER_MILLION' => self::definition(null, 'number'),
            'FAST_SCORING_OUTPUT_USD_PER_MILLION' => self::definition(null, 'number'),
            'DEDALUS_API_KEY' => self::definition(null, 'string', secret: true),
            'DEDALUS_API_BASE_URL' => self::definition(null, 'string'),
            'DEDALUS_MODEL' => self::definition(null, 'string'),
            'DEDALUS_TIMEOUT_SECONDS' => self::definition(null, 'integer'),
            'DEDALUS_POST_ANALYSIS_PROMPT_PATH' => self::definition(null, 'path'),
            'DEDALUS_ANALYSIS_MODE' => self::definition(null, 'string'),
            'DEDALUS_AGENT_REPLIES_ENABLED' => self::definition(true, 'boolean', template: true),
            'DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED' => self::definition(false, 'boolean', template: true),
            'AGENT_RESPONSE_REQUESTS_ENABLED' => self::definition(true, 'boolean', template: true),
            'LLM_CONVERSATION_RECORDING_ENABLED' => self::definition(true, 'boolean', template: true),
            'LLM_CONVERSATION_UI_ENABLED' => self::definition(true, 'boolean', template: true),
            'LLM_EXCHANGE_DATABASE_PATH' => self::definition(null, 'path'),
            'VISITOR_STATISTICS_DATABASE_PATH' => self::definition(null, 'path'),
        ];
    }

    /** @return array<string, mixed> */
    public static function templateDefaults(): array
    {
        $defaults = [];
        foreach (self::definitions() as $key => $definition) {
            if ($definition['template']) {
                $defaults[$key] = $definition['default'];
            }
        }

        return $defaults;
    }

    /** @return list<string> */
    public static function environmentKeys(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * @param array<string, mixed> $fileValues
     * @param null|array<string, mixed> $environment
     * @return array<string, array{value:mixed,source:string,definition:array{default:mixed,type:string,secret:bool,template:bool,required:bool}}>
     */
    public static function resolve(array $fileValues, ?array $environment = null): array
    {
        $environment ??= self::environmentValues();
        $resolved = [];

        foreach (self::definitions() as $key => $definition) {
            $value = $definition['default'];
            $source = 'default';
            if (array_key_exists($key, $fileValues)) {
                $value = $fileValues[$key];
                $source = 'file';
            } elseif (($legacyKey = self::legacyFallbackKey($key)) !== null
                && array_key_exists($legacyKey, $fileValues)
                && !self::isBlank($fileValues[$legacyKey])) {
                $value = $fileValues[$legacyKey];
                $source = 'legacy ' . $legacyKey;
            } elseif ($key === 'LLM_PROVIDER'
                && strtolower(trim((string) ($fileValues['DEDALUS_ANALYSIS_MODE'] ?? ''))) === 'stub') {
                $value = 'stub';
                $source = 'legacy DEDALUS_ANALYSIS_MODE';
            }

            if (array_key_exists($key, $environment)) {
                $value = $environment[$key];
                $source = 'environment override';
            }

            $resolved[$key] = [
                'value' => $value,
                'source' => $source,
                'definition' => $definition,
            ];
        }

        return $resolved;
    }

    public static function legacyFallbackKey(string $key): ?string
    {
        return match ($key) {
            'LLM_API_KEY' => 'DEDALUS_API_KEY',
            'LLM_API_BASE_URL' => 'DEDALUS_API_BASE_URL',
            'LLM_MODEL' => 'DEDALUS_MODEL',
            'LLM_TIMEOUT_SECONDS' => 'DEDALUS_TIMEOUT_SECONDS',
            'LLM_POST_ANALYSIS_PROMPT_PATH' => 'DEDALUS_POST_ANALYSIS_PROMPT_PATH',
            default => null,
        };
    }

    public static function formatValue(string $key, mixed $value): string
    {
        if (is_array($value)) {
            $redacted = [];
            foreach ($value as $name => $item) {
                $redacted[$name] = trim((string) $item) === '' ? '<empty>' : '<set>';
            }

            return var_export($redacted, true);
        }

        $definition = self::definitions()[$key] ?? null;
        if (($definition['secret'] ?? false) || preg_match('/(API_KEY|SECRET|TOKEN|PASSWORD|PRIVATE_KEY)/i', $key) === 1) {
            $stringValue = trim((string) $value);
            if ($stringValue === '') {
                return '<empty>';
            }
            if ($stringValue === 'replace-with-real-key') {
                return '<placeholder>';
            }

            return '<set>';
        }

        return var_export($value, true);
    }

    /** @param array<string, mixed> $fileValues */
    public static function additionalFileValues(array $fileValues): array
    {
        $additional = array_values(array_diff(array_keys($fileValues), array_keys(self::templateDefaults())));
        sort($additional);

        return $additional;
    }

    /**
     * @return array{default:mixed,type:string,secret:bool,template:bool,required:bool}
     */
    private static function definition(mixed $default, string $type, bool $secret = false, bool $template = false, bool $required = false): array
    {
        return compact('default', 'type', 'secret', 'template', 'required');
    }

    /** @return array<string, mixed> */
    private static function environmentValues(): array
    {
        $values = [];
        foreach (self::environmentKeys() as $key) {
            $value = getenv($key);
            if ($value !== false) {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    private static function isBlank(mixed $value): bool
    {
        return trim((string) $value) === '';
    }
}
