<?php

declare(strict_types=1);

final class OpenPgpProductionCanaryCommandTest
{
    public function testBrowserCanaryHarnessCoversSuccessAndRepeatPromptFailure(): void
    {
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open('node --test tests/browser/OpenPgpProductionCanaryTest.mjs', $descriptor, $pipes, dirname(__DIR__));
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start the browser canary harness.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        assertSame(0, $exitCode);
        assertStringContains('# pass 3', (string) $stdout);
        assertSame('', (string) $stderr);
    }
}
