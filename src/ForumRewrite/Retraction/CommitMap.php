<?php

declare(strict_types=1);

namespace ForumRewrite\Retraction;

use ForumRewrite\Canonical\GenericTextRecordParser;
use ForumRewrite\Canonical\RecordLimits;
use RuntimeException;

/**
 * History rewrite record v1 (docs/specs/history_rewrite_record_v1.md): the
 * committed `Key: value` map from pre-purge commit identities to post-purge
 * ones, and resolution of an old identity through every record in order.
 */
final class CommitMap
{
    public const DIRECTORY = 'records/history-rewrites';
    public const FORMAT = 'history-rewrite-v1';
    public const BLOCKS_PER_PART = 100000;
    private const HEADERS = ['Format', 'Rewritten-At', 'Reason', 'Receipt-Count', 'Part', 'Parts'];
    private const PATH = '#^records/history-rewrites/history-rewrite-(\d{8}T\d{6}Z)(?:-([2-9]|[1-9]\d+))?\.txt$#D';

    /**
     * Encodes one run as one or more record files. Pairs whose identity did not
     * change are dropped. Returns relative path => bytes, in part order.
     *
     * @param array<string,string> $map old commit id => new commit id
     * @return array<string,string>
     */
    public static function encode(string $rewrittenAt, int $receiptCount, array $map): array
    {
        self::timestamp($rewrittenAt);
        if ($receiptCount < 0) {
            throw new RuntimeException('Invalid receipt count.');
        }
        $pairs = [];
        $seenNew = [];
        foreach ($map as $old => $new) {
            $old = (string) $old;
            if (!self::sha($old) || !self::sha($new)) {
                throw new RuntimeException('Invalid commit identity in map.');
            }
            if ($old === $new) {
                continue;
            }
            if (isset($seenNew[$new])) {
                throw new RuntimeException('Commit map is not one to one: ' . $new);
            }
            $seenNew[$new] = true;
            $pairs[$old] = $new;
        }
        ksort($pairs, SORT_STRING);
        $chunks = $pairs === [] ? [[]] : array_chunk($pairs, self::BLOCKS_PER_PART, true);
        $parts = count($chunks);
        $files = [];
        foreach ($chunks as $index => $chunk) {
            $bytes = 'Format: ' . self::FORMAT . "\nRewritten-At: " . $rewrittenAt . "\nReason: purge\nReceipt-Count: " . $receiptCount
                . "\nPart: " . ($index + 1) . "\nParts: " . $parts . "\n\n";
            foreach ($chunk as $old => $new) {
                $bytes .= 'Old-Commit: ' . $old . "\nNew-Commit: " . $new . "\n\n";
            }
            $path = self::path($rewrittenAt, $index + 1);
            self::decode($bytes, $path);
            $files[$path] = $bytes;
        }
        return $files;
    }

    /**
     * Strictly validates one record file. When $path is given, the filename must
     * agree with Rewritten-At and Part.
     *
     * @return array{headers: array<string,string>, map: array<string,string>}
     */
    public static function decode(string $bytes, ?string $path = null): array
    {
        if (strlen($bytes) > RecordLimits::TEXT) {
            throw new RuntimeException('History rewrite record exceeds 16 MiB.');
        }
        $text = (new GenericTextRecordParser())->parse($bytes);
        $h = $text->headers;
        if (array_keys($h) !== self::HEADERS || $h['Format'] !== self::FORMAT || $h['Reason'] !== 'purge') {
            throw new RuntimeException('Unsupported history rewrite record.');
        }
        self::timestamp($h['Rewritten-At']);
        foreach (['Receipt-Count', 'Part', 'Parts'] as $key) {
            if (!preg_match('/^(?:0|[1-9]\d*)$/D', $h[$key])) {
                throw new RuntimeException('Invalid history rewrite ' . $key . '.');
            }
        }
        if ((int) $h['Part'] < 1 || (int) $h['Parts'] < 1 || (int) $h['Part'] > (int) $h['Parts']) {
            throw new RuntimeException('Invalid history rewrite part numbering.');
        }
        if ($path !== null && $path !== self::path($h['Rewritten-At'], (int) $h['Part'])) {
            throw new RuntimeException('History rewrite record path does not match its headers: ' . $path);
        }
        $map = [];
        $seenNew = [];
        $previous = '';
        if ($text->body !== '') {
            if (!str_ends_with($text->body, "\n\n")) {
                throw new RuntimeException('Invalid history rewrite blocks.');
            }
            foreach (explode("\n\n", substr($text->body, 0, -2)) as $block) {
                $b = (new GenericTextRecordParser())->parse($block . "\n\n")->headers;
                if (array_keys($b) !== ['Old-Commit', 'New-Commit'] || !self::sha($b['Old-Commit']) || !self::sha($b['New-Commit'])) {
                    throw new RuntimeException('Invalid history rewrite block.');
                }
                if ($b['Old-Commit'] === $b['New-Commit'] || strcmp($b['Old-Commit'], $previous) <= 0 || isset($seenNew[$b['New-Commit']])) {
                    throw new RuntimeException('Invalid history rewrite mapping: ' . $b['Old-Commit']);
                }
                $previous = $b['Old-Commit'];
                $seenNew[$b['New-Commit']] = true;
                $map[$b['Old-Commit']] = $b['New-Commit'];
            }
            if (count($map) > self::BLOCKS_PER_PART) {
                throw new RuntimeException('History rewrite record holds more than ' . self::BLOCKS_PER_PART . ' mappings.');
            }
        }
        return ['headers' => $h, 'map' => $map];
    }

    /**
     * Writes an encoded run into the repository. Refuses to overwrite, so two
     * runs cannot share a second. Returns the relative paths written.
     *
     * @param array<string,string> $files from encode()
     * @return list<string>
     */
    public static function write(string $root, array $files): array
    {
        foreach ($files as $path => $bytes) {
            if (file_exists($root . '/' . $path)) {
                throw new RuntimeException('History rewrite record already exists: ' . $path);
            }
        }
        $directory = $root . '/' . self::DIRECTORY;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot create ' . $directory);
        }
        $written = [];
        foreach ($files as $path => $bytes) {
            if (file_put_contents($root . '/' . $path, $bytes, LOCK_EX) !== strlen($bytes)) {
                throw new RuntimeException('Cannot write ' . $path);
            }
            $written[] = $path;
        }
        return $written;
    }

    /**
     * Record files in resolution order (Rewritten-At, then Part), relative to $root.
     *
     * @return list<string>
     */
    public static function records(string $root): array
    {
        $directory = $root . '/' . self::DIRECTORY;
        if (!is_dir($directory)) {
            return [];
        }
        $found = [];
        foreach (scandir($directory) ?: [] as $name) {
            $path = self::DIRECTORY . '/' . $name;
            if (preg_match(self::PATH, $path, $m) === 1 && is_file($directory . '/' . $name)) {
                $found[] = [$m[1], (int) ($m[2] ?? 1), $path];
            }
        }
        usort($found, static fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        return array_map(static fn (array $entry): string => $entry[2], $found);
    }

    /**
     * Newest identity for a commit across all records, or null when no record
     * names it. Scans record bytes for the block instead of parsing every
     * mapping, so a 100,000-line record costs one substring search per lookup.
     */
    public static function resolve(string $root, string $commitSha): ?string
    {
        $current = strtolower($commitSha);
        if (!self::sha($current)) {
            return null;
        }
        $mapped = false;
        foreach (self::records($root) as $path) {
            $bytes = RecordLimits::read($root . '/' . $path);
            if (!str_starts_with($bytes, 'Format: ' . self::FORMAT . "\n")) {
                throw new RuntimeException('Unsupported history rewrite record: ' . $path);
            }
            $needle = "\nOld-Commit: " . $current . "\nNew-Commit: ";
            $at = strpos($bytes, $needle);
            if ($at === false) {
                continue;
            }
            $new = substr($bytes, $at + strlen($needle), 40);
            if (!self::sha($new) || substr($bytes, $at + strlen($needle) + 40, 2) !== "\n\n") {
                throw new RuntimeException('Corrupt history rewrite record: ' . $path);
            }
            $current = $new;
            $mapped = true;
        }
        return $mapped ? $current : null;
    }

    public static function path(string $rewrittenAt, int $part = 1): string
    {
        $stamp = str_replace(['-', ':'], '', $rewrittenAt);
        return self::DIRECTORY . '/history-rewrite-' . $stamp . ($part > 1 ? '-' . $part : '') . '.txt';
    }

    public static function isRecordPath(string $relativePath): bool
    {
        return preg_match(self::PATH, $relativePath) === 1;
    }

    private static function sha(string $value): bool
    {
        return preg_match('/^[0-9a-f]{40}$/D', $value) === 1;
    }

    private static function timestamp(string $value): void
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, new \DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d\TH:i:s\Z') !== $value) {
            throw new RuntimeException('Invalid history rewrite timestamp.');
        }
    }
}
