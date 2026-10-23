<?php

declare(strict_types=1);

use ForumRewrite\Application;
use ForumRewrite\Docs\PublicMarkdownRenderer;

require __DIR__ . '/../autoload.php';

final class MarkdownTaskListTest
{
    public function testTaskStateNamesAndParagraphBoundaries(): void
    {
        $source = "- [ ] Pending\n  wrapped\n\n  Later paragraph [x] unchanged\n  - [x] Child\n- [X] Done\n- ordinary\n+ [ ] Plus";
        $html = PublicMarkdownRenderer::render($source);
        assertSame(4, substr_count($html, '<input type="checkbox" disabled'));
        assertSame(2, substr_count($html, ' disabled checked'));
        assertSame(4, substr_count($html, 'class="docs-task-item"'));
        assertStringContains('<label class="docs-task-label"><input type="checkbox" disabled>Pending' . "\nwrapped</label></p>", $html);
        assertStringContains('<p>Later paragraph [x] unchanged</p><ul>', $html);
        assertStringContains('disabled checked>Child</label></p>', $html);
        assertStringContains('<li><p>ordinary</p></li>', $html);
        assertTrue(!str_contains($html, ' id='));
        assertTrue(!str_contains($html, ' name='));
    }

    public function testUnsupportedMarkerPositionsRemainLiteral(): void
    {
        foreach ([
            '[x] Paragraph', '1. [x] Ordered', '2) [ ] Ordered', '- [z] Unknown', '- [x]No space',
            '- [] Missing', '- [ ]', '- [ ] ', '- [ ] ** **', "- [ ] \u{00a0}",
            '- \\[x] Escaped', '- `[x]` Code', '- Prose [x] later', "- First\n\n  [x] Later paragraph",
            "```\n- [x] Example\n```", "| A | B |\n| --- | --- |\n| [x] Cell | [ ] Cell |",
        ] as $source) {
            assertTrue(!str_contains(PublicMarkdownRenderer::render($source), '<input'), $source);
        }
    }

    public function testWholeListFallbackKeepsRawTasksAndCodeBytes(): void
    {
        $source = "- [x] Parent\n  ```\n- [ ] Code example\n  ```\n- [ ] Other";
        $html = PublicMarkdownRenderer::render($source);
        assertStringContains(htmlspecialchars($source, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $html);
        assertStringContains('aria-label="List source"', $html);
        assertTrue(!str_contains($html, '<input'));
        $html = PublicMarkdownRenderer::render('- [ ] <!--PRIVATE-->');
        assertTrue(!str_contains($html, 'PRIVATE'));
        assertTrue(!str_contains($html, '<input'));
    }

    public function testTaskLabelsRetainCanonicalInlineSafetyAndLinks(): void
    {
        $source = "- [x] **Safe** [`CLI`](v3_cli.md#delete-a-canonical-record) <script>bad()</script>\n- [ ] [bad](javascript:bad) ![image](https://example.test/i.png)";
        $html = PublicMarkdownRenderer::render($source, 'docs/reference/author_post_retraction.md');
        assertStringContains('<strong>Safe</strong>', $html);
        assertStringContains('href="/docs/reference/v3_cli.md#delete-a-canonical-record"', $html);
        assertStringContains('&lt;script&gt;bad()&lt;/script&gt;', $html);
        assertStringContains('bad (source unavailable)', $html);
        assertStringContains('image (image unavailable)', $html);
        foreach (['<script', '<img', 'href="javascript:', '<form', ' onchange=', ' onclick='] as $unsafe) { assertTrue(!str_contains($html, $unsafe)); }
    }

    public function testRepeatedTasksHaveNoDuplicateIdentifiersAndKeepOrderedValues(): void
    {
        $html = PublicMarkdownRenderer::render("- [ ] Same\n- [x] Same\n\n3. [x] Ordered literal\n3. Repeated number\n8. Gap");
        assertSame(2, substr_count($html, '<input type="checkbox" disabled'));
        assertTrue(!str_contains($html, ' id='));
        assertStringContains('<li value="3"><p>[x] Ordered literal</p>', $html);
        assertStringContains('<li value="8"><p>Gap</p>', $html);
    }

    public function testOrdinaryChecklistReaderDisplaysDisabledTasks(): void
    {
        $database = sys_get_temp_dir() . '/task-docs-' . bin2hex(random_bytes(6)) . '.sqlite3';
        try {
            $app = new Application(dirname(__DIR__), __DIR__ . '/fixtures/parity_minimal_v1', $database);
            ob_start();
            $app->handle('GET', '/docs/plans/agent_api_onboarding_checklist.md');
            $html = (string) ob_get_clean();
            assertStringContains('<input type="checkbox" disabled>', $html);
            assertStringContains('Audit zenmemes content for unsigned posts', $html);
        } finally { @unlink($database); }
    }
}
