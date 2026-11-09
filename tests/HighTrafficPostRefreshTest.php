<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Host\HighTrafficCache;
use ForumRewrite\Host\HighTrafficRefresh;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Write\LocalWriteService;

/** In high-traffic mode, a post write re-renders its cached thread page; other writes leave it alone. */
final class HighTrafficPostRefreshTest
{
    public function testReplyRefreshesCachedThreadPageAndTagDoesNot(): void
    {
        [$repository, $database, $public, $cache, $restore] = $this->environment();
        try {
            $service = $this->service($repository, $database, $public);
            $route = '/threads/root-001/';
            $cache->atomic($cache->path($route), 'old HTML');

            $result = $service->createReply(['thread_id' => 'root-001', 'parent_id' => 'root-001', 'body' => 'Fresh reply for the cache test.']);
            assertSame('ok', $result['status']);
            $html = (string) file_get_contents($cache->path($route));
            assertStringContains('Fresh reply for the cache test.', $html);
            assertTrue(isset($result['timings']['high_traffic_refresh']));

            $cache->atomic($cache->path($route), 'old HTML');
            $service->applyThreadTag(['thread_id' => 'root-001', 'tag' => 'like', 'author_identity_id' => 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954']);
            assertSame('old HTML', file_get_contents($cache->path($route)));
        } finally {
            $restore();
        }
    }

    public function testNewThreadAndFailedRenderStillSavePost(): void
    {
        [$repository, $database, $public, $cache, $restore] = $this->environment();
        try {
            $service = $this->service($repository, $database, $public);
            // A new thread has no cached page yet: nothing to refresh, first reader generates it.
            $thread = $service->createThread(['board_tags' => 'general', 'subject' => 'Cache test thread', 'body' => 'Opening post.']);
            assertSame('ok', $thread['status']);
            $route = '/threads/' . $thread['thread_id'] . '/';
            assertSame(false, is_file($cache->path($route)));

            // A render that cannot run (page lock held) leaves the post saved and the route queued.
            $cache->atomic($cache->path($route), 'old HTML');
            $page = fopen($cache->root . '/locks/' . substr(hash('sha256', $route), 0, 3) . '.lock', 'c');
            flock($page, LOCK_EX);
            try {
                $reply = $service->createReply(['thread_id' => $thread['thread_id'], 'parent_id' => $thread['thread_id'], 'body' => 'Reply while a render runs.']);
            } finally {
                flock($page, LOCK_UN);
                fclose($page);
            }
            assertSame('ok', $reply['status']);
            assertSame('old HTML', file_get_contents($cache->path($route)));
            assertSame([$route], (new HighTrafficRefresh($cache))->hints());
        } finally {
            $restore();
        }
    }

    public function testModeOffLeavesCacheUntouched(): void
    {
        [$repository, $database, $public, $cache, $restore] = $this->environment();
        try {
            unlink($public . '/.high-traffic');
            $route = '/threads/root-001/';
            $cache->atomic($cache->path($route), 'old HTML');
            $result = $this->service($repository, $database, $public)->createReply(['thread_id' => 'root-001', 'parent_id' => 'root-001', 'body' => 'Reply with the mode off.']);
            assertSame('ok', $result['status']);
            assertSame('old HTML', file_get_contents($cache->path($route)));
            assertSame(false, isset($result['timings']['high_traffic_refresh']));
        } finally {
            $restore();
        }
    }

    /** @return array{string,string,string,HighTrafficCache,callable} */
    private function environment(): array
    {
        $tmp = sys_get_temp_dir() . '/v3-post-refresh-' . bin2hex(random_bytes(6));
        $repository = $tmp . '/repo';
        $public = $tmp . '/public';
        $database = $tmp . '/read.sqlite3';
        mkdir($repository, 0777, true);
        mkdir($public, 0777, true);
        exec('cp -R ' . escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1') . '/. ' . escapeshellarg($repository));
        $this->run($repository, 'git init -q && git config user.name "Forum Rewrite" && git config user.email "forum-rewrite@example.invalid" && git add . && git -c commit.gpgsign=false commit -q -m init');
        $application = new Application(dirname(__DIR__), $repository, $database, $public);
        ob_start();
        $application->handle('GET', '/');
        ob_end_clean();

        $old = getenv('FORUM_PUBLIC_ARTIFACT_ROOT');
        putenv('FORUM_PUBLIC_ARTIFACT_ROOT=' . $public);
        $cache = new HighTrafficCache(dirname(__DIR__), $repository, $database, $public);
        $cache->initialize();
        $cache->atomic($cache->root . '/state.json', json_encode(['enabled' => true, 'generation' => 'test', 'public_root' => realpath($public)]));
        symlink($cache->root . '/pages', $public . '/.high-traffic');
        $restore = static function () use ($old, $cache, $tmp): void {
            putenv($old === false ? 'FORUM_PUBLIC_ARTIFACT_ROOT' : 'FORUM_PUBLIC_ARTIFACT_ROOT=' . $old);
            exec('rm -rf ' . escapeshellarg($cache->root) . ' ' . escapeshellarg($tmp));
        };

        return [$repository, $database, $public, $cache, $restore];
    }

    private function service(string $repository, string $database, string $public): LocalWriteService
    {
        return new LocalWriteService($repository, $database, $public, new CanonicalRecordRepository($repository), featureFlags: FeatureFlagEvaluator::forRepository($repository));
    }

    private function run(string $directory, string $command): void
    {
        exec('cd ' . escapeshellarg($directory) . ' && ' . $command . ' 2>&1', $output, $code);
        if ($code !== 0) {
            throw new RuntimeException('Command failed: ' . $command . "\n" . implode("\n", $output));
        }
    }
}
