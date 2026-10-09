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

    public function testFeatureFlagsScriptSignsPreparedChangesAfterCapturingFormData(): void
    {
        $script = (string) file_get_contents(__DIR__ . '/../public/assets/feature_flags.js');
        $fieldsOffset = strpos($script, 'var fields = Object.fromEntries(new FormData(form).entries());');
        $pendingOffset = strpos($script, 'setPending(form, true);');

        assertSame(true, $fieldsOffset !== false);
        assertSame(true, $pendingOffset !== false);
        assertSame(true, $fieldsOffset < $pendingOffset);
        assertSame(true, str_contains($script, '"/api/prepare_feature_flag_change"'));
        assertSame(true, str_contains($script, '"/api/finalize_feature_flag_change"'));
        assertSame(true, str_contains($script, 'window.ForumBrowserSigning.signCanonicalRecord'));
    }
}
