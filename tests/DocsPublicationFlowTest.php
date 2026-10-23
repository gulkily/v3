<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/DocsTestProject.php';
require_once __DIR__ . '/Support/ImportHttpServer.php';

use ForumRewrite\Docs\DocsPublication;
use ForumRewrite\Host\StaticArtifactReleasePublisher;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\Canonical\CanonicalRecordRepository;

final class DocsPublicationFlowTest
{
    public function testRealCliColdNewRecordAndDuplicateImports(): void
    {
        $w = new ImportTestWorkspace();
        $project = DocsTestProject::create($w);
        $target = $w->repository('target', true);
        $w->git($target);
        $source = $w->repository('source');
        $w->put($source . '/records/posts/first.txt', $w->post('first'));
        $archive = function () use ($w): void {
            [$code, $out] = $w->command(['tar', '-czf', $w->root . '/source.tar.gz', '-C', $w->root, 'source']);
            assertSame(0, $code, $out);
        };
        $archive();
        $w->put($w->root . '/router.php', '<?php readfile(__DIR__ . "/source.tar.gz");');
        $server = new ImportHttpServer($w->root . '/router.php', $w->root . '/http.log');
        $database = $w->root . '/cache/read.sqlite3';
        $static = $w->root . '/static';
        $command = [$project . '/v3', 'import-instance', $server->url,
            '--repository-root=' . $target, '--database-path=' . $database, '--static-html-root=' . $static];
        [$code, $out] = $w->command($command);
        assertSame(0, $code, $out);
        assertStringContains('Rendering documentation (', $out);
        assertTrue(is_file($static . '/current/' . DocsPublication::MANIFEST));
        $initial = realpath($static . '/current');
        $initialHtml = file_get_contents($initial . '/docs/guide.md.html');
        $w->put($source . '/records/posts/second.txt', $w->post('second'));
        $archive();
        foreach ([false, true] as $duplicate) {
            [$code, $out] = $w->command($command);
            clearstatcache(true); // The child atomically replaces current; discard this process's cached target.
            assertSame(0, $code, $out);
            assertStringContains('Reusing documentation (2 documents).', $out);
            assertStringNotContains('Rendering documentation', $out);
            assertStringContains('Imported second', file_get_contents($static . '/current/tags/general.html'));
            $head = trim($w->command(['git', '-C', $target, 'rev-parse', 'HEAD'])[1]);
            $html = file_get_contents($static . '/current/docs/guide.md.html');
            assertStringContains('<meta name="app-version" content="' . $head . '">', $html);
            assertSame($initialHtml, file_get_contents($initial . '/docs/guide.md.html'));
            if ($duplicate) {
                assertStringContains('import: 0', $out);
            }
        }
        $host = new ImportHttpServer($project . '/public/router.php', $w->root . '/target.log', [
            'FORUM_REPOSITORY_ROOT' => $target, 'FORUM_DATABASE_PATH' => $database,
            'FORUM_STATIC_HTML_ROOT' => $static, 'FORUM_APPROVED_MEMBERS_ONLY' => 'false',
        ]);
        assertSame($head, trim(file_get_contents($host->url . '/api/version')));
        assertStringContains('route-source: static-html', file_get_contents($host->url . '/docs/guide.md'));
    }

    public function testFullPublicationAutomaticallyRebuildsLegacyChangedAndDamagedDocs(): void
    {
        [$w, $project, $target, $database, $static, $publisher] = $this->destination();
        $release = $publisher->build($database);
        $publisher->activate($release);
        foreach (['legacy', 'changed', 'damaged'] as $case) {
            if ($case === 'legacy') {
                unlink($release . '/' . DocsPublication::MANIFEST);
            } elseif ($case === 'changed') {
                unlink($project . '/docs/guide.md');
                $w->put($project . '/docs/replacement.md', "# Replacement\n\nNew content.\n");
            } else {
                unlink($release . '/docs/index.html');
            }
            $progress = [];
            $release = $publisher->build($database, static function ($line) use (&$progress): void { $progress[] = $line; });
            assertStringContains('Rendering documentation (', implode("\n", $progress), $case);
            assertStringNotContains('Reusing documentation', implode("\n", $progress), $case);
            assertTrue(is_file($release . '/docs/index.html'));
            assertTrue(is_file($release . '/' . DocsPublication::MANIFEST));
            if ($case !== 'legacy') {
                assertTrue(!is_file($release . '/docs/guide.md.html'));
                assertStringContains('New content.', file_get_contents($release . '/docs/replacement.md.html'));
                assertStringNotContains('docs/guide.md', file_get_contents($release . '/docs/_search.json'));
            }
            $publisher->activate($release);
        }
    }

    private function destination(): array
    {
        $w = new ImportTestWorkspace();
        $project = DocsTestProject::create($w);
        $target = $w->repository('target', true);
        $w->git($target);
        $database = $w->root . '/cache/read.sqlite3';
        $static = $w->root . '/static';
        (new ReadModelBuilder($target, $database, new CanonicalRecordRepository($target)))->rebuild();
        return [$w, $project, $target, $database, $static, new StaticArtifactReleasePublisher($project, $target, $static)];
    }
}
