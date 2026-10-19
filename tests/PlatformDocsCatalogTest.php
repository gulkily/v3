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
        $path = 'docs/railspike/README.md';

        assertTrue(!in_array($path, array_column(PlatformDocsCatalog::entries(), 'path'), true));
        assertSame(realpath($this->projectRoot . '/' . $path), PlatformDocsCatalog::resolvePath($this->projectRoot, $path));
    }

    public function testRejectsPathsOutsideTheMarkdownDocsBoundary(): void
    {
        foreach ([
            '',
            '/docs/reference/v3_cli.md',
            'private.md',
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

    public function testInventoryDiscoversAllMarkdownAndRootDocuments(): void
    {
        $start = hrtime(true);
        $entries = PlatformDocsCatalog::inventory($this->projectRoot);
        $paths = array_column($entries, 'path');
        assertSame(count($paths), count(array_unique($paths)));
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->projectRoot . '/docs', FilesystemIterator::SKIP_DOTS)) as $file) {
            $relative = substr($file->getPathname(), strlen($this->projectRoot) + 1);
            if (str_ends_with($relative, '.md') && !$file->isLink()) {
                assertTrue(in_array($relative, $paths, true), $relative);
            }
        }
        foreach (PlatformDocsCatalog::ROOT_DOCUMENTS as $path) {
            assertTrue(in_array($path, $paths, true));
            assertSame('/docs/_project/' . $path, PlatformDocsCatalog::routeForPath($path));
        }
        assertTrue((hrtime(true) - $start) / 1e9 < 5, 'Inventory should complete within five seconds');
    }

    public function testInventoryReflectsNewFilesAndOversizedStates(): void
    {
        $root = sys_get_temp_dir() . '/docs-inventory-' . bin2hex(random_bytes(5));
        mkdir($root . '/docs', 0777, true);
        file_put_contents($root . '/docs/new.md', '# New document');
        file_put_contents($root . '/docs/large.md', str_repeat('x', PlatformDocsCatalog::MAX_DOCUMENT_BYTES + 1));
        file_put_contents($root . '/private.md', '# Secret');
        symlink($root . '/private.md', $root . '/docs/escape.md');
        try {
            $entries = array_column(PlatformDocsCatalog::inventory($root), null, 'path');
            assertSame('New document', $entries['docs/new.md']['title']);
            assertSame(false, $entries['docs/large.md']['available']);
            assertTrue(!isset($entries['docs/escape.md']));
            unlink($root . '/docs/new.md');
            assertSame(['docs/large.md'], array_column(PlatformDocsCatalog::inventory($root), 'path'));
        } finally {
            foreach (glob($root . '/docs/*') as $path) { unlink($path); }
            unlink($root . '/private.md');
            rmdir($root . '/docs'); rmdir($root);
        }
    }

    public function testRejectsOversizedFilesAndSymlinkEscapes(): void
    {
        $root = sys_get_temp_dir() . '/forum-rewrite-platform-docs-boundary-' . bin2hex(random_bytes(6));
        mkdir($root . '/docs', 0777, true);
        file_put_contents($root . '/docs/large.md', str_repeat('a', PlatformDocsCatalog::MAX_DOCUMENT_BYTES + 1));
        file_put_contents($root . '/outside.md', '# outside');
        symlink($root . '/outside.md', $root . '/docs/escape.md');

        try {
            assertSame(null, PlatformDocsCatalog::resolvePath($root, 'docs/large.md'));
            assertSame(null, PlatformDocsCatalog::resolvePath($root, 'docs/escape.md'));
        } finally {
            @unlink($root . '/docs/escape.md');
            @unlink($root . '/docs/large.md');
            @unlink($root . '/outside.md');
            @rmdir($root . '/docs');
            @rmdir($root);
        }
    }
}
