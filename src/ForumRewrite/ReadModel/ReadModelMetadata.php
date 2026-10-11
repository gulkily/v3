<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use PDO;

final class ReadModelMetadata
{
    public const SCHEMA_VERSION = '15';

    /**
     * @return array{schema_version:string,schema_fingerprint:string}
     */
    public static function expectedSchemaIdentity(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'schema_fingerprint' => ReadModelSchema::fingerprint(),
        ];
    }

    /** @param array<string, mixed> $metadata */
    public static function hasExpectedSchemaIdentity(array $metadata): bool
    {
        foreach (self::expectedSchemaIdentity() as $key => $expectedValue) {
            if (($metadata[$key] ?? null) !== $expectedValue) {
                return false;
            }
        }

        return true;
    }

    public static function repositoryHead(string $repositoryRoot): string
    {
        if (!is_dir($repositoryRoot . '/.git')) {
            return 'no-git';
        }

        $output = [];
        $exitCode = 0;
        exec('cd ' . escapeshellarg($repositoryRoot) . ' && git rev-parse HEAD 2>&1', $output, $exitCode);

        return $exitCode === 0 ? trim(implode("\n", $output)) : 'git-error';
    }

    /** Content evidence for repositories without Git history; no schema change. */
    public static function canonicalFingerprint(string $repositoryRoot): string
    {
        $base = $repositoryRoot . '/records';
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relative = substr($file->getPathname(), strlen($base) + 1);
                $hash = hash_file('sha256', $file->getPathname());
                if ($hash === false) {
                    throw new \RuntimeException('Unable to fingerprint canonical record: ' . $relative);
                }
                $files[$relative] = $hash;
            }
        }
        ksort($files);
        return hash('sha256', json_encode($files, JSON_THROW_ON_ERROR));
    }

    public static function repositoryShortCommit(string $repositoryRoot): string
    {
        $output = [];
        $exitCode = 0;
        exec('git -C ' . escapeshellarg($repositoryRoot) . ' rev-parse --short HEAD 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            return 'unknown';
        }

        $shortCommit = trim(implode("\n", $output));

        return $shortCommit !== '' ? $shortCommit : 'unknown';
    }

    /**
     * @return array{short:string,date:string,subject:string}|null
     */
    public static function latestRepositoryCommit(string $repositoryRoot): ?array
    {
        if (!is_dir($repositoryRoot . '/.git')) {
            return null;
        }

        $command = sprintf('git -C %s log -1 --format=%%h%%x09%%cI%%x09%%s 2>&1', escapeshellarg($repositoryRoot));
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        if ($exitCode !== 0 || $output === []) {
            return null;
        }

        $parts = explode("\t", trim(implode("\n", $output)), 3);
        if (count($parts) !== 3) {
            return null;
        }

        return [
            'short' => $parts[0],
            'date' => $parts[1],
            'subject' => $parts[2],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function readMetadata(PDO $pdo): array
    {
        $rows = $pdo->query('SELECT key, value FROM metadata')->fetchAll();
        $metadata = [];
        foreach ($rows as $row) {
            $metadata[(string) $row['key']] = (string) $row['value'];
        }

        return $metadata;
    }
}
