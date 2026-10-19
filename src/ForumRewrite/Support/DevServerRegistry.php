<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/** Checkout-local runtime hints. Readers never create, prune, or repair files. */
final class DevServerRegistry
{
    public static function register(string $root, string $repository, string $database, int $pid, string $address): ?string
    {
        // proc_open can return before exec replaces the child's command line.
        $expected = ['-S', $address, '-t', $root . '/public', $root . '/public/router.php'];
        $process = null;
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $command = @file_get_contents('/proc/' . $pid . '/cmdline');
            if ($command !== false && array_slice(explode("\0", rtrim($command, "\0")), 1) === $expected) {
                $process = self::processIdentity($pid);
                break;
            }
            usleep(1000);
        }
        if ($process === null) { return null; }
        $directory = $root . '/state/runtime/frontend';
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) { return null; }
        $path = $directory . '/' . $pid . '-' . $process . '.json';
        $host = substr($address, 0, strrpos($address, ':'));
        $port = substr($address, strrpos($address, ':') + 1);
        if ($host === '0.0.0.0') { $host = '127.0.0.1'; }
        if ($host === '[::]') { $host = '[::1]'; }
        $contents = json_encode(['pid' => $pid, 'process' => $process,
            'identity' => FrontendInstance::identity($root, $repository, $database),
            'url' => 'http://' . $host . ':' . $port], JSON_THROW_ON_ERROR);
        if (@file_put_contents($path, $contents, LOCK_EX) === false) { return null; }
        @chmod($path, 0600);
        return $path;
    }

    /** @return list<array{url:string,source:string,identity:string}> */
    public static function active(string $root, string $repository, string $database): array
    {
        $identity = FrontendInstance::identity($root, $repository, $database);
        $entries = [];
        foreach (glob($root . '/state/runtime/frontend/*.json') ?: [] as $path) {
            if (is_link($path) || filesize($path) > 4096) { continue; }
            $record = json_decode((string) @file_get_contents($path), true);
            if (!is_array($record) || ($record['identity'] ?? '') !== $identity
                || !is_int($record['pid'] ?? null) || !is_string($record['process'] ?? null)
                || self::processIdentity($record['pid']) !== $record['process'] || !is_string($record['url'] ?? null)) { continue; }
            try { $url = FrontendTargetResolver::validateUrl($record['url']); } catch (\RuntimeException) { continue; }
            $entries[] = ['url' => $url, 'source' => 'development server', 'identity' => $identity];
        }
        return $entries;
    }

    private static function processIdentity(int $pid): ?string
    {
        // Start ticks, boot identity and command line reject stale files and reused PIDs.
        $stat = @file_get_contents('/proc/' . $pid . '/stat');
        $command = @file_get_contents('/proc/' . $pid . '/cmdline');
        $boot = @file_get_contents('/proc/sys/kernel/random/boot_id');
        if ($stat === false || $command === false || $command === '' || $boot === false) { return null; }
        $fields = explode(' ', substr($stat, strrpos($stat, ')') + 2));
        if (!isset($fields[19]) || in_array($fields[0], ['Z', 'X'], true)) { return null; }
        return hash('sha256', $boot . "\0" . $fields[19] . "\0" . $command);
    }
}
