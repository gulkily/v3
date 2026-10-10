<?php

declare(strict_types=1);

namespace ForumRewrite\Import;

use Closure;
use RuntimeException;

/** Bounded tar.gz reader. Validates every entry before extracting records plus isolated objects for legacy date recovery. */
final class RepositoryArchive
{
    public function __construct(
        private readonly int $maxCompressedBytes = 268435456,
        private readonly int $maxExpandedBytes = 1073741824,
        private readonly int $maxEntries = 100000,
        private readonly ?Closure $progress = null,
    ) {
    }

    public function extract(string $archive, string $directory): array
    {
        if (!is_file($archive) || filesize($archive) > $this->maxCompressedBytes) {
            throw new RuntimeException('Archive is missing or exceeds compressed size limit.');
        }
        $stream = gzopen($archive, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Unable to open gzip archive.');
        }
        $progress = new ImportProgress($this->progress);
        $progress->start('Archive validation: scanning entries...');
        $offset = 0;
        $entries = [];
        $roots = [];
        $longName = null;
        try {
            while (true) {
                $header = $this->read($stream, 512, $offset);
                if ($header === str_repeat("\0", 512)) {
                    if ($this->read($stream, 512, $offset) !== $header) {
                        throw new RuntimeException('Invalid tar end marker.');
                    }
                    // Account for padding and reject concatenated/uninspected tar content.
                    while (!gzeof($stream)) {
                        $tail = gzread($stream, 8192);
                        if ($tail === false || trim($tail, "\0") !== '') {
                            throw new RuntimeException('Invalid data after tar end marker.');
                        }
                        $offset += strlen($tail);
                        $this->checkSize($offset);
                        $progress->update(sprintf('Archive validation: %d entries, %.1f MiB checked', count($entries), $offset / 1048576));
                    }
                    break;
                }
                $checksum = $this->octal(substr($header, 148, 8));
                $checkHeader = substr_replace($header, str_repeat(' ', 8), 148, 8);
                if (array_sum(unpack('C*', $checkHeader)) !== $checksum) {
                    throw new RuntimeException('Invalid tar header checksum.');
                }
                $size = $this->octal(substr($header, 124, 12));
                $type = $header[156];
                $name = rtrim(substr($header, 0, 100), "\0");
                if (substr($header, 257, 6) === "ustar\0") {
                    $prefix = rtrim(substr($header, 345, 155), "\0");
                    $name = ($prefix !== '' ? $prefix . '/' : '') . $name;
                }
                if (count($entries) >= $this->maxEntries) {
                    throw new RuntimeException('Archive exceeds entry count limit.');
                }
                if ($type === 'L') {
                    if ($longName !== null || $size > 4096) {
                        throw new RuntimeException('Invalid GNU tar long name.');
                    }
                    $longName = rtrim($this->read($stream, $size, $offset), "\0");
                    $this->read($stream, (512 - $size % 512) % 512, $offset);
                    continue;
                }
                $name = $longName ?? $name;
                $longName = null;
                if (in_array($name, ['.', './'], true) && $type === '5' && $size === 0) {
                    continue;
                }
                $name = $this->safePath($name);
                if (!in_array($type, ["\0", '0', '5'], true) || ($type === '5' && $size !== 0)) {
                    throw new RuntimeException('Archive contains a link, special entry, or unsupported tar extension: ' . $name);
                }
                if (isset($entries[$name])) {
                    throw new RuntimeException('Archive contains duplicate entry: ' . $name);
                }
                $entries[$name] = ['offset' => $offset, 'size' => $size, 'directory' => $type === '5'];
                if (preg_match('#^(?:(.+)/)?records(?:/|$)#', $name, $match)) {
                    $roots[$match[1] ?? ''] = true;
                }
                $remaining = $size + (512 - $size % 512) % 512;
                while ($remaining > 0) {
                    $length = min(65536, $remaining);
                    $this->read($stream, $length, $offset);
                    $remaining -= $length;
                    $progress->update(sprintf('Archive validation: %d entries, %.1f MiB checked', count($entries), $offset / 1048576));
                }
            }
        } finally {
            gzclose($stream);
        }
        if ($longName !== null || count($roots) !== 1) {
            throw new RuntimeException('Archive must contain one unambiguous records/ root.');
        }
        $progress->update(sprintf('Archive validation complete: %d entries, %.1f MiB checked', count($entries), $offset / 1048576), true);
        $progress->start('Archive integrity: checking gzip checksum...');
        // gzip verifies the trailer/CRC; the preceding pass already bounded decompression.
        $process = proc_open(['gzip', '-t', '--', $archive], [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to validate gzip archive.');
        }
        stream_set_blocking($pipes[2], false);
        $error = '';
        try {
            do {
                $error .= stream_get_contents($pipes[2]);
                $status = proc_get_status($process);
                if (!$status['running']) { break; }
                $progress->update('Archive integrity: checking gzip checksum...');
                usleep(100000);
            } while (true);
            $error .= stream_get_contents($pipes[2]);
        } finally {
            fclose($pipes[2]);
            proc_close($process);
        }
        if ($status['exitcode'] !== 0) {
            throw new RuntimeException('Invalid gzip archive: ' . trim($error));
        }
        $progress->update('Archive integrity check complete', true);
        if (file_exists($directory) || is_link($directory)) {
            throw new RuntimeException('Extraction directory must not already exist.');
        }
        if (!mkdir($directory . '/records', 0700, true)) {
            throw new RuntimeException('Unable to create private extraction directory.');
        }
        $prefix = (string) array_key_first($roots);
        $prefix = $prefix === '' ? '' : $prefix . '/';
        $excluded = [];
        $totalFiles = 0;
        $totalBytes = 0;
        foreach ($entries as $path => $entry) {
            if (!$entry['directory'] && str_starts_with($path, $prefix)
                && (str_starts_with($path, $prefix . 'records/') || LegacyArchiveTimestamps::isHistoryPath(substr($path, strlen($prefix))))) {
                $totalFiles++;
                $totalBytes += $entry['size'];
            }
        }
        $copiedFiles = 0;
        $copiedBytes = 0;
        $progress->start(sprintf('Archive extraction: 0/%d files, 0.0/%.1f MiB', $totalFiles, $totalBytes / 1048576));
        $stream = gzopen($archive, 'rb');
        try {
            foreach ($entries as $path => $entry) {
                if ($entry['directory']) {
                    continue;
                }
                if (!str_starts_with($path, $prefix . 'records/')) {
                    $category = str_starts_with($path, $prefix) ? explode('/', substr($path, strlen($prefix)))[0] : 'outside-repository';
                    $excluded[$category] = ($excluded[$category] ?? 0) + 1;
                    if (!str_starts_with($path, $prefix) || !LegacyArchiveTimestamps::isHistoryPath(substr($path, strlen($prefix)))) {
                        continue;
                    }
                }
                $relative = substr($path, strlen($prefix));
                $target = $directory . '/' . (str_starts_with($relative, '.git/')
                    ? '.import-history/' . substr($relative, 5) : $relative);
                if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0700, true)) {
                    throw new RuntimeException('Unable to create extracted directory.');
                }
                if (gzseek($stream, $entry['offset']) !== 0) {
                    throw new RuntimeException('Unable to seek archive entry.');
                }
                $output = fopen($target, 'xb');
                if ($output === false) {
                    throw new RuntimeException('Unable to create extracted record.');
                }
                try {
                    $position = $entry['offset'];
                    for ($remaining = $entry['size']; $remaining > 0; $remaining -= strlen($chunk)) {
                        $chunk = $this->read($stream, min(65536, $remaining), $position);
                        if (fwrite($output, $chunk) !== strlen($chunk)) {
                            throw new RuntimeException('Unable to write extracted record.');
                        }
                        $copiedBytes += strlen($chunk);
                        $progress->update(sprintf('Archive extraction: %d/%d files, %.1f/%.1f MiB', $copiedFiles, $totalFiles, $copiedBytes / 1048576, $totalBytes / 1048576));
                    }
                } finally {
                    fclose($output);
                }
                $copiedFiles++;
                $progress->update(sprintf('Archive extraction: %d/%d files, %.1f/%.1f MiB', $copiedFiles, $totalFiles, $copiedBytes / 1048576, $totalBytes / 1048576));
            }
        } finally {
            gzclose($stream);
        }
        $progress->update(sprintf('Archive extraction complete: %d/%d files, %.1f MiB', $copiedFiles, $totalFiles, $copiedBytes / 1048576), true);
        return ['root' => $directory, 'excluded_archive_categories' => $excluded,
            'recovered_legacy_timestamps' => (new LegacyArchiveTimestamps($this->progress))->recover($directory)];
    }

    private function read($stream, int $length, int &$offset): string
    {
        $this->checkSize($offset + $length);
        $result = '';
        while (strlen($result) < $length) {
            $chunk = gzread($stream, $length - strlen($result));
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('Truncated or corrupt tar.gz archive.');
            }
            $result .= $chunk;
        }
        $offset += $length;
        return $result;
    }

    private function octal(string $field): int
    {
        $field = trim($field, "\0 ");
        if ($field === '' || preg_match('/^[0-7]+$/', $field) !== 1 || strlen($field) > 12) {
            throw new RuntimeException('Unsupported tar numeric field.');
        }
        return (int) octdec($field);
    }

    private function safePath(string $path): string
    {
        while (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }
        $path = rtrim($path, '/');
        if ($path === '' || $path[0] === '/' || preg_match('/[\x00-\x1f\x7f\\\\]/', $path)
            || array_intersect(explode('/', $path), ['', '.', '..']) !== []) {
            throw new RuntimeException('Unsafe archive path.');
        }
        return $path;
    }

    private function checkSize(int $size): void
    {
        if ($size > $this->maxExpandedBytes) {
            throw new RuntimeException('Archive exceeds expanded size limit.');
        }
    }
}
