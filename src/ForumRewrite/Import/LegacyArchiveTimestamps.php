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
    public function __construct(private readonly ?Closure $progress = null)
    {
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
        $progress->start(sprintf('Legacy timestamp recovery: 0/%d posts checked', count($candidates)));
        foreach ($candidates as $index => $path) {
            $id = basename($path, '.txt');
            $metadata = $root . '/' . LegacyPostTimestamp::path($id);
            if (file_exists($metadata)) { continue; }
            $contents = (string) file_get_contents($root . '/' . $path);
            $message = sprintf('Legacy timestamp recovery: checking post %d/%d; %d dates recovered', $index + 1, count($candidates), $count);
            $progress->update($message);
            // Only assign history dates when the downloaded bytes match the tracked tip.
            $blob = trim($this->git($history, ['rev-parse', '--verify', $head . ':' . $path], $deadline, $progress, $message) ?? '');
            if ($blob !== sha1('blob ' . strlen($contents) . "\0" . $contents)) { continue; }
            $log = $this->git($history, ['log', '--no-ext-diff', '--no-textconv', '--diff-filter=A', '--follow', '--format=%aI', $head, '--', $path], $deadline, $progress, $message);
            if ($log === null || trim($log) === '') { continue; }
            $dates = explode("\n", trim($log));
            $raw = end($dates);
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/D', $raw) !== 1) { continue; }
            $date = (new \DateTimeImmutable($raw))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
            if (!is_dir(dirname($metadata))) { mkdir(dirname($metadata), 0700, true); }
            if (file_put_contents($metadata, LegacyPostTimestamp::encode($id, $contents, $date)) === false) {
                throw new RuntimeException('Unable to retain legacy creation timestamp.');
            }
            $count++;
        }
        $progress->update(sprintf('Legacy timestamp recovery complete: %d/%d posts checked; %d dates recovered', count($candidates), count($candidates), $count), true);
        return $count;
    }

    private function git(string $directory, array $arguments, float $deadline, ImportProgress $progress, string $message): ?string
    {
        // A fresh environment also blocks caller-provided Git configuration/objects.
        $env = ['PATH' => '/usr/bin:/bin', 'GIT_CONFIG_NOSYSTEM' => '1', 'GIT_CONFIG_GLOBAL' => '/dev/null',
            'GIT_NO_REPLACE_OBJECTS' => '1', 'GIT_TERMINAL_PROMPT' => '0', 'GIT_NO_LAZY_FETCH' => '1', 'LC_ALL' => 'C'];
        $process = proc_open(['git', '--git-dir=' . $directory, '-c', 'protocol.allow=never', ...$arguments],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, $directory, $env);
        if (!is_resource($process)) { throw new RuntimeException('Unable to read isolated source history.'); }
        stream_set_blocking($pipes[1], false);
        $output = '';
        try {
            do {
                $output .= stream_get_contents($pipes[1]);
                if (microtime(true) > $deadline || strlen($output) > 1048576) {
                    proc_terminate($process, 9);
                    throw new RuntimeException('Source history exceeds the 120 second or 1 MiB query output limit.');
                }
                $status = proc_get_status($process);
                if (!$status['running']) {
                    $output .= stream_get_contents($pipes[1]);
                    return $status['exitcode'] === 0 ? $output : null;
                }
                $progress->update($message);
                usleep(10000);
            } while (true);
        } finally {
            fclose($pipes[1]);
            proc_close($process);
        }
    }
}
