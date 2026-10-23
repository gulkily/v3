<?php

declare(strict_types=1);

final class CheckoutsCommandTest
{
    public function testRealGitOperatorAcceptance(): void
    {
        $pipes = [];
        $process = proc_open(
            ['python3', __DIR__ . '/test_checkouts.py'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]],
            $pipes,
            dirname(__DIR__),
        );
        assertTrue(is_resource($process), 'Could not start checkout acceptance tests (requires python3 and Git).');
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        assertSame(0, proc_close($process), $output);
    }
}
