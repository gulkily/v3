<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

final class HealthIndicators
{
    public const OK = 'OK';
    public const WARN = 'WARN';
    public const FAIL = 'FAIL';

    /**
     * @param array<string, mixed> $status OperatorStatusCollector::collect() result
     * @return list<array{level:string,label:string,detail:string,fix:?string}>
     */
    public static function fromStatus(array $status): array
    {
        $readModel = $status['read_model'];
        $queue = $status['task_queue'];
        $frontend = $status['frontend'] ?? ['status' => 'not_checked'];
        $access = $status['database_access'] ?? [];
        $rebuildRunning = $queue['rebuild_task_status'] === 'running';
        $readModelBusy = $readModel['lock_status'] === 'locked'
            || ($access['read_model']['status'] ?? null) === 'locked';
        $readModelFix = $readModelBusy
            ? 'Read model: identify the lock holder before starting another rebuild; a web request or manual rebuild may already be rebuilding it.'
            : 'Read model: Run ./v3 task-queue enqueue-rebuild or the manual rebuild command in the recovery runbook.';
        $indicators = [];

        if (isset($frontend['source'])) {
            $frontend['url'] .= ' (' . $frontend['source'] . ')';
        }
        if ($frontend['status'] !== 'not_checked') {
            $indicators[] = match ($frontend['status']) {
                'ambiguous', 'unresolved' => self::row(self::WARN, 'Frontend', $frontend['detail'], 'Frontend: set FORUM_STATUS_URL in private config, or pass --url=http://host:port.'),
                'redirected' => self::row(self::FAIL, 'Frontend', $frontend['detail'] . ' at ' . $frontend['url'], 'Frontend: set FORUM_STATUS_URL or --url to the direct URL for this instance.'),
                'responding' => self::row(self::OK, 'Frontend', $frontend['detail'] . ' at ' . $frontend['url']),
                'timed_out' => self::row(self::FAIL, 'Frontend', $frontend['detail'] . ' at ' . $frontend['url'], 'Frontend: check the web server/PHP error logs and running PHP processes for the reported URL; inspect the locks below before restarting anything.'),
                'error' => self::row(self::FAIL, 'Frontend', $frontend['detail'] . ' at ' . $frontend['url'], 'Frontend: server is answering with errors; check its web server/PHP error logs (or terminal output for a local ./v3 start server).'),
                default => self::row(self::FAIL, 'Frontend', $frontend['detail'] . ' at ' . $frontend['url'], 'Frontend: verify the reported URL and its web server; use ./v3 start only for a local development server.'),
            };
        }

        $indicators[] = match ($readModel['status']) {
            'ready' => self::row(self::OK, 'Read model', 'ready, schema ' . $readModel['schema_version'] . '/' . $readModel['expected_schema_version']),
            'unavailable' => self::row(self::FAIL, 'Read model', 'unavailable', $readModelFix),
            default => self::row(self::WARN, 'Read model', 'stale', $readModelFix),
        };

        $indicators[] = $readModel['stale_marker'] === 'present'
            ? self::row(self::WARN, 'Stale marker', 'present (' . $readModel['stale_reason'] . ')', 'Stale marker: rebuild the read model to clear it.')
            : self::row(self::OK, 'Stale marker', 'absent');

        $indicators[] = $readModel['lock_status'] === 'locked'
            ? self::row(self::WARN, 'Shared lock', 'held', 'Shared lock: ' . ($rebuildRunning ? 'a rebuild is running; wait, then rerun ./v3 status.' : 'no queued-worker rebuild is reported; web-request and manual rebuilds are not tracked here. Identify the holder with lslocks and inspect its process before intervening.'))
            : self::row(self::OK, 'Shared lock', 'unlocked');

        foreach (['read_model' => 'Read-model DB', 'task_queue' => 'Queue DB'] as $key => $label) {
            $probe = $access[$key] ?? null;
            if ($probe === null) {
                continue;
            }
            if ($probe['status'] === 'missing') {
                // A missing read model is reported by the Read model row; a missing queue just is not created yet.
                if ($key === 'task_queue') {
                    $indicators[] = self::row(self::OK, $label, 'not created yet');
                }
                continue;
            }
            $indicators[] = match ($probe['status']) {
                'accessible' => self::row(self::OK, $label, $probe['detail']),
                'locked' => self::row(self::FAIL, $label, $probe['detail'], $label . ': ' . ($rebuildRunning ? 'locked while a rebuild is running; wait, then rerun ./v3 status.' : 'no queued-worker rebuild is reported; inspect the lock holder with lslocks and ps. A web request or manual command may be writing the database.')),
                default => self::row(self::FAIL, $label, $probe['detail'], $label . ': database cannot be opened; check the file and run ./v3 rebuild diagnose.'),
            };
        }

        $indicators[] = match ($queue['rebuild_task_status']) {
            'running' => self::row(self::WARN, 'Rebuild task', 'running', 'Rebuild task: A queued-worker rebuild is running; wait, then rerun ./v3 status.'),
            'queued' => self::row(self::WARN, 'Rebuild task', 'queued', 'Rebuild task: A rebuild is queued; ensure the task worker runs, then rerun ./v3 status.'),
            'failed' => self::row(self::FAIL, 'Rebuild task', 'failed', 'Rebuild task: Inspect ./v3 task-queue status and recover the failed rebuild.'),
            'unavailable' => self::row(self::FAIL, 'Rebuild task', 'unavailable', 'Rebuild task: task queue database cannot be read; see ./v3 task-queue status.'),
            default => self::row(self::OK, 'Rebuild task', 'none pending'),
        };

        $executorActive = in_array($queue['executor_status'], ['fresh', 'running'], true);
        $backlogStalled = $queue['queued'] > 0 && !$executorActive;
        $indicators[] = $executorActive || ($queue['queued'] === 0 && $queue['executor_status'] !== 'failed')
            ? self::row(self::OK, 'Task executor', $queue['executor_status'] . ($executorActive ? '' : ' (nothing queued)'))
            : self::row(self::WARN, 'Task executor', $queue['executor_status'] . ' (last completed: ' . $queue['executor_last_completed_at'] . ')', 'Task executor: start the task worker (' . $queue['queued'] . ' tasks queued); see ./v3 task-queue status.');

        $indicators[] = $queue['status'] === 'unavailable'
            ? self::row(self::FAIL, 'Task queue', 'unavailable', 'Task queue: database cannot be read; see ./v3 task-queue status.')
            : ($backlogStalled
                ? self::row(self::WARN, 'Task queue', sprintf('%d queued, %d running, no active executor', $queue['queued'], $queue['running']), 'Task queue: ' . $queue['queued'] . ' tasks are waiting with no active executor; start the task worker.')
                : self::row(self::OK, 'Task queue', sprintf('%d queued, %d running', $queue['queued'], $queue['running'])));

        $indicators[] = $queue['failed'] > 0
            ? self::row(self::WARN, 'Failed tasks', (string) $queue['failed'], 'Failed tasks: ' . $queue['failed'] . ' failed; inspect ./v3 task-queue status.')
            : self::row(self::OK, 'Failed tasks', '0');

        return $indicators;
    }

    /**
     * @param list<array{level:string,label:string,detail:string,fix:?string}> $indicators
     * @return list<string>
     */
    public static function fixes(array $indicators): array
    {
        $fixes = [];
        foreach ($indicators as $indicator) {
            if ($indicator['fix'] !== null) {
                $fixes[] = $indicator['fix'];
            }
        }

        return $fixes;
    }

    /**
     * @return array{level:string,label:string,detail:string,fix:?string}
     */
    private static function row(string $level, string $label, string $detail, ?string $fix = null): array
    {
        return ['level' => $level, 'label' => $label, 'detail' => $detail, 'fix' => $fix];
    }
}
