<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';
require_once __DIR__ . '/Support/ImportHttpServer.php';

use ForumRewrite\Import\InstanceArchiveDownloader;

final class ImportInstanceCommandTest
{
    public function testRealInstanceDownloadPreviewImportAndRepeat(): void
    {
        $w = new ImportTestWorkspace();
        $source = $w->repository('remote-renamed', true);
        $w->put($source . '/records/posts/remote-thread.txt', $w->post('remote-thread'));
        $w->git($source);
        $target = $w->repository('target', true);
        $w->git($target);
        $server = new ImportHttpServer(realpath(__DIR__ . '/../public/router.php'), $w->root . '/source.log', [
            'FORUM_REPOSITORY_ROOT' => $source,
            'FORUM_DATABASE_PATH' => $w->root . '/source-cache/index.sqlite3',
            'FORUM_STATIC_HTML_ROOT' => $w->root . '/source-static',
            'FORUM_TASK_QUEUE_DATABASE_PATH' => $w->root . '/source-private/tasks.sqlite3',
            'FORUM_APPROVED_MEMBERS_ONLY' => 'false',
        ]);
        $command = [__DIR__ . '/../v3', 'import-instance', $server->url,
            '--repository-root=' . $target, '--database-path=' . $w->root . '/target-cache/index.sqlite3', '--static-html-root=' . $w->root . '/target-static'];
        [$code, $output] = $w->command([...$command, '--dry-run']);
        assertSame(0, $code, $output);
        assertStringContains('Preview result: complete', $output);
        assertTrue(!is_file($target . '/records/posts/remote-thread.txt'));
        [$code, $output] = $w->command($command);
        assertSame(0, $code, $output);
        assertStringContains('Import result: complete', $output);
        assertTrue(is_file($target . '/records/posts/remote-thread.txt'));
        assertStringContains('Imported remote-thread', file_get_contents($w->root . '/target-static/current/tags/general.html'));
        $w->put($w->root . '/sources.json', json_encode(['remote' => $server->url]));
        $command[2] = 'remote';
        $command[] = '--sources=' . $w->root . '/sources.json';
        [$code, $output] = $w->command($command);
        assertSame(0, $code, $output);
        assertStringContains('import: 0', $output);
        assertSame('2', trim($w->command(['git', '-C', $target, 'rev-list', '--count', 'HEAD'])[1]));
    }

    public function testPartialResultsExplainConflictsAndExclusions(): void
    {
        $w = new ImportTestWorkspace();
        $source = $w->repository('source', true);
        $target = $w->repository('target', true);
        $w->git($target);
        $original = file_get_contents($target . '/records/posts/root-001.txt');
        $w->put($source . '/records/posts/root-001.txt', $w->post('root-001', '', 'Conflicting source content'));
        $w->put($source . '/records/new-family/unknown.txt', 'unsupported');
        $archive = $w->root . '/source.tar.gz';
        $w->command(['tar', '-czf', $archive, '-C', $w->root, 'source']);
        $router = $w->root . '/router.php';
        $w->put($router, '<?php readfile(__DIR__ . "/source.tar.gz");');
        $server = new ImportHttpServer($router, $w->root . '/http.log');
        [$code, $output] = $w->command([__DIR__ . '/../v3', 'import-instance', $server->url,
            '--repository-root=' . $target, '--database-path=' . $w->root . '/cache/index.sqlite3', '--static-html-root=' . $w->root . '/static']);
        assertSame(2, $code, $output);
        assertStringContains('Import result: partial', $output);
        assertStringContains('conflict: records/posts/root-001.txt', $output);
        assertStringContains('unsupported: records/new-family/unknown.txt', $output);
        assertStringContains('excluded: records/instance/public.txt', $output);
        assertStringContains('Review:', $output);
        assertSame($original, file_get_contents($target . '/records/posts/root-001.txt'));
        assertSame('1', trim($w->command(['git', '-C', $target, 'rev-list', '--count', 'HEAD'])[1]));
    }

    public function testDownloadFailuresAreBoundedAndLeaveNoFile(): void
    {
        $w = new ImportTestWorkspace();
        $router = $w->root . '/router.php';
        $w->put($router, <<<'ROUTER'
<?php
if (str_starts_with($_SERVER['REQUEST_URI'], '/redirect/')) { header('Location: /redirect/downloads/repository.tar.gz'); exit; }
if (str_starts_with($_SERVER['REQUEST_URI'], '/large/')) { echo str_repeat('x', 300); exit; }
if (str_starts_with($_SERVER['REQUEST_URI'], '/slow/')) { sleep(2); echo 'x'; exit; }
http_response_code(404); echo 'not found';
ROUTER);
        $server = new ImportHttpServer($router, $w->root . '/http.log');
        foreach (['/missing', '/redirect', '/large', '/slow'] as $path) {
            $failed = false;
            try {
                (new InstanceArchiveDownloader(maxBytes: 100, timeoutSeconds: 1))->download($server->url . $path, $w->root . '/download');
            } catch (RuntimeException) { $failed = true; }
            assertTrue($failed);
            assertTrue(!is_file($w->root . '/download'));
        }
        foreach (['file:///etc/passwd', 'http://user:password@example.test', 'ftp://example.test'] as $url) {
            $failed = false;
            try { InstanceArchiveDownloader::validateUrl($url); } catch (RuntimeException) { $failed = true; }
            assertTrue($failed);
        }
    }
}
