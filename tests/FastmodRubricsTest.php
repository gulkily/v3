<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Scoring\FastmodRubrics;

final class FastmodRubricsTest
{
    public function testCompositionIsOrderedAndDisabledRubricsAreAbsent(): void
    {
        $rubrics = new FastmodRubrics(dirname(__DIR__));
        $both = $rubrics->compose(['moderation', 'meta']);
        assertSame(['meta', 'moderation'], $both['ids']);
        assertSame($both, $rubrics->compose(['meta', 'moderation']));
        $single = $rubrics->compose(['meta']);
        assertSame(false, str_contains($single['prompt'], '- Moderation:'));
        assertSame(false, $single['revision'] === $both['revision']);
        assertSame(true, str_contains($single['prompt'], 'application support'));
    }

    public function testEmptyUnknownAndDuplicateSelectionsFail(): void
    {
        foreach ([[], ['missing'], ['meta', 'meta'], [['nested']]] as $ids) {
            try {
                (new FastmodRubrics(dirname(__DIR__)))->compose($ids);
                throw new RuntimeException('Invalid selection accepted.');
            } catch (InvalidArgumentException) {
                assertSame(true, true);
            }
        }
    }

    public function testNewTemplatesAndEditsNeedNoSpecialCase(): void
    {
        $root = sys_get_temp_dir() . '/fastmod-' . bin2hex(random_bytes(6));
        mkdir($root . '/prompts/fastmod', 0777, true);
        try {
            file_put_contents($root . '/prompts/fastmod_introduction.txt', 'intro');
            file_put_contents($root . '/prompts/fastmod/custom.txt', '- custom');
            $rubrics = new FastmodRubrics($root);
            $first = $rubrics->compose(['custom']);
            file_put_contents($root . '/prompts/fastmod/custom.txt', '- revised');
            assertSame(false, $first['revision'] === $rubrics->compose(['custom'])['revision']);
            file_put_contents($root . '/prompts/fastmod/custom.txt', '');
            try {
                $rubrics->compose(['custom']);
                throw new LogicException('Empty template accepted.');
            } catch (RuntimeException) {
                assertSame(true, true);
            }
        } finally {
            unlink($root . '/prompts/fastmod/custom.txt');
            unlink($root . '/prompts/fastmod_introduction.txt');
            rmdir($root . '/prompts/fastmod');
            rmdir($root . '/prompts');
            rmdir($root);
        }
    }
}
