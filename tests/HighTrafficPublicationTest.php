<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Host\HighTrafficPublication;

final class HighTrafficPublicationTest
{
    public function testAbsentMalformedAndChangedRetractionsDoNotBlockRendering(): void
    {
        $root = $this->fixture();
        $context = HighTrafficPublication::captureForRender($root);
        assertSame(null, $context['fingerprint']);
        foreach (glob($root . '/records/post-retractions/*.txt') as $path) file_put_contents($path, 'malformed legacy evidence');
        HighTrafficPublication::validate($context);
        $database = $root . '/live.sqlite3';
        \ForumRewrite\Host\HighTrafficRenderInput::build(HighTrafficPublication::capture($root), $database);
        $hash = hash_file('sha256', $database);
        foreach (glob($root . '/records/post-retractions/*.txt') as $path) unlink($path);
        assertSame($database, \ForumRewrite\Host\HighTrafficRenderInput::prepare($context, $database, $root . '/candidate'));
        assertSame($hash, hash_file('sha256', $database));
        assertSame(false, is_file($database . '.high-traffic.sqlite3'));
    }

    public function testMissingModelProvidesMaintenanceRecoveryWithoutRenderingRebuild(): void
    {
        $root = $this->fixture();
        try {
            \ForumRewrite\Host\HighTrafficRenderInput::prepare(HighTrafficPublication::captureForRender($root), $root . '/missing.sqlite3', $root . '/candidate');
            throw new LogicException('Missing model accepted');
        } catch (RuntimeException $e) { assertStringContains('./v3 high-traffic prepare', $e->getMessage()); }
        assertSame(false, is_file($root . '/missing.sqlite3'));
    }

    private function rejects(callable $callback): void
    {
        $failed = false;
        try { $callback(); } catch (RuntimeException) { $failed = true; }
        assertTrue($failed, 'Unsafe or changed input must reject publication.');
    }

    public function testNormalHomeAndCliRefreshRecoverWithBothAbsentProductionTargets(): void
    {
        $root = $this->fixture();
        file_put_contents($root . '/records/posts/visible.txt', "Post-ID: visible\nCreated-At: 2026-10-14T11:00:00Z\nBoard-Tags: general\nSubject: Surviving thread\n\nSurviving body\n");
        $project = dirname(__DIR__);
        $database = $root . '/live.sqlite3';
        $env = array_merge(getenv(), ['FORUM_REPOSITORY_ROOT' => $root, 'FORUM_DATABASE_PATH' => $database, 'FORUM_PUBLIC_ARTIFACT_ROOT' => $root . '/public']);
        foreach (range(1, 2) as $attempt) {
            $proc = proc_open([PHP_BINARY, $project . '/scripts/high_traffic.php', 'prepare', '--json'],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
            fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            assertSame(0, proc_close($proc), $err);
            assertSame($database, json_decode($out, true)['prepared_model']);
            if ($attempt === 2) {
                assertStringContains('Reusing current live read model', $err);
                assertSame(false, str_contains($err, 'Read model:'));
            }
        }
        mkdir($root . '/public');
        $cache = new \ForumRewrite\Host\HighTrafficCache($project, $root, $database, $root . '/public');
        $cache->initialize();
        symlink($cache->root . '/pages', $root . '/public/.high-traffic');
        $cache->atomic($cache->root . '/state.json', json_encode(['enabled' => true, 'generation' => 'test']));
        try {
            http_response_code(200);
            ob_start();
            (new \ForumRewrite\Host\FrontController($project, $root, $database, $root . '/static', $root . '/public'))->handle('GET', '/', []);
            $html = ob_get_clean();
            assertSame(200, http_response_code());
            assertStringContains('v3-generated-at', $html);
            assertSame($html, file_get_contents($cache->path('/')));
            assertSame(true, is_file($database), 'Maintenance prepared the ordinary live model.');
            foreach (['/about/', '/threads/', '/threads/visible/'] as $route) {
                assertStringContains('v3-generated-at', (string) $cache->generate($route));
            }
            $guard = $root . '/no-full-record-hashing.php';
            file_put_contents($guard, '<?php namespace ForumRewrite\\ReadModel; function hash_file(...$args) { throw new \\RuntimeException("Refresh performed a full-record fingerprint"); }');
            $workerPhp = var_export([PHP_BINARY, '-d', 'auto_prepend_file=' . $guard], true);
            $wrapper = $root . '/progress-php';
            file_put_contents($wrapper, "#!/usr/bin/env php\n<?php fwrite(STDERR, \"High-traffic stage: CLI live-progress fixture\\n\"); usleep(500000); "
                . '$proc=proc_open(array_merge(' . $workerPhp . ',array_slice($argv,1)),[STDIN,STDOUT,STDERR],$pipes); exit(proc_close($proc));');
            chmod($wrapper, 0755);
            $proc = proc_open([PHP_BINARY, '-d', 'auto_prepend_file=' . $guard, $project . '/scripts/high_traffic.php', 'refresh', '/', '--json'],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null,
                array_merge(getenv(), ['FORUM_REPOSITORY_ROOT' => $root, 'FORUM_DATABASE_PATH' => $database, 'FORUM_PUBLIC_ARTIFACT_ROOT' => $root . '/public', 'FORUM_PHP_CLI' => $wrapper]));
            fclose($pipes[0]);
            stream_set_blocking($pipes[2], false);
            $err = ''; $deadline = microtime(true) + 3;
            do {
                $err .= stream_get_contents($pipes[2]);
                if (str_contains($err, 'Renderer: CLI live-progress fixture')) break;
                usleep(1000);
            } while (microtime(true) < $deadline);
            assertStringContains('Renderer: CLI live-progress fixture', $err);
            assertTrue(proc_get_status($proc)['running'], 'Progress must arrive before the worker finishes.');
            stream_set_blocking($pipes[2], true);
            $out = stream_get_contents($pipes[1]); $err .= stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            assertSame(0, proc_close($proc), $err);
            assertSame('/', json_decode($out, true)['refreshed']);
            assertStringContains('Repository: ', $err);
            assertStringContains('Using live read model', $err);
            assertSame(false, str_contains($err, 'Read model: index'));
            assertStringContains('Published / (', $err);
            assertSame([], glob($cache->root . '/.render-*'));
            assertSame([], glob($cache->root . '/assets/*.sqlite3'), 'Private render models must never enter public assets.');
        } finally {
            \ForumRewrite\Host\HighTrafficCache::removeTemporaryTree($cache->root);
        }
    }

    public function testParentAllowsRetractionDelayButRejectsModeChangeAndWorkerFailure(): void
    {
        $root = $this->fixture();
        file_put_contents($root . '/records/posts/visible.txt', "Post-ID: visible\nCreated-At: 2026-10-14T11:00:00Z\nBoard-Tags: general\nSubject: Visible thread\n\nIndependent body\n");
        \ForumRewrite\Host\HighTrafficRenderInput::build(HighTrafficPublication::capture($root), $root . '/live.sqlite3');
        mkdir($root . '/public');
        $cache = new \ForumRewrite\Host\HighTrafficCache(dirname(__DIR__), $root, $root . '/live.sqlite3', $root . '/public');
        $cache->initialize();
        symlink($cache->root . '/pages', $root . '/public/.high-traffic');
        $cache->atomic($cache->root . '/state.json', json_encode(['enabled' => true, 'generation' => 'test']));
        $oldPhp = getenv('FORUM_PHP_CLI');
        try {
            $previous = $cache->generate('/threads/visible/');
            $target = 'reply-20260526032118-654b2b08';
            $path = $root . '/records/posts/' . $target . '.txt';
            $bytes = "Post-ID: $target\nCreated-At: 2026-10-14T11:30:00Z\nBoard-Tags: general\nThread-ID: visible\nParent-ID: visible\n\nReappeared independent source\n";
            $evidence = glob($root . '/records/post-retractions/*.txt')[0];
            $originalEvidence = file_get_contents($evidence);
            $mutations = [
                'file_put_contents(' . var_export($path, true) . ', ' . var_export($bytes, true) . ');',
                'unlink(' . var_export($root . '/public/.high-traffic', true) . '); file_put_contents(' . var_export($cache->root . '/state.json', true) . ', ' . var_export(json_encode(['enabled' => false, 'generation' => 'changed']), true) . ');',
                'file_put_contents(' . var_export($evidence, true) . ', "malformed");',
                'exit(1);',
            ];
            foreach ($mutations as $index => $mutation) {
                $wrapper = $root . '/php-wrapper';
                file_put_contents($wrapper, "#!/usr/bin/env php\n<?php\n"
                    . '$proc=proc_open(array_merge([PHP_BINARY],array_slice($argv,1)),[STDIN,STDOUT,STDERR],$pipes);'
                    . '$code=proc_close($proc); if ($code===0) {' . $mutation . '} exit($code);');
                chmod($wrapper, 0755);
                putenv('FORUM_PHP_CLI=' . $wrapper);
                if (in_array($index, [1, 3], true)) {
                    $this->rejects(fn () => $cache->generate('/threads/visible/', true));
                    assertSame($previous, file_get_contents($cache->path('/threads/visible/')));
                } else {
                    $previous = $cache->generate('/threads/visible/', true);
                    assertStringContains('Independent body', $previous);
                }
                assertSame([], glob($cache->root . '/.render-*'));
                putenv($oldPhp === false ? 'FORUM_PHP_CLI' : 'FORUM_PHP_CLI=' . $oldPhp);
                if ($index === 0) {
                    (new \ForumRewrite\ReadModel\ReadModelStaleMarker($root . '/live.sqlite3'))->mark(['reason' => 'external source changed']);
                    \ForumRewrite\Host\HighTrafficRenderInput::build(HighTrafficPublication::capture($root), $root . '/live.sqlite3');
                    $previous = $cache->generate('/threads/visible/', true);
                    assertStringContains('Reappeared independent source', $previous);
                }
                if ($index === 1) {
                    symlink($cache->root . '/pages', $root . '/public/.high-traffic');
                    $cache->atomic($cache->root . '/state.json', json_encode(['enabled' => true, 'generation' => 'retry']));
                }
                if ($index === 2) file_put_contents($evidence, $originalEvidence);
            }
            assertStringContains('Reappeared independent source', (string) $cache->generate('/threads/visible/', true));
        } finally {
            putenv($oldPhp === false ? 'FORUM_PHP_CLI' : 'FORUM_PHP_CLI=' . $oldPhp);
            \ForumRewrite\Host\HighTrafficCache::removeTemporaryTree($cache->root);
        }
    }

    public function fixture(): string
    {
        $root = sys_get_temp_dir() . '/v3-html-publication-' . bin2hex(random_bytes(6));
        mkdir($root . '/records/posts', 0755, true);
        mkdir($root . '/records/post-retractions');
        mkdir($root . '/records/instance');
        copy(__DIR__ . '/fixtures/parity_minimal_v1/records/instance/public.txt', $root . '/records/instance/public.txt');
        foreach (['reply-20260526032118-654b2b08', 'reply-20260526032816-a1447d49'] as $id) {
            file_put_contents($root . '/records/post-retractions/post-retraction-' . $id . '.txt',
                "Record-ID: post-retraction-$id\nCreated-At: 2026-10-14T12:00:00Z\nPost-ID: $id\nOperation: retract\nAuthor-Identity-ID: openpgp:" . str_repeat('a', 40) . "\n\n");
        }
        return $root;
    }
}
