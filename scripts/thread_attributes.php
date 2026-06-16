<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\ThreadLabelRecord;
use ForumRewrite\ReadModel\ReadModelConnection;
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
$repository = new CanonicalRecordRepository($repositoryRoot);
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

$replyPaths = [];
foreach (glob($repositoryRoot . '/records/posts/*.txt') ?: [] as $path) {
    $relativePath = repositoryRelativePath($repositoryRoot, $path);
    if ($relativePath === $rootPath) {
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

$labelRecords = loadThreadLabelRecords($repositoryRoot, $repository, $threadId);
$effectiveLabels = effectiveLabels($labelRecords);
$derivedThread = loadDerivedThread($databasePath, $threadId);

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
 * @return list<array{path:string,record:ThreadLabelRecord}>
 */
function loadThreadLabelRecords(string $repositoryRoot, CanonicalRecordRepository $repository, string $threadId): array
{
    $entries = [];
    foreach (glob($repositoryRoot . '/records/thread-labels/*.txt') ?: [] as $path) {
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
  ./v3 thread-attributes <thread_id> [repository_root] [database_path]

Prints canonical root post attributes, derived read-model thread attributes,
effective labels, and each thread-label record that adds a label.

TEXT;
}
