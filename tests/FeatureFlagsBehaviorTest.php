<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;

final class FeatureFlagsBehaviorTest
{
    public function testPresentationOrderCoversEveryFlagOnceInItsAssignedGroup(): void
    {
        $registry = new FeatureFlagRegistry();
        $order = FeatureFlagRegistry::PRESENTATION_ORDER;
        assertSame(['ACCESS', 'AUTHORING', 'EXPERIENCE', 'RENDERING', 'BACKUP', 'FASTMOD', 'DEDALUS', 'LLM'], array_keys($order));
        $keys = array_merge(...array_values($order));
        assertSame(count($keys), count(array_unique($keys)));
        $registered = array_map(static fn ($definition) => $definition->key, $registry->all());
        sort($keys); sort($registered);
        assertSame($registered, $keys);
        foreach ($order as $group => $entries) {
            foreach ($entries as $position => $key) {
                $definition = $registry->get($key);
                assertSame($group, $definition->groupKey(), $key);
                if ($definition->requiresEnabledFlag === null) continue;
                $parent = array_search($definition->requiresEnabledFlag, $entries, true);
                assertTrue($parent !== false && $parent < $position, $key . ' follows its parent');
                for ($index = $parent + 1; $index < $position; $index++) {
                    assertSame($definition->requiresEnabledFlag, $registry->get($entries[$index])->requiresEnabledFlag,
                        $key . ' stays beside its parent and siblings');
                }
            }
        }
    }

    public function testRenderedOrderIgnoresRegistryInputOrder(): void
    {
        $registry = new FeatureFlagRegistry();
        $states = (new FeatureFlagEvaluator())->all();
        assertSame($registry->presentationGroups($states), $registry->presentationGroups(array_reverse($states)));
        exec('php ' . escapeshellarg(__DIR__ . '/Support/feature_flags_toolbar_fixture.php') . ' canonical', $lines, $status);
        assertSame(0, $status);
        preg_match_all('/data-flag-key="([^"]+)"/', implode("\n", $lines), $matches);
        assertSame(array_merge(...array_values(FeatureFlagRegistry::PRESENTATION_ORDER)), $matches[1]);
    }

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
