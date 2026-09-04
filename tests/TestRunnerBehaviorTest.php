<?php

declare(strict_types=1);

final class TestRunnerBehaviorTest
{
    public function testCompletedRunReportsTestsAtOrAboveConfiguredThreshold(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCommand(
            dirname(__DIR__),
            'FORUM_TEST_SLOW_REPORT_THRESHOLD_SECONDS=0 '
                . $this->phpCommand()
                . ' tests/run.php AgentReplyCommandTest::testAgentReplyLiveTestHelpDescribesProviderCheck'
        );

        assertSame(0, $exitCode);
        assertStringContains('All tests passed.', $stdout);
        assertStringContains('Tests at or above 0.00 seconds:', $stdout);
        assertStringContains('AgentReplyCommandTest::testAgentReplyLiveTestHelpDescribesProviderCheck', $stdout);
        assertStringContains(' ms ', $stdout);
        assertSame('', $stderr);
    }

    public function testCompletedRunDoesNotReportFastTestsBelowDefaultThreshold(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCommand(
            dirname(__DIR__),
            $this->phpCommand() . ' tests/run.php AgentReplyCommandTest::testAgentReplyLiveTestHelpDescribesProviderCheck'
        );

        assertSame(0, $exitCode);
        assertStringContains('All tests passed.', $stdout);
        assertStringNotContains('Tests at or above', $stdout);
        assertStringNotContains(' ms ', $stdout);
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
        usleep(250_000);
        proc_terminate($process, SIGINT);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        $combinedOutput = (string) $stdout . (string) $stderr;

        assertStringContains('Interrupted by signal', $combinedOutput);
        assertStringContains('Tests at or above 0.10 seconds so far:', $combinedOutput);
        assertStringContains('TestRunnerBehaviorTest::testSignalFixtureSleepsUntilInterrupted', $combinedOutput);
        assertStringContains('running when interrupted', $combinedOutput);
    }

    public function testSignalFixtureSleepsUntilInterrupted(): void
    {
        if (getenv('FORUM_TEST_RUNNER_SIGNAL_FIXTURE') !== '1') {
            return;
        }

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

    private function phpCommand(): string
    {
        return escapeshellarg(PHP_BINARY);
    }
}
