<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Host\HighTrafficRate;

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
}
