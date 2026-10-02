<?php

declare(strict_types=1);

use ForumRewrite\Docs\PlatformDocsCatalog;
use ForumRewrite\Host\FrontController;
use ForumRewrite\Host\StaticArtifactBuilder;

require __DIR__ . '/../autoload.php';

final class PlatformDocsStaticTest
{
    public function testStaticReleaseIncludesTheIndexAndEveryCataloguedDocument(): void
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
            foreach (PlatformDocsCatalog::entries() as $entry) {
                assertTrue(is_file($releaseRoot . '/' . $entry['path'] . '.html'));
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

            assertTrue(str_contains($index, 'route-source: static-html'));
            assertTrue(str_contains($index, 'Platform Docs'));
            assertTrue(str_contains($document, 'route-source: static-html'));
            assertTrue(str_contains($document, 'Repository source: <code>docs/examples/extension_improvement_cookbook.md</code>'));
            assertTrue(str_contains($invalid, '<h1>Not Found</h1>'));
        } finally {
            @unlink($databasePath);
            $this->deleteTree($staticRoot);
            $this->deleteTree($publicRoot);
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
