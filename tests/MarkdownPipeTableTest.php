<?php

declare(strict_types=1);

use ForumRewrite\Docs\PublicMarkdownRenderer;

require __DIR__ . '/../autoload.php';

final class MarkdownPipeTableTest
{
    public function testEscapedAndCodePipesKeepTheirCells(): void
    {
        $source = <<<'MD'
| Name | Value |
| --- | --- |
| a\|b | `c|d` |
| empty | |
| | last |
| | |
MD;
        $html = PublicMarkdownRenderer::render($source);
        assertSame(1, substr_count($html, '<table>'));
        assertSame(8, substr_count($html, '<td>'));
        assertStringContains('<td>a|b</td><td><code>c|d</code></td>', $html);
        assertStringContains('<td></td><td></td>', $html);
        assertStringContains('<td>empty</td><td></td>', $html);
        assertStringContains('<td></td><td>last</td>', $html);
    }

    public function testOptionalOuterPipesAndEmptyCells(): void
    {
        foreach (["A | B\n--- | ---\nx | y", "| A | B\n| --- | ---\n| x | y", "A | B |\n--- | --- |\nx | y |"] as $source) {
            assertStringContains('<td>x</td><td>y</td>', PublicMarkdownRenderer::render($source));
        }
        $html = PublicMarkdownRenderer::render("|| B |\n| --- | --- |\n|| x |");
        assertStringContains('<th scope="col"></th>', $html);
        assertStringContains('<td></td><td>x</td>', $html);
    }

    public function testEvenAndOddBackslashRunsDoNotMoveDelimiters(): void
    {
        $even = PublicMarkdownRenderer::render(<<<'MD'
| A | B |
| --- | --- |
| two\\| next |
MD);
        assertStringContains('<td>two\\\\</td><td>next</td>', $even);
        $odd = PublicMarkdownRenderer::render(<<<'MD'
| A | B |
| --- | --- |
| three\\\|inside | next |
MD);
        assertStringContains('<td>three\\\\|inside</td><td>next</td>', $odd);
    }

    public function testAmbiguousBlocksKeepAllTheirSource(): void
    {
        foreach ([
            "| A | B |\n| --- | --- |\n| one | two | extra |",
            "| A | B |\n| --- | --- |\n| missing |",
            "| A | B |\n| -- | --- |\n| one | two |",
            "| A | B |\n| --- | --- |\n| `unclosed | two |",
            "| A | B |\n| --- | --- |\n| ``wide|code`` | two |",
            "| A |\n| --- |\n| one |",
        ] as $source) {
            $html = PublicMarkdownRenderer::render($source . "\n\nAfter");
            assertTrue(!str_contains($html, '<table>'), $source);
            assertStringContains(htmlspecialchars($source, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $html);
            assertStringContains('<p>After</p>', $html);
        }
    }

    public function testTablesDoNotConsumeAdjacentBlocksOrFencedExamples(): void
    {
        $source = "| A | B |\n| --- | --- |\n| one | two |\n# Following | heading\n\n```\n| Code | Only |\n| --- | --- |\n```";
        $html = PublicMarkdownRenderer::render($source);
        assertSame(1, substr_count($html, '<table>'));
        assertStringContains('Following | heading</h1>', $html);
        assertStringContains("| Code | Only |\n| --- | --- |", $html);
        foreach (["- List\n\n  | A | B |\n  | --- | --- |\n  | x | y |", "> A | B\n> --- | ---\n> x | y", "    | A | B |\n    | --- | --- |"] as $nested) {
            assertTrue(!str_contains(PublicMarkdownRenderer::render($nested), '<table>'));
        }
    }

    public function testCellLinksUseCanonicalPolicyWithoutFetchingImages(): void
    {
        $source = <<<'MD'
| Safe | Unavailable |
| --- | --- |
| [CLI](v3_cli.md#delete-a-canonical-record) | [bad](javascript:alert) |
| [fragment](#target) | [escape](../../../../etc/passwd) |
| [web](https://example.test/) | ![image](https://example.test/i.png) |
| **bold** and *em* | <script>alert(1)</script> |
MD;
        $html = PublicMarkdownRenderer::render($source, 'docs/reference/author_post_retraction.md');
        assertStringContains('href="/docs/reference/v3_cli.md#delete-a-canonical-record"', $html);
        assertStringContains('href="#target"', $html);
        assertStringContains('href="https://example.test/"', $html);
        assertStringContains('bad (source unavailable)', $html);
        assertStringContains('escape (source unavailable)', $html);
        assertStringContains('image (image unavailable)', $html);
        assertStringContains('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        assertStringContains('<strong>bold</strong> and <em>em</em>', $html);
        foreach (['<script', '<img', 'href="javascript:', 'href="../../'] as $unsafe) {
            assertTrue(!str_contains($html, $unsafe));
        }
    }

    public function testTableLinksCannotEscapeThroughSymlinks(): void
    {
        $root = sys_get_temp_dir() . '/markdown-table-boundary-' . bin2hex(random_bytes(6));
        mkdir($root . '/docs', 0777, true);
        symlink('/etc/passwd', $root . '/docs/escape.md');
        try {
            $html = PublicMarkdownRenderer::render("| A | B |\n| --- | --- |\n| [bad](escape.md) | ok |", 'docs/source.md', $root);
            assertStringContains('bad (source unavailable)', $html);
            assertTrue(!str_contains($html, 'href='));
        } finally {
            unlink($root . '/docs/escape.md');
            rmdir($root . '/docs');
            rmdir($root);
        }
    }
}
