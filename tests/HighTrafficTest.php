<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/autoload.php';

final class HighTrafficTest
{
    public function testColdGenerationLimitsAndAtomicReplacement(): void
    {
        $tmp = sys_get_temp_dir() . '/v3-cache-' . bin2hex(random_bytes(6));
        mkdir($tmp . '/repo', 0755, true);
        symlink(__DIR__ . '/fixtures/parity_minimal_v1/records', $tmp . '/repo/records');
        $cache = new \ForumRewrite\Host\HighTrafficCache(dirname(__DIR__), $tmp . '/repo', $tmp . '/read.sqlite3', $tmp . '/public');
        $cache->initialize();
        mkdir($tmp . '/public');
        symlink($cache->root . '/pages', $tmp . '/public/.high-traffic');
        $cache->atomic($cache->root . '/state.json', json_encode(['enabled'=>true,'generation'=>'test']));
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
            $cache->limits['generators']=1;
            $slot=fopen($cache->root . '/locks/slot-0','c'); flock($slot, LOCK_EX);
            assertSame(null, $cache->generate('/'));
            assertSame($html,$cache->generate('/about/'));
            flock($slot,LOCK_UN);fclose($slot);
            $cache->limits['pages']=1;
            $failed=false;
            try { $cache->generate('/'); } catch (RuntimeException) { $failed=true; }
            assertTrue($failed);
            assertSame(false,is_file($cache->path('/')));
            assertSame($html,file_get_contents($cache->path('/about/')));
        } finally {
            exec('rm -rf ' . escapeshellarg($cache->root));
        }
    }

    public function testCanonicalSafetyRetainsRoutinePagesAndWithdrawsBeforeRestrictions(): void
    {
        $tmp=sys_get_temp_dir() . '/v3-safety-' . bin2hex(random_bytes(6));
        mkdir($tmp . '/public',0755,true); mkdir($tmp . '/repo');
        $cache=new \ForumRewrite\Host\HighTrafficCache(dirname(__DIR__),$tmp . '/repo',$tmp . '/db',$tmp . '/public');
        $cache->initialize();
        $cache->atomic($cache->root . '/state.json',json_encode(['enabled'=>true,'generation'=>'initial']));
        $cache->atomic($cache->path('/'),'old HTML');
        symlink($cache->root . '/pages',$tmp . '/public/.high-traffic');
        $old=getenv('FORUM_PUBLIC_ARTIFACT_ROOT');putenv('FORUM_PUBLIC_ARTIFACT_ROOT=' . $tmp . '/public');
        try {
            \ForumRewrite\Host\HighTrafficSafety::beforeWrite($tmp . '/repo',$tmp . '/db','records/posts/new.txt');
            assertSame('old HTML',file_get_contents($cache->path('/')));
            assertTrue(is_link($tmp . '/public/.high-traffic'));
            assertTrue($cache->state()['generation'] !== 'initial');
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
        $cmd = [PHP_BINARY, dirname(__DIR__) . '/scripts/high_traffic_render.php', dirname(__DIR__), __DIR__ . '/fixtures/parity_minimal_v1', $root . '/read.sqlite3', $root . '/assets', '/about/'];
        $run = static function (array $command): array {
            $proc = proc_open($command, [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
            fclose($pipes[0]); $body = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            return [proc_close($proc), $body, $error];
        };
        [$status, $html, $error] = $run($cmd);
        assertSame(0, $status, $error);
        assertStringContains('name="v3-generated-at"', $html);
        assertSame(1, preg_match('/content="\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z"/', $html));
        $cmd[6] = '/threads/nonexistent/';
        [$status] = $run($cmd);
        assertSame(1, $status);
    }
}
