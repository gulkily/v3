<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

final class ReadModelMetadata
{
    public const SCHEMA_VERSION = '8';

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
}
