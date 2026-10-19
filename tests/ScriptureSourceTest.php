<?php

declare(strict_types=1);

use ForumRewrite\Agent\ScriptureSource;

final class ScriptureSourceTest
{
    public function testSourceCoverageAndIndependentlyKnownPassages(): void
    {
        $source = new ScriptureSource();
        assertSame(66, count($source->books()));
        assertSame('In the beginning God created the heaven and the earth.', $source->passage('Genesis', 1, 1, 1));
        assertSame('The thoughts of the diligent tend only to plenteousness; but of every one that is hasty only to want.', $source->passage('Proverbs', 21, 5, 5));
        assertSame('The LORD is my shepherd; I shall not want.', $source->passage('Psalms', 23, 1, 1));
        assertSame(6, count($source->chapter('Psalm', 23)));
        assertStringContains('For God so loved the world', $source->passage('John', 3, 16, 17));
        assertStringContains('For God sent not his Son', $source->passage('John', 3, 16, 17));
        assertStringContains('the LORD God', $source->passage('Genesis', 2, 4, 4));
    }

    public function testMissingCorruptAndUnsupportedSourcesFailClosed(): void
    {
        foreach ([['Hezekiah', 4, 12, 12], ['John', 3, 99, 99], ['John', 3, 2, 1]] as $args) {
            try { (new ScriptureSource())->passage(...$args); throw new LogicException('Accepted invalid passage'); }
            catch (RuntimeException $expected) { assertSame(false, $expected instanceof LogicException); }
        }
        foreach ([__FILE__, __FILE__ . '.missing'] as $path) {
            try { (new ScriptureSource($path))->books(); throw new LogicException('Accepted invalid source'); }
            catch (RuntimeException $expected) { assertStringContains('digest', $expected->getMessage()); }
        }
    }
}
