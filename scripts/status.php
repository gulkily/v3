<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\ReadModel\ReadModelConnection;
use ForumRewrite\ReadModel\ReadModelStaleMarker;
use ForumRewrite\Support\ExecutionLock;
use ForumRewrite\Support\OperatorStatusCollector;
use ForumRewrite\TaskQueue\TaskQueueDatabaseConfig;
$projectRoot = dirname(__DIR__);

try {
    $options = parseStatusOptions(array_slice($argv, 1));
    if (($options['help'] ?? false) === true) {
        printStatusUsage(STDOUT);
        exit(0);
    }

    $repositoryRoot = (string) ($options['repository-root'] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: ($projectRoot . '/state/local_repository')));
    $databasePath = (string) ($options['database-path'] ?? (getenv('FORUM_DATABASE_PATH') ?: ($projectRoot . '/state/cache/post_index.sqlite3')));
    $queuePath = TaskQueueDatabaseConfig::path(
        $projectRoot,
        isset($options['queue-database-path']) ? (string) $options['queue-database-path'] : null,
    );
    $status = (new OperatorStatusCollector(
        $repositoryRoot,
        $databasePath,
        $queuePath,
        new ExecutionLock(dirname($databasePath) . '/forum-rewrite.lock'),
        new ReadModelStaleMarker($databasePath),
        static fn (): PDO => (new ReadModelConnection($databasePath))->open(),
    ))->collect();

    printStatus($status, $repositoryRoot, $databasePath, $queuePath);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Error: ' . $exception->getMessage() . "\n\n");
    printStatusUsage(STDERR);
    exit(1);
}

/**
 * @param list<string> $arguments
 * @return array<string, string|bool>
 */
function parseStatusOptions(array $arguments): array
{
    $options = [];
    foreach ($arguments as $argument) {
        if ($argument === '-h' || $argument === '--help') {
            $options['help'] = true;
            continue;
        }

        foreach (['repository-root', 'database-path', 'queue-database-path'] as $key) {
            $prefix = '--' . $key . '=';
            if (str_starts_with($argument, $prefix)) {
                $value = substr($argument, strlen($prefix));
                if ($value === '') {
                    throw new InvalidArgumentException('Option requires a value: --' . $key);
                }
                $options[$key] = $value;
                continue 2;
            }
        }

        throw new InvalidArgumentException(str_starts_with($argument, '--')
            ? 'Unknown option: ' . $argument
            : 'Unknown argument: ' . $argument);
    }

    return $options;
}

/**
 * @param array{
 *   read_model:array{status:string,freshness_status:string,database_exists:bool,metadata_readable:bool,schema_version:string,repository_root:string,repository_head:string,current_repository_head:string,rebuilt_at:string,rebuild_reason:string,lock_status:string,stale_marker:string,stale_reason:string,stale_commit_sha:string,commits_capability:string},
 *   task_queue:array{status:string,queued:int,running:int,completed:int,failed:int,rebuild_task_status:string,executor_status:string,executor_last_completed_at:string}
 * } $status
 */
function printStatus(array $status, string $repositoryRoot, string $databasePath, string $queuePath): void
{
    $readModel = $status['read_model'];
    $queue = $status['task_queue'];

    fwrite(STDOUT, "v3 status\n");
    fwrite(STDOUT, "Repository: {$repositoryRoot}\n");
    fwrite(STDOUT, 'Repository head: ' . $readModel['current_repository_head'] . "\n");
    fwrite(STDOUT, "Read-model database: {$databasePath}\n");
    fwrite(STDOUT, 'Read model: ' . $readModel['status'] . "\n");
    fwrite(STDOUT, 'Read-model freshness: ' . $readModel['freshness_status'] . "\n");
    fwrite(STDOUT, 'Stale marker: ' . $readModel['stale_marker'] . ' (' . $readModel['stale_reason'] . ")\n");
    fwrite(STDOUT, 'Shared lock: ' . $readModel['lock_status'] . " (general protected activity)\n");
    fwrite(STDOUT, "Task queue: {$queuePath}\n");
    fwrite(STDOUT, sprintf(
        'Task queue status: %s (queued: %d, running: %d, failed: %d)' . "\n",
        $queue['status'],
        $queue['queued'],
        $queue['running'],
        $queue['failed'],
    ));
    fwrite(STDOUT, 'Read-model rebuild task: ' . $queue['rebuild_task_status'] . "\n");
    fwrite(STDOUT, 'Task queue executor: ' . $queue['executor_status'] . ' (last completed: ' . $queue['executor_last_completed_at'] . ")\n");
    fwrite(STDOUT, 'Next action: ' . statusNextAction($readModel['status'], $readModel['lock_status'], $queue['rebuild_task_status']) . "\n");
    fwrite(STDOUT, "Details: ./v3 task-queue status; ./v3 fast-score status\n");
}

function statusNextAction(string $readModelStatus, string $lockStatus, string $rebuildTaskStatus): string
{
    if ($rebuildTaskStatus === 'running') {
        return 'A queued-worker rebuild is running; wait, then rerun ./v3 status.';
    }
    if ($rebuildTaskStatus === 'queued') {
        return 'A rebuild is queued; ensure the task worker runs, then rerun ./v3 status.';
    }
    if ($rebuildTaskStatus === 'failed') {
        return 'Inspect ./v3 task-queue status and recover the failed rebuild.';
    }
    if ($lockStatus === 'locked') {
        return 'A shared lock is held; wait briefly and rerun ./v3 status.';
    }
    if ($readModelStatus !== 'ready') {
        return 'Run ./v3 task-queue enqueue-rebuild or the manual rebuild command in the recovery runbook.';
    }

    return 'No read-model recovery is currently required.';
}

function printStatusUsage($stream): void
{
    fwrite($stream, <<<'TEXT'
Usage:
  ./v3 status [--repository-root=/path/repository] [--database-path=/path/read-model.sqlite3] [--queue-database-path=/private/path/tasks.sqlite3]

TEXT);
}
