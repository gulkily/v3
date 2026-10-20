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

    public function testRejectsPathsOutsideDocumentationBoundary(): void
    {
        foreach ([
            '',
            '/docs/reference/v3_cli.md',
            'private.md',
            'docs/reference/private.php',
            'docs/.hidden.md',
            'docs/a%252fsecret.md',
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
        file_put_contents($root . '/docs/new.md', "# New document\n\nInvalid byte: \xff");
        file_put_contents($root . '/docs/large.md', str_repeat('x', PlatformDocsCatalog::MAX_DOCUMENT_BYTES + 1));
        file_put_contents($root . '/private.md', '# Secret');
        symlink($root . '/private.md', $root . '/docs/escape.md');
        try {
            $entries = array_column(PlatformDocsCatalog::inventory($root), null, 'path');
            assertSame('New document', $entries['docs/new.md']['title']);
            assertTrue(mb_check_encoding($entries['docs/new.md']['text'], 'UTF-8'));
            assertTrue(is_string(json_encode($entries, JSON_THROW_ON_ERROR)));
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

    public function testSearchCoversBodyPathsAndCombinedFilters(): void
    {
        $entries = PlatformDocsCatalog::inventory($this->projectRoot);
        foreach (['post retraction', 'operator recovery', 'docs/plans/', 'docs/railspike/'] as $q) {
            assertTrue(count(PlatformDocsCatalog::search($entries, ['q' => $q])) > 0, $q);
        }
        $body = PlatformDocsCatalog::search($entries, ['q' => 'SCHEMA_VERSION', 'folder' => 'Project', 'category' => 'Project', 'group' => 'Current guidance']);
        assertTrue(in_array('CONTRIBUTING.md', array_column($body, 'path'), true));
        assertSame([], PlatformDocsCatalog::search($entries, ['q' => 'no-such-doc-7aa32']));
        assertSame([], PlatformDocsCatalog::search($entries, ['folder' => 'Project', 'group' => 'Workflow']));
        assertSame(count($entries), count(PlatformDocsCatalog::search($entries, [])));
        assertSame('Current guidance', $entries[0]['group']);
    }

    public function testCategoriesMergeCaseVariantsAndCountsMatchInventory(): void
    {
        $entries = PlatformDocsCatalog::inventory($this->projectRoot);
        $counts = PlatformDocsCatalog::counts($entries);
        $labels = array_map(static fn (array $category): string => mb_strtolower($category['label']), $counts['category']);
        assertSame(count($labels), count(array_unique($labels)));
        assertSame('Architecture', $counts['category']['architecture']['label']);
        assertSame(count($entries), array_sum(array_column($counts['category'], 'count')));
        assertSame(count($entries), array_sum($counts['group']));
        assertSame(count($entries), $counts['folder']['docs'] + $counts['folder']['Project']);
        assertSame($counts['folder']['docs/plans'], $counts['group']['Plans and history']);
        foreach (['Architecture', 'architecture', 'ARCHITECTURE'] as $legacy) {
            assertSame($counts['category']['architecture']['count'], count(PlatformDocsCatalog::search($entries, ['category' => $legacy])), $legacy);
        }
        assertSame(2, count(PlatformDocsCatalog::search($entries, ['category' => 'Extending the Platform'])));
        assertSame(['Architecture', 'Trust and Identity', 'Extending the Platform', 'CLI Reference', 'Operations'], array_keys(PlatformDocsCatalog::topics()));
    }

    public function testSupportingFormatsAndRootLinksShareTheBoundary(): void
    {
        $entries = array_column(PlatformDocsCatalog::inventory($this->projectRoot), null, 'path');
        foreach (['docs/examples/secrets.php.example', 'docs/examples/apache_vhost.conf', 'docs/railspike/LICENSE',
            'docs/plans/backlog/feature-flags-redesign.html', 'docs/research/sigtbd_fault_tolerant_governance_for_internet_message_boards.pdf'] as $path) {
            assertTrue(isset($entries[$path]), $path);
            assertTrue(PlatformDocsCatalog::resolveSource($this->projectRoot, $path) !== null);
        }
        assertTrue(!isset($entries['docs/railspike/.gitignore']));
        assertSame('/docs/_project/README.md', PlatformDocsCatalog::resolveLink('../README.md', 'docs/a.md', $this->projectRoot));
        assertSame('/docs/_project/CONTRIBUTING.md', PlatformDocsCatalog::resolveLink('CONTRIBUTING.md', 'README.md', $this->projectRoot));
        assertSame('/docs/examples/secrets.php.example', PlatformDocsCatalog::resolveLink('../examples/secrets.php.example', 'docs/reference/a.md', $this->projectRoot));
        assertSame('/docs/?folder=docs%2Fplans#docs-library-heading', PlatformDocsCatalog::resolveLink('plans/', 'docs/a.md', $this->projectRoot));
        assertTrue(count(PlatformDocsCatalog::search(array_values($entries), ['folder' => 'docs/plans'])) > 100);
        assertSame(null, PlatformDocsCatalog::resolveLink('../../etc/passwd', 'docs/a.md', $this->projectRoot));
    }

    public function testRejectsOversizedFilesAndSymlinkEscapes(): void
    {
        $root = sys_get_temp_dir() . '/forum-rewrite-platform-docs-boundary-' . bin2hex(random_bytes(6));
        mkdir($root . '/docs', 0777, true);
        file_put_contents($root . '/docs/large.md', str_repeat('a', PlatformDocsCatalog::MAX_DOCUMENT_BYTES + 1));
        file_put_contents($root . '/outside.md', '# outside');
        symlink($root . '/outside.md', $root . '/docs/escape.md');
        file_put_contents($root . '/docs/.hidden.md', '# Hidden');
        symlink($root . '/docs/.hidden.md', $root . '/docs/alias.md');

        try {
            assertSame(null, PlatformDocsCatalog::resolvePath($root, 'docs/large.md'));
            assertSame(null, PlatformDocsCatalog::resolvePath($root, 'docs/escape.md'));
            assertSame(null, PlatformDocsCatalog::resolveSource($root, 'docs/alias.md'));
        } finally {
            @unlink($root . '/docs/escape.md');
            @unlink($root . '/docs/alias.md');
            @unlink($root . '/docs/.hidden.md');
            @unlink($root . '/docs/large.md');
            @unlink($root . '/outside.md');
            @rmdir($root . '/docs');
            @rmdir($root);
        }
    }
}
