<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use PDO;

final class ReadModelMetadata
{
    public const SCHEMA_VERSION = '13';

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
