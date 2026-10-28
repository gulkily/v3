<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

final class PrivateConfigCommandTest
{
    public function testPrivateConfigExistingMessageMentionsView(): void
    {
        $secretsPath = sys_get_temp_dir() . '/forum-rewrite-private-config-' . bin2hex(random_bytes(6)) . '/secrets.php';
        mkdir(dirname($secretsPath), 0700, true);
        file_put_contents($secretsPath, "<?php\n\nreturn [\n"
            . "    'LLM_PROVIDER' => 'stub',\n"
            . "];\n");

        try {
            $output = $this->runCommand(
                dirname(__DIR__),
                'FORUM_SECRETS_PATH=' . escapeshellarg($secretsPath) . ' ./v3 private-config'
            );

            assertStringContains('Private config already exists at ' . $secretsPath, $output);
            assertStringContains('Run with view to inspect redacted current values.', $output);
            assertStringContains('Run with refresh-template to add current comments/examples while preserving values.', $output);
        } finally {
            @unlink($secretsPath);
            @unlink($secretsPath . '.lock');
            @rmdir(dirname($secretsPath));
        }
    }

    public function testPrivateConfigViewShowsNeutralLlmFallbacksAndRedactsHeaders(): void
    {
        $secretsPath = sys_get_temp_dir() . '/forum-rewrite-private-config-' . bin2hex(random_bytes(6)) . '/secrets.php';
        mkdir(dirname($secretsPath), 0700, true);
        file_put_contents($secretsPath, "<?php\n\nreturn [\n"
            . "    'DEDALUS_API_KEY' => 'prod-secret-value',\n"
            . "    'DEDALUS_MODEL' => 'openai/gpt-5-nano',\n"
            . "    'LLM_EXTRA_HEADERS' => ['HTTP-Referer' => 'https://example.test'],\n"
            . "];\n");

        try {
            $output = $this->runCommand(
                dirname(__DIR__),
                'FORUM_SECRETS_PATH=' . escapeshellarg($secretsPath) . ' ./v3 private-config view'
            );

            assertStringContains("LLM_PROVIDER = '' (default)", $output);
            assertStringContains('LLM_API_KEY = <set> (legacy DEDALUS_API_KEY)', $output);
            assertStringContains("LLM_MODEL = 'openai/gpt-5-nano' (legacy DEDALUS_MODEL)", $output);
            assertStringContains("'HTTP-Referer' => '<set>'", $output);
            assertStringContains('LLM_PROVIDER is required (no default). Supported values: openai, openrouter, anthropic, stub', $output);
            assertStringContains('OpenAI-compatible providers use LLM_API_BASE_URL + /v1/chat/completions', $output);
            assertStringContains('LLM_PROVIDER, LLM_MODEL, FAST_SCORING_LLM_MODEL, and LLM_EXTRA_HEADERS', $output);
            assertStringContains('FAST_SCORING_ENABLED = false (default)', $output);
            assertStringNotContains('prod-secret-value', $output);
            assertStringNotContains('https://example.test', $output);
        } finally {
            @unlink($secretsPath);
            @unlink($secretsPath . '.lock');
            @rmdir(dirname($secretsPath));
        }
    }

    public function testPrivateConfigViewUsesEnvironmentOverridesFromSharedSchema(): void
    {
        $secretsPath = sys_get_temp_dir() . '/forum-rewrite-private-config-' . bin2hex(random_bytes(6)) . '/secrets.php';
        mkdir(dirname($secretsPath), 0700, true);
        file_put_contents($secretsPath, "<?php\n\nreturn ['LLM_MODEL' => 'file-model', 'LLM_API_KEY' => 'file-secret'];\n");

        try {
            $output = $this->runCommand(
                dirname(__DIR__),
                'FORUM_SECRETS_PATH=' . escapeshellarg($secretsPath)
                    . ' LLM_MODEL=environment-model LLM_API_KEY=environment-secret ./v3 private-config view'
            );

            assertStringContains("LLM_MODEL = 'environment-model' (environment override)", $output);
            assertStringContains('LLM_API_KEY = <set> (environment override)', $output);
            assertStringNotContains('environment-secret', $output);
            assertStringNotContains('file-secret', $output);
        } finally {
            @unlink($secretsPath);
            @unlink($secretsPath . '.lock');
            @rmdir(dirname($secretsPath));
        }
    }

    public function testPrivateConfigRefreshTemplatePreservesValuesAndAddsComments(): void
    {
        $secretsPath = sys_get_temp_dir() . '/forum-rewrite-private-config-' . bin2hex(random_bytes(6)) . '/secrets.php';
        mkdir(dirname($secretsPath), 0700, true);
        file_put_contents($secretsPath, "<?php\n\nreturn [\n"
            . "    'DEDALUS_API_KEY' => 'prod-secret-value',\n"
            . "    'DEDALUS_MODEL' => 'openai/gpt-5-nano',\n"
            . "    'LLM_EXTRA_HEADERS' => ['HTTP-Referer' => 'https://example.test'],\n"
            . "    'CUSTOM_SETTING' => 'kept',\n"
            . "];\n");

        try {
            $output = $this->runCommand(
                dirname(__DIR__),
                'FORUM_SECRETS_PATH=' . escapeshellarg($secretsPath) . ' ./v3 private-config refresh-template'
            );
            $contents = (string) file_get_contents($secretsPath);
            $config = require $secretsPath;

            assertStringContains('Refreshed private config at ' . $secretsPath, $output);
            assertStringNotContains('prod-secret-value', $output);
            assertSame('', $config['LLM_PROVIDER']);
            assertSame('prod-secret-value', $config['LLM_API_KEY']);
            assertSame('openai/gpt-5-nano', $config['LLM_MODEL']);
            assertSame('https://example.test', $config['LLM_EXTRA_HEADERS']['HTTP-Referer']);
            assertSame('prod-secret-value', $config['DEDALUS_API_KEY']);
            assertSame('kept', $config['CUSTOM_SETTING']);
            assertSame(true, $config['AGENT_RESPONSE_REQUESTS_ENABLED']);
            assertSame(true, $config['LLM_CONVERSATION_RECORDING_ENABLED']);
            assertSame(true, $config['LLM_CONVERSATION_UI_ENABLED']);
            foreach ((new \ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry())->all() as $definition) {
                if ($definition->category !== 'private') {
                    continue;
                }

                assertTrue(array_key_exists($definition->environmentVariable, $config));
            }
            assertStringContains('Run ./v3 private-config refresh-template', $contents);
            assertStringContains('Provider examples. Copy the relevant values into the returned array above.', $contents);
            assertStringContains('Direct Anthropic:', $contents);
            assertStringContains('Additional existing values preserved by refresh-template.', $contents);
        } finally {
            @unlink($secretsPath);
            @unlink($secretsPath . '.lock');
            @rmdir(dirname($secretsPath));
        }
    }

    public function testGeneratedAndRefreshedConfigIncludesEverySchemaTemplateSetting(): void
    {
        $secretsPath = sys_get_temp_dir() . '/forum-rewrite-private-config-' . bin2hex(random_bytes(6)) . '/secrets.php';
        $defaults = \ForumRewrite\Support\PrivateConfigSchema::templateDefaults();

        try {
            $this->runCommand(dirname(__DIR__), './v3 private-config --path=' . escapeshellarg($secretsPath));
            $generated = require $secretsPath;
            foreach ($defaults as $key => $value) {
                assertTrue(array_key_exists($key, $generated), 'Missing generated setting: ' . $key);
                assertSame($value, $generated[$key]);
            }

            $saved = [];
            foreach ($defaults as $key => $value) {
                $saved[$key] = match (true) {
                    is_bool($value) => !$value,
                    is_int($value) => $value + 7,
                    is_float($value) => $value + 0.01,
                    is_array($value) => ['X-Fixture' => 'saved-header'],
                    default => 'saved-' . $key,
                };
            }
            $saved['FORUM_STATUS_URL'] = 'https://saved.example.test/forum/';
            $saved['CUSTOM_SETTING'] = 'keep-custom';
            file_put_contents($secretsPath, '<?php return ' . var_export($saved, true) . ';');
            $this->runCommand(dirname(__DIR__), 'FORUM_STATUS_URL=https://environment.example.test/ ./v3 private-config refresh-template --path=' . escapeshellarg($secretsPath));
            $refreshed = require $secretsPath;
            foreach ($saved as $key => $value) {
                assertTrue(array_key_exists($key, $refreshed), 'Missing refreshed setting: ' . $key);
                assertSame($value, $refreshed[$key]);
            }
            assertSame(count($saved), count($refreshed));
            assertStringContains('Status frontend URL', (string) file_get_contents($secretsPath));
        } finally {
            @unlink($secretsPath);
            @unlink($secretsPath . '.lock');
            @rmdir(dirname($secretsPath));
        }
    }

    public function testPrivateConfigEditUsesConfiguredEditorWithoutPrintingConfigValues(): void
    {
        $secretsPath = sys_get_temp_dir() . '/forum-rewrite-private-config-' . bin2hex(random_bytes(6)) . '/secrets.php';
        mkdir(dirname($secretsPath), 0700, true);
        file_put_contents($secretsPath, "<?php\n\nreturn ['LLM_API_KEY' => 'secret-value'];\n");

        try {
            $output = $this->runCommand(
                dirname(__DIR__),
                'FORUM_SECRETS_PATH=' . escapeshellarg($secretsPath) . ' EDITOR=/bin/true ./v3 private-config edit'
            );

            assertStringContains('Opening private config with /bin/true: ' . $secretsPath, $output);
            assertStringNotContains('secret-value', $output);
        } finally {
            @unlink($secretsPath);
            @unlink($secretsPath . '.lock');
            @rmdir(dirname($secretsPath));
        }
    }

    public function testPrivateConfigUpdateLlmReadsSecretOnlyFromStandardInput(): void
    {
        $secretsPath = sys_get_temp_dir() . '/forum-rewrite-private-config-' . bin2hex(random_bytes(6)) . '/secrets.php';
        mkdir(dirname($secretsPath), 0700, true);
        file_put_contents($secretsPath, "<?php\n\nreturn ['CUSTOM_SETTING' => 'kept', 'LLM_API_KEY' => 'old-secret', 'FORUM_STATUS_URL' => 'https://saved.example.test/', 'SCRIPTURE_SOURCE_PATH' => '/private/scripture'];\n");
        $request = json_encode(['values' => [
            'LLM_PROVIDER' => 'openai', 'LLM_API_KEY' => 'new-secret', 'LLM_API_BASE_URL' => 'https://api.openai.com', 'LLM_MODEL' => 'gpt-5-nano', 'LLM_TIMEOUT_SECONDS' => 30,
        ]], JSON_THROW_ON_ERROR);

        try {
            $output = $this->runCommand(dirname(__DIR__), 'FORUM_SECRETS_PATH=' . escapeshellarg($secretsPath) . ' ./v3 private-config update-llm', $request);
            $config = require $secretsPath;
            assertStringContains('Updated private config', $output);
            assertStringNotContains('new-secret', $output);
            assertSame('new-secret', $config['LLM_API_KEY']);
            assertSame('https://saved.example.test/', $config['FORUM_STATUS_URL']);
            assertSame('/private/scripture', $config['SCRIPTURE_SOURCE_PATH']);
            assertSame('kept', $config['CUSTOM_SETTING']);
        } finally {
            @unlink($secretsPath);
            @unlink($secretsPath . '.lock');
            @rmdir(dirname($secretsPath));
        }
    }

    private function runCommand(string $cwd, string $command, string $input = ''): string
    {
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($command, $descriptor, $pipes, $cwd);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to run command.');
        }

        fwrite($pipes[0], $input);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            throw new RuntimeException("Command failed with {$exitCode}: {$stderr}");
        }

        return (string) $stdout;
    }
}

if (!function_exists('assertStringContains')) {
    function assertStringContains(string $needle, string $haystack): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException("Expected to find {$needle} in output.");
        }
    }
}

if (!function_exists('assertStringNotContains')) {
    function assertStringNotContains(string $needle, string $haystack): void
    {
        if (str_contains($haystack, $needle)) {
            throw new RuntimeException("Did not expect to find {$needle} in output.");
        }
    }
}
