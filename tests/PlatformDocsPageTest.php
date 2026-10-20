<?php

declare(strict_types=1);

use ForumRewrite\Application;
use ForumRewrite\Docs\PublicMarkdownRenderer;

require __DIR__ . '/../autoload.php';

final class PlatformDocsPageTest
{
    private string $databasePath;

    public function __construct()
    {
        $this->databasePath = sys_get_temp_dir() . '/forum-rewrite-platform-docs-' . bin2hex(random_bytes(6)) . '.sqlite3';
    }

    public function testPublicIndexListsCataloguedDocuments(): void
    {
        $html = $this->render('/docs/');
        $diagram = explode('</ol>', (string) strstr($html, '<ol class="docs-flow"'), 2)[0];

        assertTrue(str_contains($html, '<h1>zenmemes Platform Docs</h1>'));
        assertTrue(str_contains($html, 'Extending the Platform'));
        assertTrue(str_contains($html, '<h2>How it works</h2>'));
        assertTrue(strpos($html, 'docs-transparency') < strpos($html, 'docs-topics'));
        assertTrue(strpos($html, 'docs-topics') < strpos($html, 'docs-overview-heading'));
        assertTrue(str_contains($html, 'data-docs-library hidden'));
        assertTrue(!str_contains($html, 'Starting guides'));
        assertTrue(str_contains($html, 'Each entry below shows the path of the file it is rendered from'));
        foreach (\ForumRewrite\Docs\PlatformDocsCatalog::entries() as $entry) {
            assertTrue(str_contains($html, htmlspecialchars($entry['description'], ENT_QUOTES)), $entry['path']);
            assertTrue(str_contains($html, '<code class="docs-entry__path">' . $entry['path'] . '</code>'), $entry['path']);
        }
        assertTrue(str_contains($html, 'href="/docs/?group=Plans+and+history">Plans and history ('));
        assertTrue(str_contains($html, 'href="/docs/?page=1">Browse all docs'));
        assertTrue((bool) preg_match('/placeholder="Search all [0-9,]+ docs…"/', $html));
        $results = $this->render('/docs/?group=Workflow');
        assertTrue(str_contains($results, 'data-docs-library>'));
        assertTrue(str_contains($results, 'docs-topics" data-docs-landing hidden'));
        assertTrue(str_contains($this->render('/docs/?q=retraction'), 'value="retraction"'));
        assertTrue(str_contains($html, '<ol class="docs-flow" aria-label="zenmemes public architecture">'));
        assertSame(4, substr_count($diagram, 'class="docs-flow__node'));
        assertSame(3, substr_count($diagram, '<span class="docs-flow__arrow" aria-hidden="true"></span>'));
        assertTrue(str_contains($diagram, "Member's browser"));
        assertTrue(str_contains($diagram, 'signed write'));
        assertTrue(str_contains($diagram, 'static HTML'));
        assertTrue(!str_contains($diagram, './v3 rebuild'));
        assertTrue(!str_contains($diagram, './v3 build-static'));
        assertTrue(str_contains($html, 'href="/docs/reference/author_post_retraction.md"'));
        assertTrue(str_contains($html, 'Post Retraction: Complete Workflow'));
        assertTrue(str_contains($html, '/docs/architecture/public_architecture_and_trust.md'));
        assertTrue(str_contains($html, '/docs/examples/extension_improvement_cookbook.md'));
        assertTrue(str_contains($html, 'docs/examples/extension_improvement_cookbook.md'));
    }

    public function testResultsViewFiltersWithLinksChipsAndPages(): void
    {
        $html = $this->render('/docs/?group=Plans+and+history&page=2');
        assertStringContains('<span aria-current="page">Page 2 of ', $html);
        assertStringContains('href="/docs/?group=Plans+and+history&amp;page=1"><span aria-hidden="true">←</span> Previous</a>', $html);
        assertStringContains('href="/docs/?group=Plans+and+history&amp;page=3">Next', $html);
        assertStringContains('href="/docs/?group=Plans+and+history" aria-current="page"', $html);
        assertStringContains('aria-label="Remove Collection: Plans and history"', $html);
        assertStringContains('href="/docs/?page=1">Clear all</a>', $html);
        assertStringContains('<details class="docs-filters">', $html);
        assertTrue(!str_contains($html, '?>'));
        // Collection is the active filter, so rows never repeat it.
        $rows = (string) strstr($html, 'data-docs-results');
        assertTrue(!str_contains(explode('</ol>', $rows)[0], '<span class="docs-badge">Plans and history</span>'));
        assertSame(1, substr_count($html, '<a href="/docs/"><span aria-hidden="true">←</span> Docs home</a>'));

        $legacy = $this->render('/docs/?category=Architecture&q=trust');
        assertStringContains('href="/docs/?q=trust&amp;category=architecture" aria-current="page"', $legacy);
        assertStringContains('Category: Architecture', $legacy);
        assertStringContains('<input type="hidden" name="category" value="architecture" data-docs-filter-input>', $legacy);
        assertStringContains('value="trust"', $legacy);
        assertStringContains('How the Git records, the views built from them', $legacy);
        assertStringContains('<span class="docs-badge">Architecture</span>', $legacy);
        assertTrue(!str_contains($legacy, '<span class="docs-badge">Current guidance</span>'));

        $folder = $this->render('/docs/?folder=docs%2Fplans%2Farchive');
        assertStringContains('<details data-docs-folder-branch="docs/plans" open><summary>plans', $folder);
        assertStringContains('<span>All in plans</span>', $folder);
        assertStringContains('href="/docs/?folder=docs%2Fplans%2Farchive" aria-current="page"', $folder);
        assertSame(1, substr_count($folder, 'href="/docs/?folder=docs"><span>All in docs</span>'));
        assertStringContains('href="/docs/?folder=docs%2Frailspike%2Fdocs"><span>All in docs</span>', $folder);

        $empty = $this->render('/docs/?q=no-such-doc-7aa32');
        assertStringContains('<a href="/docs/?page=1">Clear filters</a> · <a href="/docs/">Docs home</a>', $empty);
        assertStringContains('data-docs-pages hidden', $empty);
    }

    public function testLibraryBrowsesRepositoryFoldersAndRootDocuments(): void
    {
        $html = $this->render('/docs/?folder=docs%2Fresearch');
        assertStringContains('Documents</h2>', $html);
        assertStringContains('docs/research/', $html);
        $root = $this->render('/docs/_project/README.md');
        assertStringContains('Repository source: <code>README.md</code>', $root);
        assertStringContains('<title>README.md - Platform Docs', $root);
    }

    public function testSearchAndReturnStateWorkWithoutJavaScript(): void
    {
        $html = $this->render('/docs/?q=SCHEMA_VERSION&folder=Project');
        assertStringContains('CONTRIBUTING.md', $html);
        assertStringContains('q=SCHEMA_VERSION&amp;folder=Project', $html);
        $reader = $this->render('/docs/_project/CONTRIBUTING.md?q=SCHEMA_VERSION&folder=Project');
        assertStringContains('/docs/?q=SCHEMA_VERSION&amp;folder=Project#docs-library-heading', $reader);
        $empty = $this->render('/docs/?q=no-such-doc-7aa32');
        assertStringContains('0 documents', $empty);
        assertStringContains('No documents found.', $empty);
        $data = json_decode($this->render('/docs/_search.json'), true, flags: JSON_THROW_ON_ERROR);
        assertTrue(count($data['entries']) > 1200);
        assertTrue(in_array('README.md', array_column($data['entries'], 'path'), true));
    }

    public function testAssetsAreInertAndMissingDocumentsOfferRecovery(): void
    {
        $html = $this->render('/docs/plans/backlog/feature-flags-redesign.html');
        assertStringContains('&lt;html', strtolower($html));
        assertStringContains('/docs/_download/docs/plans/backlog/feature-flags-redesign.html', $html);
        assertSame(1, substr_count(strtolower($html), '<html'));
        $example = $this->render('/docs/examples/secrets.php.example');
        assertStringContains('replace-with-real-key', $example);
        assertStringContains('&lt;?php', $example);
        assertStringContains('tabindex="0" role="region" aria-label="Document source"', $example);
        $pdf = $this->render('/docs/research/sigtbd_fault_tolerant_governance_for_internet_message_boards.pdf');
        assertStringContains('Preview unavailable', $pdf);
        assertStringContains('Download</a>', $pdf);
        $missing = $this->render('/docs/not-present.md');
        assertStringContains('data-docs-return', $missing);
        assertStringContains('data-docs-document-retry', $missing);
        $download = $this->render('/docs/_download/docs/examples/secrets.php.example');
        assertSame(file_get_contents(dirname(__DIR__) . '/docs/examples/secrets.php.example'), $download);
        $invalid = $this->render('/docs/_download/docs/%2e%2e/private.md');
        assertStringContains('<h1>Not Found</h1>', $invalid);
    }

    public function testPublicIndexUsesConcreteCopy(): void
    {
        $text = strtolower(html_entity_decode(strip_tags($this->render('/docs/')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

        foreach (['legible', 'durable', 'accountable', 'by construction', 'safely', 'fit together'] as $forbidden) {
            assertTrue(!str_contains($text, $forbidden));
        }

        assertSame(2, substr_count($text, 'canonical'));
    }

    public function testDocsAreDiscoverableFromToolsWithoutAddingGlobalNavigation(): void
    {
        $board = $this->render('/');
        $tools = $this->render('/tools/');

        assertTrue(!str_contains($board, 'href="/docs/">Docs</a>'));
        assertTrue(str_contains($tools, 'href="/docs/">Platform Docs</a>'));
    }

    public function testCataloguedAndUncataloguedDocumentsRenderWithSourcePaths(): void
    {
        $catalogued = $this->render('/docs/examples/extension_improvement_cookbook.md');
        $uncatalogued = $this->render('/docs/railspike/README.md');
        $architecture = $this->render('/docs/architecture/public_architecture_and_trust.md');

        assertTrue(str_contains($catalogued, 'Repository source: <code>docs/examples/extension_improvement_cookbook.md</code>'));
        assertTrue(str_contains($catalogued, '<h1 id="extension-and-improvement-cookbook">Extension and Improvement Cookbook</h1>'));
        assertTrue(str_contains($uncatalogued, 'Repository source: <code>docs/railspike/README.md</code>'));
        assertTrue(str_contains($uncatalogued, '<h1 id="railspike">Railspike</h1>'));
        assertTrue(str_contains($architecture, 'Repository source: <code>docs/architecture/public_architecture_and_trust.md</code>'));
        assertTrue(str_contains($architecture, '<h1 id="public-architecture-and-trust-model">Public Architecture and Trust Model</h1>'));
        assertTrue(str_contains($architecture, 'The public record lives in Git'));
    }

    public function testRailspikeAndLegacyTransitionAreReadable(): void
    {
        $workflow = $this->render('/docs/railspike/RAILSPIKE.md');
        $guide = $this->render('/docs/reference/railspike_workflow.md');
        $legacy = $this->render('/docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md');
        assertStringContains('<h1 id="railspike">Railspike</h1>', $workflow);
        assertStringContains('Git subtree', $guide);
        assertStringContains('tree has been removed', strip_tags($guide));
        assertStringContains('<h1>Not Found</h1>', $legacy);
    }

    public function testInvalidDocumentationPathsAreNotRendered(): void
    {
        $html = $this->render('/docs/%2e%2e/README.md');

        assertTrue(str_contains($html, '<h1>Not Found</h1>'));
        assertTrue(!str_contains($html, 'Repository source:'));
    }

    public function testDocsRemainPublicWhenMembersOnlyModeIsEnabled(): void
    {
        $previousFlag = getenv('FORUM_APPROVED_MEMBERS_ONLY');
        putenv('FORUM_APPROVED_MEMBERS_ONLY=true');

        try {
            $html = $this->render('/docs/');

            assertTrue(str_contains($html, '<h1>zenmemes Platform Docs</h1>'));
            assertTrue(!str_contains($html, '<h1>Lobby</h1>'));
        } finally {
            if ($previousFlag === false) {
                putenv('FORUM_APPROVED_MEMBERS_ONLY');
            } else {
                putenv('FORUM_APPROVED_MEMBERS_ONLY=' . $previousFlag);
            }
        }
    }

    public function testMarkdownEscapesRawHtmlAndRejectsUnsafeLinks(): void
    {
        $html = PublicMarkdownRenderer::render("# Title\n\n<script>alert(1)</script>\n\n[unsafe](javascript:alert(1))\n\n[safe](https://example.test/docs)");

        assertTrue(str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;'));
        assertTrue(!str_contains($html, '<script>'));
        assertTrue(!str_contains($html, 'href="javascript:'));
        assertTrue(str_contains($html, 'href="https://example.test/docs"'));
    }


    public function testCoreFormattingAndDocumentScopedAnchors(): void
    {
        $source = "# Same\n\n# Same\n\n- [x] Done\n  - Nested\n\n[ref][r] and ~~old~~ and _emphasis_\n\n[r]: https://example.test\n";
        $html = PublicMarkdownRenderer::render($source);
        assertStringContains('id="same"', $html);
        assertStringContains('id="same-1"', $html);
        assertStringContains('disabled=""', $html);
        assertSame(2, substr_count($html, '<ul>'));
        assertStringContains('<del>old</del>', $html);
        assertStringContains('<em>emphasis</em>', $html);
        assertStringContains('href="https://example.test"', $html);
        assertSame($html, PublicMarkdownRenderer::render($source));
        $guide = PublicMarkdownRenderer::render(file_get_contents(dirname(__DIR__) . '/docs/reference/author_post_retraction.md'));
        assertSame(4, substr_count($guide, '<table>'));
        assertSame(1, substr_count($guide, '<ol>'));
        $procedure = explode('</ol>', explode('<ol>', $guide)[1])[0];
        assertSame(6, substr_count($procedure, '<li>'));
    }


    public function testDocumentLinkPolicyCoversAllMarkdownLinkForms(): void
    {
        $source = "[CLI](v3_cli.md#delete-a-canonical-record)\n\n[architecture][a]\n\n[a]: ../architecture/public_architecture_and_trust.md\n\n<https://example.test/guide>\n\n[section](#local)\n\n![Logo](../../public/logo.svg)";
        $html = PublicMarkdownRenderer::render($source, 'docs/reference/author_post_retraction.md');
        assertStringContains('href="/docs/reference/v3_cli.md#delete-a-canonical-record"', $html);
        assertStringContains('href="/docs/architecture/public_architecture_and_trust.md"', $html);
        assertStringContains('href="https://example.test/guide"', $html);
        assertStringContains('href="#local"', $html);
        assertStringContains('Logo (image unavailable)', $html);
        assertTrue(!str_contains($html, '<img'));
        foreach (['//evil.test/x', 'javascript:alert%281%29', 'data:text/html,evil',
            '%2e%2e/v3_cli.md', '%252e%252e/v3_cli.md', '../../autoload.php',
            '/autoload.php', '../missing.md', 'v3_cli.md?download=1', 'file:///etc/passwd',
            'https://example.test/%0aevil', '..%5c..%5cautoload.php'] as $url) {
            $result = PublicMarkdownRenderer::render('[label](' . $url . ')', 'docs/reference/author_post_retraction.md');
            if ($url === 'https://example.test/%0aevil') {
                // Encoded HTTP path data does not become local file access or HTML.
                assertStringContains('href="https://example.test/%0aevil"', $result);
            } else {
                assertTrue(!str_contains($result, '<a '));
                assertStringContains('label (source unavailable)', $result);
            }
        }
        assertStringContains('source unavailable', PublicMarkdownRenderer::render('[source](autoload.php)'));
    }

    public function testDocumentLinkPolicyRejectsSymlinkEscape(): void
    {
        $root = sys_get_temp_dir() . '/docs-link-policy-' . bin2hex(random_bytes(4));
        mkdir($root . '/docs', 0777, true);
        file_put_contents($root . '/secret.md', '# Private');
        symlink($root . '/secret.md', $root . '/docs/escape.md');
        try {
            $html = PublicMarkdownRenderer::render('[private](escape.md)', 'docs/index.md', $root);
            assertTrue(!str_contains($html, '<a '));
            assertStringContains('source unavailable', $html);
        } finally {
            unlink($root . '/docs/escape.md');
            unlink($root . '/secret.md');
            rmdir($root . '/docs');
            rmdir($root);
        }
    }


    public function testInventoriedSpecialSourcesRemainReadableAndEscaped(): void
    {
        $root = dirname(__DIR__);
        $slides = PublicMarkdownRenderer::render(file_get_contents($root . '/docs/research/sigtbd_fault_tolerant_governance_slides_5min_marp.md'));
        assertStringContains('<summary>Document metadata</summary>', $slides);
        assertStringContains('marp: true', $slides);
        assertStringContains('id="byzantine-fault-tolerant-governance-for-internet-message-boards"', $slides);
        assertTrue(!str_contains($slides, '<style'));
        assertTrue(!str_contains($slides, '&lt;!--'));
        $diagrams = PublicMarkdownRenderer::render(file_get_contents($root . '/docs/architecture/private_messaging.md'));
        assertSame(5, substr_count($diagrams, '<figcaption>Mermaid source</figcaption>'));
        assertStringContains('class="language-mermaid"', $diagrams);
        $logo = PublicMarkdownRenderer::render(file_get_contents($root . '/docs/railspike/README.md'));
        assertStringContains('Railspike rail and spike logo (image unavailable)', $logo);
        assertTrue(!str_contains($logo, '<img'));
        $references = PublicMarkdownRenderer::render(file_get_contents($root . '/docs/plans/thywill_site_option_review.md'), 'docs/plans/thywill_site_option_review.md');
        assertTrue(!str_contains($references, '[T1]'));
        assertStringContains('source unavailable', $references);
        $hostile = PublicMarkdownRenderer::render("---\nstyle: <script>bad</script>\n---\n\n~~~html\n<script>bad</script>\n~~~\n\n<img src=x alt=\"&lt;script&gt;\">\n");
        assertTrue(!str_contains($hostile, '<script>'));
        assertStringContains('&lt;script&gt;', $hostile);
        assertStringContains('<figcaption>html</figcaption>', $hostile);
    }


    public function testDocumentTemplatePreservesCodeBytes(): void
    {
        $markdown = file_get_contents(dirname(__DIR__) . '/docs/reference/author_post_retraction.md');
        $expected = PublicMarkdownRenderer::render($markdown, 'docs/reference/author_post_retraction.md');
        $page = $this->render('/docs/reference/author_post_retraction.md');
        preg_match_all('~<pre\b[^>]*>(.*?)</pre>~s', $expected, $expectedBlocks);
        preg_match_all('~<pre\b[^>]*>(.*?)</pre>~s', $page, $actualBlocks);
        assertSame($expectedBlocks[1], $actualBlocks[1]);
        assertStringContains('tabindex="0" role="region" aria-label="Table"', $page);
        assertStringContains('tabindex="0" role="region" aria-label="Code"', $page);
    }

    private function render(string $path): string
    {
        $application = new Application(
            dirname(__DIR__),
            __DIR__ . '/fixtures/parity_minimal_v1',
            $this->databasePath,
        );
        ob_start();
        $application->handle('GET', $path);

        return (string) ob_get_clean();
    }
}
