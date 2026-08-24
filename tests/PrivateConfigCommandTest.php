<?php

declare(strict_types=1);

final class PrivateConfigCommandTest
{
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

            assertStringContains("LLM_PROVIDER = 'dedalus' (default)", $output);
            assertStringContains('LLM_API_KEY = <set> (legacy DEDALUS_API_KEY)', $output);
            assertStringContains("LLM_MODEL = 'openai/gpt-5-nano' (legacy DEDALUS_MODEL)", $output);
            assertStringContains("'HTTP-Referer' => '<set>'", $output);
            assertStringContains('Supported LLM_PROVIDER values: dedalus, openai, openrouter, anthropic, stub', $output);
            assertStringContains('OpenAI-compatible providers use LLM_API_BASE_URL + /v1/chat/completions', $output);
            assertStringContains('LLM_PROVIDER, LLM_MODEL, and LLM_EXTRA_HEADERS', $output);
            assertStringNotContains('prod-secret-value', $output);
            assertStringNotContains('https://example.test', $output);
        } finally {
            @unlink($secretsPath);
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
            assertSame('dedalus', $config['LLM_PROVIDER']);
            assertSame('prod-secret-value', $config['LLM_API_KEY']);
            assertSame('openai/gpt-5-nano', $config['LLM_MODEL']);
            assertSame('https://example.test', $config['LLM_EXTRA_HEADERS']['HTTP-Referer']);
            assertSame('prod-secret-value', $config['DEDALUS_API_KEY']);
            assertSame('kept', $config['CUSTOM_SETTING']);
            assertStringContains('Run ./v3 private-config refresh-template', $contents);
            assertStringContains('Provider examples. Copy the relevant values into the returned array above.', $contents);
            assertStringContains('Direct Anthropic:', $contents);
            assertStringContains('Additional existing values preserved by refresh-template.', $contents);
        } finally {
            @unlink($secretsPath);
            @rmdir(dirname($secretsPath));
        }
    }

    private function runCommand(string $cwd, string $command): string
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
