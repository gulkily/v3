<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Host\StaticArtifactBuilder;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\Support\ExecutionLock;
use ForumRewrite\Support\LocalRepositoryBootstrap;

$projectRoot = dirname(__DIR__);
$recordId = (string) ($argv[1] ?? '');

if ($recordId === '' || in_array($recordId, ['-h', '--help'], true)) {
    fwrite(STDERR, usage());
    exit($recordId === '' ? 1 : 0);
}

if (preg_match('/^[A-Za-z0-9._:-]+$/', $recordId) !== 1) {
    fwrite(STDERR, "record_id must contain only ASCII letters, numbers, dot, underscore, colon, or hyphen.\n");
    exit(1);
}

$repositoryRoot = normalizePath($argv[2] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot)));
$databasePath = normalizePath($argv[3] ?? (getenv('FORUM_DATABASE_PATH') ?: ($projectRoot . '/state/cache/post_index.sqlite3')));
$artifactRoot = $argv[4] ?? (getenv('FORUM_PUBLIC_ARTIFACT_ROOT') ?: null);
$artifactRoot = $artifactRoot !== null && $artifactRoot !== '' ? normalizePath($artifactRoot) : null;
$relativePath = CanonicalPathResolver::threadLabel($recordId);
$absolutePath = $repositoryRoot . '/' . $relativePath;

if (!is_file($absolutePath)) {
    fwrite(STDERR, "Thread-label record does not exist: {$relativePath}\n");
    fwrite(STDERR, "Repository: {$repositoryRoot}\n");
    exit(1);
}

if (!is_dir($repositoryRoot . '/.git')) {
    fwrite(STDERR, "Repository is not a git checkout: {$repositoryRoot}\n");
    exit(1);
}

try {
    $repository = new CanonicalRecordRepository($repositoryRoot);
    $record = $repository->loadThreadLabel($relativePath);
} catch (Throwable $throwable) {
    fwrite(STDERR, "Unable to load thread-label record: {$throwable->getMessage()}\n");
    exit(1);
}

$gitRm = runCommand(
    sprintf(
        'git -C %s rm -- %s',
        escapeshellarg($repositoryRoot),
        escapeshellarg($relativePath),
    )
);
if ($gitRm['exit_code'] !== 0) {
    fwrite(STDERR, trim($gitRm['output']) . "\n");
    exit($gitRm['exit_code']);
}

$commitMessage = 'Delete thread label ' . $recordId;
$gitCommit = runCommand(
    sprintf(
        'git -C %s commit -m %s -- %s',
        escapeshellarg($repositoryRoot),
        escapeshellarg($commitMessage),
        escapeshellarg($relativePath),
    )
);
if ($gitCommit['exit_code'] !== 0) {
    fwrite(STDERR, trim($gitCommit['output']) . "\n");
    exit($gitCommit['exit_code']);
}

$commitSha = trim(runCommand(sprintf('git -C %s rev-parse HEAD', escapeshellarg($repositoryRoot)))['output']);

try {
    (new ExecutionLock(dirname($databasePath) . '/forum-rewrite.lock'))->withExclusiveLock(
        static function () use ($projectRoot, $repositoryRoot, $databasePath, $artifactRoot): void {
            (new ReadModelBuilder(
                $repositoryRoot,
                $databasePath,
                new CanonicalRecordRepository($repositoryRoot),
            ))->rebuild();

            if ($artifactRoot !== null) {
                (new StaticArtifactBuilder($projectRoot, $repositoryRoot, $databasePath, $artifactRoot))->build();
            }
        }
    );
} catch (Throwable $throwable) {
    fwrite(STDERR, "Deleted and committed the record, but refresh failed: {$throwable->getMessage()}\n");
    exit(1);
}

fwrite(STDOUT, "Deleted thread-label record.\n");
fwrite(STDOUT, "Record-ID: {$record->recordId}\n");
fwrite(STDOUT, "Thread-ID: {$record->threadId}\n");
fwrite(STDOUT, "Labels: " . implode(' ', $record->labels) . "\n");
fwrite(STDOUT, "Removed: {$relativePath}\n");
fwrite(STDOUT, "Commit: {$commitSha}\n");
fwrite(STDOUT, "Rebuilt read model: {$databasePath}\n");
if ($artifactRoot !== null) {
    fwrite(STDOUT, "Rebuilt static artifacts: {$artifactRoot}\n");
}

/**
 * @return array{exit_code:int,output:string}
 */
function runCommand(string $command): array
{
    $output = [];
    $exitCode = 0;
    exec($command . ' 2>&1', $output, $exitCode);

    return [
        'exit_code' => $exitCode,
        'output' => implode("\n", $output),
    ];
}

function normalizePath(string $path): string
{
    if ($path === '') {
        return $path;
    }

    if ($path[0] === '/') {
        return rtrim($path, '/');
    }

    $resolved = realpath($path);
    if ($resolved !== false) {
        return $resolved;
    }

    return rtrim(getcwd() . '/' . $path, '/');
}

function usage(): string
{
    return <<<TEXT
Usage:
  ./v3 delete-thread-label <adding_record_id> [repository_root] [database_path] [artifact_root]

Deletes records/thread-labels/<adding_record_id>.txt with git rm, commits the
removal, rebuilds the read model, and rebuilds static artifacts when artifact_root
or FORUM_PUBLIC_ARTIFACT_ROOT is present.

TEXT;
}
