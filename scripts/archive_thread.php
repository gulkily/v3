<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Host\StaticArtifactBuilder;
use ForumRewrite\Support\ExecutionLock;
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

$repositoryRoot = normalizePath($argv[2] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot)));
$databasePath = normalizePath($argv[3] ?? (getenv('FORUM_DATABASE_PATH') ?: ($projectRoot . '/state/cache/post_index.sqlite3')));
$artifactRoot = normalizePath($argv[4] ?? (getenv('FORUM_PUBLIC_ARTIFACT_ROOT') ?: ($projectRoot . '/public')));
$archivePath = normalizePath($argv[5] ?? defaultArchivePath($projectRoot, $threadId));

try {
    $result = (new ExecutionLock(dirname($databasePath) . '/forum-rewrite.lock'))->withExclusiveLock(
        static function () use ($projectRoot, $repositoryRoot, $databasePath, $artifactRoot, $threadId, $archivePath): array {
            $repository = new CanonicalRecordRepository($repositoryRoot);
            $componentPaths = discoverThreadComponentPaths($repositoryRoot, $repository, $threadId);
            $supportingPublicKeyPaths = discoverSupportingPublicKeyPaths($repositoryRoot, $repository, $componentPaths);
            $archivePaths = archivePaths($componentPaths, $supportingPublicKeyPaths);
            $manifest = buildManifest($repositoryRoot, $threadId, $archivePaths, $componentPaths, $supportingPublicKeyPaths);
            writeArchive($repositoryRoot, $archivePath, $archivePaths, $manifest);
            removeArchivedComponents($repositoryRoot, $componentPaths);
            $archiveCommit = commitArchiveRemoval($repositoryRoot, $threadId, $componentPaths);
            $removedArtifactPaths = removeStaleStaticArtifacts($artifactRoot, $threadId, $componentPaths);
            (new StaticArtifactBuilder($projectRoot, $repositoryRoot, $databasePath, $artifactRoot))->build();

            return [
                'component_paths' => $componentPaths,
                'archive_paths' => $archivePaths,
                'supporting_public_key_paths' => $supportingPublicKeyPaths,
                'archive_commit' => $archiveCommit,
                'removed_artifact_paths' => $removedArtifactPaths,
            ];
        }
    );

    fwrite(STDOUT, "Archived thread and removed live canonical records.\n");
    fwrite(STDOUT, "Thread: {$threadId}\n");
    fwrite(STDOUT, "Repository: {$repositoryRoot}\n");
    fwrite(STDOUT, "Database: {$databasePath}\n");
    fwrite(STDOUT, "Artifacts: {$artifactRoot}\n");
    fwrite(STDOUT, "Archive: {$archivePath}\n");
    fwrite(STDOUT, sprintf("Files archived: %d\n", count($result['archive_paths'])));
    fwrite(STDOUT, sprintf("Supporting public keys archived: %d\n", count($result['supporting_public_key_paths'])));
    fwrite(STDOUT, sprintf("Files removed: %d\n", count($result['component_paths'])));
    fwrite(STDOUT, sprintf("Stale artifacts removed: %d\n", count($result['removed_artifact_paths'])));
    if ($result['archive_commit'] !== null) {
        fwrite(STDOUT, "Removal commit: {$result['archive_commit']}\n");
    }
    fwrite(STDOUT, "Read model and static artifacts refreshed.\n");
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

/**
 * @return list<string>
 */
function discoverThreadComponentPaths(string $repositoryRoot, CanonicalRecordRepository $repository, string $threadId): array
{
    $rootPath = CanonicalPathResolver::post($threadId);
    if (!is_file($repositoryRoot . '/' . $rootPath)) {
        throw new RuntimeException('Thread root post does not exist: ' . $threadId);
    }

    $root = $repository->loadPost($rootPath);
    if ($root->threadId !== null) {
        throw new RuntimeException('thread_id must refer to a root thread, not a reply: ' . $threadId);
    }

    $paths = [$rootPath];
    $threadPostIds = [$threadId => true];

    foreach (glob($repositoryRoot . '/records/posts/*.txt') ?: [] as $path) {
        $relativePath = repositoryRelativePath($repositoryRoot, $path);
        if ($relativePath === $rootPath) {
            continue;
        }

        $post = $repository->loadPost($relativePath);
        if ($post->threadId !== $threadId) {
            continue;
        }

        $paths[] = $relativePath;
        $threadPostIds[$post->postId] = true;
    }

    foreach (glob($repositoryRoot . '/records/thread-labels/*.txt') ?: [] as $path) {
        $relativePath = repositoryRelativePath($repositoryRoot, $path);
        $record = $repository->loadThreadLabel($relativePath);
        if ($record->threadId === $threadId) {
            $paths[] = $relativePath;
        }
    }

    foreach (glob($repositoryRoot . '/records/post-reactions/*.txt') ?: [] as $path) {
        $relativePath = repositoryRelativePath($repositoryRoot, $path);
        $record = $repository->loadPostReaction($relativePath);
        if (isset($threadPostIds[$record->postId])) {
            $paths[] = $relativePath;
        }
    }

    $paths = array_values(array_unique($paths));
    sort($paths);

    return $paths;
}

/**
 * @param list<string> $componentPaths
 * @return list<string>
 */
function discoverSupportingPublicKeyPaths(string $repositoryRoot, CanonicalRecordRepository $repository, array $componentPaths): array
{
    $identityIds = [];

    foreach ($componentPaths as $relativePath) {
        if (str_starts_with($relativePath, 'records/posts/')) {
            $identityId = $repository->loadPost($relativePath)->authorIdentityId;
        } elseif (str_starts_with($relativePath, 'records/thread-labels/')) {
            $identityId = $repository->loadThreadLabel($relativePath)->authorIdentityId;
        } elseif (str_starts_with($relativePath, 'records/post-reactions/')) {
            $identityId = $repository->loadPostReaction($relativePath)->authorIdentityId;
        } else {
            $identityId = null;
        }

        if ($identityId !== null) {
            $identityIds[$identityId] = true;
        }
    }

    $paths = [];
    foreach (array_keys($identityIds) as $identityId) {
        if (preg_match('/^openpgp:([a-fA-F0-9]+)$/', $identityId, $matches) !== 1) {
            continue;
        }

        $publicKeyPath = CanonicalPathResolver::publicKey(strtoupper((string) $matches[1]));
        if (!is_file($repositoryRoot . '/' . $publicKeyPath)) {
            throw new RuntimeException('Referenced public key record does not exist for identity: ' . $identityId);
        }

        $repository->loadPublicKey($publicKeyPath);
        $paths[] = $publicKeyPath;
    }

    $paths = array_values(array_unique($paths));
    sort($paths);

    return $paths;
}

/**
 * @param list<string> $componentPaths
 * @param list<string> $supportingPublicKeyPaths
 * @return list<string>
 */
function archivePaths(array $componentPaths, array $supportingPublicKeyPaths): array
{
    $paths = array_values(array_unique(array_merge($componentPaths, $supportingPublicKeyPaths)));
    sort($paths);

    return $paths;
}

/**
 * @param list<string> $archivePaths
 * @param list<string> $componentPaths
 * @param list<string> $supportingPublicKeyPaths
 * @return array<string, mixed>
 */
function buildManifest(string $repositoryRoot, string $threadId, array $archivePaths, array $componentPaths, array $supportingPublicKeyPaths): array
{
    $files = [];
    foreach ($archivePaths as $relativePath) {
        $absolutePath = $repositoryRoot . '/' . $relativePath;
        $hash = hash_file('sha256', $absolutePath);
        if ($hash === false) {
            throw new RuntimeException('Unable to hash archive component: ' . $relativePath);
        }

        $files[] = [
            'path' => $relativePath,
            'sha256' => $hash,
            'bytes' => filesize($absolutePath),
        ];
    }

    return [
        'schema_version' => 1,
        'kind' => 'thread_archive',
        'thread_id' => $threadId,
        'archived_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'source_commit' => sourceCommit($repositoryRoot),
        'component_paths' => $componentPaths,
        'supporting_public_key_paths' => $supportingPublicKeyPaths,
        'files' => $files,
    ];
}

/**
 * @param list<string> $componentPaths
 * @param array<string, mixed> $manifest
 */
function writeArchive(string $repositoryRoot, string $archivePath, array $componentPaths, array $manifest): void
{
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('The PHP zip extension is required to archive a thread.');
    }

    if (is_file($archivePath)) {
        throw new RuntimeException('Archive already exists: ' . $archivePath);
    }

    $archiveDirectory = dirname($archivePath);
    if (!is_dir($archiveDirectory) && !mkdir($archiveDirectory, 0777, true) && !is_dir($archiveDirectory)) {
        throw new RuntimeException('Unable to create archive directory: ' . $archiveDirectory);
    }

    $temporaryPath = tempnam($archiveDirectory, 'thread-archive-');
    if ($temporaryPath === false) {
        throw new RuntimeException('Unable to create temporary archive path in ' . $archiveDirectory);
    }

    $zip = new ZipArchive();
    if ($zip->open($temporaryPath, ZipArchive::OVERWRITE) !== true) {
        @unlink($temporaryPath);
        throw new RuntimeException('Unable to open temporary archive for writing.');
    }

    try {
        foreach ($componentPaths as $relativePath) {
            $absolutePath = $repositoryRoot . '/' . $relativePath;
            if (!$zip->addFile($absolutePath, $relativePath)) {
                throw new RuntimeException('Unable to add archive component: ' . $relativePath);
            }
        }

        $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if (!$zip->addFromString('manifest.json', $manifestJson)) {
            throw new RuntimeException('Unable to add archive manifest.');
        }
    } finally {
        $zip->close();
    }

    verifyArchive($temporaryPath, $componentPaths);

    if (!rename($temporaryPath, $archivePath)) {
        @unlink($temporaryPath);
        throw new RuntimeException('Unable to move archive into place: ' . $archivePath);
    }
}

/**
 * @param list<string> $componentPaths
 */
function verifyArchive(string $archivePath, array $componentPaths): void
{
    $zip = new ZipArchive();
    if ($zip->open($archivePath) !== true) {
        throw new RuntimeException('Unable to verify written archive.');
    }

    try {
        if ($zip->locateName('manifest.json') === false) {
            throw new RuntimeException('Written archive is missing manifest.json.');
        }

        foreach ($componentPaths as $relativePath) {
            if ($zip->locateName($relativePath) === false) {
                throw new RuntimeException('Written archive is missing component: ' . $relativePath);
            }
        }
    } finally {
        $zip->close();
    }
}

/**
 * @param list<string> $componentPaths
 */
function removeArchivedComponents(string $repositoryRoot, array $componentPaths): void
{
    foreach ($componentPaths as $relativePath) {
        $absolutePath = $repositoryRoot . '/' . $relativePath;
        if (!is_file($absolutePath)) {
            throw new RuntimeException('Archive component disappeared before removal: ' . $relativePath);
        }

        if (!unlink($absolutePath)) {
            throw new RuntimeException('Unable to remove archived component from live repository: ' . $relativePath);
        }
    }
}

/**
 * @param list<string> $componentPaths
 */
function commitArchiveRemoval(string $repositoryRoot, string $threadId, array $componentPaths): ?string
{
    if (!is_dir($repositoryRoot . '/.git')) {
        return null;
    }

    $trackedPaths = array_values(array_filter(
        $componentPaths,
        static fn (string $relativePath): bool => isGitTracked($repositoryRoot, $relativePath)
    ));
    if ($trackedPaths === []) {
        return null;
    }

    $pathspec = implode(' ', array_map('escapeshellarg', $trackedPaths));
    runGit($repositoryRoot, 'add -u -- ' . $pathspec);
    runGit($repositoryRoot, 'commit --only -m ' . escapeshellarg('Archive thread ' . $threadId) . ' -- ' . $pathspec);

    return sourceCommit($repositoryRoot);
}

function isGitTracked(string $repositoryRoot, string $relativePath): bool
{
    $output = [];
    $exitCode = 0;
    exec(
        'git -C ' . escapeshellarg($repositoryRoot) . ' ls-files --error-unmatch -- ' . escapeshellarg($relativePath) . ' 2>/dev/null',
        $output,
        $exitCode
    );

    return $exitCode === 0;
}

function runGit(string $repositoryRoot, string $arguments): string
{
    $output = [];
    $exitCode = 0;
    exec('git -C ' . escapeshellarg($repositoryRoot) . ' ' . $arguments . ' 2>&1', $output, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException('Git command failed: git ' . $arguments . "\n" . implode("\n", $output));
    }

    return implode("\n", $output);
}

/**
 * @param list<string> $componentPaths
 * @return list<string>
 */
function removeStaleStaticArtifacts(string $artifactRoot, string $threadId, array $componentPaths): array
{
    $postIds = [$threadId => true];
    foreach ($componentPaths as $relativePath) {
        if (preg_match('#^records/posts/([^/]+)\.txt$#', $relativePath, $matches) === 1) {
            $postIds[(string) $matches[1]] = true;
        }
    }

    $candidatePaths = [
        $artifactRoot . '/index.html',
        $artifactRoot . '/threads.html',
        $artifactRoot . '/threads/index.html',
        $artifactRoot . '/activity.html',
        $artifactRoot . '/tags.html',
        $artifactRoot . '/tags/index.html',
    ];

    $candidatePaths[] = $artifactRoot . '/threads/' . $threadId . '.html';
    foreach (array_keys($postIds) as $postId) {
        $candidatePaths[] = $artifactRoot . '/posts/' . $postId . '.html';
    }

    foreach (glob($artifactRoot . '/tags/*.html') ?: [] as $tagPath) {
        $candidatePaths[] = $tagPath;
    }

    $removed = [];
    foreach (array_values(array_unique($candidatePaths)) as $path) {
        if (!is_file($path)) {
            continue;
        }

        if (!unlink($path)) {
            throw new RuntimeException('Unable to remove stale artifact: ' . $path);
        }

        $removed[] = $path;
    }

    sort($removed);

    return $removed;
}

function repositoryRelativePath(string $repositoryRoot, string $path): string
{
    $prefix = rtrim($repositoryRoot, '/') . '/';
    if (!str_starts_with($path, $prefix)) {
        throw new RuntimeException('Path is outside repository root: ' . $path);
    }

    return substr($path, strlen($prefix));
}

function sourceCommit(string $repositoryRoot): ?string
{
    if (!is_dir($repositoryRoot . '/.git')) {
        return null;
    }

    $output = [];
    $exitCode = 0;
    exec(
        'git -C ' . escapeshellarg($repositoryRoot) . ' rev-parse HEAD 2>/dev/null',
        $output,
        $exitCode
    );

    if ($exitCode !== 0 || trim($output[0] ?? '') === '') {
        return null;
    }

    return trim($output[0]);
}

function normalizePath(string $path): string
{
    return rtrim($path, '/');
}

function defaultArchivePath(string $projectRoot, string $threadId): string
{
    return $projectRoot . '/state/archives/threads/' . $threadId . '-' . gmdate('Ymd\THis\Z') . '.zip';
}

function usage(): string
{
    return "Usage: php scripts/archive_thread.php <thread_id> [repository_root] [database_path] [artifact_root] [archive_path]\n";
}
