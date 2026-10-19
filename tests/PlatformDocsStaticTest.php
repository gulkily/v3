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
            (new StaticArtifactBuilder(
                dirname(__DIR__),
                __DIR__ . '/fixtures/parity_minimal_v1',
                $databasePath,
                $releaseRoot,
            ))->build();
            symlink('release', $staticRoot . '/current');

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
            assertTrue(str_contains($invalid, '<h1>Not Found</h1>'));
            assertStringContains('route-source: static-html', $workflow);
            assertStringContains('route-source: static-html', $this->render($controller, '/docs/_project/README.md?q=README'));
            assertStringContains('route-source: static-html', $this->render($controller, '/docs/?q=recovery'));
            $assetPath = 'docs/examples/secrets.php.example';
            assertSame(file_get_contents(dirname(__DIR__) . '/' . $assetPath), $this->render($controller, '/docs/_download/' . $assetPath));
            assertSame(array_column($indexEntries, 'path'), array_column(json_decode($this->render($controller, '/docs/_search.json'), true)['entries'], 'path'));
            assertStringContains('&lt;html', strtolower($this->render($controller, '/docs/plans/backlog/feature-flags-redesign.html')));
            assertStringContains('data-docs-document-retry', $this->render($controller, '/docs/missing.md'));

            // Shared refresh must remove stale readers/downloads and retain existing URLs.
            file_put_contents($releaseRoot . '/docs/deleted.md.html', 'stale');
            file_put_contents($releaseRoot . '/docs/_download/deleted.md.bin', 'stale');
            (new StaticArtifactBuilder(dirname(__DIR__), __DIR__ . '/fixtures/parity_minimal_v1', $databasePath, $releaseRoot))->refreshSharedPagesFromReadModel();
            assertTrue(!is_file($releaseRoot . '/docs/deleted.md.html'));
            assertTrue(!is_file($releaseRoot . '/docs/_download/deleted.md.bin'));
            assertStringContains('route-source: static-html', $this->render($controller, '/docs/_project/README.md'));

        } finally {
            @unlink($databasePath);
            $this->deleteTree($staticRoot);
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
