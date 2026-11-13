<?php

declare(strict_types=1);

use ForumRewrite\Docs\PublicMarkdownRenderer;

require __DIR__ . '/../autoload.php';

final class PostMarkdownRendererTest
{
    public function testRendersTheCommonPostSyntax(): void
    {
        $html = PublicMarkdownRenderer::renderPost("# Deploys\n\nIntro **bold** and *em* with `code`.\n\n- one\n- two\n\n1. first\n2. second\n\n> quoted\n\n```\nraw <b>\n```\n\n| A | B |\n| --- | --- |\n| 1 | 2 |");
        assertStringContains('<h3>Deploys</h3>', $html);
        assertStringContains('<strong>bold</strong>', $html);
        assertStringContains('<em>em</em>', $html);
        assertStringContains('<code>code</code>', $html);
        assertStringContains('<ul><li><p>one</p></li><li><p>two</p></li></ul>', $html);
        assertStringContains('<ol start="1">', $html);
        assertStringContains('<blockquote><p>quoted</p></blockquote>', $html);
        assertStringContains('raw &lt;b&gt;', $html);
        assertStringContains('<table>', $html);
    }

    public function testPostTablesCarryFitColumnsAndZoomControlsButDocTablesDoNot(): void
    {
        $source = "| A | B | C | D |\n| --- | --- | --- | --- |\n| 1 | 2 | 3 | 4 |";
        $post = PublicMarkdownRenderer::renderPost($source);
        assertStringContains('<div class="zoom-table zoom-table--post" data-zoom-kind="post"><div class="zoom-buttons zoom-table-buttons" role="group" aria-label="Text size">', $post);
        assertStringContains('data-table-zoom="out"', $post);
        assertStringContains('data-table-zoom="fit"', $post);
        assertStringContains('data-table-zoom="page"', $post);
        assertStringContains('data-table-zoom="in"', $post);
        assertStringContains('<div class="zoom-table-scroll" style="--table-min: ', $post);
        assertStringContains('</tbody></table></div></div>', $post);
        assertStringContains('</table></div></div>', $post);
        $doc = PublicMarkdownRenderer::render($source);
        assertStringNotContains('zoom-table', $doc);
        assertStringNotContains('--table-min', $doc);
        assertStringContains('<div class="docs-table-scroll" tabindex="0" role="region" aria-label="Table"><table>', $doc);
    }

    public function testPostTableMinimumWidthFollowsEachColumnsLongestWord(): void
    {
        $minimum = static function (string $source): float {
            preg_match('/--table-min: ([0-9.]+)"/', PublicMarkdownRenderer::renderPost($source), $m);
            return (float) ($m[1] ?? -1);
        };
        // Header "What" (4 bold chars) and body "reports" (7 chars) set the first column; the
        // second column's long prompt only counts its longest word, "behavior," (9 chars).
        $labels = $minimum("| What you want | What to ask |\n| --- | --- |\n| Group bug reports | Find the reports that describe the same behavior, and link them. |");
        assertSame(round(7 * 0.55 + 1.1 + 9 * 0.55 + 1.1, 1), $labels);
        // More columns of short words need less room than two columns of long ones.
        $narrow = $minimum("| a | b | c | d | e |\n| --- | --- | --- | --- | --- |\n| 1 | 2 | 3 | 4 | 5 |");
        assertSame(round(5 * (0.6 + 1.1), 1), $narrow);
        // A URL counts as 20 characters (it may break in the table) and markup isn't counted.
        $link = $minimum("| Source | x |\n| --- | --- |\n| [https://example.test/a/very/long/path/to/a/page](https://example.test/x) | x |");
        $code = $minimum("| Route | x |\n| --- | --- |\n| `GET /api/get_thread?thread_id=thread-20261111034513` | x |");
        assertSame(round(20 * 0.55 + 1.1 + 0.6 + 1.1, 1), $link);
        assertSame(round(20 * 0.55 + 1.1 + 0.6 + 1.1, 1), $code);
    }

    public function testHeadingsNestInsideThePostWithoutPageIds(): void
    {
        assertSame('<h3>Top</h3>', PublicMarkdownRenderer::renderPost('# Top'));
        assertSame('<h6>Deep</h6>', PublicMarkdownRenderer::renderPost('##### Deep'));
        assertStringNotContains(' id="', PublicMarkdownRenderer::renderPost("## A\n\n## A"));
    }

    public function testDisablingTagLinksKeepsAuthoredLinksAndDoesNotLeakToNextRender(): void
    {
        $body = '**#meta** [tag](https://example.test/tags/meta)';
        $off = \ForumRewrite\Docs\PublicMarkdownRenderer::renderPost($body, false);
        assertStringNotContains('href="/tags/', $off);
        assertStringContains('<strong>#meta</strong>', $off);
        assertStringContains('href="https://example.test/tags/meta"', $off);
        assertStringContains('href="/tags/meta"', \ForumRewrite\Docs\PublicMarkdownRenderer::renderPost($body));
    }

    public function testHashtagLinesLinkToTagPagesWithoutBecomingHeadings(): void
    {
        assertSame('<p><a href="/tags/v3">#v3</a> <a href="/tags/changelog">#changelog</a> <a href="/tags/meta">#meta</a></p>', PublicMarkdownRenderer::renderPost('#v3 #changelog #meta'));
    }

    public function testHashtagsPreserveMarkdownCodeLinksAndDocs(): void
    {
        $source = "**#meta** and (#needs-review).\n\n`#inline`\n\n```\n#fenced\n```\n\n[#label](https://example.test/#fragment) https://example.test/#bare\n\n![#alt](https://example.test/image.png)";
        $html = PublicMarkdownRenderer::renderPost($source);
        assertStringContains('<strong><a href="/tags/meta">#meta</a></strong>', $html);
        assertStringContains('(<a href="/tags/needs-review">#needs-review</a>).', $html);
        assertStringContains('<code>#inline</code>', $html);
        assertStringContains('#fenced', $html);
        assertStringContains('<a href="https://example.test/#fragment" rel="nofollow ugc noopener">#label</a>', $html);
        assertStringContains('<a href="https://example.test/#bare" rel="nofollow ugc noopener">https://example.test/#bare</a>', $html);
        assertSame(2, substr_count($html, 'href="/tags/'));
        assertStringNotContains('href="/tags/', PublicMarkdownRenderer::render($source));
    }

    public function testSingleNewlinesStayLineBreaks(): void
    {
        assertSame("<p>Sources:<br>\nline two</p>", PublicMarkdownRenderer::renderPost("Sources:\nline two"));
    }

    public function testBareAndExplicitHttpLinksAreMarkedUserContent(): void
    {
        $html = PublicMarkdownRenderer::renderPost('See https://zenmemes.com/threads/a?x=1&y=2. Also [docs](https://example.com/a_b).');
        assertStringContains('<a href="https://zenmemes.com/threads/a?x=1&amp;y=2" rel="nofollow ugc noopener">https://zenmemes.com/threads/a?x=1&amp;y=2</a>.', $html);
        assertStringContains('<a href="https://example.com/a_b" rel="nofollow ugc noopener">docs</a>.', $html);
        assertSame(2, substr_count($html, '<a '));
    }

    public function testUrlsInCodeAndParenthesesAreHandled(): void
    {
        assertStringNotContains('<a ', PublicMarkdownRenderer::renderPost('`https://example.com`'));
        assertStringContains('(<a href="https://example.com/x" rel="nofollow ugc noopener">https://example.com/x</a>)', PublicMarkdownRenderer::renderPost('(https://example.com/x)'));
    }

    public function testArithmeticAndLoneAsterisksStayLiteral(): void
    {
        assertSame('<p>2 * 3 * 4</p>', PublicMarkdownRenderer::renderPost('2 * 3 * 4'));
        assertSame('<p>a*b*c</p>', PublicMarkdownRenderer::renderPost('a*b*c'));
    }

    public function testHostileInputNeverProducesMarkupOrUnsafeLinks(): void
    {
        $inputs = [
            '<script>alert(1)</script>',
            '<img src=x onerror=alert(1)>',
            '[x](javascript:alert(1))',
            '[x](data:text/html,hi)',
            '[x](//evil.example/path)',
            '[x](/docs/README.md)',
            '[x](https://a.example" onmouseover="alert(1))',
            '![pic](https://a.example/p.png)',
            'javascript:alert(1)',
            "<!-- hidden -->\nvisible",
            "---\ntitle: x\n---\nbody",
            '**[x](https://a.example)**<iframe>',
        ];
        foreach ($inputs as $input) {
            $html = PublicMarkdownRenderer::renderPost($input);
            foreach (['<script', '<img', '<iframe', 'href="javascript:', 'href="data:', 'href="//', 'href="/docs'] as $unsafe) {
                if (str_contains($html, $unsafe)) {
                    throw new RuntimeException('Unsafe output for ' . $input . ': ' . $html);
                }
            }
            // Event-handler text may appear escaped as prose, never as an attribute of a real tag.
            if (preg_match('/<[a-z][^>]*\son\w+\s*=/i', $html) === 1) {
                throw new RuntimeException('Event handler attribute for ' . $input . ': ' . $html);
            }
        }
        assertStringContains('&lt;!-- hidden --&gt;', PublicMarkdownRenderer::renderPost("<!-- hidden -->\nvisible"));
        assertStringContains('title: x', PublicMarkdownRenderer::renderPost("---\ntitle: x\n---\nbody"));
        assertStringContains('[x](javascript:alert(1))', PublicMarkdownRenderer::renderPost('[x](javascript:alert(1))'));
    }

    public function testDocsModeIsUnchangedAfterAPostRender(): void
    {
        $source = "# Title\n\nSee [guide](https://example.com) and https://example.com.\n<!-- private -->";
        $before = PublicMarkdownRenderer::render($source);
        PublicMarkdownRenderer::renderPost($source);
        assertSame($before, PublicMarkdownRenderer::render($source));
        assertStringContains('<h1 id="title">Title</h1>', $before);
        assertStringNotContains('private', $before);
    }
}

if (!function_exists('assertStringNotContains')) {
    function assertStringNotContains(string $needle, string $haystack): void
    {
        if (str_contains($haystack, $needle)) {
            throw new RuntimeException('Failed asserting that output does not contain: ' . $needle);
        }
    }
}
