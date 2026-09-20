<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Support\ExecutionLock;
use ForumRewrite\TaskQueue\ReadModelRebuildTaskHandler;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;
use ForumRewrite\TaskQueue\TaskQueueDatabaseConfig;
use ForumRewrite\TaskQueue\TaskQueueWorker;

$projectRoot = dirname(__DIR__);
$command = $argv[1] ?? '';
$options = parseTaskQueueOptions(array_slice($argv, 2));
$repositoryRoot = (string) ($options['repository-root'] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: ($projectRoot . '/state/local_repository')));
$databasePath = (string) ($options['database-path'] ?? (getenv('FORUM_DATABASE_PATH') ?: ($projectRoot . '/state/cache/post_index.sqlite3')));
$queuePath = TaskQueueDatabaseConfig::path($projectRoot, isset($options['queue-database-path']) ? (string) $options['queue-database-path'] : null);

try {
    $queueDirectory = dirname($queuePath);
    if (!is_dir($queueDirectory) && !mkdir($queueDirectory, 0777, true) && !is_dir($queueDirectory)) {
        throw new RuntimeException('Task queue directory is not writable.');
    }

    $pdo = new PDO('sqlite:' . $queuePath);
    $store = new SqliteTaskQueueStore($pdo);

    if ($command === 'enqueue-rebuild') {
        $task = $store->enqueue(SqliteTaskQueueStore::REBUILD_READ_MODEL, 'read-model');
        fwrite(STDOUT, sprintf(
            "Task %s: id=%d status=%s\n",
            $task['enqueued'] ? 'enqueued' : 'already outstanding',
            $task['id'],
            $task['status'],
        ));
        exit(0);
    }

    if ($command === 'status') {
        $counts = $store->counts();
        fwrite(STDOUT, "Task queue status\n");
        fwrite(STDOUT, "Queue database: {$queuePath}\n");
        fwrite(STDOUT, sprintf(
            "Queued: %d, running: %d, completed: %d, failed: %d\n",
            $counts['queued'],
            $counts['running'],
            $counts['completed'],
            $counts['failed'],
        ));
        foreach ($store->recent((int) ($options['limit'] ?? 25)) as $task) {
            fwrite(STDOUT, sprintf(
                "Task id=%d type=%s status=%s attempts=%d/%d failure=%s\n",
                $task['id'],
                $task['type'],
                $task['status'],
                $task['attempts'],
                $task['max_attempts'],
                $task['failure_code'] ?? 'none',
            ));
        }
        exit(0);
    }

    if ($command === 'run') {
        $limit = max(1, (int) ($options['limit'] ?? 1));
        $quiet = ($options['quiet'] ?? false) === true;
        if (($options['dry-run'] ?? false) === true) {
            $counts = $store->counts();
            emitTaskQueue($quiet, "Task queue dry run\n");
            emitTaskQueue($quiet, "Queued tasks: {$counts['queued']}\n");
            emitTaskQueue($quiet, "Limit: {$limit}\n");
            exit(0);
        }

        $run = static function () use ($store, $repositoryRoot, $databasePath, $limit, $quiet): void {
            $worker = new TaskQueueWorker($store, new ReadModelRebuildTaskHandler($repositoryRoot, $databasePath));
            $summary = $worker->run($limit);
            emitTaskQueue($quiet, sprintf(
                "Task queue run complete: recovered=%d claimed=%d completed=%d retried=%d failed=%d\n",
                $summary['recovered'],
                $summary['claimed'],
                $summary['completed'],
                $summary['retried'],
                $summary['failed'],
            ));
        };

        try {
            (new ExecutionLock($queueDirectory . '/forum-rewrite-task-queue.lock', 0))->withExclusiveLock($run);
        } catch (RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'Timed out waiting for execution lock')) {
                emitTaskQueue($quiet, "Another task queue worker is already running.\n");
                exit(0);
            }

            throw $exception;
        }
        exit(0);
    }

    if ($command === 'cron') {
        $logPath = (string) ($options['log'] ?? '/var/log/forum-task-queue.log');
        $appRoot = realpath($projectRoot) ?: $projectRoot;
        $cronLine = '* * * * * cd ' . escapeshellarg($appRoot)
            . ' && php scripts/task_queue.php run --quiet --limit=1 >> '
            . escapeshellarg($logPath) . ' 2>&1';
        fwrite(STDOUT, "Task queue cron reference\n\nInstall:\n  crontab -e\n  {$cronLine}\n");
        exit(0);
    }

    printTaskQueueUsage(STDERR);
    exit(1);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Error: ' . $exception->getMessage() . "\n");
    exit(1);
}

/**
 * @param list<string> $arguments
 * @return array<string, string|bool|int>
 */
function parseTaskQueueOptions(array $arguments): array
{
    $options = [];
    foreach ($arguments as $argument) {
        if (in_array($argument, ['--dry-run', '--quiet'], true)) {
            $options[substr($argument, 2)] = true;
            continue;
        }
        foreach (['limit', 'repository-root', 'database-path', 'queue-database-path', 'log'] as $key) {
            $prefix = '--' . $key . '=';
            if (str_starts_with($argument, $prefix)) {
                $value = substr($argument, strlen($prefix));
                $options[$key] = $key === 'limit' ? max(1, (int) $value) : $value;
                continue 2;
            }
        }
        throw new InvalidArgumentException('Unknown option: ' . $argument);
    }

    return $options;
}

function emitTaskQueue(bool $quiet, string $message): void
{
    if (!$quiet) {
        fwrite(STDOUT, $message);
    }
}

function printTaskQueueUsage($stream): void
{
    fwrite($stream, <<<'TEXT'
Usage:
  php scripts/task_queue.php enqueue-rebuild [--queue-database-path=/private/path/tasks.sqlite3]
  php scripts/task_queue.php run [--limit=1] [--dry-run] [--quiet] [--repository-root=/path/repository] [--database-path=/path/read-model.sqlite3] [--queue-database-path=/private/path/tasks.sqlite3]
  php scripts/task_queue.php status [--limit=25] [--queue-database-path=/private/path/tasks.sqlite3]
  php scripts/task_queue.php cron [--log=/var/log/forum-task-queue.log]

TEXT);
}
