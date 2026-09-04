<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Codex\CodexHandoffRunner;
use ForumRewrite\Codex\CodexHandoffStore;

final class CodexHandoffRunnerTest
{
    public function testRunnerCompletesClaimedApprovedHandoffWithFakeCodexExec(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new CodexHandoffStore($pdo);
        $handoff = $this->approvedHandoff($store);
        $claimed = $store->claimNextApproved();
        $logPath = sys_get_temp_dir() . '/forum-codex-runner-' . bin2hex(random_bytes(6)) . '.log';
        $fakeCodex = $this->fakeCodexExecutable($logPath, 0);

        try {
            $result = (new CodexHandoffRunner($store, dirname(__DIR__), $fakeCodex))->runApproved($claimed[0]);
            $stored = $store->findByHandoffId($handoff['handoff_id']);
            $log = (string) file_get_contents($logPath);

            assertSame('completed', $result['status']);
            assertSame(0, $result['exit_code']);
            assertSame('completed', $stored['status']);
            assertStringContains("exec\n--json\n--sandbox\nworkspace-write\n# Draft", $log);
            assertSame(0, $stored['status_context']['completed']['exit_code']);
        } finally {
            @unlink($fakeCodex);
            @unlink($logPath);
        }
    }

    public function testRunnerMarksFailedHandoffWhenCodexExecFails(): void
    {
        $store = new CodexHandoffStore(new PDO('sqlite::memory:'));
        $handoff = $this->approvedHandoff($store);
        $claimed = $store->claimNextApproved();
        $logPath = sys_get_temp_dir() . '/forum-codex-runner-' . bin2hex(random_bytes(6)) . '.log';
        $fakeCodex = $this->fakeCodexExecutable($logPath, 7);

        try {
            $result = (new CodexHandoffRunner($store, dirname(__DIR__), $fakeCodex))->runApproved($claimed[0]);
            $stored = $store->findByHandoffId($handoff['handoff_id']);

            assertSame('failed', $result['status']);
            assertSame(7, $result['exit_code']);
            assertSame('failed', $stored['status']);
            assertSame(7, $stored['status_context']['failed']['exit_code']);
            assertStringContains('fake codex stderr', $stored['status_context']['failed']['stderr_tail']);
        } finally {
            @unlink($fakeCodex);
            @unlink($logPath);
        }
    }

    public function testRunCodexHandoffsScriptDryRunReportsApprovedQueue(): void
    {
        $databasePath = sys_get_temp_dir() . '/forum-codex-runner-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $pdo = new PDO('sqlite:' . $databasePath);
        $store = new CodexHandoffStore($pdo);
        $this->approvedHandoff($store);

        try {
            [$exitCode, $stdout, $stderr] = $this->runCommand(
                dirname(__DIR__),
                'php scripts/run_codex_handoffs.php --dry-run --database-path=' . escapeshellarg($databasePath)
            );

            assertSame(0, $exitCode);
            assertStringContains('Codex handoff dry run', $stdout);
            assertStringContains('Approved handoffs: 1', $stdout);
            assertSame('', $stderr);
        } finally {
            @unlink($databasePath);
        }
    }

    public function testV3CodexHandoffCommandUsageIsAvailable(): void
    {
        [$rootExitCode, $rootStdout, $rootStderr] = $this->runCommand(dirname(__DIR__), './v3');
        [$subExitCode, $subStdout, $subStderr] = $this->runCommand(dirname(__DIR__), './v3 codex-handoff');

        assertSame(1, $rootExitCode);
        assertStringContains('./v3 codex-handoff run', $rootStdout);
        assertStringContains('./v3 codex-handoff test-local', $rootStdout);
        assertSame('', $rootStderr);
        assertSame(1, $subExitCode);
        assertSame('', $subStdout);
        assertStringContains('./v3 codex-handoff run', $subStderr);
        assertStringContains('./v3 codex-handoff test-local', $subStderr);
    }

    /**
     * @return array<string, mixed>
     */
    private function approvedHandoff(CodexHandoffStore $store): array
    {
        $handoff = $store->requestForPost([
            'post_id' => 'root-001',
            'thread_id' => 'root-001',
            'subject' => 'Codex runner',
            'body' => 'Run the approved Codex handoff.',
        ], [
            'identity_id' => 'openpgp:requester',
            'profile_slug' => 'requester',
            'username' => 'requester',
        ]);
        $draft = $store->updateStatus($handoff['handoff_id'], 'draft_ready', [
            'user_story' => 'As an approved local user, I want Codex to run so that approved work is executed.',
            'fdp_step1' => '# Step 1',
            'confidence_summary' => 'High confidence.',
            'draft_text' => "# Draft\n\nApproved local Codex work.",
        ]);

        return $store->updateStatus($draft['handoff_id'], 'approved', [
            'approved_by_identity_id' => 'openpgp:requester',
            'approved_by_profile_slug' => 'requester',
            'approved_by_username' => 'requester',
        ]);
    }

    private function fakeCodexExecutable(string $logPath, int $exitCode): string
    {
        $path = sys_get_temp_dir() . '/fake-codex-' . bin2hex(random_bytes(6));
        file_put_contents($path, "#!/usr/bin/env php\n<?php\nfile_put_contents(" . var_export($logPath, true) . ", implode(\"\\n\", array_slice(\$argv, 1)) . \"\\n\");\nfwrite(STDOUT, \"fake codex stdout\\n\");\nfwrite(STDERR, \"fake codex stderr\\n\");\nexit({$exitCode});\n");
        chmod($path, 0700);

        return $path;
    }

    /**
     * @return array{0:int,1:string,2:string}
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
