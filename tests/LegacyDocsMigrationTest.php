<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/DocsTestProject.php';

use ForumRewrite\Docs\DocsReleaseLocator;
use ForumRewrite\Docs\DocsReleasePublisher;
use ForumRewrite\Host\StaticArtifactReleasePublisher;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;

final class LegacyDocsMigrationTest
{
    public function testLegacyDocsSurviveForumReplacementInvalidationAndRetractionCleanup(): void
    {
        [$w, $project, $repo, $static, $database, $publisher, $legacy] = $this->legacy();
        $locator = new DocsReleaseLocator($static);
        assertSame(true, $locator->active()['legacy']);
        $version = $locator->active()['version'];
        assertTrue(!is_file($static . '/' . DocsReleaseLocator::LEGACY));
        \ForumRewrite\Retraction\ArtifactSweep::run([$static], time(), preview: true);
        assertTrue(!is_file($static . '/' . DocsReleaseLocator::LEGACY));
        $responder = new \ForumRewrite\Docs\DocsResponder($project, $static);
        $read = static function (string $uri) use ($responder): string {
            ob_start();
            assertTrue($responder->serve('GET', $uri));
            return (string) ob_get_clean();
        };
        $html = $read('/docs/guide.md');
        assertStringContains('content="' . $version . '"', $html);
        assertStringContains('/api/docs-version', $html);
        assertStringContains('forum_pending_docs_version', $html);
        $stored = file_get_contents($legacy . '/docs/guide.md.html');
        $next = $static . '/releases/next-forum';
        $w->put($next . '/index.html', 'new forum');
        $w->put($next . '.retraction-generation', 'legacy-stamp');
        $publisher->activate($next);
        assertTrue(is_file($static . '/' . DocsReleaseLocator::LEGACY));
        assertSame($legacy, $locator->active()['root']);
        assertSame('new forum', file_get_contents($static . '/current/index.html'));
        (new \ForumRewrite\Write\StaticArtifactInvalidator($static))->invalidateApprovalSeed();
        assertTrue(!is_link($static . '/current'));
        for ($n = 0; $n < 1000; $n++) $w->put($legacy . '/docs/extra-' . $n . '.md.html', 'docs');
        $sweep = \ForumRewrite\Retraction\ArtifactSweep::run([$static], time());
        assertTrue($sweep['examined'] < 300);
        assertTrue(!is_file($legacy . '/index.html'));
        assertTrue(!is_file($next . '/index.html'));
        assertSame($html, $read('/docs/guide.md'));
        assertSame($stored, file_get_contents($legacy . '/docs/guide.md.html'));
        assertSame($version, trim($read('/api/docs-version')));
        assertSame(hash_file('sha256', $legacy . '/favicon.ico'), hash('sha256', $read('/docs/_assets/favicon.ico')));
    }

    public function testDirectLegacyLayoutRetainsDocsWithoutTraversingThemDuringInvalidation(): void
    {
        $w = new ImportTestWorkspace();
        $static = $w->root . '/direct';
        $w->put($static . '/docs/index.html', 'legacy docs');
        $w->put($static . '/docs/_search.json', '{}');
        $w->put($static . '/docs.html', 'docs alias');
        $w->put($static . '/index.html', 'forum output');
        (new \ForumRewrite\Write\StaticArtifactInvalidator($static))->invalidateApprovalSeed();
        assertSame('legacy docs', file_get_contents($static . '/docs/index.html'));
        assertSame('docs alias', file_get_contents($static . '/docs.html'));
        assertTrue(!is_file($static . '/index.html'));
        assertSame($static, (new DocsReleaseLocator($static))->active()['root']);
    }

    public function testExplicitPublicationMigratesWithoutReplacingForumState(): void
    {
        [$w, $project, $repo, $static, $database, $publisher, $legacy] = $this->legacy();
        $beforeForum = realpath($static . '/current');
        $beforeDatabase = hash_file('sha256', $database);
        $locator = new DocsReleaseLocator($static);
        $locator->preserveLegacy();
        $template = $project . '/templates/pages/platform_docs_document.php';
        $original = file_get_contents($template);
        file_put_contents($template, '<?php throw new RuntimeException("Migration failure");');
        try {
            (new DocsReleasePublisher($project, $repo, $static))->publish();
            throw new LogicException('Expected failure');
        } catch (RuntimeException) {
            assertSame($legacy, $locator->active()['root']);
        }
        file_put_contents($template, $original);
        $release = (new DocsReleasePublisher($project, $repo, $static))->publish();
        assertSame(false, $locator->active()['legacy']);
        assertSame($release, $locator->active()['root']);
        assertSame($beforeForum, realpath($static . '/current'));
        assertSame($beforeDatabase, hash_file('sha256', $database));
    }

    public function testEscapingLegacyReferencesAndDirectoryLinksAreRejected(): void
    {
        $w = new ImportTestWorkspace();
        $static = $w->root . '/static';
        $outside = $w->root . '/outside';
        $w->put($outside . '/docs/index.html', 'outside');
        $w->put($outside . '/docs/_search.json', '{}');
        $w->put($static . '/' . DocsReleaseLocator::LEGACY, json_encode(['format' => 1, 'relative' => '../outside']));
        symlink($outside, $static . '/current');
        $locator = new DocsReleaseLocator($static);
        assertSame(null, $locator->active());
        $locator->pinLegacyRelease($outside);
        assertSame('../outside', json_decode(file_get_contents($static . '/' . DocsReleaseLocator::LEGACY), true)['relative']);
        $w->put($static . '/safe/docs/index.html', 'safe');
        $w->put($static . '/safe/docs/_search.json', '{}');
        $locator->pinLegacyRelease($static . '/safe');
        assertSame($static . '/safe', $locator->legacy()['root']);
        unlink($static . '/safe/docs/index.html');
        symlink($outside . '/docs/index.html', $static . '/safe/docs/index.html');
        assertSame(null, $locator->legacy());
    }

    private function legacy(): array
    {
        $w = new ImportTestWorkspace();
        $project = DocsTestProject::create($w);
        $repo = $w->repository('records', true);
        $w->git($repo);
        $database = $w->root . '/read.sqlite3';
        (new ReadModelBuilder($repo, $database, new CanonicalRecordRepository($repo)))->rebuild();
        $static = $w->root . '/static';
        $publisher = new StaticArtifactReleasePublisher($project, $repo, $static);
        $legacy = $static . '/releases/legacy-fixture';
        $placeholder = str_repeat('0', 64);
        (new \ForumRewrite\Docs\DocsArtifactBuilder($project, $repo, $legacy, $placeholder))->build();
        $head = \ForumRewrite\ReadModel\ReadModelMetadata::repositoryHead($repo);
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($legacy, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'html') continue;
            $html = file_get_contents($file->getPathname());
            $html = str_replace(['content="' . $placeholder . '"', '/api/docs-version', '/docs/_assets/favicon.ico'],
                ['content="' . $head . '"', '/api/version', '/favicon.ico'], $html);
            $html = preg_replace('~<meta name="app-version-storage-key" content="[^"]*">~', '', $html);
            file_put_contents($file->getPathname(), $html);
        }
        foreach (glob($legacy . '/docs/_assets/*') as $favicon) unlink($favicon);
        rmdir($legacy . '/docs/_assets');
        $w->put($legacy . '/index.html', 'legacy forum output');
        $w->put($legacy . '.retraction-generation', 'legacy-stamp');
        $publisher->activate($legacy);
        return [$w, $project, $repo, $static, $database, $publisher, $legacy];
    }
}
