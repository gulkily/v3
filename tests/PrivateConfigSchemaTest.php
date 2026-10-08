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
}
