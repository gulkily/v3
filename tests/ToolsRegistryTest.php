<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Tools\ToolsPageSupport;

final class ToolsRegistryTest
{
    public function testEveryToolIsGroupedOnceWithAStableTone(): void
    {
        $groups = array_keys(ToolsPageSupport::groups());
        $tools = ToolsPageSupport::registry();
        assertSame(15, count($tools));
        assertSame(count($tools), count(array_unique(array_column($tools, 'key'))));
        assertSame(count($tools), count(array_unique(array_column($tools, 'href'))));
        assertSame(count($tools), count(array_unique(array_column($tools, 'tone'))));
        assertSame(false, in_array('/account/key/', array_column($tools, 'href'), true), 'Account is in the main navigation');

        // Tools are listed group by group, in the groups' order.
        $seen = array_values(array_unique(array_column($tools, 'group')));
        assertSame($groups, $seen);
        foreach ($tools as $tool) {
            assertSame(1, preg_match('/^[a-z][a-z-]*$/', $tool['tone']), $tool['key']);
        }
    }

    public function testTheToolsBarMarksEachGroupBoundary(): void
    {
        $options = ToolsPageSupport::navOptions('fastmod');
        assertSame(array_column(ToolsPageSupport::registry(), 'href'), array_column($options, 'href'));
        assertSame(count(ToolsPageSupport::groups()) - 1, count(array_filter(array_column($options, 'group_start'))));
        assertSame(false, $options[0]['group_start']);
        assertSame(['fastmod'], array_column(array_filter($options, static fn (array $o): bool => $o['is_active']), 'key'));
    }
}
