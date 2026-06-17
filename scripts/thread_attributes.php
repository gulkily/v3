<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\IdentityBootstrapRecordParser;
use ForumRewrite\Canonical\PostReactionRecordParser;
use ForumRewrite\Canonical\PostRecordParser;
use ForumRewrite\Canonical\ThreadLabelRecordParser;
use ForumRewrite\Canonical\ThreadLabelRecord;
use ForumRewrite\ReadModel\ReadModelConnection;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\Support\LocalRepositoryBootstrap;

$projectRoot = dirname(__DIR__);
$target = (string) ($argv[1] ?? '');

if ($target === '' || in_array($target, ['-h', '--help'], true)) {
    fwrite(STDERR, usage());
    exit($target === '' ? 1 : 0);
}

if (preg_match('/^[A-Za-z0-9._:\/.-]+$/', $target) !== 1) {
    fwrite(STDERR, "target must contain only ASCII letters, numbers, slash, dot, underscore, colon, or hyphen.\n");
    exit(1);
}

$repositoryRoot = normalizePath($argv[2] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot)));
$databasePath = normalizePath($argv[3] ?? (getenv('FORUM_DATABASE_PATH') ?: ($projectRoot . '/state/cache/post_index.sqlite3')));
$repository = new CanonicalRecordRepository($repositoryRoot);
$targetInfo = resolveTargetInfo($repositoryRoot, $repository, $target);
$threadId = $targetInfo['thread_id'];

if ($threadId === null) {
    printSection('Target Item');
    foreach ($targetInfo['metadata'] as $key => $value) {
        printField($key, $value);
    }
    printField('thread_id', '(none)');
    fwrite(STDERR, "Target does not resolve to a thread: {$target}\n");
    exit(1);
}

$rootPath = CanonicalPathResolver::post($threadId);

if (!is_file($repositoryRoot . '/' . $rootPath)) {
    fwrite(STDERR, "Thread root post does not exist: {$threadId}\n");
    fwrite(STDERR, "Repository: {$repositoryRoot}\n");
    exit(1);
}

try {
    $root = $repository->loadPost($rootPath);
} catch (Throwable $throwable) {
    fwrite(STDERR, "Unable to load thread root: {$throwable->getMessage()}\n");
    exit(1);
}

if (!$root->isRoot()) {
    fwrite(STDERR, "thread_id must refer to a root thread, not a reply: {$threadId}\n");
    exit(1);
}

$replyPaths = loadReplyPaths($repositoryRoot, $repository, $databasePath, $threadId, $rootPath);

$labelRecords = loadThreadLabelRecords($repositoryRoot, $repository, $threadId);
$effectiveLabels = effectiveLabels($labelRecords);
$derivedThread = loadDerivedThread($databasePath, $threadId);

printSection('Target Item');
foreach ($targetInfo['metadata'] as $key => $value) {
    printField($key, $value);
}
if ($targetInfo['path'] !== null) {
    printField('target_record_path', $targetInfo['path']);
}

printSection('Thread');
printField('thread_id', $threadId);
printField('repository_root', $repositoryRoot);
printField('root_record_path', $rootPath);
printField('reply_record_count', (string) count($replyPaths));

printSection('Canonical Root Post');
printField('post_id', $root->postId);
printField('created_at', $root->createdAt);
printField('board_tags', implode(' ', $root->boardTags));
printField('thread_id', nullable($root->threadId));
printField('parent_id', nullable($root->parentId));
printField('author_identity_id', nullable($root->authorIdentityId));
printField('subject', nullable($root->subject));
printField('thread_type', nullable($root->threadType));
printField('task_status', nullable($root->taskStatus));
printField('task_presentability_impact', $root->taskPresentabilityImpact === null ? '(none)' : (string) $root->taskPresentabilityImpact);
printField('task_implementation_difficulty', $root->taskImplementationDifficulty === null ? '(none)' : (string) $root->taskImplementationDifficulty);
printField('task_depends_on', implode(' ', $root->taskDependsOn));
printField('task_sources', implode(' ', $root->taskSources));
printField('body', $root->body);

if ($derivedThread !== null) {
    printSection('Derived Thread');
    foreach ($derivedThread as $key => $value) {
        printField((string) $key, (string) $value);
    }
} else {
    printSection('Derived Thread');
    printField('status', is_file($databasePath) ? 'not found in read model' : 'database not found');
    printField('database_path', $databasePath);
}

printSection('Effective Labels');
if ($effectiveLabels === []) {
    printField('labels', '(none)');
} else {
    printField('labels', implode(' ', $effectiveLabels));
}

printSection('Label Additions');
if ($labelRecords === []) {
    printField('records', '(none)');
} else {
    foreach ($labelRecords as $entry) {
        $record = $entry['record'];
        foreach ($record->labels as $label) {
            printField('label', $label);
            printField('adding_post_id', '(not applicable; labels are added by thread-label records)');
            printField('adding_record_id', $record->recordId);
            printField('adding_record_path', $entry['path']);
            printField('adding_record_created_at', $record->createdAt);
            printField('operation', $record->operation);
            printField('author_identity_id', nullable($record->authorIdentityId));
            printField('reason', nullable($record->reason));
            printField('body', $record->body === '' ? '(empty)' : $record->body);
            fwrite(STDOUT, "\n");
        }
    }
}

printSection('Reply Records');
if ($replyPaths === []) {
    printField('paths', '(none)');
} else {
    foreach ($replyPaths as $path) {
        printField('path', $path);
    }
}

/**
 * @return array{path:?string,thread_id:?string,metadata:array<string,string>}
 */
function resolveTargetInfo(string $repositoryRoot, CanonicalRecordRepository $repository, string $target): array
{
    $target = trim($target);
    $matches = [];

    if (str_starts_with($target, rtrim($repositoryRoot, '/') . '/')) {
        $target = substr($target, strlen(rtrim($repositoryRoot, '/') . '/'));
    }

    if (str_contains($target, '/')) {
        $relativePath = ltrim($target, '/');
        assertCanonicalRecordPath($relativePath);

        return loadTargetInfo($repositoryRoot, $repository, $relativePath);
    }

    foreach (candidateRecordPaths($target) as $relativePath) {
        if (is_file($repositoryRoot . '/' . $relativePath)) {
            $matches[] = $relativePath;
        }
    }

    $matches = array_values(array_unique($matches));
    sort($matches);

    if (count($matches) > 1) {
        fwrite(STDERR, "Ambiguous target: {$target}\n");
        foreach ($matches as $match) {
            fwrite(STDERR, "  {$match}\n");
        }
        exit(1);
    }

    if ($matches !== []) {
        return loadTargetInfo($repositoryRoot, $repository, $matches[0]);
    }

    foreach (recordFiles($repositoryRoot) as $relativePath) {
        $stem = preg_replace('/\.(txt|asc)$/', '', basename($relativePath));
        if ($stem === $target) {
            $matches[] = $relativePath;
        }
    }

    foreach (candidateRecordPaths($target) as $relativePath) {
        if (readHistoricalRecord($repositoryRoot, $relativePath) !== null) {
            $matches[] = $relativePath;
        }
    }

    $matches = array_values(array_unique($matches));
    sort($matches);

    if ($matches === []) {
        $postPath = CanonicalPathResolver::post($target);
        return [
            'path' => $postPath,
            'thread_id' => $target,
            'metadata' => [
                'target' => $target,
                'target_type' => 'thread-id',
            ],
        ];
    }

    if (count($matches) > 1) {
        fwrite(STDERR, "Ambiguous target: {$target}\n");
        foreach ($matches as $match) {
            fwrite(STDERR, "  {$match}\n");
        }
        exit(1);
    }

    return loadTargetInfo($repositoryRoot, $repository, $matches[0]);
}

/**
 * @return array{path:string,thread_id:?string,metadata:array<string,string>}
 */
function loadTargetInfo(string $repositoryRoot, CanonicalRecordRepository $repository, string $relativePath): array
{
    $historicalContents = null;
    $isCurrent = is_file($repositoryRoot . '/' . $relativePath);

    if (!$isCurrent) {
        $historicalContents = readHistoricalRecord($repositoryRoot, $relativePath);
        if ($historicalContents === null) {
            fwrite(STDERR, "Canonical record does not exist in current files or git history: {$relativePath}\n");
            exit(1);
        }
    }

    try {
        if (str_starts_with($relativePath, 'records/thread-labels/')) {
            $record = $isCurrent
                ? $repository->loadThreadLabel($relativePath)
                : (new ThreadLabelRecordParser())->parse($historicalContents ?? '');

            return [
                'path' => $relativePath,
                'thread_id' => $record->threadId,
                'metadata' => [
                    'target_type' => $isCurrent ? 'thread-label' : 'deleted-thread-label',
                    'record_id' => $record->recordId,
                    'created_at' => $record->createdAt,
                    'thread_id' => $record->threadId,
                    'labels' => implode(' ', $record->labels),
                    'author_identity_id' => nullable($record->authorIdentityId),
                ],
            ];
        }

        if (str_starts_with($relativePath, 'records/posts/')) {
            $record = $isCurrent
                ? $repository->loadPost($relativePath)
                : (new PostRecordParser())->parse($historicalContents ?? '');

            return [
                'path' => $relativePath,
                'thread_id' => $record->threadId ?? $record->postId,
                'metadata' => [
                    'target_type' => $record->isRoot() ? ($isCurrent ? 'thread-root-post' : 'deleted-thread-root-post') : ($isCurrent ? 'reply-post' : 'deleted-reply-post'),
                    'post_id' => $record->postId,
                    'thread_id' => $record->threadId ?? $record->postId,
                    'parent_id' => nullable($record->parentId),
                    'subject' => nullable($record->subject),
                ],
            ];
        }

        if (str_starts_with($relativePath, 'records/post-reactions/')) {
            $record = $isCurrent
                ? $repository->loadPostReaction($relativePath)
                : (new PostReactionRecordParser())->parse($historicalContents ?? '');
            $threadId = resolvePostThreadId($repositoryRoot, $repository, $record->postId);

            return [
                'path' => $relativePath,
                'thread_id' => $threadId,
                'metadata' => [
                    'target_type' => $isCurrent ? 'post-reaction' : 'deleted-post-reaction',
                    'record_id' => $record->recordId,
                    'created_at' => $record->createdAt,
                    'post_id' => $record->postId,
                    'thread_id' => nullable($threadId),
                    'tags' => implode(' ', $record->tags),
                    'author_identity_id' => nullable($record->authorIdentityId),
                ],
            ];
        }

        if (str_starts_with($relativePath, 'records/identity/')) {
            $record = $isCurrent
                ? $repository->loadIdentity($relativePath)
                : (new IdentityBootstrapRecordParser())->parse($historicalContents ?? '');

            return [
                'path' => $relativePath,
                'thread_id' => $record->bootstrapByThread,
                'metadata' => [
                    'target_type' => $isCurrent ? 'identity-bootstrap' : 'deleted-identity-bootstrap',
                    'identity_id' => $record->identityId,
                    'username' => $record->username,
                    'bootstrap_by_post' => $record->bootstrapByPost,
                    'bootstrap_by_thread' => $record->bootstrapByThread,
                ],
            ];
        }
    } catch (Throwable $throwable) {
        fwrite(STDERR, "Unable to load target record {$relativePath}: {$throwable->getMessage()}\n");
        exit(1);
    }

    return [
        'path' => $relativePath,
        'thread_id' => null,
        'metadata' => [
            'target_type' => $isCurrent ? 'canonical-record' : 'deleted-canonical-record',
        ],
    ];
}

function resolvePostThreadId(string $repositoryRoot, CanonicalRecordRepository $repository, string $postId): ?string
{
    $relativePath = CanonicalPathResolver::post($postId);
    try {
        if (is_file($repositoryRoot . '/' . $relativePath)) {
            $record = $repository->loadPost($relativePath);
        } else {
            $contents = readHistoricalRecord($repositoryRoot, $relativePath);
            if ($contents === null) {
                return null;
            }
            $record = (new PostRecordParser())->parse($contents);
        }
    } catch (Throwable) {
        return null;
    }

    return $record->threadId ?? $record->postId;
}

/**
 * @return list<string>
 */
function candidateRecordPaths(string $target): array
{
    $paths = [
        CanonicalPathResolver::post($target),
        CanonicalPathResolver::threadLabel($target),
        CanonicalPathResolver::postReaction($target),
    ];

    if (preg_match('/^identity-openpgp-([a-fA-F0-9]{40})$/', $target, $matches) === 1) {
        $paths[] = CanonicalPathResolver::identity(strtolower($matches[1]));
    }

    if (preg_match('/^openpgp-([a-fA-F0-9]{40})$/', $target, $matches) === 1) {
        $paths[] = CanonicalPathResolver::approvalSeed(strtolower($matches[1]));
        $paths[] = CanonicalPathResolver::publicKey(strtoupper($matches[1]));
    }

    return array_values(array_unique($paths));
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

function readHistoricalRecord(string $repositoryRoot, string $relativePath): ?string
{
    if (!is_dir($repositoryRoot . '/.git')) {
        return null;
    }

    $commits = runCommand(sprintf(
        'git -C %s rev-list --all -- %s',
        escapeshellarg($repositoryRoot),
        escapeshellarg($relativePath)
    ));
    if ($commits['exit_code'] !== 0) {
        return null;
    }

    foreach (array_filter(explode("\n", trim($commits['output']))) as $commit) {
        $exists = runCommand(sprintf(
            'git -C %s cat-file -e %s:%s',
            escapeshellarg($repositoryRoot),
            escapeshellarg($commit),
            escapeshellarg($relativePath)
        ));
        if ($exists['exit_code'] !== 0) {
            continue;
        }

        $contents = shell_exec(sprintf(
            'git -C %s show %s:%s 2>/dev/null',
            escapeshellarg($repositoryRoot),
            escapeshellarg($commit),
            escapeshellarg($relativePath)
        ));
        if (is_string($contents)) {
            return $contents;
        }
    }

    return null;
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

/**
 * @return list<string>
 */
function loadReplyPaths(
    string $repositoryRoot,
    CanonicalRecordRepository $repository,
    string $databasePath,
    string $threadId,
    string $rootPath
): array
{
    $readModelReplyPaths = loadReadModelReplyPaths($repositoryRoot, $databasePath, $threadId);
    if ($readModelReplyPaths !== null) {
        return $readModelReplyPaths;
    }

    $replyPaths = [];
    foreach (glob($repositoryRoot . '/records/posts/*.txt') ?: [] as $path) {
        $relativePath = repositoryRelativePath($repositoryRoot, $path);
        if ($relativePath === $rootPath) {
            continue;
        }

        if (postHeaderThreadId($path) !== $threadId) {
            continue;
        }

        try {
            $post = $repository->loadPost($relativePath);
        } catch (Throwable) {
            continue;
        }

        if ($post->threadId === $threadId) {
            $replyPaths[] = $relativePath;
        }
    }
    sort($replyPaths);

    return $replyPaths;
}

/**
 * @return list<string>|null
 */
function loadReadModelReplyPaths(string $repositoryRoot, string $databasePath, string $threadId): ?array
{
    if (!isReadModelUsableForThreadAttributes($repositoryRoot, $databasePath, $threadId)) {
        return null;
    }

    try {
        $pdo = (new ReadModelConnection($databasePath))->open();
        $stmt = $pdo->prepare(
            'SELECT post_id
             FROM posts
             WHERE thread_id = :thread_id AND post_id <> :thread_id
             ORDER BY sequence_number ASC'
        );
        $stmt->execute(['thread_id' => $threadId]);
        $rows = $stmt->fetchAll();
    } catch (Throwable) {
        return null;
    }

    $paths = [];
    foreach ($rows as $row) {
        $postId = (string) ($row['post_id'] ?? '');
        if ($postId === '') {
            return null;
        }

        $relativePath = CanonicalPathResolver::post($postId);
        if (!is_file($repositoryRoot . '/' . $relativePath)) {
            return null;
        }

        $paths[] = $relativePath;
    }

    return $paths;
}

/**
 * @return list<array{path:string,record:ThreadLabelRecord}>
 */
function loadThreadLabelRecords(string $repositoryRoot, CanonicalRecordRepository $repository, string $threadId): array
{
    $entries = [];
    foreach (glob($repositoryRoot . '/records/thread-labels/*.txt') ?: [] as $path) {
        if (threadLabelHeaderThreadId($path) !== $threadId) {
            continue;
        }

        $relativePath = repositoryRelativePath($repositoryRoot, $path);

        try {
            $record = $repository->loadThreadLabel($relativePath);
        } catch (Throwable) {
            continue;
        }

        if ($record->threadId === $threadId) {
            $entries[] = [
                'path' => $relativePath,
                'record' => $record,
            ];
        }
    }

    usort($entries, static function (array $left, array $right): int {
        $timeComparison = strcmp($left['record']->createdAt, $right['record']->createdAt);
        if ($timeComparison !== 0) {
            return $timeComparison;
        }

        return strcmp($left['record']->recordId, $right['record']->recordId);
    });

    return $entries;
}

function postHeaderThreadId(string $path): ?string
{
    return recordHeaderValue($path, 'Thread-ID');
}

function threadLabelHeaderThreadId(string $path): ?string
{
    return recordHeaderValue($path, 'Thread-ID');
}

function recordHeaderValue(string $path, string $headerName): ?string
{
    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        return null;
    }

    try {
        $prefix = $headerName . ':';
        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                return null;
            }

            if (str_starts_with($line, $prefix)) {
                return trim(substr($line, strlen($prefix)));
            }
        }
    } finally {
        fclose($handle);
    }

    return null;
}

function isReadModelUsableForThreadAttributes(string $repositoryRoot, string $databasePath, string $threadId): bool
{
    if (!is_file($databasePath)) {
        return false;
    }

    try {
        $pdo = (new ReadModelConnection($databasePath))->open();
        $thread = $pdo->prepare('SELECT 1 FROM threads WHERE root_post_id = :thread_id');
        $thread->execute(['thread_id' => $threadId]);
        if ($thread->fetchColumn() === false) {
            return false;
        }

        $metadata = $pdo->prepare('SELECT value FROM metadata WHERE key = :key');
        $metadata->execute(['key' => 'repository_head']);
        $indexedHead = $metadata->fetchColumn();
    } catch (Throwable) {
        return false;
    }

    return is_string($indexedHead) && trim($indexedHead) === ReadModelMetadata::repositoryHead($repositoryRoot);
}

/**
 * @param list<array{path:string,record:ThreadLabelRecord}> $labelRecords
 * @return list<string>
 */
function effectiveLabels(array $labelRecords): array
{
    $labels = [];
    foreach ($labelRecords as $entry) {
        foreach ($entry['record']->labels as $label) {
            $labels[$label] = true;
        }
    }

    $labelList = array_keys($labels);
    sort($labelList);

    return $labelList;
}

/**
 * @return array<string, scalar|null>|null
 */
function loadDerivedThread(string $databasePath, string $threadId): ?array
{
    if (!is_file($databasePath)) {
        return null;
    }

    try {
        $pdo = (new ReadModelConnection($databasePath))->open();
        $stmt = $pdo->prepare('SELECT * FROM threads WHERE root_post_id = :root_post_id');
        $stmt->execute(['root_post_id' => $threadId]);
        $row = $stmt->fetch();
    } catch (Throwable) {
        return null;
    }

    return is_array($row) ? $row : null;
}

function printSection(string $title): void
{
    fwrite(STDOUT, "\n{$title}\n");
    fwrite(STDOUT, str_repeat('-', strlen($title)) . "\n");
}

function printField(string $key, string $value): void
{
    $lines = explode("\n", rtrim($value, "\n"));
    if ($lines === ['']) {
        $lines = ['(empty)'];
    }

    fwrite(STDOUT, sprintf("%s: %s\n", $key, $lines[0]));
    foreach (array_slice($lines, 1) as $line) {
        fwrite(STDOUT, sprintf("%s  %s\n", str_repeat(' ', strlen($key)), $line));
    }
}

function nullable(?string $value): string
{
    return $value === null || $value === '' ? '(none)' : $value;
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
  ./v3 thread-attributes <thread_id_or_record> [repository_root] [database_path]

Accepts a thread ID, post ID, canonical record path, or unambiguous canonical
record filename stem. Prints the target item, canonical root post attributes,
derived read-model thread attributes, effective labels, and each thread-label
record that adds a label.

TEXT;
}
