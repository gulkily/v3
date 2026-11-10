<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/**
 * Human-readable byte counts and cheap size probes for /tools/codebase/.
 *
 * Probes return null instead of throwing so a missing file, a source-only
 * deploy without .git, or a slow git call costs one "unavailable" row, never
 * the page. None of them walk the filesystem: Git stores are sized from
 * `git count-objects -v`, tracked trees from blob sizes in `git ls-tree -l`.
 */
final class ByteSize
{
    private const UNITS = ['KiB', 'MiB', 'GiB', 'TiB'];
    private const PROBE_SECONDS = 2;
    private const MAX_LS_TREE_OUTPUT = 64 * 1024 * 1024;

    public static function format(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        $value = $bytes / 1024;
        $unit = 0;
        while ($value >= 1024 && $unit < count(self::UNITS) - 1) {
            $value /= 1024;
            $unit++;
        }

        return number_format($value, 1, '.', '') . ' ' . self::UNITS[$unit];
    }

    public static function file(string $path): ?int
    {
        if (!is_file($path) || is_link($path)) {
            return null;
        }
        clearstatcache(true, $path);
        $size = @filesize($path);

        return $size === false ? null : $size;
    }

    /** Loose plus packed object storage, as reported by `git count-objects -v`. */
    public static function gitStore(string $root): ?int
    {
        $output = self::git($root, ['count-objects', '-v'], 64 * 1024);
        if ($output === null) {
            return null;
        }
        $kib = [];
        foreach (explode("\n", $output) as $line) {
            if (preg_match('/^(size|size-pack): (\d+)$/D', trim($line), $m)) {
                $kib[$m[1]] = (int) $m[2];
            }
        }
        if (!isset($kib['size'], $kib['size-pack'])) {
            return null;
        }

        return ($kib['size'] + $kib['size-pack']) * 1024;
    }

    /** Sum of blob sizes committed under $path at HEAD (not the working tree). */
    public static function trackedTree(string $root, string $path): ?int
    {
        $output = self::git($root, ['ls-tree', '-r', '-l', 'HEAD', '--', $path], self::MAX_LS_TREE_OUTPUT);
        if ($output === null) {
            return null;
        }
        $total = 0;
        foreach (explode("\n", $output) as $line) {
            // "<mode> blob <sha> <size>\t<path>"; submodules report "-".
            if (preg_match('/^\d+ blob [0-9a-f]+ +(\d+)\t/', $line, $m)) {
                $total += (int) $m[1];
            }
        }

        return $total;
    }

    /** @param list<string> $arguments */
    private static function git(string $root, array $arguments, int $maxOutput): ?string
    {
        // A .git file is valid for linked worktrees and submodules; without
        // either, do not report an enclosing repository.
        if (!is_dir($root . '/.git') && !is_file($root . '/.git')) {
            return null;
        }
        // coreutils timeout bounds a slow git; a blocking read keeps a few MB
        // of ls-tree output fast (BoundedProcess polls in 64 KiB / 10 ms steps).
        $timeout = is_executable('/usr/bin/timeout') ? '/usr/bin/timeout' : (is_executable('/bin/timeout') ? '/bin/timeout' : null);
        if ($timeout === null || !function_exists('proc_open')) {
            return null;
        }
        $process = @proc_open(
            [$timeout, '-s', 'KILL', (string) self::PROBE_SECONDS, 'git', '--no-optional-locks', '-C', $root, ...$arguments],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
        );
        if (!is_resource($process)) {
            return null;
        }
        $output = stream_get_contents($pipes[1], $maxOutput + 1);
        $overflow = $output !== false && strlen($output) > $maxOutput;
        fclose($pipes[1]);
        if ($overflow) {
            proc_terminate($process, 9);
        }
        $exitCode = proc_close($process);

        return ($output === false || $overflow || $exitCode !== 0) ? null : $output;
    }
}
