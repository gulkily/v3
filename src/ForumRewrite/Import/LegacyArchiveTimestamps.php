<?php

declare(strict_types=1);

namespace ForumRewrite\Import;

use ForumRewrite\Canonical\CanonicalRecordParseException;
use ForumRewrite\Canonical\LegacyPostTimestamp;
use ForumRewrite\Canonical\PostRecordParser;
use Closure;
use RuntimeException;

/** Reads source history only in a private, reconstructed bare repository. */
final class LegacyArchiveTimestamps
{
    public function __construct(
        private readonly ?Closure $progress = null,
        private readonly int $maxConcurrentProcesses = 4,
    ) {
        if ($maxConcurrentProcesses < 1 || $maxConcurrentProcesses > 4) {
            throw new RuntimeException('Legacy history concurrency must be between one and four.');
        }
    }

    public static function isHistoryPath(string $path): bool
    {
        return in_array($path, ['.git/HEAD', '.git/packed-refs'], true)
            || preg_match('#^\.git/(objects/[a-f0-9]{2}/[a-f0-9]{38}|objects/pack/pack-[a-f0-9]{40}\.(pack|idx)|refs/heads/[A-Za-z0-9._/-]+)$#D', $path) === 1;
    }

    public function recover(string $root): int
    {
        $progress = new ImportProgress($this->progress);
        $progress->start('Legacy timestamps: inspecting source history...');
        $history = $root . '/.import-history';
        $headFile = $history . '/HEAD';
        if (!is_file($headFile) || filesize($headFile) > 4096) {
            $progress->update('Legacy timestamps: no usable source history; skipping recovery', true);
            return 0;
        }
        $head = trim((string) file_get_contents($headFile));
        if (preg_match('#^ref: (refs/heads/[A-Za-z0-9._/-]+)$#D', $head, $match)) {
            $ref = $match[1];
            if (array_intersect(explode('/', $ref), ['', '.', '..']) !== []) {
                $progress->update('Legacy timestamps: unusable source history reference; skipping recovery', true);
                return 0;
            }
            $head = '';
            if (is_file($history . '/' . $ref) && filesize($history . '/' . $ref) <= 4096) {
                $head = trim((string) file_get_contents($history . '/' . $ref));
            } elseif (is_file($history . '/packed-refs') && filesize($history . '/packed-refs') <= 1048576) {
                foreach (file($history . '/packed-refs', FILE_IGNORE_NEW_LINES) as $line) {
                    if (preg_match('/^([a-f0-9]{40}) ' . preg_quote($ref, '/') . '$/D', $line, $m)) { $head = $m[1]; }
                }
            }
        }
        if (preg_match('/^[a-f0-9]{40}$/D', $head) !== 1) {
            $progress->update('Legacy timestamps: no usable source history tip; skipping recovery', true);
            return 0;
        }
        // No source configuration, hooks, replacements, grafts, alternates, or index.
        file_put_contents($headFile, $head . "\n");
        file_put_contents($history . '/config', "[core]\nrepositoryformatversion = 0\nbare = true\n");
        foreach (['objects', 'refs'] as $directory) {
            if (!is_dir($history . '/' . $directory)) { mkdir($history . '/' . $directory, 0700, true); }
        }
        $deadline = microtime(true) + 120;
        $count = 0;
        $candidates = [];
        $paths = ContentImportPlanner::files($root);
        $progress->start('Legacy timestamps: scanning record files...');
        foreach ($paths as $index => $path) {
            $progress->update(sprintf('Legacy timestamps: scanned %d/%d record files; %d posts need dates', $index, count($paths), count($candidates)));
            if (!str_starts_with($path, 'records/posts/') || !str_ends_with($path, '.txt')
                || !ArchiveRecordCatalog::isRecordPath($path) || filesize($root . '/' . $path) > 16 * 1024 * 1024) { continue; }
            $id = basename($path, '.txt');
            $metadata = $root . '/' . LegacyPostTimestamp::path($id);
            if (file_exists($metadata)) { continue; }
            $contents = (string) file_get_contents($root . '/' . $path);
            try {
                (new PostRecordParser())->parse($contents);
                continue;
            } catch (CanonicalRecordParseException $error) {
                if ($error->getMessage() !== 'Missing required post header: Created-At') { continue; }
            }
            $candidates[] = $path;
        }
        $progress->update(sprintf('Legacy timestamp scan complete: %d record files; %d posts need dates', count($paths), count($candidates)), true);
        $progress->start(sprintf('Legacy timestamp recovery: checking %d posts with up to %d concurrent Git processes', count($candidates), $this->maxConcurrentProcesses));
        // First verify every archived post against the tracked tip. Keep only tiny
        // hashes/dates in memory; completed Git output is consumed immediately.
        $queries = [];
        foreach ($candidates as $path) {
            $queries[$path] = ['rev-parse', '--verify', $head . ':' . $path];
        }
        $histories = [];
        $verifiedHashes = [];
        foreach ($this->gitBatch($history, $queries, $deadline, $progress, 'Legacy timestamp verification') as $path => $blob) {
            $contents = (string) file_get_contents($root . '/' . $path);
            if (trim($blob ?? '') === sha1('blob ' . strlen($contents) . "\0" . $contents)) {
                $verifiedHashes[$path] = hash('sha256', $contents);
                $histories[$path] = ['log', '--no-ext-diff', '--no-textconv', '--diff-filter=A', '--follow', '--format=%aI', $head, '--', $path];
            }
        }
        $dates = [];
        foreach ($this->gitBatch($history, $histories, $deadline, $progress, 'Legacy timestamp history lookup') as $path => $log) {
            if ($log === null || trim($log) === '') { continue; }
            $lines = explode("\n", trim($log));
            $raw = end($lines);
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/D', $raw) !== 1) { continue; }
            $dates[$path] = (new \DateTimeImmutable($raw))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        }
        // Apply in canonical path order, independent of worker completion order.
        foreach ($candidates as $path) {
            if (!isset($dates[$path])) { continue; }
            $id = basename($path, '.txt');
            $metadata = $root . '/' . LegacyPostTimestamp::path($id);
            if (file_exists($metadata)) { continue; }
            $contents = (string) file_get_contents($root . '/' . $path);
            if (hash('sha256', $contents) !== $verifiedHashes[$path]) { continue; }
            if (!is_dir(dirname($metadata))) { mkdir(dirname($metadata), 0700, true); }
            if (file_put_contents($metadata, LegacyPostTimestamp::encode($id, $contents, $dates[$path])) === false) {
                throw new RuntimeException('Unable to retain legacy creation timestamp.');
            }
            $count++;
        }
        $progress->update(sprintf('Legacy timestamp recovery complete: %d/%d posts checked; %d dates recovered', count($candidates), count($candidates), $count), true);
        return $count;
    }

    /** @return \Generator<string, ?string> Results arrive as jobs finish. */
    private function gitBatch(string $directory, array $queries, float $deadline, ImportProgress $progress, string $phase): \Generator
    {
        // A fresh environment also blocks caller-provided Git configuration/objects.
        $env = ['PATH' => '/usr/bin:/bin', 'GIT_CONFIG_NOSYSTEM' => '1', 'GIT_CONFIG_GLOBAL' => '/dev/null',
            'GIT_NO_REPLACE_OBJECTS' => '1', 'GIT_TERMINAL_PROMPT' => '0', 'GIT_NO_LAZY_FETCH' => '1', 'LC_ALL' => 'C'];
        $pending = new \ArrayIterator($queries);
        $running = [];
        $completed = 0;
        $progress->update(sprintf('%s: 0/%d posts checked', $phase, count($queries)), true);
        try {
            while ($pending->valid() || $running !== []) {
                if (microtime(true) > $deadline) {
                    throw new RuntimeException('Source history exceeds the 120 second or 1 MiB query output limit.');
                }
                while ($pending->valid() && count($running) < $this->maxConcurrentProcesses) {
                    $path = $pending->key();
                    $process = proc_open(['git', '--git-dir=' . $directory, '-c', 'protocol.allow=never', ...$pending->current()],
                        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, $directory, $env);
                    if (!is_resource($process)) { throw new RuntimeException('Unable to read isolated source history.'); }
                    stream_set_blocking($pipes[1], false);
                    $running[$path] = ['process' => $process, 'output' => $pipes[1], 'contents' => ''];
                    $pending->next();
                }
                foreach (array_keys($running) as $path) {
                    $job = $running[$path];
                    $job['contents'] .= stream_get_contents($job['output']);
                    $status = proc_get_status($job['process']);
                    if (!$status['running']) {
                        $job['contents'] .= stream_get_contents($job['output']);
                    }
                    $running[$path] = $job;
                    if (strlen($job['contents']) > 1048576) {
                        throw new RuntimeException('Source history exceeds the 120 second or 1 MiB query output limit.');
                    }
                    if (!$status['running']) {
                        $result = $status['exitcode'] === 0 ? $job['contents'] : null;
                        fclose($job['output']);
                        proc_close($job['process']);
                        unset($job, $running[$path]);
                        $completed++;
                        yield $path => $result;
                    }
                    unset($job);
                }
                $progress->update(sprintf('%s: %d/%d posts checked; %d Git processes running', $phase, $completed, count($queries), count($running)));
                if ($running !== []) { usleep(10000); }
            }
        } finally {
            // Includes timeouts, output limits, consumer exceptions and early exit.
            // Stop all children before waiting on any one of them.
            foreach ($running as $job) { proc_terminate($job['process'], 9); }
            foreach ($running as $job) {
                fclose($job['output']);
                proc_close($job['process']);
            }
        }
        $progress->update(sprintf('%s complete: %d/%d posts checked', $phase, $completed, count($queries)), true);
    }
}
