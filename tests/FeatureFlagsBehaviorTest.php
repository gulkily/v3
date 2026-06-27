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
}
