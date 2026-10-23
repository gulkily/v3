<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Docs\DocsPublication;
use ForumRewrite\Docs\PlatformDocsCatalog;

final class DocsPublicationTest
{
    public function testValidatedReuseRefreshesOnlyMarkersAndIsIndependentAcrossReleases(): void
    {
        [$w, $publication, $project, $repo, $release] = $this->publication();
        $previous = $publication->version();
        $original = file_get_contents($release . '/docs/index.html');
        $w->put($repo . '/records/posts/imported.txt', $w->post('imported'));
        $w->git($repo);
        $current = $publication->version();
        assertTrue($previous !== $current);
        $candidate = $w->root . '/candidate';
        mkdir($candidate . '/docs', 0777, true);
        link($release . '/docs/index.html', $candidate . '/docs/index.html');
        assertTrue($publication->reuseInto($release, $candidate));
        assertSame($original, file_get_contents($release . '/docs/index.html'));
        $after = file_get_contents($candidate . '/docs/index.html');
        assertSame(str_replace('content="' . $previous . '"', 'content="' . $current . '"', $original), $after);
        $evidence = json_decode(file_get_contents($candidate . '/' . DocsPublication::MANIFEST), true);
        assertSame($current, $evidence['version']);
        assertSame(hash('sha256', $after), $evidence['files']['docs/index.html']);
        assertTrue($publication->reuseInto($candidate, $w->root . '/next'));
        file_put_contents($candidate . '/docs/index.html', 'candidate mutation');
        assertSame($original, file_get_contents($release . '/docs/index.html'));
        assertSame($after, file_get_contents($w->root . '/next/docs/index.html'));
    }

    public function testDamagedMissingLegacyAndUntrustedOutputsCannotBeReused(): void
    {
        foreach (['missing', 'corrupt', 'asset', 'stale', 'symlink', 'traversal', 'legacy', 'marker', 'duplicate', 'endpoint'] as $case) {
            [$w, $publication, $project, $repo, $release] = $this->publication();
            $manifest = $release . '/' . DocsPublication::MANIFEST;
            $path = $release . '/docs/index.html';
            switch ($case) {
                case 'missing': unlink($path); break;
                case 'corrupt': file_put_contents($path, 'damaged'); break;
                case 'asset': unlink(glob($release . '/assets/*')[0]); break;
                case 'stale': $w->put($release . '/docs/deleted.md.html', 'stale'); break;
                case 'symlink': unlink($path); symlink($release . '/docs.html', $path); break;
                case 'traversal':
                    $data = json_decode(file_get_contents($manifest), true);
                    $data['files']['../outside'] = hash('sha256', 'outside');
                    file_put_contents($manifest, json_encode($data));
                    break;
                case 'legacy': unlink($manifest); break;
                default:
                    $html = file_get_contents($path);
                    if ($case === 'marker') {
                        $html = str_replace('name="app-version"', 'name="unexpected"', $html);
                    } elseif ($case === 'duplicate') {
                        $html .= '<meta name="app-version" content="' . $publication->version() . '">';
                    } else {
                        $html = str_replace('content="/api/version"', 'content="/wrong"', $html);
                    }
                    file_put_contents($path, $html);
                    // Even matching file integrity cannot excuse malformed marker structure.
                    $data = json_decode(file_get_contents($manifest), true);
                    $data['files']['docs/index.html'] = hash('sha256', $html);
                    file_put_contents($manifest, json_encode($data));
            }
            assertSame(false, $publication->reuseInto($release, $w->root . '/candidate'), $case);
        }
    }

    public function testDisabledNotificationsStayAbsentAndModeChangesInvalidate(): void
    {
        $old = getenv('FORUM_APP_VERSION_NOTIFICATION');
        putenv('FORUM_APP_VERSION_NOTIFICATION=false');
        try {
            [$w, $publication, $project, $repo, $release] = $this->publication();
            $w->put($repo . '/records/posts/imported.txt', $w->post('imported'));
            $w->git($repo);
            assertTrue($publication->reuseInto($release, $w->root . '/candidate'));
            assertStringNotContains('app-version', file_get_contents($w->root . '/candidate/docs/index.html'));
            putenv('FORUM_APP_VERSION_NOTIFICATION=true');
            assertSame(false, $publication->reuseInto($release, $w->root . '/enabled'));
        } finally {
            putenv($old === false ? 'FORUM_APP_VERSION_NOTIFICATION' : 'FORUM_APP_VERSION_NOTIFICATION=' . $old);
        }
    }

    public function testInterruptedTransferCleansDocsAndDoesNotFollowCandidateSymlinks(): void
    {
        [$w, $publication, $project, $repo, $release] = $this->publication();
        mkdir($w->root . '/candidate');
        mkdir($w->root . '/outside');
        symlink($w->root . '/outside', $w->root . '/candidate/assets');
        assertSame(false, $publication->reuseInto($release, $w->root . '/candidate'));
        assertSame([], glob($w->root . '/outside/*'));
        assertTrue(!is_dir($w->root . '/candidate/docs'));
        assertTrue(!is_file($w->root . '/candidate/' . DocsPublication::MANIFEST));
        assertTrue(is_file($release . '/docs/index.html'));
    }

    public function testInputsChangingDuringPublicationPreventRecordingEvidence(): void
    {
        [$w, $publication, $project, $repo, $release] = $this->publication();
        $signature = $publication->inputSignature();
        $version = $publication->version();
        $w->put($project . '/docs/guide.md', '# Changed');
        try {
            $publication->recordPublication($release, $signature, $version);
            throw new LogicException('Expected input change failure');
        } catch (RuntimeException $error) {
            assertStringContains('inputs changed', $error->getMessage());
        }
    }

    private function publication(): array
    {
        $w = new ImportTestWorkspace();
        $project = $w->root . '/project';
        $repo = $w->repository('records', true);
        $w->git($repo);
        $w->put($project . '/README.md', '# Readme');
        $w->put($project . '/docs/guide.md', '# Guide');
        $w->put($project . '/public/assets/docs.js', 'console.log("docs");');
        $publication = new DocsPublication($project, $repo);
        $release = $w->root . '/release';
        $paths = ['docs.html', 'docs/index.html', 'docs/_missing.html', 'docs/_search.html'];
        $inventory = PlatformDocsCatalog::inventory($project);
        foreach ($inventory as $entry) {
            $paths[] = ltrim($entry['href'], '/') . '.html';
            $w->put($release . '/docs/_download/' . $entry['path'] . '.bin', file_get_contents($project . '/' . $entry['path']));
        }
        foreach (PlatformDocsCatalog::browseQueries($inventory, PlatformDocsCatalog::PAGE_SIZE) as $query) {
            $paths[] = ltrim(PlatformDocsCatalog::browsePath($query), '/');
        }
        $asset = ForumRewrite\Host\AssetFingerprint::fingerprintedPath($project . '/public', '/assets/docs.js');
        $w->put($release . $asset, file_get_contents($project . '/public/assets/docs.js'));
        $enabled = ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator::forApplication($repo, $project)
            ->isEnabled(ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry::APP_VERSION_NOTIFICATION);
        foreach ($paths as $path) {
            $w->put($release . '/' . $path, '<html><head>' . ($enabled
                ? '<meta name="app-version" content="' . $publication->version() . '"><meta name="app-version-endpoint" content="/api/version">'
                : '') . '<script src="' . $asset . '"></script></head><body>Documentation</body></html>');
        }
        $w->put($release . '/docs/_search.json', json_encode(['entries' => $inventory]));
        $publication->recordPublication($release);
        return [$w, $publication, $project, $repo, $release];
    }

    public function testCompatibilityTracksInputsButNotUnrelatedRecordCommits(): void
    {
        $w = new ImportTestWorkspace();
        $project = $w->root . '/project';
        $repo = $w->repository('records', true);
        $w->git($repo);
        foreach (['README.md', 'docs/guide.md', 'src/render.php', 'templates/layout.php', 'public/assets/docs.css'] as $file) {
            $w->put($project . '/' . $file, '# Original');
        }
        $publication = new DocsPublication($project, $repo);
        $original = $publication->inputSignature();
        $w->put($repo . '/records/posts/new-post.txt', $w->post('new-post'));
        $w->git($repo);
        assertSame($original, $publication->inputSignature());
        foreach (['docs/guide.md', 'src/render.php', 'templates/layout.php', 'public/assets/docs.css'] as $file) {
            $w->put($project . '/' . $file, '# Changed');
            assertTrue($original !== $publication->inputSignature(), $file);
            $w->put($project . '/' . $file, '# Original');
            assertSame($original, $publication->inputSignature());
        }
        $w->put($project . '/docs/added.md', '# Added');
        assertTrue($original !== $publication->inputSignature());
        unlink($project . '/docs/added.md');
        unlink($project . '/docs/guide.md');
        assertTrue($original !== $publication->inputSignature());
        $w->put($project . '/docs/guide.md', '# Original');
        mkdir($project . '/docs/empty');
        assertTrue($original !== $publication->inputSignature(), 'Empty folder links affect output');
        rmdir($project . '/docs/empty');
        assertSame($original, $publication->inputSignature());
        foreach (['FORUM_SITE_ID' => 'qdb', 'FORUM_APP_VERSION_NOTIFICATION' => 'false'] as $key => $value) {
            $before = getenv($key);
            putenv($key . '=' . $value);
            try {
                assertTrue($original !== $publication->inputSignature(), $key);
            } finally {
                putenv($before === false ? $key : $key . '=' . $before);
            }
        }
    }

    public function testFreshSignatureDetectsChangesWithinCatalogSnapshot(): void
    {
        $w = new ImportTestWorkspace();
        $repo = $w->repository('records');
        $project = $w->root . '/project';
        $w->put($project . '/docs/guide.md', '# Original');
        $publication = new DocsPublication($project, $repo);
        $signature = $publication->inputSignature();
        PlatformDocsCatalog::withSnapshot($project, function () use ($w, $project, $publication, $signature): void {
            $w->put($project . '/docs/new.md', '# New');
            assertSame(1, count(PlatformDocsCatalog::inventory($project)));
            assertTrue($signature !== $publication->inputSignature());
        });
        assertSame(2, count(PlatformDocsCatalog::inventory($project)));
    }
}
