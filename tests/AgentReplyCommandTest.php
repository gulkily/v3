<?php

declare(strict_types=1);

final class AgentReplyCommandTest
{
    public function testRootUsageShowsConsolidatedAgentReplyCommand(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCommand(dirname(__DIR__), './v3');

        assertSame(1, $exitCode);
        assertStringContains('./v3 agent-reply cron', $stdout);
        assertStringContains('./v3 agent-reply status', $stdout);
        assertStringContains('./v3 agent-reply test', $stdout);
        assertStringContains('./v3 agent-reply test-local', $stdout);
        assertStringNotContains('agent-reply-cron', $stdout);
        assertStringNotContains('agent-reply-status', $stdout);
        assertSame('', $stderr);
    }

    public function testAgentReplyRequiresKnownSubcommand(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCommand(dirname(__DIR__), './v3 agent-reply');

        assertSame(1, $exitCode);
        assertSame('', $stdout);
        assertStringContains('./v3 agent-reply cron', $stderr);
        assertStringContains('./v3 agent-reply status', $stderr);
        assertStringContains('./v3 agent-reply test', $stderr);
        assertStringContains('./v3 agent-reply test-local', $stderr);
    }

    public function testAgentReplyLiveTestHelpDescribesProviderCheck(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCommand(dirname(__DIR__), './v3 agent-reply test --help');

        assertSame(0, $exitCode);
        assertStringContains('Sends one live structured prompt', $stdout);
        assertStringContains('validate the API key and model service', $stdout);
        assertStringContains('./v3 agent-reply test [--timeout=30]', $stdout);
        assertSame('', $stderr);
    }

    public function testAgentReplyLiveTestRejectsStubProviderConfig(): void
    {
        $secretsPath = sys_get_temp_dir() . '/forum-rewrite-agent-reply-stub-' . bin2hex(random_bytes(6)) . '.php';
        file_put_contents(
            $secretsPath,
            "<?php\n\nreturn [\n    'LLM_PROVIDER' => 'stub',\n    'LLM_API_KEY' => 'test-key',\n];\n"
        );

        try {
            [$exitCode, $stdout, $stderr] = $this->runCommand(
                dirname(__DIR__),
                'FORUM_SECRETS_PATH=' . escapeshellarg($secretsPath) . ' ./v3 agent-reply test --timeout=1'
            );

            assertSame(1, $exitCode);
            assertStringContains('Provider: stub', $stdout);
            assertStringContains('LLM_PROVIDER is stub; configure a live provider/API key', $stderr);
        } finally {
            @unlink($secretsPath);
        }
    }

    /**
     * @return array{0:int, 1:string, 2:string}
     */
    private function runCommand(string $cwd, string $command): array
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

        return [$exitCode, (string) $stdout, (string) $stderr];
    }
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(
                'Failed asserting that values are identical. Expected '
                . var_export($expected, true)
                . ' but got '
                . var_export($actual, true)
                . '.'
            );
        }
    }
}

if (!function_exists('assertStringContains')) {
    function assertStringContains(string $needle, string $haystack): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException('Failed asserting that output contains: ' . $needle);
        }
    }
}

if (!function_exists('assertStringNotContains')) {
    function assertStringNotContains(string $needle, string $haystack): void
    {
        if (str_contains($haystack, $needle)) {
            throw new RuntimeException('Failed asserting that output does not contain: ' . $needle);
        }
    }
}
