<?php

declare(strict_types=1);

final class TestRunnerBehaviorTest
{
    public function testCompletedResultsShowRunTimeAndIndividualDuration(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCommand(
            dirname(__DIR__),
            $this->phpCommand() . ' tests/fixtures/test_runner_timing.php RunnerTimingFixtureTest'
        );

        assertSame(1, $exitCode);
        assertSame(2, preg_match_all('/^\[(\d+\.\d{2})s\] PASS RunnerTimingFixtureTest::(testPass|testFast) \[(\d+\.\d{2})s\]$/m', $stdout, $passes, PREG_SET_ORDER));
        assertSame(1, preg_match('/^\[(\d+\.\d{2})s\] FAIL RunnerTimingFixtureTest::testFailure - First failure line \[(\d+\.\d{2})s\]\nDiagnostic detail\n\n$/', $stderr, $failure));
        assertSame('testPass', $passes[0][2]);
        assertSame('testFast', $passes[1][2]);
        // Constructor/setup belongs to run time, not individual test duration.
        assertTrue((float) $passes[0][1] - (float) $passes[0][3] >= 0.09);
        assertTrue((float) $passes[0][3] >= 0.02);
        assertTrue((float) $passes[1][1] >= (float) $passes[0][1]);
        assertTrue((float) $failure[1] >= (float) $passes[1][1]);
        assertTrue((float) $failure[1] >= (float) $failure[2]);
        assertStringContains("fixture output\n[", $stdout);
        assertStringContains('Summary: 3 run, 2 passed, 1 failed', $stdout);
        assertStringContains('RunnerTimingFixtureTest::testFailure - First failure line (+2 more lines, see FAIL output above)', $stdout);
        assertStringNotContains('] FAIL ', $stdout);
        assertStringNotContains('] PASS ', $stderr);
    }

    public function testFilteredFastResultHasTimingWithoutSlowReport(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCommand(
            dirname(__DIR__),
            $this->phpCommand() . ' tests/fixtures/test_runner_timing.php RunnerTimingFixtureTest::testFast'
        );

        assertSame(0, $exitCode);
        assertSame('', $stderr);
        assertSame(1, preg_match('/^\[\d+\.\d{2}s\] PASS RunnerTimingFixtureTest::testFast \[\d+\.\d{2}s\]$/m', $stdout));
        assertStringContains('Summary: 1 run, 1 passed, 0 failed', $stdout);
        assertStringNotContains('Tests at or above', $stdout);
        assertStringNotContains('testPass', $stdout);
        assertStringNotContains('testFailure', $stdout);
        assertStringNotContains('elapsed', $stdout);
        assertStringNotContains('duration', $stdout);
    }

    public function testCompletedRunReportsTestsAtOrAboveConfiguredThreshold(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCommand(
            dirname(__DIR__),
            'FORUM_TEST_SLOW_REPORT_THRESHOLD_SECONDS=0 '
                . $this->phpCommand()
                . ' tests/run.php AgentReplyCommandTest::testAgentReplyLiveTestHelpDescribesProviderCheck'
        );

        assertSame(0, $exitCode);
        assertStringContains('Summary: 1 run, 1 passed, 0 failed', $stdout);
        assertStringContains('Tests at or above 0.00 seconds:', $stdout);
        assertStringContains('AgentReplyCommandTest::testAgentReplyLiveTestHelpDescribesProviderCheck', $stdout);
        assertStringContains(' ms ', $stdout);
        assertSame(1, preg_match('/^\[\d+\.\d{2}s\] PASS AgentReplyCommandTest::testAgentReplyLiveTestHelpDescribesProviderCheck \[\d+\.\d{2}s\]$/m', $stdout));
        assertSame('', $stderr);
    }

    public function testCompletedRunDoesNotReportFastTestsBelowDefaultThreshold(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCommand(
            dirname(__DIR__),
            './v3 test AgentReplyCommandTest::testAgentReplyLiveTestHelpDescribesProviderCheck'
        );

        assertSame(0, $exitCode);
        assertStringContains('Summary: 1 run, 1 passed, 0 failed', $stdout);
        assertStringNotContains('Tests at or above', $stdout);
        assertStringNotContains(' ms ', $stdout);
        assertSame(1, preg_match('/^\[\d+\.\d{2}s\] PASS AgentReplyCommandTest::testAgentReplyLiveTestHelpDescribesProviderCheck \[\d+\.\d{2}s\]$/m', $stdout));
        assertSame('', $stderr);
    }

    public function testInterruptedRunReportsCurrentSlowTest(): void
    {
        if (!function_exists('pcntl_signal') || !defined('SIGINT')) {
            return;
        }

        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open(
            [
                PHP_BINARY,
                'tests/run.php',
                'TestRunnerBehaviorTest::testSignalFixtureSleepsUntilInterrupted',
            ],
            $descriptor,
            $pipes,
            dirname(__DIR__),
            [
                'FORUM_TEST_RUNNER_SIGNAL_FIXTURE' => '1',
                'FORUM_TEST_SLOW_REPORT_THRESHOLD_SECONDS' => '0.1',
            ] + $_ENV
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to run command.');
        }

        fclose($pipes[0]);
        stream_set_timeout($pipes[1], 5);
        $ready = fgets($pipes[1]);
        usleep(150_000);
        proc_terminate($process, SIGINT);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        $combinedOutput = (string) $stdout . (string) $stderr;

        assertSame("SIGNAL_FIXTURE_READY\n", $ready);
        assertStringContains('Interrupted by signal', $combinedOutput);
        assertStringContains('Tests at or above 0.10 seconds so far:', $combinedOutput);
        assertStringContains('TestRunnerBehaviorTest::testSignalFixtureSleepsUntilInterrupted', $combinedOutput);
        assertStringContains('running when interrupted', $combinedOutput);
        assertStringNotContains('] PASS ', $combinedOutput);
        assertStringNotContains('] FAIL ', $combinedOutput);
    }

    public function testSignalFixtureSleepsUntilInterrupted(): void
    {
        if (getenv('FORUM_TEST_RUNNER_SIGNAL_FIXTURE') !== '1') {
            return;
        }

        fwrite(STDOUT, "SIGNAL_FIXTURE_READY\n");
        sleep(10);
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
        $historyPath = sys_get_temp_dir() . '/forum-runner-behavior-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $command = 'FORUM_TEST_HISTORY_DB_PATH=' . escapeshellarg($historyPath) . ' ' . $command;
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
        @unlink($historyPath);

        return [$exitCode, (string) $stdout, (string) $stderr];
    }

    private function phpCommand(): string
    {
        return escapeshellarg(PHP_BINARY);
    }
}
