<?php

declare(strict_types=1);
namespace ForumRewrite\Host;

/**
 * Lock-free per-second count of PHP requests, used to turn high-traffic mode on automatically.
 * Each request appends one byte to a file named for the current second; a bucket's size is its count.
 */
final class HighTrafficRate
{
    private const KEEP_SECONDS = 60;
    public function __construct(public readonly string $directory) {}
    public static function forCache(HighTrafficCache $cache): self { return new self($cache->root . '/rate'); }
    /**
     * Count one request. Returns true only for the first request in a new second, which
     * is the one that may check the rate and prune old buckets; never throws.
     */
    public function record(?int $now = null): bool
    {
        $now ??= time();
        $bucket = $this->directory . '/' . $now;
        $first = @fopen($bucket, 'x');
        if ($first === false) {
            // Usual case: the second's bucket exists, so append to it.
            if (@file_put_contents($bucket, '.', FILE_APPEND) !== false) return false;
            if (!@mkdir($this->directory, 0755, true) && !is_dir($this->directory)) return false;
            $first = @fopen($bucket, 'x');
            if ($first === false) { @file_put_contents($bucket, '.', FILE_APPEND); return false; }
        }
        fwrite($first, '.');
        fclose($first);
        $this->prune($now);
        return true;
    }
    /** Average requests per second over the complete seconds before $now. */
    public function perSecond(int $window, ?int $now = null): float
    {
        $now ??= time();
        $window = max(1, min($window, self::KEEP_SECONDS - 1));
        $total = 0;
        for ($second = $now - $window; $second < $now; $second++) {
            $size = @filesize($this->directory . '/' . $second);
            if ($size !== false) $total += $size;
        }
        return $total / $window;
    }
    private function prune(int $now): void
    {
        foreach (@scandir($this->directory) ?: [] as $name) {
            if (ctype_digit($name) && (int)$name < $now - self::KEEP_SECONDS) @unlink($this->directory . '/' . $name);
        }
    }
}
