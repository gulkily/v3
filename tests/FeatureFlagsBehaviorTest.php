<?php

declare(strict_types=1);

final class FeatureFlagsBehaviorTest
{
    public function testFeatureFlagsScriptHasValidSyntax(): void
    {
        $command = sprintf(
            'node --check %s',
            escapeshellarg(__DIR__ . '/../public/assets/feature_flags.js')
        );

        exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException('Feature flags script syntax check failed: ' . implode("\n", $output));
        }
    }

    public function testFeatureFlagsScriptCapturesFormDataBeforeDisablingControls(): void
    {
        $script = (string) file_get_contents(__DIR__ . '/../public/assets/feature_flags.js');
        $bodyOffset = strpos($script, 'var body = new URLSearchParams(new FormData(form)).toString();');
        $pendingOffset = strpos($script, 'setPending(form, true);');

        assertSame(true, $bodyOffset !== false);
        assertSame(true, $pendingOffset !== false);
        assertSame(true, $bodyOffset < $pendingOffset);
    }
}
