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

        assertTrue(str_contains($html, '<h1>Platform Docs</h1>'));
        assertTrue(str_contains($html, 'Extending the Platform'));
        assertTrue(str_contains($html, '/docs/examples/extension_improvement_cookbook.md'));
        assertTrue(str_contains($html, 'docs/examples/extension_improvement_cookbook.md'));
    }

    public function testCataloguedAndUncataloguedDocumentsRenderWithSourcePaths(): void
    {
        $catalogued = $this->render('/docs/examples/extension_improvement_cookbook.md');
        $uncatalogued = $this->render('/docs/fdp/README.md');

        assertTrue(str_contains($catalogued, 'Repository source: <code>docs/examples/extension_improvement_cookbook.md</code>'));
        assertTrue(str_contains($catalogued, '<h1>Extension and Improvement Cookbook</h1>'));
        assertTrue(str_contains($uncatalogued, 'Repository source: <code>docs/fdp/README.md</code>'));
        assertTrue(str_contains($uncatalogued, '<h1>Feature Development Process (FDP)</h1>'));
    }

    public function testInvalidDocumentationPathsAreNotRendered(): void
    {
        $html = $this->render('/docs/%2e%2e/README.md');

        assertTrue(str_contains($html, '<h1>Not Found</h1>'));
        assertTrue(!str_contains($html, 'Repository source:'));
    }

    public function testMarkdownEscapesRawHtmlAndRejectsUnsafeLinks(): void
    {
        $html = PublicMarkdownRenderer::render("# Title\n\n<script>alert(1)</script>\n\n[unsafe](javascript:alert(1))\n\n[safe](https://example.test/docs)");

        assertTrue(str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;'));
        assertTrue(!str_contains($html, '<script>'));
        assertTrue(!str_contains($html, 'href="javascript:'));
        assertTrue(str_contains($html, 'href="https://example.test/docs"'));
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
