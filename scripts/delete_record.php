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
$target = (string) ($argv[1] ?? '');

if ($target === '' || in_array($target, ['-h', '--help'], true)) {
    fwrite(STDERR, usage());
    exit($target === '' ? 1 : 0);
}

if (preg_match('/^[A-Za-z0-9._:\/.-]+$/', $target) !== 1) {
    fwrite(STDERR, "record target must contain only ASCII letters, numbers, slash, dot, underscore, colon, or hyphen.\n");
    exit(1);
}

$repositoryRoot = normalizePath($argv[2] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot)));
$databasePath = normalizePath($argv[3] ?? (getenv('FORUM_DATABASE_PATH') ?: ($projectRoot . '/state/cache/post_index.sqlite3')));
$artifactRoot = $argv[4] ?? (getenv('FORUM_PUBLIC_ARTIFACT_ROOT') ?: null);
$artifactRoot = $artifactRoot !== null && $artifactRoot !== '' ? normalizePath($artifactRoot) : null;
$relativePath = resolveRecordPath($repositoryRoot, $target);
$absolutePath = $repositoryRoot . '/' . $relativePath;

if (!is_file($absolutePath)) {
    fwrite(STDERR, "Canonical record does not exist: {$relativePath}\n");
    fwrite(STDERR, "Repository: {$repositoryRoot}\n");
    exit(1);
}

if (!is_dir($repositoryRoot . '/.git')) {
    fwrite(STDERR, "Repository is not a git checkout: {$repositoryRoot}\n");
    exit(1);
}

$metadata = loadRecordMetadata($repositoryRoot, $relativePath);

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

$commitMessage = 'Delete canonical record ' . $relativePath;
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

fwrite(STDOUT, "Deleted canonical record.\n");
foreach ($metadata as $key => $value) {
    fwrite(STDOUT, "{$key}: {$value}\n");
}
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

function resolveRecordPath(string $repositoryRoot, string $target): string
{
    $target = trim($target);
    if ($target === '') {
        throw new InvalidArgumentException('Record target cannot be empty.');
    }

    if (str_starts_with($target, $repositoryRoot . '/')) {
        $target = substr($target, strlen(rtrim($repositoryRoot, '/') . '/'));
    }

    if (str_contains($target, '/')) {
        $relativePath = ltrim($target, '/');
        assertCanonicalRecordPath($relativePath);

        return $relativePath;
    }

    $matches = [];
    foreach (recordFiles($repositoryRoot) as $relativePath) {
        $fileName = basename($relativePath);
        $stem = preg_replace('/\.(txt|asc)$/', '', $fileName);
        if ($stem === $target) {
            $matches[] = $relativePath;
        }
    }

    if ($matches === []) {
        $legacyThreadLabelPath = CanonicalPathResolver::threadLabel($target);
        if (is_file($repositoryRoot . '/' . $legacyThreadLabelPath)) {
            return $legacyThreadLabelPath;
        }

        fwrite(STDERR, "No canonical record found for target: {$target}\n");
        exit(1);
    }

    if (count($matches) > 1) {
        fwrite(STDERR, "Ambiguous record target: {$target}\n");
        foreach ($matches as $match) {
            fwrite(STDERR, "  {$match}\n");
        }
        exit(1);
    }

    return $matches[0];
}

function assertCanonicalRecordPath(string $relativePath): void
{
    if (!str_starts_with($relativePath, 'records/')) {
        fwrite(STDERR, "Record path must be under records/: {$relativePath}\n");
        exit(1);
    }

    if (str_contains($relativePath, '..') || str_starts_with($relativePath, '/') || str_ends_with($relativePath, '/')) {
        fwrite(STDERR, "Invalid record path: {$relativePath}\n");
        exit(1);
    }

    if (!preg_match('/\.(txt|asc)$/', $relativePath)) {
        fwrite(STDERR, "Record path must end in .txt or .asc: {$relativePath}\n");
        exit(1);
    }
}

/**
 * @return list<string>
 */
function recordFiles(string $repositoryRoot): array
{
    $recordsRoot = $repositoryRoot . '/records';
    if (!is_dir($recordsRoot)) {
        return [];
    }

    $paths = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($recordsRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }

        $path = $item->getPathname();
        if (!preg_match('/\.(txt|asc)$/', $path)) {
            continue;
        }

        $paths[] = repositoryRelativePath($repositoryRoot, $path);
    }
    sort($paths);

    return $paths;
}

/**
 * @return array<string,string>
 */
function loadRecordMetadata(string $repositoryRoot, string $relativePath): array
{
    $repository = new CanonicalRecordRepository($repositoryRoot);

    try {
        if (str_starts_with($relativePath, 'records/thread-labels/')) {
            $record = $repository->loadThreadLabel($relativePath);

            return [
                'Record-Type' => 'thread-label',
                'Record-ID' => $record->recordId,
                'Thread-ID' => $record->threadId,
                'Labels' => implode(' ', $record->labels),
            ];
        }

        if (str_starts_with($relativePath, 'records/posts/')) {
            $record = $repository->loadPost($relativePath);

            return [
                'Record-Type' => $record->isRoot() ? 'thread-root-post' : 'reply-post',
                'Post-ID' => $record->postId,
                'Thread-ID' => $record->threadId ?? $record->postId,
            ];
        }

        if (str_starts_with($relativePath, 'records/post-reactions/')) {
            $record = $repository->loadPostReaction($relativePath);

            return [
                'Record-Type' => 'post-reaction',
                'Record-ID' => $record->recordId,
                'Post-ID' => $record->postId,
                'Tags' => implode(' ', $record->tags),
            ];
        }
    } catch (Throwable $throwable) {
        return [
            'Record-Type' => 'unparsed',
            'Parse-Error' => $throwable->getMessage(),
        ];
    }

    return [
        'Record-Type' => 'canonical-record',
    ];
}

function repositoryRelativePath(string $repositoryRoot, string $path): string
{
    $prefix = rtrim($repositoryRoot, '/') . '/';
    if (str_starts_with($path, $prefix)) {
        return substr($path, strlen($prefix));
    }

    return ltrim($path, '/');
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
  ./v3 delete-record <record_path_or_id> [repository_root] [database_path] [artifact_root]

Deletes a canonical file under records/ with git rm, commits the removal,
rebuilds the read model, and rebuilds static artifacts when artifact_root or
FORUM_PUBLIC_ARTIFACT_ROOT is present.

record_path_or_id may be a relative path like:
  records/thread-labels/thread-label-20260530000001-zenrules.txt

or an unambiguous filename stem like:
  thread-label-20260530000001-zenrules

TEXT;
}
