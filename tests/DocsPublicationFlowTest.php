<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/DocsTestProject.php';
require_once __DIR__ . '/Support/ImportHttpServer.php';

use ForumRewrite\Docs\DocsReleaseLocator;
use ForumRewrite\Docs\DocsReleasePublisher;
use ForumRewrite\Host\StaticArtifactReleasePublisher;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\Canonical\CanonicalRecordRepository;

final class DocsPublicationFlowTest
{
    public function testReportedResumeCompletesWithChangedAndMissingDamagedOrLegacyDocs(): void
    {
        foreach (['missing', 'damaged', 'legacy'] as $case) {
            [$w, $project, $target, $database, $static, $publisher] = $this->destination();
            if ($case === 'damaged') {
                $docs = (new DocsReleasePublisher($project, $target, $static))->publish();
                file_put_contents($docs . '/docs/guide.md.html', 'damaged docs output');
            } elseif ($case === 'legacy') {
                $legacy = $static . '/releases/legacy';
                (new \ForumRewrite\Docs\DocsArtifactBuilder($project, $target, $legacy, str_repeat('a', 64)))->build();
                $w->put($legacy . '/index.html', 'previous forum');
                $w->put($legacy . '.retraction-generation', \ForumRewrite\Retraction\PublicationGuard::generation($target));
                $publisher->activate($legacy);
            }
            $locator = new DocsReleaseLocator($static);
            $beforeDocs = $locator->active();
            $source = $w->repository('source');
            $w->put($source . '/records/posts/resumed.txt', $w->post('resumed'));
            $w->command(['tar', '-czf', $w->root . '/source.tar.gz', '-C', $w->root, 'source']);
            $w->put($w->root . '/router.php', '<?php readfile(__DIR__ . "/source.tar.gz");');
            $server = new ImportHttpServer($w->root . '/router.php', $w->root . '/http.log');
            $options = ['--repository-root=' . $target, '--database-path=' . $database, '--static-html-root=' . $static];
            $template = $project . '/templates/pages/tag.php';
            $original = file_get_contents($template);
            file_put_contents($template, '<?php throw new RuntimeException("Injected forum publication failure");');
            [$code, $out] = $w->command([$project . '/v3', 'import-instance', $server->url, ...$options]);
            assertSame(1, $code, $out);
            assertStringContains('--resume', $out);
            assertTrue(is_file($target . '/.git/instance-import/pending.json'));
            $head = trim($w->command(['git', '-C', $target, 'rev-parse', 'HEAD'])[1]);
            file_put_contents($template, $original);
            $this->trapDocsBulkWork($w, $project);
            $w->put($project . '/docs/guide.md', '# Source changed before resume');
            unset($server);
            [$code, $out] = $w->command([$project . '/v3', 'import-instance', '--resume', ...$options]);
            assertSame(0, $code, $out);
            $this->assertNoDocsWork($out);
            clearstatcache(true);
            assertSame($head, trim($w->command(['git', '-C', $target, 'rev-parse', 'HEAD'])[1]));
            assertSame('2', trim($w->command(['git', '-C', $target, 'rev-list', '--count', 'HEAD'])[1]));
            assertTrue(!is_file($target . '/.git/instance-import/pending.json'));
            assertStringContains('Imported resumed', file_get_contents($static . '/current/tags/general.html'));
            assertTrue(!is_dir($static . '/current/docs'));
            assertSame($beforeDocs, $locator->active());
        }
    }

    public function testForumImportCompletesWhileDocsPublicationHoldsItsOwnLock(): void
    {
        foreach ([false, true] as $failDocs) {
            [$w, $project, $target, $database, $static] = $this->destination();
            (new DocsReleasePublisher($project, $target, $static))->publish();
            $locator = new DocsReleaseLocator($static);
            $beforeDocs = $locator->active();
            $w->put($project . '/docs/guide.md', '# Independently changed docs');
            $ready = $w->root . '/docs-ready';
            $continue = $w->root . '/docs-continue';
            $result = $w->root . '/docs-result';
            $script = $w->root . '/docs-worker.php';
            $w->put($script, '<?php require ' . var_export($project . '/autoload.php', true) . ';'
                . '$ready=' . var_export($ready, true) . ';$continue=' . var_export($continue, true) . ';'
                . 'try {(new \\ForumRewrite\\Docs\\DocsReleasePublisher(' . var_export($project, true) . ','
                . var_export($target, true) . ',' . var_export($static, true) . '))->publish(function()use($ready,$continue){'
                . 'if(!file_exists($ready)){file_put_contents($ready,"ready");$until=microtime(true)+60;'
                . 'while(!file_exists($continue)){if(microtime(true)>$until)throw new RuntimeException("Barrier timeout");usleep(20000);}}});'
                . 'file_put_contents(' . var_export($result, true) . ',"complete");}'
                . 'catch(Throwable $e){file_put_contents(' . var_export($result, true) . ',$e->getMessage());exit(1);}');
            $process = proc_open([PHP_BINARY, $script], [0 => ['file', '/dev/null', 'r'],
                1 => ['file', $w->root . '/worker.log', 'a'], 2 => ['file', $w->root . '/worker.log', 'a']], $pipes);
            try {
                for ($n = 0; $n < 500 && !file_exists($ready); $n++) usleep(20000);
                assertTrue(is_file($ready));
                if ($failDocs) $w->put($project . '/templates/pages/platform_docs_document.php', '<?php throw new RuntimeException("Concurrent docs failure");');
                $source = $w->repository('source');
                $w->put($source . '/records/posts/concurrent.txt', $w->post('concurrent'));
                $w->command(['tar', '-czf', $w->root . '/source.tar.gz', '-C', $w->root, 'source']);
                $w->put($w->root . '/router.php', '<?php readfile(__DIR__ . "/source.tar.gz");');
                $server = new ImportHttpServer($w->root . '/router.php', $w->root . '/http.log');
                [$code, $out] = $w->command([$project . '/v3', 'import-instance', $server->url,
                    '--repository-root=' . $target, '--database-path=' . $database, '--static-html-root=' . $static]);
                assertSame(0, $code, $out);
                $this->assertNoDocsWork($out);
                assertTrue(!file_exists($result)); // The docs process is still paused inside its publication lock.
                clearstatcache(true);
                $forum = realpath($static . '/current');
                $databaseHash = hash_file('sha256', $database);
                $w->put($continue, 'continue');
                $exit = proc_close($process);
                $process = null;
                assertSame($failDocs ? 1 : 0, $exit, file_get_contents($result));
                assertSame($forum, realpath($static . '/current'));
                assertSame($databaseHash, hash_file('sha256', $database));
                assertStringContains('Imported concurrent', file_get_contents($static . '/current/tags/general.html'));
                if ($failDocs) assertSame($beforeDocs, $locator->active());
                else assertTrue($beforeDocs['version'] !== $locator->active()['version']);
            } finally {
                $w->put($continue, 'continue');
                if (is_resource($process)) { proc_terminate($process); proc_close($process); }
            }
        }
    }

    public function testRealCliImportsDoNoDocsBulkWorkWithBrokenDocsAndUnavailableStorage(): void
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
        $w->put($static . '-docs', 'unavailable docs storage');
        $this->trapDocsBulkWork($w, $project);
        $command = [$project . '/v3', 'import-instance', $server->url,
            '--repository-root=' . $target, '--database-path=' . $database, '--static-html-root=' . $static];
        foreach (['cold', 'new-record', 'duplicate'] as $case) {
            if ($case === 'new-record') {
                $w->put($source . '/records/posts/second.txt', $w->post('second'));
                $archive();
            }
            [$code, $out] = $w->command($command);
            assertSame(0, $code, $out);
            $this->assertNoDocsWork($out);
            assertStringContains('./v3 docs publish', $out);
            clearstatcache(true);
            assertTrue(!is_dir($static . '/current/docs'));
            assertTrue(!is_file($static . '/current/.docs-publication.json'));
            assertSame('unavailable docs storage', file_get_contents($static . '-docs'));
            assertStringContains('Imported ' . ($case === 'cold' ? 'first' : 'second'), file_get_contents($static . '/current/tags/general.html'));
            if ($case === 'duplicate') assertStringContains('import: 0', $out);
        }
        assertSame('3', trim($w->command(['git', '-C', $target, 'rev-list', '--count', 'HEAD'])[1]));
        assertTrue(!is_file($target . '/.git/instance-import/pending.json'));
    }

    public function testForumBuildAndSharedRefreshNeverValidateOrCopyThePublishedDocsLibrary(): void
    {
        [$w, $project, $target, $database, $static, $publisher] = $this->destination();
        $docs = (new DocsReleasePublisher($project, $target, $static))->publish();
        $locator = new DocsReleaseLocator($static);
        $before = $locator->active();
        $guide = file_get_contents($docs . '/docs/guide.md.html');
        $this->trapDocsBulkWork($w, $project);
        unlink($project . '/docs/guide.md');
        foreach ([[], ['--shared-only']] as $mode) {
            [$code, $out] = $w->command([$project . '/v3', 'build-static', ...$mode, $target, $database, $static]);
            assertSame(0, $code, $out);
            $this->assertNoDocsWork($out);
            clearstatcache(true);
            assertSame($before, $locator->active());
            assertSame($guide, file_get_contents($docs . '/docs/guide.md.html'));
            assertTrue(!is_dir($static . '/current/docs'));
        }
    }

    public function testDocsChangesDoNotRejectForumCandidatesOrActivation(): void
    {
        [$w, $project, $target, $database, $static, $publisher] = $this->destination();
        $release = $publisher->build($database, function (string $line) use ($w, $project): void {
            if (str_starts_with($line, 'Rendering tag pages')) $w->put($project . '/docs/guide.md', '# Changed during forum rendering');
        });
        $w->put($project . '/docs/guide.md', '# Changed again before forum activation');
        $publisher->activate($release);
        assertSame($release, realpath($static . '/current'));
        assertTrue(!is_dir($release . '/docs'));
        assertTrue(!is_dir($static . '-docs'));
    }

    public function testPrivateCliImportKeepsPublicForumArtifactsAbsent(): void
    {
        [$w, $project, $target, $database, $static] = $this->destination();
        $source = $w->repository('source');
        $w->put($source . '/records/posts/private.txt', $w->post('private'));
        $w->command(['tar', '-czf', $w->root . '/source.tar.gz', '-C', $w->root, 'source']);
        $w->put($w->root . '/router.php', '<?php readfile(__DIR__ . "/source.tar.gz");');
        $server = new ImportHttpServer($w->root . '/router.php', $w->root . '/http.log');
        $old = getenv('FORUM_APPROVED_MEMBERS_ONLY');
        putenv('FORUM_APPROVED_MEMBERS_ONLY=true');
        try {
            [$code, $out] = $w->command([$project . '/v3', 'import-instance', $server->url,
                '--repository-root=' . $target, '--database-path=' . $database, '--static-html-root=' . $static]);
            assertSame(0, $code, $out);
            assertStringContains('Private destination: public static/offline publication skipped', $out);
            assertTrue(is_file($target . '/records/posts/private.txt'));
            assertTrue(!file_exists($static . '/current'));
            assertTrue(!file_exists($static . '/offline/snapshot.sqlite3'));
        } finally {
            putenv($old === false ? 'FORUM_APPROVED_MEMBERS_ONLY' : 'FORUM_APPROVED_MEMBERS_ONLY=' . $old);
        }
    }

    private function trapDocsBulkWork(ImportTestWorkspace $w, string $project): void
    {
        foreach (['DocsPublication.php' => 'public function inputSignature(): string',
            'PlatformDocsCatalog.php' => 'public static function inventory(string $projectRoot, bool $fresh = false): array'] as $file => $signature) {
            $path = $project . '/src/ForumRewrite/Docs/' . $file;
            $source = file_get_contents($path);
            $needle = $signature . "\n    {";
            assertTrue(str_contains($source, $needle));
            file_put_contents($path, str_replace($needle, $needle . "\n        throw new \\RuntimeException('Docs bulk work was called from forum publication');", $source));
        }
        $w->put($project . '/templates/pages/platform_docs_document.php', '<?php throw new RuntimeException("Broken docs renderer");');
    }

    private function assertNoDocsWork(string $output): void
    {
        assertStringNotContains('Rendering documentation', $output);
        assertStringNotContains('Reusing documentation', $output);
        assertStringNotContains('Docs bulk work was called', $output);
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
