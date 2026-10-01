<?php

declare(strict_types=1);

use ForumRewrite\Docs\PlatformDocsCatalog;

require __DIR__ . '/../autoload.php';

final class PlatformDocsCatalogTest
{
    private string $projectRoot;

    public function __construct()
    {
        $this->projectRoot = dirname(__DIR__);
    }

    public function testCatalogEntriesResolveToUniqueSafeMarkdownDocuments(): void
    {
        $paths = [];

        foreach (PlatformDocsCatalog::entries() as $entry) {
            assertSame($entry['path'], PlatformDocsCatalog::normalizePath($entry['path']));
            assertTrue(PlatformDocsCatalog::resolvePath($this->projectRoot, $entry['path']) !== null);
            $paths[] = $entry['path'];
        }

        assertSame(count($paths), count(array_unique($paths)));
    }

    public function testSafeUncataloguedDocumentResolves(): void
    {
        $path = 'docs/fdp/README.md';

        assertTrue(!in_array($path, array_column(PlatformDocsCatalog::entries(), 'path'), true));
        assertSame(realpath($this->projectRoot . '/' . $path), PlatformDocsCatalog::resolvePath($this->projectRoot, $path));
    }

    public function testRejectsPathsOutsideTheMarkdownDocsBoundary(): void
    {
        foreach ([
            '',
            '/docs/reference/v3_cli.md',
            'README.md',
            'docs/reference/v3_cli.txt',
            'docs/../README.md',
            'docs/%2e%2e/README.md',
            'docs//reference/v3_cli.md',
            'docs\\..\\README.md',
        ] as $path) {
            assertSame(null, PlatformDocsCatalog::normalizePath($path));
            assertSame(null, PlatformDocsCatalog::resolvePath($this->projectRoot, $path));
        }
    }
}
