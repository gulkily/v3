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
