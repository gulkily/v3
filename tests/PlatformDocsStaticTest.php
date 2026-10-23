<?php

declare(strict_types=1);

use ForumRewrite\Docs\PlatformDocsCatalog;
use ForumRewrite\Host\FrontController;
use ForumRewrite\Host\StaticArtifactBuilder;

require __DIR__ . '/../autoload.php';

final class PlatformDocsStaticTest
{
    public function testStaticReleaseIncludesCompleteInventoryAndSafeAssets(): void
    {
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-platform-docs-static-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $staticRoot = sys_get_temp_dir() . '/forum-rewrite-platform-docs-static-root-' . bin2hex(random_bytes(6));
        $publicRoot = sys_get_temp_dir() . '/forum-rewrite-platform-docs-public-root-' . bin2hex(random_bytes(6));
        $releaseRoot = $staticRoot . '/release';
        mkdir($staticRoot, 0777, true);
        mkdir($publicRoot, 0777, true);

        try {
            $docsPublisher = new \ForumRewrite\Docs\DocsReleasePublisher(dirname(__DIR__), __DIR__ . '/fixtures/parity_minimal_v1', $staticRoot);
            $releaseRoot = $docsPublisher->publish();

            assertTrue(is_file($releaseRoot . '/docs.html'));
            assertTrue(is_file($releaseRoot . '/docs/index.html'));
            $indexEntries = json_decode(file_get_contents($releaseRoot . '/docs/_search.json'), true, flags: JSON_THROW_ON_ERROR)['entries'];
            assertSame(array_column(PlatformDocsCatalog::inventory(dirname(__DIR__)), 'path'), array_column($indexEntries, 'path'));
            foreach ($indexEntries as $entry) {
                assertTrue(is_file($releaseRoot . $entry['href'] . '.html'), $entry['path']);
                assertSame($entry['hash'], hash_file('sha256', $releaseRoot . '/docs/_download/' . $entry['path'] . '.bin'));
            }

            $controller = new FrontController(
                dirname(__DIR__),
                __DIR__ . '/fixtures/parity_minimal_v1',
                $databasePath,
                $staticRoot,
                $publicRoot,
            );
            // Every browse page is prebuilt and matches a live render of the same query.
            $application = new \ForumRewrite\Application(dirname(__DIR__), __DIR__ . '/fixtures/parity_minimal_v1', $databasePath, $publicRoot, $staticRoot);
            $results = static fn (string $html): string => (string) preg_replace('/\s+/', ' ', explode('<nav class="docs-pages"', (string) strstr($html, '<p role="status"'), 2)[0]);
            $browseQueries = PlatformDocsCatalog::browseQueries(PlatformDocsCatalog::inventory(dirname(__DIR__)), PlatformDocsCatalog::PAGE_SIZE);
            assertTrue(count($browseQueries) > 300);
            foreach ($browseQueries as $browseQuery) {
                $file = $releaseRoot . PlatformDocsCatalog::browsePath($browseQuery);
                assertTrue(is_file($file), $file);
                ob_start();
                $application->handle('GET', '/docs/?' . http_build_query($browseQuery));
                assertSame($results((string) ob_get_clean()), $results((string) file_get_contents($file)), http_build_query($browseQuery));
            }
            $workflowPage = $this->render($controller, '/docs/?q=&folder=&category=&group=Workflow&page=');
            assertStringContains('route-source: static-html', $workflowPage);
            assertStringContains('href="/docs/?group=Workflow" aria-current="page"', $workflowPage);
            assertStringContains('<code class="docs-result__path">docs/railspike/RAILSPIKE.md</code>', $workflowPage);
            assertStringContains('<span aria-current="page">Page 2 of', $this->render($controller, '/docs/?group=Plans+and+history&page=2'));
            assertStringContains('href="/docs/?category=architecture" aria-current="page"><span>Architecture</span>', $this->render($controller, '/docs/?category=Architecture'));
            assertStringContains('href="/docs/?folder=docs%2Fplans%2Farchive" aria-current="page"', $this->render($controller, '/docs/?folder=docs%2Fplans%2Farchive'));
            foreach (['/docs/?q=recovery', '/docs/?group=Workflow&category=railspike', '/docs/?page=999', '/docs/?folder=docs%2Fnot-a-folder'] as $shellRoute) {
                $shell = $this->render($controller, $shellRoute);
                assertStringContains('route-source: static-html', $shell);
                assertStringContains('Searching this copy of the docs needs JavaScript.', $shell);
                assertStringContains('<ol class="docs-results" data-docs-results> </ol>', (string) preg_replace('/\s+/', ' ', $shell));
            }
            assertTrue(!str_contains($this->render($controller, '/docs/'), 'needs JavaScript'));
            $index = $this->render($controller, '/docs/');
            $document = $this->render($controller, '/docs/examples/extension_improvement_cookbook.md');
            $invalid = $this->render($controller, '/docs/%2e%2e/README.md');
            $workflow = $this->render($controller, '/docs/railspike/RAILSPIKE.md');
            $readme = $this->render($controller, '/docs/railspike/README.md');
            assertStringContains('<h1 id="railspike">Railspike</h1>', $workflow);
            assertStringContains('<h1 id="railspike">Railspike</h1>', $readme);

            assertTrue(str_contains($index, 'route-source: static-html'));
            assertTrue(str_contains($index, 'Platform Docs'));
            assertTrue(str_contains($document, 'route-source: static-html'));
            assertTrue(str_contains($document, 'Repository source: <code>docs/examples/extension_improvement_cookbook.md</code>'));
            assertStringContains('data-docs-document-retry', $invalid);
            assertStringContains('route-source: static-html', $workflow);
            assertStringContains('route-source: static-html', $this->render($controller, '/docs/_project/README.md?q=README'));
            assertStringContains('route-source: static-html', $this->render($controller, '/docs/?q=recovery'));
            $assetPath = 'docs/examples/secrets.php.example';
            assertSame(file_get_contents(dirname(__DIR__) . '/' . $assetPath), $this->render($controller, '/docs/_download/' . $assetPath));
            assertSame(array_column($indexEntries, 'path'), array_column(json_decode($this->render($controller, '/docs/_search.json'), true)['entries'], 'path'));
            assertStringContains('&lt;html', strtolower($this->render($controller, '/docs/plans/backlog/feature-flags-redesign.html')));
            assertStringContains('data-docs-document-retry', $this->render($controller, '/docs/missing.md'));

            // A separate docs publication repairs stale readers/downloads and retains existing URLs.
            file_put_contents($releaseRoot . '/docs/deleted.md.html', 'stale');
            file_put_contents($releaseRoot . '/docs/_download/deleted.md.bin', 'stale');
            $releaseRoot = $docsPublisher->publish();
            assertTrue(!is_file($releaseRoot . '/docs/deleted.md.html'));
            assertTrue(!is_file($releaseRoot . '/docs/_download/deleted.md.bin'));
            assertStringContains('route-source: static-html', $this->render($controller, '/docs/_project/README.md'));

        } finally {
            @unlink($databasePath);
            $this->deleteTree($staticRoot);
            $this->deleteTree($staticRoot . '-docs');
            $this->deleteTree($publicRoot);
        }
    }

    public function testOnDemandRootAndWorkflowReadersUseTheSameBoundary(): void
    {
        $root = sys_get_temp_dir() . '/docs-on-demand-' . bin2hex(random_bytes(6));
        mkdir($root);
        $repo = __DIR__ . '/fixtures/parity_minimal_v1';
        try {
            (new \ForumRewrite\ReadModel\ReadModelBuilder($repo, $root . '/read.sqlite3', new \ForumRewrite\Canonical\CanonicalRecordRepository($repo)))->rebuild();
            $builder = new StaticArtifactBuilder(dirname(__DIR__), $repo, $root . '/read.sqlite3', $root . '/release');
            foreach (['/docs/_project/CONTRIBUTING.md', '/docs/railspike/README.md'] as $route) {
                assertTrue($builder->buildSingleRoute($route));
                assertStringContains('route-source: static-html', file_get_contents($root . '/release' . $route . '.html'));
            }
            assertSame(false, $builder->buildSingleRoute('/docs/%2e%2e/README.md'));
            assertSame(false, $builder->buildSingleRoute('/docs/missing.md'));
        } finally {
            $this->deleteTree($root);
        }
    }

    private function render(FrontController $controller, string $path): string
    {
        ob_start();
        $controller->handle('GET', $path);

        return (string) ob_get_clean();
    }

    private function deleteTree(string $path): void
    {
        if (!is_dir($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        $entries = scandir($path);
        if ($entries !== false) {
            foreach ($entries as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $this->deleteTree($path . '/' . $entry);
                }
            }
        }

        @rmdir($path);
    }
}
