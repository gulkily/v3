<?php

declare(strict_types=1);

use ForumRewrite\Docs\PublicMarkdownRenderer;

require __DIR__ . '/../autoload.php';

final class MarkdownListStructureTest
{
    public function testFlatParagraphOwnershipAndLiteralNumbers(): void
    {
        $html = PublicMarkdownRenderer::render("3. First\n   wrapped\n\n   Another paragraph.\n3. Repeated\n8. Gap\n1. Restart\n\nAfter\n\n# Heading");
        assertStringContains('<ol start="3"><li value="3"><p>First' . "\nwrapped</p><p>Another paragraph.</p></li>", $html);
        assertStringContains('<li value="8"><p>Gap</p></li><li value="1"><p>Restart</p></li></ol>', $html);
        assertStringContains('<p>After</p>', $html);
        assertSame(2, substr_count($html, 'value="3"'));
        assertStringContains('<h1 id="heading">Heading</h1>', $html);
    }

    public function testSiblingMarkerChangesAndBlankLines(): void
    {
        $html = PublicMarkdownRenderer::render("- One\n\n- Two\n+ Plus\n* Star\n2) Ordered\n5) Gap\n1. Dot");
        assertStringContains('<ul><li><p>One</p></li><li><p>Two</p></li></ul>', $html);
        assertSame(3, substr_count($html, '<ul>'));
        assertStringContains('<ol start="2"><li value="2"><p>Ordered</p></li><li value="5"><p>Gap</p></li></ol>', $html);
        assertStringContains('<ol start="1">', $html);
    }

    public function testMixedNestingAndParentParagraphAfterChildren(): void
    {
        $html = PublicMarkdownRenderer::render("- Parent\n  3. Child\n     wrapped\n     - Grandchild\n  8. Second child\n\n  Parent again.\n- Sibling");
        assertStringContains('<li><p>Parent</p><ol start="3">', $html);
        assertStringContains('<li value="3"><p>Child' . "\nwrapped</p><ul><li><p>Grandchild</p></li></ul></li>", $html);
        assertStringContains('<li value="8"><p>Second child</p></li></ol><p>Parent again.</p></li>', $html);
        assertStringContains('<li><p>Sibling</p></li></ul>', $html);
    }

    public function testNumberWidthAndEightLevels(): void
    {
        $html = PublicMarkdownRenderer::render("10. Parent\n    - Child\n      text\n    + Other child\n11. Next");
        assertStringContains('<li value="10"><p>Parent</p><ul>', $html);
        assertStringContains('<li value="11"><p>Next</p></li>', $html);
        $source = '';
        for ($depth = 0; $depth < 8; $depth++) { $source .= str_repeat('  ', $depth) . '- Level ' . $depth . "\n"; }
        assertSame(8, substr_count(PublicMarkdownRenderer::render($source), '<ul>'));
    }

    public function testAccountPlanRiskBulletsHaveAnOwner(): void
    {
        $html = PublicMarkdownRenderer::render(file_get_contents(dirname(__DIR__) . '/docs/plans/account_key_page_redesign/account_key_page_redesign_step3_development_plan.md'));
        assertStringContains('<li><p>Risks or open questions:</p><ul><li><p>Impact:', $html);
    }

    public function testRetractionGuideKeepsSixProcedureParagraphs(): void
    {
        $html = PublicMarkdownRenderer::render(file_get_contents(dirname(__DIR__) . '/docs/reference/author_post_retraction.md'), 'docs/reference/author_post_retraction.md');
        assertSame(1, substr_count($html, '<ol start="1">'));
        preg_match_all('/<li value="([0-9]+)">/', $html, $numbers);
        assertSame(['1', '2', '3', '4', '5', '6'], $numbers[1]);
        assertStringContains("retraction record, signs it with the acting identity, and submits it.</p></li>", $html);
        assertSame(4, substr_count($html, '<table>'));
    }
}
