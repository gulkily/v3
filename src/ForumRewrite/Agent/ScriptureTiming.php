<?php
declare(strict_types=1);

namespace ForumRewrite\Agent;

use ForumRewrite\Support\PrivateConfig;

/** Optional private phase markers: never contain forum text or provider credentials. */
final class ScriptureTiming
{
    public static function record(string $projectRoot, string $postId, string $event): void
    {
        $config = PrivateConfig::load($projectRoot);
        if (!filter_var($config['SCRIPTURE_RESPONSE_TIMING_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN)) { return; }
        $directory = $projectRoot . '/state/private';
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) { return; }
        @file_put_contents($directory . '/scripture_timing.jsonl', json_encode([
            'post_id' => $postId, 'event' => $event, 'at_ms' => microtime(true) * 1000,
        ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . "\n", FILE_APPEND | LOCK_EX);
    }
}
