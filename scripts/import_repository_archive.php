<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\Support\ExecutionLock;
use ForumRewrite\Support\LocalRepositoryBootstrap;
use ForumRewrite\Host\StaticArtifactBuilder;

$projectRoot = dirname(__DIR__);
$arguments = parseArguments(array_slice($argv, 1));

if ($arguments['help'] || $arguments['archive_path'] === null) {
    fwrite($arguments['help'] ? STDOUT : STDERR, usage());
    exit($arguments['help'] ? 0 : 1);
}

$archivePath = normalizeExistingPath($arguments['archive_path'], getcwd());
$repositoryRoot = normalizePath(
    $arguments['repository_root']
        ?? (getenv('FORUM_REPOSITORY_ROOT') ?: LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot)),
    getcwd(),
);
$databasePath = normalizePath(
    $arguments['database_path']
        ?? (getenv('FORUM_DATABASE_PATH') ?: ($projectRoot . '/state/cache/post_index.sqlite3')),
    getcwd(),
);
$artifactRoot = $arguments['artifact_root'] ?? (getenv('FORUM_PUBLIC_ARTIFACT_ROOT') ?: null);
$artifactRoot = $artifactRoot !== null && $artifactRoot !== ''
    ? normalizePath($artifactRoot, getcwd())
    : null;

if (!is_dir($repositoryRoot . '/records')) {
    fwrite(STDERR, "Repository root is missing records/: {$repositoryRoot}\n");
    exit(1);
}

if (!is_dir($repositoryRoot . '/.git')) {
    fwrite(STDERR, "Repository is not a git checkout: {$repositoryRoot}\n");
    exit(1);
}

if (!$arguments['dry_run'] && !$arguments['no_commit']) {
    $status = runCommand(sprintf('git -C %s status --porcelain', escapeshellarg($repositoryRoot)));
    if ($status['exit_code'] !== 0) {
        fwrite(STDERR, trim($status['output']) . "\n");
        exit($status['exit_code']);
    }

    if (trim($status['output']) !== '') {
        fwrite(STDERR, "Repository has pending changes; import refused before making edits.\n");
        fwrite(STDERR, "Repository: {$repositoryRoot}\n");
        exit(1);
    }
}

$runId = gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(4));
$workRoot = $projectRoot . '/state/imports';
$extractRoot = $workRoot . '/tmp-' . $runId;
$conflictRoot = $workRoot . '/conflicts-' . $runId;

try {
    ensureDirectory($workRoot);
    ensureDirectory($extractRoot);
    validateArchiveEntries($archivePath);

    $extract = runCommand(sprintf(
        'tar -xzf %s -C %s',
        escapeshellarg($archivePath),
        escapeshellarg($extractRoot),
    ));
    if ($extract['exit_code'] !== 0) {
        throw new RuntimeException("Unable to extract archive:\n" . $extract['output']);
    }

    $sourceRoot = locateSourceRepositoryRoot($extractRoot);
    $existingIndex = buildExistingRecordIndex($repositoryRoot);
    $sourceFiles = canonicalSourceFiles($sourceRoot);

    $summary = [
        'seen' => 0,
        'imported' => 0,
        'duplicate_paths' => 0,
        'duplicate_identities' => 0,
        'conflicts' => 0,
        'invalid_skipped' => 0,
        'non_record_skipped' => 0,
    ];
    $importedPaths = [];
    $conflictPaths = [];

    foreach ($sourceFiles as $relativePath) {
        $summary['seen']++;

        if (!isValidCanonicalSourcePath($relativePath)) {
            $summary['non_record_skipped']++;
            continue;
        }

        $sourcePath = $sourceRoot . '/' . $relativePath;
        $sourceContents = file_get_contents($sourcePath);
        if ($sourceContents === false) {
            $summary['invalid_skipped']++;
            continue;
        }

        $sourceKey = canonicalIdentityKey($sourceRoot, $relativePath);
        if ($sourceKey === null) {
            $summary['invalid_skipped']++;
            continue;
        }

        $targetPath = $repositoryRoot . '/' . $relativePath;
        if (is_file($targetPath)) {
            if (hash_equals(hash_file('sha256', $targetPath), hash('sha256', $sourceContents))) {
                $summary['duplicate_paths']++;
                continue;
            }

            $summary['conflicts']++;
            $conflictPaths[] = $arguments['dry_run']
                ? $relativePath
                : saveConflict($conflictRoot, $relativePath, $sourceContents, 'path');
            continue;
        }

        if (isset($existingIndex[$sourceKey])) {
            $matchingDuplicate = false;
            foreach ($existingIndex[$sourceKey] as $existingPath) {
                $existingAbsolutePath = $repositoryRoot . '/' . $existingPath;
                if (is_file($existingAbsolutePath)
                    && hash_equals(hash_file('sha256', $existingAbsolutePath), hash('sha256', $sourceContents))) {
                    $matchingDuplicate = true;
                    break;
                }
            }

            if ($matchingDuplicate) {
                $summary['duplicate_identities']++;
                continue;
            }

            $summary['conflicts']++;
            $conflictPaths[] = $arguments['dry_run']
                ? $relativePath
                : saveConflict($conflictRoot, $relativePath, $sourceContents, 'identity');
            continue;
        }

        $summary['imported']++;
        $importedPaths[] = $relativePath;
        $existingIndex[$sourceKey][] = $relativePath;

        if ($arguments['dry_run']) {
            continue;
        }

        ensureDirectory(dirname($targetPath));
        if (!copy($sourcePath, $targetPath)) {
            throw new RuntimeException('Unable to copy imported record: ' . $relativePath);
        }
    }

    $commitSha = null;
    if (!$arguments['dry_run'] && $importedPaths !== []) {
        foreach (array_chunk($importedPaths, 100) as $chunk) {
            $add = runCommand(sprintf(
                'git -C %s add -- %s',
                escapeshellarg($repositoryRoot),
                implode(' ', array_map('escapeshellarg', $chunk)),
            ));
            if ($add['exit_code'] !== 0) {
                throw new RuntimeException("Unable to stage imported records:\n" . $add['output']);
            }
        }

        if (!$arguments['no_commit']) {
            $commit = runCommand(sprintf(
                'git -C %s commit -m %s',
                escapeshellarg($repositoryRoot),
                escapeshellarg('Import repository archive ' . basename($archivePath)),
            ));
            if ($commit['exit_code'] !== 0) {
                throw new RuntimeException("Unable to commit imported records:\n" . $commit['output']);
            }

            $revParse = runCommand(sprintf('git -C %s rev-parse HEAD', escapeshellarg($repositoryRoot)));
            if ($revParse['exit_code'] === 0) {
                $commitSha = trim($revParse['output']);
            }
        }

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
    }

    fwrite(STDOUT, "Repository archive import complete.\n");
    fwrite(STDOUT, "Archive: {$archivePath}\n");
    fwrite(STDOUT, "Repository: {$repositoryRoot}\n");
    fwrite(STDOUT, sprintf("Canonical files considered: %d\n", $summary['seen']));
    fwrite(STDOUT, sprintf("Imported: %d\n", $summary['imported']));
    fwrite(STDOUT, sprintf("Duplicate same path skipped: %d\n", $summary['duplicate_paths']));
    fwrite(STDOUT, sprintf("Duplicate canonical identity skipped: %d\n", $summary['duplicate_identities']));
    fwrite(STDOUT, sprintf("Conflicts saved for review: %d\n", $summary['conflicts']));
    fwrite(STDOUT, sprintf("Invalid canonical files skipped: %d\n", $summary['invalid_skipped']));
    fwrite(STDOUT, sprintf("Non-record files skipped: %d\n", $summary['non_record_skipped']));

    if ($commitSha !== null) {
        fwrite(STDOUT, "Commit: {$commitSha}\n");
    } elseif ($arguments['dry_run']) {
        fwrite(STDOUT, "Dry run: no files were copied or committed.\n");
    } elseif ($arguments['no_commit'] && $importedPaths !== []) {
        fwrite(STDOUT, "Imported files were staged in the working tree; no commit was created.\n");
    } elseif ($importedPaths === []) {
        fwrite(STDOUT, "No new files needed to be imported.\n");
    }

    if ($importedPaths !== [] && !$arguments['dry_run']) {
        fwrite(STDOUT, "Rebuilt read model: {$databasePath}\n");
        if ($artifactRoot !== null) {
            fwrite(STDOUT, "Rebuilt static artifacts: {$artifactRoot}\n");
        }
    }

    if ($conflictPaths !== [] && $arguments['dry_run']) {
        fwrite(STDOUT, "Dry run: conflict files would be saved during a real import.\n");
    } elseif ($conflictPaths !== []) {
        fwrite(STDOUT, "Conflict directory: {$conflictRoot}\n");
    }

    deleteDirectory($extractRoot);
} catch (Throwable $throwable) {
    deleteDirectory($extractRoot);
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

/**
 * @param list<string> $argv
 * @return array{archive_path:?string,repository_root:?string,database_path:?string,artifact_root:?string,dry_run:bool,no_commit:bool,help:bool}
 */
function parseArguments(array $argv): array
{
    $values = [];
    $dryRun = false;
    $noCommit = false;
    $help = false;

    foreach ($argv as $argument) {
        if ($argument === '--dry-run') {
            $dryRun = true;
            continue;
        }

        if ($argument === '--no-commit') {
            $noCommit = true;
            continue;
        }

        if ($argument === '-h' || $argument === '--help') {
            $help = true;
            continue;
        }

        $values[] = $argument;
    }

    return [
        'archive_path' => $values[0] ?? null,
        'repository_root' => $values[1] ?? null,
        'database_path' => $values[2] ?? null,
        'artifact_root' => $values[3] ?? null,
        'dry_run' => $dryRun,
        'no_commit' => $noCommit,
        'help' => $help,
    ];
}

function usage(): string
{
    return "Usage: php scripts/import_repository_archive.php <archive.tar.gz> [repository_root] [database_path] [artifact_root] [--dry-run] [--no-commit]\n";
}

function normalizeExistingPath(string $path, string|false $base): string
{
    $normalized = normalizePath($path, $base);
    $realPath = realpath($normalized);
    if ($realPath === false || !is_file($realPath)) {
        fwrite(STDERR, "Archive file does not exist: {$normalized}\n");
        exit(1);
    }

    return $realPath;
}

function normalizePath(string $path, string|false $base): string
{
    if ($path === '') {
        return '';
    }

    if (str_starts_with($path, '/')) {
        return rtrim($path, '/');
    }

    return rtrim(($base !== false ? $base : getcwd()) . '/' . $path, '/');
}

function validateArchiveEntries(string $archivePath): void
{
    $list = runCommand(sprintf('tar -tzf %s', escapeshellarg($archivePath)));
    if ($list['exit_code'] !== 0) {
        throw new RuntimeException("Unable to list archive:\n" . $list['output']);
    }

    foreach (explode("\n", $list['output']) as $entry) {
        $entry = trim($entry);
        if ($entry === '') {
            continue;
        }

        if (str_starts_with($entry, '/') || str_contains($entry, "\0")) {
            throw new RuntimeException('Archive contains an unsafe absolute path: ' . $entry);
        }

        foreach (explode('/', $entry) as $segment) {
            if ($segment === '..') {
                throw new RuntimeException('Archive contains an unsafe parent path: ' . $entry);
            }
        }
    }
}

function locateSourceRepositoryRoot(string $extractRoot): string
{
    $candidates = [
        $extractRoot . '/local_repository',
        $extractRoot,
    ];

    foreach ($candidates as $candidate) {
        if (is_dir($candidate . '/records')) {
            return $candidate;
        }
    }

    throw new RuntimeException('Archive does not contain records/ or local_repository/records/.');
}

/**
 * @return list<string>
 */
function canonicalSourceFiles(string $sourceRoot): array
{
    $paths = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $item) {
        if (!$item->isFile() || $item->isLink()) {
            continue;
        }

        $relativePath = repositoryRelativePath($sourceRoot, $item->getPathname());
        if (str_starts_with($relativePath, 'records/')) {
            $paths[] = $relativePath;
        }
    }

    sort($paths);

    return $paths;
}

/**
 * @return array<string,list<string>>
 */
function buildExistingRecordIndex(string $repositoryRoot): array
{
    $index = [];
    foreach (canonicalSourceFiles($repositoryRoot) as $relativePath) {
        if (!isValidCanonicalSourcePath($relativePath)) {
            continue;
        }

        $key = canonicalIdentityKey($repositoryRoot, $relativePath);
        if ($key === null) {
            continue;
        }

        $index[$key][] = $relativePath;
    }

    return $index;
}

function repositoryRelativePath(string $repositoryRoot, string $path): string
{
    $prefix = rtrim($repositoryRoot, '/') . '/';
    if (!str_starts_with($path, $prefix)) {
        throw new RuntimeException('Path is outside repository root: ' . $path);
    }

    return substr($path, strlen($prefix));
}

function isValidCanonicalSourcePath(string $relativePath): bool
{
    return isValidCanonicalRecordSourcePath($relativePath)
        || isValidCanonicalDetachedSignaturePath($relativePath);
}

function isValidCanonicalRecordSourcePath(string $relativePath): bool
{
    if (!str_starts_with($relativePath, 'records/')) {
        return false;
    }

    return $relativePath === 'records/instance/public.txt'
        || $relativePath === 'records/instance/feature-flags.txt'
        || preg_match('#^records/posts/(?:\d{4}/\d{2}/\d{2}/)?[A-Za-z0-9][A-Za-z0-9._-]*\.txt$#', $relativePath) === 1
        || preg_match('#^records/thread-labels/[A-Za-z0-9][A-Za-z0-9._-]*\.txt$#', $relativePath) === 1
        || preg_match('#^records/post-reactions/[A-Za-z0-9][A-Za-z0-9._-]*\.txt$#', $relativePath) === 1
        || preg_match('#^records/identity/identity-openpgp-[A-Fa-f0-9]{40}\.txt$#', $relativePath) === 1
        || preg_match('#^records/approval-seeds/openpgp-[A-Fa-f0-9]{40}\.txt$#', $relativePath) === 1
        || preg_match('#^records/public-keys/openpgp-[A-Fa-f0-9]{40}\.asc$#', $relativePath) === 1;
}

function isValidCanonicalDetachedSignaturePath(string $relativePath): bool
{
    foreach (['.asc', '.sig'] as $suffix) {
        if (str_ends_with($relativePath, $suffix)) {
            return isValidCanonicalRecordSourcePath(substr($relativePath, 0, -strlen($suffix)));
        }
    }

    return false;
}

function canonicalIdentityKey(string $repositoryRoot, string $relativePath): ?string
{
    if (isValidCanonicalDetachedSignaturePath($relativePath) && !isValidCanonicalRecordSourcePath($relativePath)) {
        $suffix = str_ends_with($relativePath, '.sig') ? '.sig' : '.asc';
        $recordPath = substr($relativePath, 0, -strlen($suffix));
        $recordKey = canonicalIdentityKey($repositoryRoot, $recordPath);

        return $recordKey !== null ? 'signature:' . $recordKey . ':' . $suffix : 'path:' . $relativePath;
    }

    $repository = new CanonicalRecordRepository($repositoryRoot);

    try {
        if (str_starts_with($relativePath, 'records/posts/')) {
            return 'post:' . $repository->loadPost($relativePath)->postId;
        }

        if (str_starts_with($relativePath, 'records/identity/')) {
            return 'identity:' . strtolower($repository->loadIdentity($relativePath)->identityId);
        }

        if (str_starts_with($relativePath, 'records/public-keys/')) {
            $fileName = basename($relativePath);
            return preg_match('/^openpgp-([A-Fa-f0-9]{40})\.asc$/', $fileName, $matches) === 1
                ? 'public-key:openpgp:' . strtolower($matches[1])
                : null;
        }

        if (str_starts_with($relativePath, 'records/approval-seeds/')) {
            return 'approval-seed:' . strtolower($repository->loadApprovalSeed($relativePath)->approvedIdentityId);
        }

        if (str_starts_with($relativePath, 'records/thread-labels/')) {
            return 'thread-label:' . $repository->loadThreadLabel($relativePath)->recordId;
        }

        if (str_starts_with($relativePath, 'records/post-reactions/')) {
            return 'post-reaction:' . $repository->loadPostReaction($relativePath)->recordId;
        }

        if ($relativePath === 'records/instance/public.txt') {
            return 'instance:public';
        }

        if ($relativePath === 'records/instance/feature-flags.txt') {
            return 'instance:feature-flags';
        }
    } catch (Throwable) {
        return null;
    }

    return null;
}

function saveConflict(string $conflictRoot, string $relativePath, string $contents, string $reason): string
{
    $conflictPath = $conflictRoot . '/' . $reason . '/' . $relativePath;
    ensureDirectory(dirname($conflictPath));
    if (file_put_contents($conflictPath, $contents) === false) {
        throw new RuntimeException('Unable to save conflict record: ' . $relativePath);
    }

    return $conflictPath;
}

function ensureDirectory(string $path): void
{
    if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create directory: ' . $path);
    }
}

function deleteDirectory(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $item) {
        if ($item->isDir() && !$item->isLink()) {
            rmdir($item->getPathname());
            continue;
        }

        unlink($item->getPathname());
    }

    rmdir($path);
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
