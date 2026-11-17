<?php

declare(strict_types=1);

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(($message !== '' ? $message . ' ' : '') . 'Expected ' . var_export($expected, true) . ' but got ' . var_export($actual, true) . '.');
        }
    }
}

final class KeyBootstrapScriptTest
{
    public function testKeyBootstrapScriptContract(): void
    {
        $output = [];
        $exitCode = 0;
        exec('node ' . escapeshellarg(__DIR__ . '/browser/key_bootstrap_contract.cjs') . ' ' . escapeshellarg(__DIR__ . '/../public/assets/key_bootstrap.js') . ' 2>&1', $output, $exitCode);
        assertSame(0, $exitCode, implode("\n", $output));
    }
}
