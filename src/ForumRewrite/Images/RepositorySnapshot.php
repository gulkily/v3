<?php

declare(strict_types=1);

namespace ForumRewrite\Images;

use RuntimeException;

/** Identifies a history-free content snapshot for matching image restoration. */
final class RepositorySnapshot
{
    private const MANIFEST = 'repository-snapshot.json';

    public static function write(string $root, string $revision): void
    {
        $manifest = ['schema' => 'forum-repository-snapshot-v1', 'repository_head' => $revision,
            'records_sha256' => self::fingerprint($root)];
        if (file_put_contents($root . '/' . self::MANIFEST, json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n") === false) {
            throw new RuntimeException('Cannot identify repository snapshot.');
        }
    }

    public static function revision(string $root): string
    {
        $path = $root . '/' . self::MANIFEST;
        if (!is_file($path) || is_link($path) || filesize($path) > 4096) {
            throw new RuntimeException('Restore the matching content archive with its repository-snapshot.json first.');
        }
        $manifest = json_decode((string) file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($manifest) || ($manifest['schema'] ?? '') !== 'forum-repository-snapshot-v1'
            || !is_string($manifest['repository_head'] ?? null)
            || !preg_match('/^[a-f0-9]{40}$/D', $manifest['repository_head'])
            || !is_string($manifest['records_sha256'] ?? null)
            || !hash_equals($manifest['records_sha256'], self::fingerprint($root))) {
            throw new RuntimeException('Content snapshot records changed or its manifest is invalid. Restore the unchanged matching archive.');
        }
        return $manifest['repository_head'];
    }

    private static function fingerprint(string $root): string
    {
        $records = $root . '/records';
        if (!is_dir($records) || is_link($records)) {
            throw new RuntimeException('Snapshot records are missing or unsafe.');
        }
        $hashes = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($records, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isLink() || !$file->isFile()) throw new RuntimeException('Snapshot records contain an unsafe entry.');
            $hash = hash_file('sha256', $file->getPathname());
            if ($hash === false) throw new RuntimeException('Cannot verify snapshot record.');
            $hashes[substr($file->getPathname(), strlen($records) + 1)] = $hash;
        }
        ksort($hashes);
        return hash('sha256', json_encode($hashes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
