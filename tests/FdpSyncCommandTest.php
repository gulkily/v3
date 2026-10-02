<?php

declare(strict_types=1);

final class FdpSyncCommandTest
{
    public function testRootUsageAndFdpHelpDescribeTheSyncCommand(): void
    {
        [$rootCode, $rootOutput, $rootError] = $this->runCommand(dirname(__DIR__), './v3');
        [$helpCode, $helpOutput, $helpError] = $this->runCommand(dirname(__DIR__), './v3 fdp sync --help');

        assertSame(1, $rootCode);
        assertStringContains('./v3 fdp sync', $rootOutput);
        assertSame('', $rootError);
        assertSame(0, $helpCode);
        assertStringContains('./v3 fdp sync', $helpOutput);
        assertStringContains('--repository-root=', $helpOutput);
        assertSame('', $helpError);
    }

    public function testFdpSyncRejectsUnknownOptionsBeforeChangingAnything(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCommand(dirname(__DIR__), './v3 fdp sync --unknown');

        assertSame(1, $exitCode);
        assertSame('', $stdout);
        assertStringContains('Unknown fdp sync option: --unknown', $stderr);
        assertStringContains('./v3 fdp sync', $stderr);
    }

    /** @return array{int,string,string} */
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

        return [proc_close($process), (string) $stdout, (string) $stderr];
    }
}
