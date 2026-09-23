<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

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
}
