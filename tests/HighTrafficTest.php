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
        $cache->atomic($cache->root . '/state.json', json_encode(['enabled'=>true,'generation'=>'test']));
        try {
            $html = $cache->generate('/about/');
            assertStringContains('v3-generated-at', (string)$html);
            assertSame($html, file_get_contents($cache->path('/about/')));
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
