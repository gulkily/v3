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

    public function testUnsupportedIndentationFallsBackAsAWholeBlock(): void
    {
        $deep = '';
        for ($depth = 0; $depth < 9; $depth++) { $deep .= str_repeat('  ', $depth) . '- Level ' . $depth . "\n"; }
        foreach (["- Parent\n - Ambiguous", "- Parent\n\t- Tab", "-\tTab marker", "  - No root", "- Parent\n  - Child\n   - Unknown column", "-     Indented code", "12345678901234567890. Huge", rtrim($deep)] as $source) {
            $html = PublicMarkdownRenderer::render($source . "\n\nAfter");
            assertSame(1, substr_count($html, 'aria-label="List source"'), $source);
            assertTrue(!str_contains($html, '<li'), $source);
            assertStringContains(htmlspecialchars($source, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $html);
            assertStringContains('<p>After</p>', $html);
        }
    }

    public function testEmbeddedBlocksRetainSourceAndFollowingTables(): void
    {
        foreach (["- Parent\n  > Quote", "- Parent\n  | A | B |\n  | --- | --- |\n  | x | y |", "- Parent\n  A | B\n  --- | ---", "- Parent\n      code", "- Parent\n  ```php\nraw unindented example\n  ```", "- ```\nraw example\n```", "- Parent\n  ~~~~\nraw ~~~ example\n  ~~~~"] as $source) {
            $html = PublicMarkdownRenderer::render($source . "\n\n# After\n\n| C | D |\n| --- | --- |\n| one | two |");
            assertSame(1, substr_count($html, 'aria-label="List source"'), $source);
            assertStringContains(htmlspecialchars($source, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $html);
            assertStringContains('<h1 id="after">After</h1>', $html);
            assertSame(1, substr_count($html, '<table>'));
        }
    }

    public function testCommentSafetyAndFencedExampleBytes(): void
    {
        $html = PublicMarkdownRenderer::render("- Visible <!--PRIVATE-->\n  text\n- Other");
        assertTrue(!str_contains($html, 'PRIVATE'));
        assertStringContains('text</p>', $html);
        $html = PublicMarkdownRenderer::render("- Parent <!--PRIVATE\nSECRET-->\n  > Quote <!--HIDDEN-->");
        foreach (['PRIVATE', 'SECRET', 'HIDDEN'] as $private) { assertTrue(!str_contains($html, $private)); }
        assertStringContains('aria-label="List source"', $html);
        $source = "- Parent\n  ```\n<!--example bytes-->\n- ```\n<!--more bytes-->\n  ```";
        $html = PublicMarkdownRenderer::render($source);
        assertStringContains(htmlspecialchars($source, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $html);
        $standalone = "<!--hidden-->```\n<!--example-->\n```";
        assertStringContains('&lt;!--example--&gt;', PublicMarkdownRenderer::render($standalone));
    }

    public function testListInlineSafetyAndLiteralTasks(): void
    {
        $source = "- [x] [CLI](v3_cli.md#delete-a-canonical-record)\n  - [ ] [bad](javascript:bad) and ![image](https://example.test/i.png)\n\n  <script>bad()</script>\n- [escape](../../../../etc/passwd)";
        $html = PublicMarkdownRenderer::render($source, 'docs/reference/author_post_retraction.md');
        assertStringContains('href="/docs/reference/v3_cli.md#delete-a-canonical-record"', $html);
        assertStringContains('[x]', $html);
        assertStringContains('[ ] bad (source unavailable)', $html);
        assertStringContains('image (image unavailable)', $html);
        assertStringContains('&lt;script&gt;bad()&lt;/script&gt;', $html);
        assertStringContains('escape (source unavailable)', $html);
        foreach (['<script', '<img', '<input', 'href="javascript:', 'href="../../'] as $unsafe) { assertTrue(!str_contains($html, $unsafe)); }
    }

    public function testListLinksCannotEscapeViaSymlinks(): void
    {
        $root = sys_get_temp_dir() . '/markdown-list-boundary-' . bin2hex(random_bytes(6));
        mkdir($root . '/docs', 0777, true);
        symlink('/etc/passwd', $root . '/docs/escape.md');
        try {
            $html = PublicMarkdownRenderer::render("- [bad](escape.md)", 'docs/source.md', $root);
            assertStringContains('bad (source unavailable)', $html);
            assertTrue(!str_contains($html, 'href='));
        } finally { unlink($root . '/docs/escape.md'); rmdir($root . '/docs'); rmdir($root); }
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
