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

    public function testFdpSyncRejectsAnExistingNonCanonicalRemoteBeforeFetching(): void
    {
        $repositoryRoot = sys_get_temp_dir() . '/v3_fdp_sync_' . bin2hex(random_bytes(8));
        $unexpectedRemote = sys_get_temp_dir() . '/unexpected_fdp_source';
        mkdir($repositoryRoot, 0700, true);

        try {
            file_put_contents($repositoryRoot . '/.gitignore', "\n");
            mkdir($repositoryRoot . '/docs/fdp', 0700, true);
            file_put_contents($repositoryRoot . '/docs/fdp/README.md', "seed\n");

            [$initCode] = $this->runCommand($repositoryRoot, 'git init --quiet');
            [$nameCode] = $this->runCommand($repositoryRoot, 'git config user.name test');
            [$emailCode] = $this->runCommand($repositoryRoot, 'git config user.email test@example.invalid');
            [$addCode] = $this->runCommand($repositoryRoot, 'git add .');
            [$commitCode] = $this->runCommand($repositoryRoot, 'git commit --quiet -m seed');
            [$remoteCode] = $this->runCommand(
                $repositoryRoot,
                'git remote add fdp ' . escapeshellarg($unexpectedRemote),
            );

            assertSame(0, $initCode);
            assertSame(0, $nameCode);
            assertSame(0, $emailCode);
            assertSame(0, $addCode);
            assertSame(0, $commitCode);
            assertSame(0, $remoteCode);

            [$exitCode, $stdout, $stderr] = $this->runCommand(
                dirname(__DIR__),
                './v3 fdp sync --repository-root=' . escapeshellarg($repositoryRoot),
            );

            assertSame(1, $exitCode);
            assertSame('', $stdout);
            assertStringContains("FDP remote URL is not the canonical source: {$unexpectedRemote}", $stderr);
            assertStringContains('Expected: https://github.com/gulkily/fdp.git', $stderr);
            assertStringContains('--remote-url=https://github.com/gulkily/fdp.git', $stderr);

            [$urlCode, $urlOutput] = $this->runCommand($repositoryRoot, 'git remote get-url fdp');
            assertSame(0, $urlCode);
            assertSame($unexpectedRemote, trim($urlOutput));
        } finally {
            $this->removeDirectory($repositoryRoot);
        }
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

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $childPath = $path . '/' . $entry;
            if (is_dir($childPath)) {
                $this->removeDirectory($childPath);
            } else {
                unlink($childPath);
            }
        }

        rmdir($path);
    }
}
