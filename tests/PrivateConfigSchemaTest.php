<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Support\PrivateConfigSchema;

final class PrivateConfigSchemaTest
{
    public function testTemplateDefaultsRetainTheExistingGeneratedConfigSurface(): void
    {
        $defaults = PrivateConfigSchema::templateDefaults();

        assertSame('', $defaults['LLM_PROVIDER']);
        assertSame('replace-with-real-key', $defaults['LLM_API_KEY']);
        assertSame(60, $defaults['LLM_TIMEOUT_SECONDS']);
        assertSame(false, $defaults['FAST_SCORING_ENABLED']);
        assertSame('openai/gpt-5-nano', $defaults['FAST_SCORING_LLM_MODEL']);
        assertSame(true, $defaults['LLM_CONVERSATION_UI_ENABLED']);
        assertSame(false, array_key_exists('DEDALUS_API_KEY', $defaults));
        assertSame(true, in_array('PRIVATE_MESSAGE_DATABASE_PATH', PrivateConfigSchema::environmentKeys(), true));
    }

    public function testResolveTracksFileLegacyAndEnvironmentPrecedence(): void
    {
        $resolved = PrivateConfigSchema::resolve(
            [
                'DEDALUS_API_KEY' => 'legacy-secret',
                'DEDALUS_ANALYSIS_MODE' => 'stub',
                'LLM_MODEL' => 'file-model',
            ],
            ['LLM_MODEL' => 'environment-model'],
        );

        assertSame('legacy-secret', $resolved['LLM_API_KEY']['value']);
        assertSame('legacy DEDALUS_API_KEY', $resolved['LLM_API_KEY']['source']);
        assertSame('stub', $resolved['LLM_PROVIDER']['value']);
        assertSame('legacy DEDALUS_ANALYSIS_MODE', $resolved['LLM_PROVIDER']['source']);
        assertSame('environment-model', $resolved['LLM_MODEL']['value']);
        assertSame('environment override', $resolved['LLM_MODEL']['source']);
    }

    public function testSchemaRedactsSecretsAndPreservesAdditionalFileKeys(): void
    {
        assertSame('<set>', PrivateConfigSchema::formatValue('LLM_API_KEY', 'private-value'));
        assertSame('<placeholder>', PrivateConfigSchema::formatValue('LLM_API_KEY', 'replace-with-real-key'));
        assertStringNotContains('example.test', PrivateConfigSchema::formatValue('LLM_EXTRA_HEADERS', ['X-Title' => 'https://example.test']));
        assertSame(['CUSTOM_SETTING'], PrivateConfigSchema::additionalFileValues(['CUSTOM_SETTING' => 'kept', 'LLM_MODEL' => 'model']));
    }

    public function testLlmEditorMetadataValidatesAndRedactsChanges(): void
    {
        assertSame('https://api.openai.com', PrivateConfigSchema::llmPresets()['openai']['LLM_API_BASE_URL']);
        assertSame([], PrivateConfigSchema::validateLlmConnection([
            'LLM_PROVIDER' => 'openai', 'LLM_API_BASE_URL' => 'https://api.openai.com', 'LLM_MODEL' => 'gpt-5-nano', 'LLM_TIMEOUT_SECONDS' => '60',
        ]));
        assertStringContains('Base URL is required unless the provider is stub.', implode(' ', PrivateConfigSchema::validateLlmConnection([
            'LLM_PROVIDER' => 'custom', 'LLM_API_BASE_URL' => '', 'LLM_MODEL' => '', 'LLM_TIMEOUT_SECONDS' => '0',
        ])));
        $diff = PrivateConfigSchema::redactedLlmDiff(['LLM_API_KEY' => 'old-secret'], ['LLM_API_KEY' => 'new-secret']);
        assertSame('<set>', $diff['LLM_API_KEY']['before']);
        assertSame('<set>', $diff['LLM_API_KEY']['after']);
        assertSame(['LLM_MODEL'], PrivateConfigSchema::lockedLlmKeys([], ['LLM_MODEL' => 'locked-model']));
    }
}
