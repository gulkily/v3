<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Docs\DocsPublication;
use ForumRewrite\Docs\PlatformDocsCatalog;

final class DocsPublicationTest
{
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
