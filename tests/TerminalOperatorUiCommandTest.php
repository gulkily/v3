<?php

declare(strict_types=1);

final class TerminalOperatorUiCommandTest
{
    public function testHelpDoesNotRequireAnInteractiveTerminal(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCommand('./v3 tui --help');

        assertSame(0, $exitCode);
        assertStringContains('Usage: ./v3 tui', $stdout);
        assertSame('', $stderr);
    }

    public function testNonInteractiveInputPrintsCliFallbackWithoutWork(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCommand('./v3 tui');

        assertSame(1, $exitCode);
        assertSame('', $stdout);
        assertStringContains('requires interactive stdin, stdout, and stderr', $stderr);
        assertStringContains('Run ./v3 status and ./v3 private-config view instead.', $stderr);
    }

    public function testMissingWhiptailPrintsCliFallback(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCommand('V3_TUI_WHIPTAIL=missing-whiptail ./v3 tui');

        assertSame(1, $exitCode);
        assertSame('', $stdout);
        assertStringContains('requires whiptail', $stderr);
        assertStringContains('Run ./v3 status and ./v3 private-config view instead.', $stderr);
    }

    /** @return array{int,string,string} */
    private function runCommand(string $command): array
    {
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, dirname(__DIR__));
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to run command.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), (string) $stdout, (string) $stderr];
    }
}
