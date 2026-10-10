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
        $author = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
        $identityPath = '/records/identity/identity-openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954.txt';
        $identity = str_replace('Subject: identity bootstrap', "Username: imported-author\nSubject: identity bootstrap", file_get_contents($source . $identityPath));
        $w->put($source . $identityPath, $identity);
        $w->put($source . '/records/posts/remote-thread.txt', str_replace("Subject: Imported remote-thread\n", '', $w->post('remote-thread', "Author-Identity-ID: {$author}\n")));
        $w->put($source . '/records/posts/remote-reply.txt', $w->post('remote-reply', "Thread-ID: remote-thread\nParent-ID: remote-thread\nAuthor-Identity-ID: {$author}\n"));
        $w->put($source . '/records/thread-subjects/remote-subject.txt', "Record-ID: remote-subject\nCreated-At: 2026-04-15T15:30:00Z\nThread-ID: remote-thread\nOperation: set\nSubject: Remote subject revised\n\n");
        $w->put($source . '/records/post-reactions/remote-like.txt', "Record-ID: remote-like\nCreated-At: 2026-04-15T15:30:00Z\nPost-ID: remote-thread\nOperation: add\nTags: like\nAuthor-Identity-ID: {$author}\n\n");
        $w->git($source);
        $target = $w->repository('target', true);
        $w->put($target . $identityPath, $identity);
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
        assertStringContains('Remote subject revised', file_get_contents($w->root . '/target-static/current/tags/general.html'));
        $targetServer = new ImportHttpServer(realpath(__DIR__ . '/../public/router.php'), $w->root . '/target.log', [
            'FORUM_REPOSITORY_ROOT' => $target,
            'FORUM_DATABASE_PATH' => $w->root . '/target-cache/index.sqlite3',
            'FORUM_STATIC_HTML_ROOT' => $w->root . '/target-static',
            'FORUM_TASK_QUEUE_DATABASE_PATH' => $w->root . '/target-private/tasks.sqlite3',
            'FORUM_APPROVED_MEMBERS_ONLY' => 'false',
        ]);
        $page = file_get_contents($targetServer->url . '/threads/remote-thread');
        assertStringContains('Remote subject revised', $page);
        assertStringContains('Imported body', $page);
        assertStringContains('imported-author', $page);
        assertStringContains('Remote subject revised', file_get_contents($targetServer->url . '/tags/general'));
        assertStringContains('Remote subject revised', file_get_contents($targetServer->url . '/api/get_thread?thread_id=remote-thread'));
        $snapshot = file_get_contents($targetServer->url . '/offline/snapshot.sqlite3');
        assertSame("SQLite format 3\0", substr($snapshot, 0, 16));
        assertSame(hash_file('sha256', $w->root . '/target-static/offline/snapshot.sqlite3'), hash('sha256', $snapshot));
        $pdo = new PDO('sqlite:' . $w->root . '/target-cache/index.sqlite3');
        assertSame(1, (int) $pdo->query("SELECT post_score_total FROM posts WHERE post_id='remote-thread'")->fetchColumn());
        assertSame(1, (int) $pdo->query('SELECT count(*) FROM profiles WHERE is_approved=1')->fetchColumn());
        $pdo = null;
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

    public function testCliResumeDoesNotNeedTheRemoteSourceAgain(): void
    {
        $w = new ImportTestWorkspace();
        $source = $w->repository('source');
        $target = $w->repository('target', true);
        $w->git($target);
        $w->put($source . '/records/posts/resume-thread.txt', $w->post('resume-thread'));
        $w->command(['tar', '-czf', $w->root . '/source.tar.gz', '-C', $w->root, 'source']);
        $w->put($w->root . '/router.php', '<?php readfile(__DIR__ . "/source.tar.gz");');
        $server = new ImportHttpServer($w->root . '/router.php', $w->root . '/http.log');
        $static = $w->root . '/static';
        $w->put($static, 'File blocking publication directory creation');
        $options = ['--repository-root=' . $target, '--database-path=' . $w->root . '/cache/index.sqlite3', '--static-html-root=' . $static];
        [$code, $output] = $w->command([__DIR__ . '/../v3', 'import-instance', $server->url, ...$options]);
        assertSame(1, $code, $output);
        assertTrue(is_file($target . '/records/posts/resume-thread.txt'));
        unset($server);
        unlink($static);
        [$code, $output] = $w->command([__DIR__ . '/../v3', 'import-instance', '--resume', ...$options]);
        assertSame(0, $code, $output);
        assertStringContains('Import result: complete', $output);
        assertStringContains('Imported resume-thread', file_get_contents($static . '/current/tags/general.html'));
        assertSame('2', trim($w->command(['git', '-C', $target, 'rev-list', '--count', 'HEAD'])[1]));
    }

    public function testNonArchiveResponseCannotMutateDestination(): void
    {
        $w = new ImportTestWorkspace();
        $target = $w->repository('target', true);
        $w->git($target);
        $w->put($w->root . '/router.php', '<?php echo "<html>Login required</html>";');
        $server = new ImportHttpServer($w->root . '/router.php', $w->root . '/http.log');
        [$code, $output] = $w->command([__DIR__ . '/../v3', 'import-instance', $server->url,
            '--repository-root=' . $target, '--database-path=' . $w->root . '/cache/index.sqlite3', '--static-html-root=' . $w->root . '/static']);
        assertSame(1, $code, $output);
        assertSame('', trim($w->command(['git', '-C', $target, 'status', '--porcelain'])[1]));
        assertSame('1', trim($w->command(['git', '-C', $target, 'rev-list', '--count', 'HEAD'])[1]));
        assertTrue(!is_dir($w->root . '/static'));
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
