<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\ReadModel\ReadModelConnection;
use ForumRewrite\ReadModel\ReadModelStaleMarker;
use ForumRewrite\Support\ExecutionLock;
use ForumRewrite\Support\HealthIndicators;
use ForumRewrite\Support\FrontendTargetResolver;
use ForumRewrite\Support\OperatorStatusCollector;
use ForumRewrite\TaskQueue\TaskQueueDatabaseConfig;
$projectRoot = dirname(__DIR__);

try {
    $options = parseStatusOptions(array_slice($argv, 1));
    if (($options['help'] ?? false) === true) {
        printStatusUsage(STDOUT);
        exit(0);
    }

    $repositoryRoot = (string) ($options['repository-root'] ?? (\ForumRewrite\Support\DeploymentSettings::value('FORUM_REPOSITORY_ROOT') ?: ($projectRoot . '/state/local_repository')));
    $databasePath = (string) ($options['database-path'] ?? (\ForumRewrite\Support\DeploymentSettings::value('FORUM_DATABASE_PATH') ?: ($projectRoot . '/state/cache/post_index.sqlite3')));
    $queuePath = TaskQueueDatabaseConfig::path(
        $projectRoot,
        isset($options['queue-database-path']) ? (string) $options['queue-database-path'] : null,
    );
    $frontendResolver = new FrontendTargetResolver($projectRoot, $repositoryRoot, $databasePath);
    $frontendTarget = $frontendResolver->resolve($options['url'] ?? null);
    $executionLock = new ExecutionLock(dirname($databasePath) . '/forum-rewrite.lock');
    if ($executionLock->isLocked()) {
        fwrite(STDOUT, "Shared lock is held; collecting status without acquiring it...\n");
        fflush(STDOUT);
    }

    $status = (new OperatorStatusCollector(
        $repositoryRoot,
        $databasePath,
        $queuePath,
        $executionLock,
        new ReadModelStaleMarker($databasePath),
        static fn (): PDO => (new ReadModelConnection($databasePath))->open(),
        $frontendTarget['url'],
        $frontendTarget['source'],
        static fn (): array => $frontendResolver->check($frontendTarget),
    ))->collect();

    if (($options['summary'] ?? false) === true) {
        fwrite(STDOUT, "Repository: {$repositoryRoot}\n");
        foreach (deploymentWarnings($projectRoot) as $warning) {
            fwrite(STDOUT, 'Deployment: WARN ' . $warning . "\n");
        }
        foreach (\ForumRewrite\Support\GpgAvailability::warnings() as $warning) {
            fwrite(STDOUT, 'Dependencies: WARN ' . $warning . "\n");
        }
        foreach (HealthIndicators::fromStatus($status) as $indicator) {
            if (in_array($indicator['label'], ['Frontend', 'Read model', 'Task queue', 'Failed tasks'], true)) {
                // Home is deliberately compact; details retain all indicators/recovery.
                $detail = $indicator['label'] === 'Frontend' ? $status['frontend']['status'] : $indicator['detail'];
                fwrite(STDOUT, $indicator['label'] . ': ' . $indicator['level'] . ' ' . $detail . "\n");
            }
        }
    } else {
        printStatus($status, $repositoryRoot, $databasePath, $queuePath);
        printDeploymentSettings($projectRoot, $repositoryRoot, $databasePath, $options);
    }
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
        if ($argument === '--summary') {
            $options['summary'] = true;
            continue;
        }
        if ($argument === '-h' || $argument === '--help') {
            $options['help'] = true;
            continue;
        }

        foreach (['repository-root', 'database-path', 'queue-database-path', 'url'] as $key) {
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

    if (isset($options['url'])) {
        $parts = parse_url((string) $options['url']);
        if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || ($parts['host'] ?? '') === '') {
            throw new InvalidArgumentException('Option --url must be an http(s) URL such as http://127.0.0.1:8000');
        }
    }

    return $options;
}

/**
 * @param array{
 *   read_model:array{status:string,freshness_status:string,database_exists:bool,metadata_readable:bool,schema_version:string,expected_schema_version:string,schema_fingerprint:string,expected_schema_fingerprint:string,repository_root:string,repository_head:string,current_repository_head:string,rebuilt_at:string,rebuild_reason:string,lock_status:string,stale_marker:string,stale_reason:string,stale_commit_sha:string,commits_capability:string},
 *   task_queue:array{status:string,queued:int,running:int,completed:int,failed:int,rebuild_task_status:string,executor_status:string,executor_last_completed_at:string,automatic_recovery_status:string,automatic_recovery_launch_status:string},
 *   frontend:array{status:string,detail:string,latency_ms:?int,url:string},
 *   database_access:array{read_model:array{status:string,detail:string},task_queue:array{status:string,detail:string}}
 * } $status
 */
function printStatus(array $status, string $repositoryRoot, string $databasePath, string $queuePath): void
{
    $readModel = $status['read_model'];
    $queue = $status['task_queue'];
    $indicators = HealthIndicators::fromStatus($status);

    fwrite(STDOUT, "v3 status\n");
    printHealthSummary($indicators);
    fwrite(STDOUT, "Repository: {$repositoryRoot}\n");
    fwrite(STDOUT, 'Repository head: ' . $readModel['current_repository_head'] . "\n");
    fwrite(STDOUT, "Read-model database: {$databasePath}\n");
    fwrite(STDOUT, 'Read model: ' . $readModel['status'] . "\n");
    fwrite(STDOUT, 'Read-model freshness: ' . $readModel['freshness_status'] . "\n");
    fwrite(STDOUT, 'Schema version: ' . $readModel['schema_version'] . ' (expected: ' . $readModel['expected_schema_version'] . ")\n");
    fwrite(STDOUT, 'Schema fingerprint: ' . $readModel['schema_fingerprint'] . ' (expected: ' . $readModel['expected_schema_fingerprint'] . ")\n");
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
    fwrite(STDOUT, 'Automatic schema recovery: ' . $queue['automatic_recovery_status'] . ' (detached launch: ' . $queue['automatic_recovery_launch_status'] . ")\n");
    $fixes = HealthIndicators::fixes($indicators);
    if ($fixes === []) {
        fwrite(STDOUT, 'Next action: ' . statusNextAction($readModel['status'], $readModel['lock_status'], $queue['rebuild_task_status']) . "\n");
    } else {
        fwrite(STDOUT, "Next action:\n");
        foreach ($fixes as $fix) {
            fwrite(STDOUT, '  ' . $fix . "\n");
        }
    }
    fwrite(STDOUT, "Details: ./v3 task-queue status; ./v3 fast-score status\n");
}

/**
 * @param list<array{level:string,label:string,detail:string,fix:?string}> $indicators
 */
function printHealthSummary(array $indicators): void
{
    $labelWidth = max(array_map(static fn (array $indicator): int => strlen($indicator['label']), $indicators));

    fwrite(STDOUT, "\nHealth summary\n");
    foreach ($indicators as $indicator) {
        fwrite(STDOUT, sprintf("%-6s%-" . ($labelWidth + 2) . "s%s\n", $indicator['level'], $indicator['label'], $indicator['detail']));
    }
    fwrite(STDOUT, "\n");
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

/**
 * Where each deployment setting comes from, so web, CLI and cron can be
 * compared. Prints only these non-secret settings, never other private values.
 */
/** @param array<string, string|bool> $options */
function printDeploymentSettings(string $projectRoot, string $repositoryRoot, string $databasePath, array $options): void
{
    $privatePath = \ForumRewrite\Support\PrivateConfig::path($projectRoot);
    fwrite(STDOUT, "\nDeployment settings\n");
    fwrite(STDOUT, 'Private config: ' . $privatePath . (is_file($privatePath) ? '' : ' (missing)') . "\n");
    $effective = ['FORUM_REPOSITORY_ROOT' => $repositoryRoot, 'FORUM_DATABASE_PATH' => $databasePath];
    foreach (\ForumRewrite\Support\DeploymentSettings::KEYS as $key) {
        try {
            $value = \ForumRewrite\Support\DeploymentSettings::value($key, $projectRoot);
            $source = \ForumRewrite\Support\DeploymentSettings::source($key, $projectRoot);
            $option = ['FORUM_REPOSITORY_ROOT' => 'repository-root', 'FORUM_DATABASE_PATH' => 'database-path'][$key] ?? null;
            if ($option !== null && isset($options[$option])) {
                [$value, $source] = [$effective[$key], '--' . $option];
            }
            if ($key === 'FORUM_SITE_ID') {
                $active = \ForumRewrite\SiteProfileRegistry::active()['name'];
                if ($value !== null && $value !== $active) {
                    $source .= ' value "' . $value . '" is not a known profile; using the default';
                }
                $value = $active;
            }
            $value ??= $effective[$key] ?? 'built-in';
            fwrite(STDOUT, "{$key}: {$value} ({$source})\n");
        } catch (Throwable $exception) {
            fwrite(STDOUT, "{$key}: ERROR " . $exception->getMessage() . "\n");
        }
    }
    foreach ([...deploymentWarnings($projectRoot), ...\ForumRewrite\Support\GpgAvailability::warnings()] as $warning) {
        fwrite(STDOUT, 'WARN  ' . $warning . "\n");
    }
}

/** @return list<string> */
function deploymentWarnings(string $projectRoot): array
{
    if (!is_dir($projectRoot . '/.git') && !is_file($projectRoot . '/.git')) {
        return [];
    }
    $output = [];
    $exitCode = 0;
    exec('git -C ' . escapeshellarg($projectRoot) . ' status --porcelain -- public/.htaccess public/index.php 2>/dev/null', $output, $exitCode);
    $warnings = [];
    foreach ($exitCode === 0 ? $output : [] as $line) {
        $file = trim(substr($line, 3));
        $warnings[] = "{$file} has local edits; a code reset discards them. Keep deployment settings in "
            . \ForumRewrite\Support\PrivateConfig::path($projectRoot) . '.';
    }

    return $warnings;
}

function printStatusUsage($stream): void
{
    fwrite($stream, <<<'TEXT'
Usage:
  ./v3 status [--summary] [--repository-root=/path/repository] [--database-path=/path/read-model.sqlite3] [--queue-database-path=/private/path/tasks.sqlite3] [--url=http://127.0.0.1:8000]

Frontend target: --url, FORUM_STATUS_URL (private config/environment), active development server, verified local port 8000/80.
TEXT);
}
