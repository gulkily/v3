<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Llm\LlmExchangeDatabaseConfig;
use ForumRewrite\Llm\LlmExchangeRecorder;
use ForumRewrite\Scoring\FastScoreContextFactory;
use ForumRewrite\Scoring\FastScoreDatabaseConfig;
use ForumRewrite\Scoring\FastScoreSweepService;
use ForumRewrite\Scoring\FastScoreWorkflowFactory;
use ForumRewrite\Scoring\FastScoringConfig;
use ForumRewrite\Scoring\FastScoringRubricRevision;
use ForumRewrite\Scoring\SqliteFastScoreStore;
use ForumRewrite\Support\PrivateConfig;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
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

    if ($command === 'enqueue-fast-score') {
        $task = $store->enqueue(SqliteTaskQueueStore::FAST_SCORE_SWEEP, 'fast-score-sweep');
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
            fwrite(STDOUT, sprintf(
                "  requested=%s claimed=%s completed=%s\n",
                $task['requested_at'],
                $task['claimed_at'] ?? 'none',
                $task['completed_at'] ?? 'none',
            ));
        }
        exit(0);
    }

    if ($command === 'run') {
        $limit = max(1, (int) ($options['limit'] ?? 1));
        $scoreLimit = max(1, (int) ($options['score-limit'] ?? 25));
        $quiet = ($options['quiet'] ?? false) === true;
        if (($options['dry-run'] ?? false) === true) {
            $counts = $store->counts();
            emitTaskQueue($quiet, "Task queue dry run\n");
            emitTaskQueue($quiet, "Queue database: {$queuePath}\n");
            emitTaskQueue($quiet, "Queued tasks: {$counts['queued']}\n");
            emitTaskQueue($quiet, "Limit: {$limit}\n");
            exit(0);
        }

        $run = static function () use ($store, $projectRoot, $repositoryRoot, $databasePath, $queuePath, $limit, $scoreLimit, $quiet): void {
            $startedAt = microtime(true);
            $before = $store->counts();
            emitTaskQueue($quiet, "Task queue worker starting\n");
            emitTaskQueue($quiet, "Queue database: {$queuePath}\n");
            emitTaskQueue($quiet, "Repository: {$repositoryRoot}\n");
            emitTaskQueue($quiet, "Read model: {$databasePath}\n");
            emitTaskQueue($quiet, "Limit: {$limit}\n");
            emitTaskQueue($quiet, "Fast-score batch limit: {$scoreLimit}\n");
            emitTaskQueue($quiet, sprintf(
                "Queue before: queued=%d running=%d completed=%d failed=%d\n",
                $before['queued'],
                $before['running'],
                $before['completed'],
                $before['failed'],
            ));
            $worker = new TaskQueueWorker(
                $store,
                new ReadModelRebuildTaskHandler($repositoryRoot, $databasePath),
                static function () use ($projectRoot, $databasePath, $scoreLimit): array {
                    if (!is_file($databasePath)) {
                        throw new RuntimeException('Read model database not found: ' . $databasePath);
                    }

                    $privateConfig = PrivateConfig::load($projectRoot);
                    $readPdo = new PDO('sqlite:' . $databasePath);
                    $readPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                    $fetchPost = static function (string $postId) use ($readPdo): ?array {
                        $statement = $readPdo->prepare('SELECT post_id, thread_id, parent_id, subject, body FROM posts WHERE post_id = :post_id');
                        $statement->execute(['post_id' => $postId]);
                        $post = $statement->fetch();
                        return $post === false ? null : $post;
                    };
                    $config = FastScoringConfig::fromPrivateConfig($privateConfig);
                    $scorePath = FastScoreDatabaseConfig::path($projectRoot, $privateConfig);
                    $scoreDirectory = dirname($scorePath);
                    if (!is_dir($scoreDirectory) && !mkdir($scoreDirectory, 0777, true) && !is_dir($scoreDirectory)) {
                        throw new RuntimeException('Fast-score database directory is not writable.');
                    }

                    $workflow = FastScoreWorkflowFactory::fromPrivateConfig(
                        $privateConfig,
                        $projectRoot,
                        $fetchPost,
                        taskQueueLlmExchangeRecorder($projectRoot, $repositoryRoot, $privateConfig),
                    );
                    $contextFactory = new FastScoreContextFactory($fetchPost);
                    $scoreStore = new SqliteFastScoreStore(new PDO('sqlite:' . $scorePath));

                    return (new FastScoreSweepService(
                        $readPdo,
                        $contextFactory,
                        $workflow,
                        $scoreStore,
                        FastScoringRubricRevision::fromConfig($config, $projectRoot),
                    ))->run($scoreLimit);
                },
            );
            $taskStartedAt = [];
            $summary = $worker->run($limit, static function (string $event, array $task) use ($quiet, &$taskStartedAt): void {
                if ($event === 'recovered') {
                    emitTaskQueue($quiet, 'Recovered abandoned tasks: ' . $task['count'] . "\n");
                    return;
                }

                $taskId = (int) ($task['id'] ?? 0);
                if ($event === 'started') {
                    $taskStartedAt[$taskId] = microtime(true);
                    emitTaskQueue($quiet, sprintf(
                        "Starting task id=%d type=%s attempt=%d/%d\n",
                        $taskId,
                        $task['type'],
                        $task['attempts'],
                        $task['max_attempts'],
                    ));
                    return;
                }

                $elapsed = isset($taskStartedAt[$taskId]) ? microtime(true) - $taskStartedAt[$taskId] : 0.0;
                emitTaskQueue($quiet, sprintf(
                    "Finished task id=%d status=%s elapsed=%.3fs failure=%s\n",
                    $taskId,
                    $task['status'],
                    $elapsed,
                    $task['failure_code'] ?? 'none',
                ));
                if (isset($task['sweep']) && is_array($task['sweep'])) {
                    emitTaskQueue($quiet, sprintf(
                        "  Fast-score sweep: processed=%d scored=%d excluded=%d failed=%d remaining=%s\n",
                        (int) ($task['sweep']['processed'] ?? 0),
                        (int) ($task['sweep']['scored'] ?? 0),
                        (int) ($task['sweep']['excluded'] ?? 0),
                        (int) ($task['sweep']['failed'] ?? 0),
                        ($task['sweep']['remaining'] ?? false) === true ? 'yes' : 'no',
                    ));
                }
            });
            $after = $store->counts();
            emitTaskQueue($quiet, sprintf(
                "Task queue run complete: recovered=%d claimed=%d completed=%d continued=%d retried=%d failed=%d\n",
                $summary['recovered'],
                $summary['claimed'],
                $summary['completed'],
                $summary['continued'],
                $summary['retried'],
                $summary['failed'],
            ));
            emitTaskQueue($quiet, sprintf(
                "Queue after: queued=%d running=%d completed=%d failed=%d\n",
                $after['queued'],
                $after['running'],
                $after['completed'],
                $after['failed'],
            ));
            emitTaskQueue($quiet, sprintf("Elapsed: %.3fs\n", microtime(true) - $startedAt));
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
        foreach (['limit', 'score-limit', 'repository-root', 'database-path', 'queue-database-path', 'log'] as $key) {
            $prefix = '--' . $key . '=';
            if (str_starts_with($argument, $prefix)) {
                $value = substr($argument, strlen($prefix));
                $options[$key] = in_array($key, ['limit', 'score-limit'], true) ? max(1, (int) $value) : $value;
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
  php scripts/task_queue.php enqueue-fast-score [--queue-database-path=/private/path/tasks.sqlite3]
  php scripts/task_queue.php run [--limit=1] [--score-limit=25] [--dry-run] [--quiet] [--repository-root=/path/repository] [--database-path=/path/read-model.sqlite3] [--queue-database-path=/private/path/tasks.sqlite3]
  php scripts/task_queue.php status [--limit=25] [--queue-database-path=/private/path/tasks.sqlite3]
  php scripts/task_queue.php cron [--log=/var/log/forum-task-queue.log]

TEXT);
}

/**
 * @param array<string, mixed> $privateConfig
 */
function taskQueueLlmExchangeRecorder(string $projectRoot, string $repositoryRoot, array $privateConfig): ?LlmExchangeRecorder
{
    if (!FeatureFlagEvaluator::forApplication($repositoryRoot, $projectRoot)->isEnabled(FeatureFlagRegistry::LLM_CONVERSATION_RECORDING_ENABLED)) {
        return null;
    }

    $path = LlmExchangeDatabaseConfig::path($projectRoot, $privateConfig);
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('LLM exchange database directory is not writable.');
    }

    return new LlmExchangeRecorder(new PDO('sqlite:' . $path));
}
