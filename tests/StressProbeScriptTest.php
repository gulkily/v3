<?php

declare(strict_types=1);

final class StressProbeScriptTest
{
    public function testSendsNothingWithoutAMatchingConfirmation(): void
    {
        // 192.0.2.1 is reserved for documentation; these runs must exit before connecting.
        [$status, $output] = $this->run(['http://192.0.2.1:9']);
        assertSame(0, $status, $output);
        assertStringContains('Plan only; nothing was sent. Add --confirm 192.0.2.1', $output);

        [$status, $output] = $this->run(['http://192.0.2.1:9', '--confirm', 'example.com']);
        assertSame(2, $status, $output);
        assertStringContains('does not match the target host 192.0.2.1. Nothing was sent.', $output);
    }

    public function testRejectsUnsafeSettings(): void
    {
        foreach ([['--steps', '64'], ['--seconds', '600'], ['--max-rps', '1000']] as $option) {
            [$status, $output] = $this->run(array_merge(['http://192.0.2.1:9'], $option));
            assertSame(2, $status, $output);
        }
        [$status] = $this->run(['http://192.0.2.1:9/threads/x']);
        assertSame(2, $status);
    }

    /** @param list<string> $arguments @return array{int, string} */
    private function run(array $arguments): array
    {
        $command = 'python3 ' . escapeshellarg(__DIR__ . '/../scripts/stress_probe.py');
        foreach ($arguments as $argument) {
            $command .= ' ' . escapeshellarg($argument);
        }
        $output = [];
        $status = 0;
        exec('timeout 10 ' . $command . ' 2>&1', $output, $status);
        return [$status, implode("\n", $output)];
    }
}
