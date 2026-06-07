<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Support\LocalRepositoryBootstrap;

$projectRoot = dirname(__DIR__);
$threadId = (string) ($argv[1] ?? '');

if ($threadId === '' || in_array($threadId, ['-h', '--help'], true)) {
    fwrite(STDERR, usage());
    exit($threadId === '' ? 1 : 0);
}

if (preg_match('/^[A-Za-z0-9._:-]+$/', $threadId) !== 1) {
    fwrite(STDERR, "thread_id must contain only ASCII letters, numbers, dot, underscore, colon, or hyphen.\n");
    exit(1);
}

$repositoryRoot = $argv[2] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot));
$databasePath = $argv[3] ?? (getenv('FORUM_DATABASE_PATH') ?: ($projectRoot . '/state/cache/post_index.sqlite3'));
$artifactRoot = $argv[4] ?? (getenv('FORUM_PUBLIC_ARTIFACT_ROOT') ?: ($projectRoot . '/public'));
$archivePath = $argv[5] ?? defaultArchivePath($projectRoot, $threadId);

fwrite(STDOUT, "Archive thread command initialized.\n");
fwrite(STDOUT, "Thread: {$threadId}\n");
fwrite(STDOUT, "Repository: {$repositoryRoot}\n");
fwrite(STDOUT, "Database: {$databasePath}\n");
fwrite(STDOUT, "Artifacts: {$artifactRoot}\n");
fwrite(STDOUT, "Archive: {$archivePath}\n");
fwrite(STDOUT, "Archive implementation is not complete yet.\n");

function defaultArchivePath(string $projectRoot, string $threadId): string
{
    return $projectRoot . '/state/archives/threads/' . $threadId . '-' . gmdate('Ymd\THis\Z') . '.zip';
}

function usage(): string
{
    return "Usage: php scripts/archive_thread.php <thread_id> [repository_root] [database_path] [artifact_root] [archive_path]\n";
}
