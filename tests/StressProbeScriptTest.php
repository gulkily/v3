<?php

declare(strict_types=1);

final class StressProbeScriptTest
{
    private const IMPLEMENTATIONS = ['python3' => 'stress_probe.py', 'php' => 'stress_probe.php', 'perl' => 'stress_probe.pl'];

    public function testEveryImplementationSendsNothingWithoutAMatchingConfirmation(): void
    {
        $plans = [];
        foreach (self::IMPLEMENTATIONS as $runtime => $script) {
            // 192.0.2.1 is reserved for documentation; these runs must exit before connecting.
            [$status, $output] = $this->run($runtime, $script, ['http://192.0.2.1:9', '--steps', '1,3', '--max-rps', '12.5']);
            assertSame(0, $status, $script . ': ' . $output);
            assertStringContains('Plan only; nothing was sent. Add --confirm 192.0.2.1', $output);
            $plans[$script] = $output;

            [$status, $output] = $this->run($runtime, $script, ['http://192.0.2.1:9', '--confirm', 'example.com']);
            assertSame(2, $status, $script . ': ' . $output);
            assertStringContains('does not match the target host 192.0.2.1. Nothing was sent.', $output);
        }
        // The three scripts are interchangeable: the same plan, word for word.
        assertSame(1, count(array_unique($plans)), implode("\n----\n", $plans));
    }

    public function testEveryImplementationRejectsUnsafeSettings(): void
    {
        foreach (self::IMPLEMENTATIONS as $runtime => $script) {
            foreach ([['--steps', '64'], ['--seconds', '600'], ['--max-rps', '1000'], ['--mode', 'all']] as $option) {
                [$status, $output] = $this->run($runtime, $script, array_merge(['http://192.0.2.1:9'], $option));
                assertSame(2, $status, $script . ' ' . implode(' ', $option) . ': ' . $output);
            }
            [$status] = $this->run($runtime, $script, ['http://192.0.2.1:9/threads/x']);
            assertSame(2, $status, $script);
        }
    }

    /** @param list<string> $arguments @return array{int, string} */
    private function run(string $runtime, string $script, array $arguments): array
    {
        $command = $runtime . ' ' . escapeshellarg(__DIR__ . '/../scripts/' . $script);
        foreach ($arguments as $argument) {
            $command .= ' ' . escapeshellarg($argument);
        }
        $output = [];
        $status = 0;
        exec('timeout 10 ' . $command . ' 2>&1', $output, $status);
        return [$status, implode("\n", $output)];
    }
}
