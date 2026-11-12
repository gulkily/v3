<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Host\HighTrafficAutomation;
use ForumRewrite\Host\HighTrafficCache;
use ForumRewrite\Host\HighTrafficMode;
use ForumRewrite\Host\HighTrafficRate;
use ForumRewrite\Host\HighTrafficSafety;

final class HighTrafficAutomationTest
{
    public function testRateCountsRequestsPerSecondAndPrunesOldBuckets(): void
    {
        $directory = sys_get_temp_dir() . '/v3-rate-' . bin2hex(random_bytes(6));
        $rate = new HighTrafficRate($directory);
        $now = 1_800_000_000;
        assertSame(true, $rate->record($now - 3), 'first request creates the directory and opens its second');
        assertSame(false, $rate->record($now - 3));
        assertSame(true, $rate->record($now - 2));
        for ($i = 0; $i < 4; $i++) $rate->record($now - 1);
        $rate->record($now);
        assertSame(7 / 3, $rate->perSecond(3, $now), 'the current, incomplete second is excluded');
        assertSame(7 / 15, $rate->perSecond(15, $now));
        assertSame(true, is_file($directory . '/' . ($now - 3)));
        $rate->record($now + 61);
        assertSame(false, is_file($directory . '/' . ($now - 3)), 'buckets older than a minute are pruned');
        assertSame(0.0, (new HighTrafficRate($directory . '/missing'))->perSecond(15, $now));
        foreach (glob($directory . '/*') ?: [] as $file) unlink($file);
        rmdir($directory);
    }

    /** @return array{0:string,1:HighTrafficMode} */
    private function site(): array
    {
        $root = sys_get_temp_dir() . '/v3-auto-' . bin2hex(random_bytes(6));
        mkdir($root . '/public', 0755, true);
        exec('cp -R ' . escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1') . ' ' . escapeshellarg($root . '/repo'));
        file_put_contents($root . '/public/.htaccess', file_get_contents(dirname(__DIR__) . '/public/.htaccess'));
        // The project root is the real one, as for HighTrafficSafety's own cache; the cache
        // directory is keyed by this temporary repository, so it is unique to the test.
        putenv('FORUM_PUBLIC_ARTIFACT_ROOT=' . $root . '/public');
        $cache = new HighTrafficCache(dirname(__DIR__), $root . '/repo', $root . '/read.sqlite3', $root . '/public');
        $cache->initialize();
        $this->caches[$root] = $cache->root;
        return [$root, new HighTrafficMode($cache)];
    }
    /** @var array<string,string> */
    private array $caches = [];
    private function cleanup(string $root): void
    {
        putenv('FORUM_PUBLIC_ARTIFACT_ROOT');
        @unlink($root . '/public/.high-traffic');
        HighTrafficCache::removeTemporaryTree($this->caches[$root]);
        HighTrafficCache::removeTemporaryTree($root);
    }

    public function testChangesRecordTheirSourceAndAnOperatorOffHoldsAgainstAutomation(): void
    {
        [$root, $mode] = $this->site();
        try {
            assertSame('operator', $mode->status()['source'], 'no state yet reads as operator');
            assertSame(null, HighTrafficAutomation::turnOnBlocked($mode));
            $mode->change(true, 'Turned on automatically, 4.0 requests/s', 'automatic');
            $status = $mode->status();
            assertSame('high-traffic', $status['effective_mode']);
            assertSame('automatic', $status['source']);
            assertSame('already on', HighTrafficAutomation::turnOnBlocked($mode));
            $mode->change(false, 'Operator CLI disable');
            assertSame('operator', $mode->status()['source']);
            assertStringContains('turned off by an operator', (string)HighTrafficAutomation::turnOnBlocked($mode));
            $mode->change(true, 'Operator CLI enable');
            $mode->change(false, 'Turned off automatically after 30 quiet minutes', 'automatic');
            assertSame(null, HighTrafficAutomation::turnOnBlocked($mode), 'an operator on clears the hold');
            $threw = false;
            try { $mode->change(true, 'x', 'someone'); } catch (RuntimeException) { $threw = true; }
            assertTrue($threw);
        } finally { $this->cleanup($root); }
    }

    public function testWithdrawalsKeepAutomationOutUntilAnOperatorTurnsTheModeOn(): void
    {
        [$root, $mode] = $this->site();
        $cache = $mode->cache;
        try {
            $mode->change(true, 'Turned on automatically, 4.0 requests/s', 'automatic');
            HighTrafficSafety::beforeWrite($cache->repositoryRoot, $cache->databasePath, 'records/feature-flags/site.txt');
            assertSame('withdrawal', $cache->state()['source']);
            assertSame('withdrawal', $mode->status()['source']);
            assertStringContains('withdrawn', (string)HighTrafficAutomation::turnOnBlocked($mode));
            $mode->change(true, 'Operator CLI enable');
            // Removals detach serving without writing state.
            HighTrafficSafety::detach($cache->publicRoot);
            assertSame('withdrawal', $mode->status()['source']);
            assertStringContains('withdrawn', (string)HighTrafficAutomation::turnOnBlocked($mode));
            // A withdrawal written by older code has only its reason.
            $cache->atomic($cache->root . '/state.json', json_encode(['enabled'=>false,'generation'=>'g','reason'=>'Content/access change; operator may re-enable after review.']));
            assertSame('withdrawal', $mode->status()['source']);
            // Any other legacy off state is an operator off without a hold.
            $cache->atomic($cache->root . '/state.json', json_encode(['enabled'=>false,'generation'=>'g','reason'=>'Operator CLI disable']));
            HighTrafficAutomation::write($cache, ['Manual-Off-Hold' => 'no']);
            assertSame('operator', $mode->status()['source']);
            assertSame(null, HighTrafficAutomation::turnOnBlocked($mode));
        } finally { $this->cleanup($root); }
    }

    public function testSwitchesAreKeptAsKeyValueTextAndShownInStatus(): void
    {
        [$root, $mode] = $this->site();
        $cache = $mode->cache;
        try {
            assertSame(false, $mode->status()['turn_on_automatically']);
            HighTrafficAutomation::set($cache, true, null, 'Operator CLI');
            $text = (string)file_get_contents($cache->root . '/automation.txt');
            assertStringContains("Turn-On: yes\n", $text);
            assertStringNotContains('{', $text);
            assertSame(true, HighTrafficAutomation::turnOnEnabled($cache));
            HighTrafficAutomation::set($cache, null, true, 'Operator CLI');
            HighTrafficAutomation::set($cache, false, null, "Operator\nCLI");
            $status = $mode->status();
            assertSame(false, $status['turn_on_automatically']);
            assertSame(true, $status['turn_off_automatically']);
            assertSame(1, substr_count((string)file_get_contents($cache->root . '/automation.txt'), 'Reason:'));
        } finally { $this->cleanup($root); }
    }

    public function testCliSetsEachSwitchSeparately(): void
    {
        [$root, $mode] = $this->site();
        $env = 'FORUM_REPOSITORY_ROOT=' . escapeshellarg($mode->cache->repositoryRoot) . ' FORUM_DATABASE_PATH=' . escapeshellarg($mode->cache->databasePath)
            . ' FORUM_PUBLIC_ARTIFACT_ROOT=' . escapeshellarg($mode->cache->publicRoot);
        $script = escapeshellarg(dirname(__DIR__) . '/scripts/high_traffic.php');
        try {
            exec($env . ' php ' . $script . ' auto-on on --json 2>&1', $out, $code);
            assertSame(0, $code, implode("\n", $out));
            $cache = $mode->cache;
            assertSame(true, HighTrafficAutomation::turnOnEnabled($cache));
            assertSame(false, HighTrafficAutomation::turnOffEnabled($cache));
            exec($env . ' php ' . $script . ' auto-on maybe 2>&1', $bad, $code);
            assertSame(1, $code);
            exec($env . ' php ' . $script . ' auto-on off 2>&1', $plain, $code);
            assertSame(0, $code);
            assertStringContains('Turn on automatically: no', implode("\n", $plain));
        } finally { $this->cleanup($root); }
    }
}
