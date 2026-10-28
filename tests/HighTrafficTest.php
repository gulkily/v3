<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/autoload.php';

final class HighTrafficTest
{
    public function testColdGenerationLimitsAndAtomicReplacement(): void
    {
        $tmp = sys_get_temp_dir() . '/v3-cache-' . bin2hex(random_bytes(6));
        mkdir($tmp . '/repo', 0755, true);
        exec('cp -R ' . escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1/records') . ' ' . escapeshellarg($tmp . '/repo/records'));
        $cache = new \ForumRewrite\Host\HighTrafficCache(dirname(__DIR__), $tmp . '/repo', $tmp . '/read.sqlite3', $tmp . '/public');
        $cache->initialize();
        mkdir($tmp . '/public');
        symlink($cache->root . '/pages', $tmp . '/public/.high-traffic');
        $cache->atomic($cache->root . '/state.json', json_encode(['enabled'=>true,'generation'=>'test']));
        \ForumRewrite\Host\HighTrafficRenderInput::build(\ForumRewrite\Host\HighTrafficPublication::capture($tmp . '/repo'), $tmp . '/read.sqlite3');
        try {
            $html = $cache->generate('/about/');
            assertStringContains('v3-generated-at', (string)$html);
            assertSame($html, file_get_contents($cache->path('/about/')));
            $refresh=new \ForumRewrite\Host\HighTrafficRefresh($cache);
            assertSame(false,$refresh->hint('/about/'));
            touch($cache->path('/about/'),1); clearstatcache();
            assertSame(true,$refresh->hint('/about/'));
            assertSame(false,$refresh->hint('/about/'));
            assertSame(['/about/'],$refresh->hints());
            touch($cache->path('/about/')); clearstatcache();
            assertSame(false,$refresh->hint('/about/'));
            assertSame(false,$refresh->hint('/tools/feature-flags/'));
            assertSame(false,$refresh->hint('/tools/operations/'));
            assertSame([],$refresh->hints());
            $cache->limits['generators']=1;
            $slot=fopen($cache->root . '/locks/slot-0','c'); flock($slot, LOCK_EX);
            assertSame(null, $cache->generate('/'));
            assertStringContains('All 1 high-traffic generator slots are occupied', $cache->generationRefusalReason());
            assertSame($html,$cache->generate('/about/'));
            assertSame(null, $cache->generationRefusalReason());
            flock($slot,LOCK_UN);fclose($slot);
            $cache->limits['pages']=1;
            assertSame(null,$cache->generate('/'));
            assertStringContains('page limit reached (1/1)', $cache->generationRefusalReason());
            assertSame(false,is_file($cache->path('/')));
            assertSame($html,file_get_contents($cache->path('/about/')));
            $cache->limits['bytes']=1;$failed=false;
            try { $cache->generate('/about/',true); } catch (RuntimeException) { $failed=true; }
            assertTrue($failed);
            assertSame($html,file_get_contents($cache->path('/about/')));
            assertSame([],glob($cache->root . '/.render-*'));
        } finally {
            exec('rm -rf ' . escapeshellarg($cache->root));
        }
    }

    public function testRefreshCliReportsTheSpecificRefusal(): void
    {
        $tmp = sys_get_temp_dir() . '/v3-refusal-' . bin2hex(random_bytes(6));
        mkdir($tmp . '/repo', 0755, true); mkdir($tmp . '/public');
        $project = dirname(__DIR__);
        $cache = new \ForumRewrite\Host\HighTrafficCache($project, $tmp . '/repo', $tmp . '/db', $tmp . '/public');
        $cache->initialize();
        symlink($cache->root . '/pages', $tmp . '/public/.high-traffic');
        $cache->atomic($cache->root . '/state.json', json_encode(['enabled' => true, 'generation' => 'test']));
        $run = static function (string $route, string $expected) use ($project, $tmp): void {
            $env = array_merge(getenv(), ['FORUM_REPOSITORY_ROOT' => $tmp . '/repo', 'FORUM_DATABASE_PATH' => $tmp . '/db', 'FORUM_PUBLIC_ARTIFACT_ROOT' => $tmp . '/public', 'FORUM_HIGH_TRAFFIC_GENERATORS' => '1', 'FORUM_HIGH_TRAFFIC_PAGES' => '1']);
            $proc = proc_open([PHP_BINARY, $project . '/scripts/high_traffic.php', 'refresh', $route, '--json'],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
            fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            assertSame(1, proc_close($proc));
            assertSame('', $out);
            assertStringContains($expected, $err);
            assertSame(false, str_contains($err, 'No generation capacity or inactive mode'));
        };
        $page = fopen($cache->root . '/locks/' . substr(hash('sha256', '/'), 0, 3) . '.lock', 'c');
        $slot = fopen($cache->root . '/locks/slot-0', 'c');
        try {
            flock($page, LOCK_EX);
            $run('/', 'Page generation is already running for /');
            flock($page, LOCK_UN);
            flock($slot, LOCK_EX);
            $run('/', 'All 1 high-traffic generator slots are occupied');
            flock($slot, LOCK_UN);
            $cache->atomic($cache->path('/about/'), 'Cached page');
            $run('/', 'page limit reached (1/1)');
            unlink($tmp . '/public/.high-traffic');
            $run('/', 'run ./v3 high-traffic status');
            $run('/tools/', 'Route is not eligible');
        } finally {
            fclose($page); fclose($slot);
            \ForumRewrite\Host\HighTrafficCache::removeTemporaryTree($cache->root);
        }
    }

    public function testRendererLimitErrorsNameTheLimitAndLastStage(): void
    {
        $tmp = sys_get_temp_dir() . '/v3-render-limits-' . bin2hex(random_bytes(6));
        mkdir($tmp);
        $wrapper = $tmp . '/php-wrapper';
        $oldPhp = getenv('FORUM_PHP_CLI');
        $cache = new \ForumRewrite\Host\HighTrafficCache(dirname(__DIR__), $tmp, $tmp . '/db', $tmp . '/public');
        try {
            putenv('FORUM_PHP_CLI=' . $wrapper);
            foreach ([
                ['usleep(500000);', 'time limit (0.05 seconds)', 0.05, 1048576],
                ['fwrite(STDOUT, str_repeat("x", 100));', 'HTML output limit (100 bytes; maximum 16)', 30, 16],
                ['fwrite(STDERR, str_repeat("x", 70000));', 'stderr output limit', 30, 1048576],
            ] as [$operation, $expected, $seconds, $bytes]) {
                file_put_contents($wrapper, "#!/usr/bin/env php\n<?php fwrite(STDERR, \"High-traffic stage: fixture operation\\n\"); " . $operation);
                chmod($wrapper, 0755);
                $cache->limits['render_seconds'] = $seconds;
                $cache->limits['page_bytes'] = $bytes;
                $message = '';
                try { $cache->render('/', $tmp . '/assets'); } catch (RuntimeException $error) { $message = $error->getMessage(); }
                assertStringContains($expected, $message);
                assertStringContains('after ', $message);
                assertStringContains('Last stage: fixture operation', $message);
            }
        } finally {
            putenv($oldPhp === false ? 'FORUM_PHP_CLI' : 'FORUM_PHP_CLI=' . $oldPhp);
        }
    }

    public function testCanonicalSafetyRetainsRoutinePagesAndWithdrawsBeforeRestrictions(): void
    {
        $tmp=sys_get_temp_dir() . '/v3-safety-' . bin2hex(random_bytes(6));
        mkdir($tmp . '/public',0755,true); mkdir($tmp . '/repo');
        $cache=new \ForumRewrite\Host\HighTrafficCache(dirname(__DIR__),$tmp . '/repo',$tmp . '/db',$tmp . '/public');
        $cache->initialize();
        $cache->atomic($cache->root . '/state.json',json_encode(['enabled'=>true,'generation'=>'initial','public_root'=>$tmp . '/public']));
        $cache->atomic($cache->path('/'),'old HTML');
        symlink($cache->root . '/pages',$tmp . '/public/.high-traffic');
        $old=getenv('FORUM_PUBLIC_ARTIFACT_ROOT');putenv('FORUM_PUBLIC_ARTIFACT_ROOT=' . $tmp . '/public');
        try {
            \ForumRewrite\Host\HighTrafficSafety::beforeWrite($tmp . '/repo',$tmp . '/db','records/posts/new.txt');
            assertSame('old HTML',file_get_contents($cache->path('/')));
            assertTrue(is_link($tmp . '/public/.high-traffic'));
            assertSame('initial',$cache->state()['generation']);
            assertTrue(is_file($cache->root . '/dirty'));
            $record="Record-ID: vote\nCreated-At: 2026-04-10T12:00:00Z\nThread-ID: root-001\nOperation: add\nLabels: like\n\n";
            \ForumRewrite\Host\HighTrafficSafety::beforeWrite($tmp . '/repo',$tmp . '/db','records/thread-labels/vote.txt',$record);
            assertTrue(is_link($tmp . '/public/.high-traffic'));
            $reaction=str_replace(['Thread-ID:','Labels: like'],['Post-ID:','Tags: flag'],$record);
            \ForumRewrite\Host\HighTrafficSafety::beforeWrite($tmp . '/repo',$tmp . '/db','records/post-reactions/vote.txt',$reaction);
            assertSame(false,is_link($tmp . '/public/.high-traffic'));
            $cache->atomic($cache->root . '/state.json',json_encode(['enabled'=>true,'generation'=>'next','public_root'=>$tmp . '/public']));
            symlink($cache->root . '/pages',$tmp . '/public/.high-traffic');

            mkdir($tmp . '/another-public');putenv('FORUM_PUBLIC_ARTIFACT_ROOT=' . $tmp . '/another-public');
            \ForumRewrite\Host\HighTrafficSafety::beforeWrite($tmp . '/repo',$tmp . '/db','records/instance/feature-flags.txt');
            assertSame(false,is_link($tmp . '/public/.high-traffic'));
            assertSame(false,$cache->enabled());
        } finally {
            putenv($old===false ? 'FORUM_PUBLIC_ARTIFACT_ROOT' : 'FORUM_PUBLIC_ARTIFACT_ROOT=' . $old);
            exec('rm -rf ' . escapeshellarg($cache->root));
        }
    }

    public function testRecentLogSamplingIsBoundedAndRanked(): void
    {
        $tmp=sys_get_temp_dir() . '/v3-log-' . bin2hex(random_bytes(6));mkdir($tmp);
        $cache=new \ForumRewrite\Host\HighTrafficCache(dirname(__DIR__),$tmp,$tmp . '/db',$tmp . '/public');
        $cache->limits['lines']=5;
        $line=static fn ($route) => '127.0.0.1 - - [now] "GET ' . $route . ' HTTP/1.1" 200 100 "-" "browser"' . "\n";
        $log=$tmp . '/access.log';
        file_put_contents($log,str_repeat($line('/'),10) . $line('/about/') . $line('/threads') . $line('/about/') . $line('/api/version') . $line('/?q=x'));
        $refresh=new \ForumRewrite\Host\HighTrafficRefresh($cache);
        assertSame(['/about/','/threads/'],$refresh->popular($log));
        $cache->limits['read_bytes']=strlen($line('/about/'))*2;
        file_put_contents($log,str_repeat($line('/'),100) . $line('/about/') . $line('/about/'));
        assertSame(['/about/'],$refresh->popular($log));
    }

    public function testRouteAndIsolatedRender(): void
    {
        assertSame(null, \ForumRewrite\Host\HighTrafficRoute::canonical('/threads/../'));
        assertSame(null, \ForumRewrite\Host\HighTrafficRoute::canonical('/?page=2'));
        $root = sys_get_temp_dir() . '/v3-high-traffic-' . bin2hex(random_bytes(6));
        mkdir($root);
        $cmd = [PHP_BINARY, dirname(__DIR__) . '/scripts/high_traffic_render.php', dirname(__DIR__), __DIR__ . '/fixtures/parity_minimal_v1', $root . '/read.sqlite3', $root . '/assets', '/about/', \ForumRewrite\SiteProfileRegistry::active()['name']];
        $run = static function (array $command) use ($root): array {
            $proc = proc_open($command, [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, null, array_merge(getenv(), ['VISITOR_STATISTICS_DATABASE_PATH'=>$root . '/visits.sqlite3']));
            fclose($pipes[0]); $body = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            return [proc_close($proc), $body, $error];
        };
        $invalidProfile = $cmd;
        $invalidProfile[7] = 'unknown-profile';
        [$status, $html, $error] = $run($invalidProfile);
        assertSame(1, $status);
        assertSame('', $html);
        assertStringContains('Unknown shared render site profile', $error);
        [$status, $html, $error] = $run(array_slice($cmd, 0, -1));
        assertSame(1, $status);
        assertStringContains('Internal render worker arguments required', $error);
        [$status, $html, $error] = $run($cmd);
        assertSame(1, $status);
        assertStringContains('Page rendering does not rebuild read models', $error);
        assertSame(false, is_file($root . '/read.sqlite3'));
        \ForumRewrite\Host\HighTrafficRenderInput::build(\ForumRewrite\Host\HighTrafficPublication::capture(__DIR__ . '/fixtures/parity_minimal_v1'), $root . '/read.sqlite3');
        [$status, $html, $error] = $run($cmd);
        assertSame(0, $status, $error);
        assertStringContains('name="v3-generated-at"', $html);
        assertSame(false,is_file($root . '/visits.sqlite3'));
        assertSame(1, preg_match('/content="\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z"/', $html));
        $cmd[6] = '/threads/nonexistent/';
        [$status] = $run($cmd);
        assertSame(4, $status);
    }
}
