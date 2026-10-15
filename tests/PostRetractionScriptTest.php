<?php

declare(strict_types=1);

final class PostRetractionScriptTest
{
    private const SCRIPT = __DIR__ . '/../public/assets/post_retraction.js';

    public function testScriptsHaveValidSyntax(): void
    {
        foreach ([self::SCRIPT, __DIR__ . '/../public/assets/browser_signing.js'] as $script) {
            exec('node --check ' . escapeshellarg($script) . ' 2>&1', $output, $exitCode);
            assertSame(0, $exitCode, 'Syntax check failed for ' . basename($script) . ': ' . implode("\n", $output));
        }
    }

    public function testRetractButtonConfirmsSubmitsAndReportsEachOutcome(): void
    {
        $command = 'node ' . escapeshellarg(__DIR__ . '/fixtures/post_retraction_script_check.js') . ' ' . escapeshellarg(self::SCRIPT) . ' 2>&1';
        exec($command, $output, $exitCode);
        assertSame(0, $exitCode, implode("\n", $output));
        $result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertSame(['submit' => 0, 'confirm' => 1, 'reload' => 0], $result['declined']['calls']);
        assertSame(false, $result['declined']['disabled']);

        assertSame(['submit' => 1, 'confirm' => 1, 'reload' => 1], $result['complete']['calls']);
        assertSame(true, $result['complete']['feedbackHidden']);
        assertSame(true, $result['complete']['initialHidden']);
        assertSame('Retracting…', $result['slow']['progress']);
        assertSame(1, $result['slow']['calls']['reload']);
        assertSame(true, $result['complete']['disabled']);

        assertSame(['submit' => 1, 'confirm' => 1, 'reload' => 0], $result['partial']['calls']);
        assertSame('Retry retraction', $result['partial']['label']);
        assertSame(false, $result['partial']['disabled']);
        assertSame('Retracted. Site update incomplete.', $result['partial']['feedback']);

        assertSame('Retraction failed. Try again.', $result['failure']['feedback']);
        assertSame(false, $result['failure']['disabled']);
        assertSame('Retraction failed. Try again.', $result['thrown']['feedback']);
        assertSame(false, $result['thrown']['disabled']);

        assertSame(0, $result['unavailable']['calls']['submit']);
        assertSame('Signing unavailable. Refresh and try again.', $result['unavailable']['feedback']);
    }
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(
                ($message !== '' ? $message . ' ' : '')
                . 'Failed asserting that values are identical. Expected ' . var_export($expected, true)
                . ' but got ' . var_export($actual, true) . '.'
            );
        }
    }
}
