<?php

declare(strict_types=1);

final class OfflineReadingDiagnosticCommandTest
{
    public function testDiagnoseReportsValidCliSnapshotAndBuildCommand(): void
    {
        $root = sys_get_temp_dir() . '/forum-offline-diagnose-' . bin2hex(random_bytes(6));
        $release = $root . '/releases/release-test';
        try {
            mkdir($release . '/offline', 0700, true);
            file_put_contents($release . '/offline/snapshot.sqlite3', "SQLite format 3\000fixture");
            symlink('releases/release-test', $root . '/current');

            [$exitCode, $stdout, $stderr] = $this->runCommand(
                dirname(__DIR__),
                './v3 offline diagnose --static-html-root=' . escapeshellarg($root),
            );

            assertSame(0, $exitCode);
            assertStringContains('Local offline snapshot:', $stdout);
            assertStringContains('SQLite header valid', $stdout);
            assertStringContains('active static release fallback', $stdout);
            assertStringContains('Local SQLite runtime:', $stdout);
            assertStringContains('FORUM_STATIC_HTML_ROOT=', $stdout);
            assertSame('', $stderr);
        } finally {
            @unlink($root . '/current');
            @unlink($release . '/offline/snapshot.sqlite3');
            @rmdir($release . '/offline');
            @rmdir($release);
            @rmdir($root . '/releases');
            @rmdir($root);
        }
    }

    public function testDiagnosePrefersIndependentPublishedSnapshot(): void
    {
        $root = sys_get_temp_dir() . '/forum-offline-diagnose-' . bin2hex(random_bytes(6));
        $release = $root . '/releases/release-test';
        try {
            mkdir($release . '/offline', 0700, true);
            mkdir($root . '/offline', 0700, true);
            file_put_contents($release . '/offline/snapshot.sqlite3', "SQLite format 3\000release fixture");
            file_put_contents($root . '/offline/snapshot.sqlite3', "SQLite format 3\000fast fixture");
            symlink('releases/release-test', $root . '/current');

            [$exitCode, $stdout] = $this->runCommand(
                dirname(__DIR__),
                './v3 offline diagnose --static-html-root=' . escapeshellarg($root),
            );

            assertSame(0, $exitCode);
            assertStringContains($root . '/offline/snapshot.sqlite3', $stdout);
            assertStringContains('independent publication', $stdout);
        } finally {
            @unlink($root . '/current');
            @unlink($root . '/offline/snapshot.sqlite3');
            @rmdir($root . '/offline');
            @unlink($release . '/offline/snapshot.sqlite3');
            @rmdir($release . '/offline');
            @rmdir($release);
            @rmdir($root . '/releases');
            @rmdir($root);
        }
    }

    public function testDiagnoseHonorsEnvironmentStaticRootForSelectedProfile(): void
    {
        $root = sys_get_temp_dir() . '/forum-offline-diagnose-' . bin2hex(random_bytes(6));
        $release = $root . '/releases/release-test';
        try {
            mkdir($release . '/offline', 0700, true);
            file_put_contents($release . '/offline/snapshot.sqlite3', "SQLite format 3\000fixture");
            symlink('releases/release-test', $root . '/current');

            [$exitCode, $stdout, $stderr] = $this->runCommand(
                dirname(__DIR__),
                'FORUM_SITE_ID=chouse FORUM_STATIC_HTML_ROOT=' . escapeshellarg($root) . ' ./v3 offline diagnose',
            );

            assertSame(0, $exitCode);
            assertStringContains('Site profile: chouse', $stdout);
            assertStringContains('Static artifact root: ' . $root, $stdout);
            assertSame('', $stderr);
        } finally {
            @unlink($root . '/current');
            @unlink($release . '/offline/snapshot.sqlite3');
            @rmdir($release . '/offline');
            @rmdir($release);
            @rmdir($root . '/releases');
            @rmdir($root);
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
}
