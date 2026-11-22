<?php

declare(strict_types=1);

namespace ForumRewrite\Import;

use ForumRewrite\TaskQueue\SqliteTaskQueueStore;
use PDO;

/**
 * Queues `instance_import` tasks and reads their state for the pages that start imports from
 * a browser: Tools → Import (ImportController) and the local start page
 * (LocalDevelopmentPageController). Each page enforces its own guard before calling this;
 * nothing here decides who may import.
 */
final class InstanceImportRequests
{
    /** Seconds after a launch with the task still queued before the page says it didn't start. */
    public const START_TIMEOUT_SECONDS = 15;

    /**
     * @param (\Closure():string)|null $launchWorker starts one detached worker; returns launched|unavailable|failed
     * @param (\Closure():int)|null $clock
     */
    public function __construct(
        private readonly string $repositoryRoot,
        private readonly string $queueDatabasePath,
        private readonly ?\Closure $launchWorker = null,
        private readonly ?\Closure $clock = null,
    ) {
    }

    /**
     * Carry out `action` = status | start_worker | import | resume | cancel for an already
     * authorized request. `import` takes `source`; `requester` is recorded on the request.
     *
     * @return array{code: int, payload: array<string, mixed>}
     */
    public function perform(string $action, ?string $source, string $requester): array
    {
        if (!in_array($action, ['status', 'start_worker'], true) && !in_array($action, InstanceImportTask::ACTIONS, true)) {
            return ['code' => 400, 'payload' => ['status' => 'error', 'error' => 'Unknown import action.']];
        }
        $message = '';
        if ($action === 'start_worker') {
            // Try again after a worker didn't start: only for a request still waiting in the queue.
            $state = $this->state();
            if (($state['task']['status'] ?? null) !== 'queued') {
                return ['code' => 409, 'payload' => ['status' => 'error', 'error' => 'No import is waiting for a worker.', 'state' => $this->publicState()]];
            }
            $message = $this->startWorker((int) $state['task']['id']) === 'launched' ? 'Starting a worker…' : 'The worker could not be started here.';
        } elseif ($action !== 'status') {
            $state = $this->state();
            $refusal = match (true) {
                $state['busy'] => 'An import is already queued or running.',
                $action === 'import' && $state['pending'] !== null => 'An interrupted import is waiting; resume or cancel it first.',
                $action !== 'import' && $state['pending'] === null => 'There is no interrupted import.',
                $action === 'cancel' && $state['pending']['committed'] => 'Its content is already committed; resume finishes publishing.',
                default => null,
            };
            if ($refusal !== null) {
                return ['code' => 409, 'payload' => ['status' => 'error', 'error' => $refusal, 'state' => $this->publicState()]];
            }
            try {
                $queued = InstanceImportTask::forQueue($this->queueDatabasePath)->queue(
                    $this->store(),
                    $action,
                    $action === 'import' ? (string) $source : null,
                    $requester,
                );
            } catch (\InvalidArgumentException $error) {
                return ['code' => 400, 'payload' => ['status' => 'error', 'error' => $error->getMessage(), 'state' => $this->publicState()]];
            } catch (\Throwable $error) {
                return ['code' => 500, 'payload' => ['status' => 'error', 'error' => 'Could not queue the import: ' . $error->getMessage()]];
            }
            $message = $queued['enqueued'] ? 'Queued for the task worker.' : $queued['message'];
            // A fresh heartbeat only says a worker ran lately. It may have been one this page started,
            // which has exited, so start one unless a worker is running now; the queue's lock keeps
            // a second worker harmless, and the import starts without waiting for the next round.
            if ($queued['enqueued'] && $state['worker'] !== 'running') {
                $message = $this->startWorker($queued['task_id']) === 'launched' ? 'Starting a worker…' : 'The worker could not be started here.';
            }
        }

        return ['code' => 200, 'payload' => ['status' => 'ok', 'action' => $action, 'message' => $message, 'state' => $this->publicState()]];
    }

    /**
     * The fields the page script polls; the per-file report stays on the page.
     *
     * @return array<string, mixed>
     */
    public function publicState(): array
    {
        $state = $this->state();

        return [
            'busy' => $state['busy'],
            'worker' => $state['worker'],
            'task' => $state['task'],
            'progress' => $state['progress'],
            'pending' => $state['pending'] !== null,
            'last_id' => $state['last']['id'] ?? null,
            'start' => $state['start'],
            'worker_command' => $state['worker_command'],
            'steps' => $state['steps'],
            'timing' => $state['timing'],
        ];
    }

    /**
     * The run's steps with the worker start first: queued shows it current and the import's
     * steps pending; once running, the import's own steps from its progress lines.
     *
     * @param array<string, mixed> $state
     * @return list<array{key: string, label: string, status: string, done: ?int, total: ?int}>
     */
    private static function steps(array $state): array
    {
        $queued = ($state['task']['status'] ?? null) === 'queued';
        $worker = ['key' => 'worker', 'label' => $state['start'] === 'starting' ? 'Starting the task worker' : 'Waiting for the task worker',
            'status' => $queued ? 'current' : 'done', 'done' => null, 'total' => null];
        $pending = [];
        if (($state['task']['action'] ?? '') === 'import') {
            foreach (ImportProgressView::STEPS as $key => $label) {
                $pending[] = ['key' => $key, 'label' => $label, 'status' => 'pending', 'done' => null, 'total' => null];
            }
        }
        if ($queued) {
            return [$worker, ...$pending];
        }
        $worker['label'] = 'Task worker started';
        // Between the claim and the import's first line, keep the list as it was while queued.
        $steps = $state['view']['steps'] ?? [];

        return [$worker, ...($steps === [] ? $pending : $steps)];
    }

    /** Start one detached worker for this task and note the attempt on its request. */
    private function startWorker(int $taskId): string
    {
        $status = $this->launchWorker !== null ? (string) ($this->launchWorker)() : 'unavailable';
        $task = InstanceImportTask::forQueue($this->queueDatabasePath);
        $row = $this->existingStore()?->findById($taskId);
        if ($row !== null) {
            $task->annotate((string) $row['deduplication_key'], ['Worker-Launch' => $status, 'Worker-Launch-At' => (string) $this->now()]);
        }

        return $status;
    }

    private function now(): int
    {
        return $this->clock !== null ? (int) ($this->clock)() : time();
    }

    private function store(): SqliteTaskQueueStore
    {
        $directory = dirname($this->queueDatabasePath);
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Task queue directory is not writable.');
        }
        $pdo = new PDO('sqlite:' . $this->queueDatabasePath);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return new SqliteTaskQueueStore($pdo);
    }

    /**
     * @return array<string, mixed>
     */
    public function state(): array
    {
        $task = InstanceImportTask::forQueue($this->queueDatabasePath);
        $state = (new InstanceImportStatus($this->repositoryRoot))->read() + [
            'sources' => $task->sources()->all(),
            'known' => KnownInstances::others((string) ($_SERVER['HTTP_HOST'] ?? '')),
            'worker' => 'not_observed',
            'task' => null,
            'progress' => [],
            'view' => null,
        ];
        $store = $this->existingStore();
        if ($store !== null) {
            try {
                $state['worker'] = $store->executorHeartbeatStatus()['status'];
                $latest = $store->latestByType(SqliteTaskQueueStore::INSTANCE_IMPORT);
                if ($latest !== null) {
                    $request = $task->request((string) $latest['deduplication_key']) ?? [];
                    $state['task'] = [
                        'id' => (int) $latest['id'],
                        'status' => (string) $latest['status'],
                        'action' => $request['Action'] ?? '',
                        'source' => $request['Source'] ?? '',
                        'requested_at' => (string) ($latest['requested_at'] ?? ''),
                        'failure' => (string) ($latest['failure_message'] ?? ''),
                        'launch' => $request['Worker-Launch'] ?? '',
                        'launch_at' => (int) ($request['Worker-Launch-At'] ?? 0),
                    ];
                    $events = $store->taskProgress((int) $latest['id'], 2000);
                    // Structured `Progress:` lines feed the step list; the rest is the raw log tail.
                    $lines = array_values(array_filter(array_column($events, 'message'), static fn ($line): bool => !str_starts_with((string) $line, 'Progress: ')));
                    $state['progress'] = array_slice($lines, -40);
                    $state['view'] = ImportProgressView::build(
                        array_map(static fn (array $event): array => ['message' => (string) $event['message'], 'created_at' => (string) $event['created_at']], $events),
                        $this->now(),
                        $task->previousStepSeconds((string) $latest['deduplication_key']),
                    );
                }
            } catch (\Throwable) {
                $state['worker'] = 'unavailable';
            }
        }
        $state['busy'] = in_array($state['task']['status'] ?? null, ['queued', 'running'], true);
        // waiting: queued and a worker is fresh or was just started; not_started: queued with no
        // worker in sight (launch failed, unavailable, or no claim within the timeout).
        $state['start'] = null;
        if (($state['task']['status'] ?? null) === 'queued') {
            $launch = $state['task']['launch'];
            $state['start'] = match (true) {
                $state['worker'] === 'fresh' || $state['worker'] === 'running' => 'waiting',
                $launch === 'launched' && $this->now() - $state['task']['launch_at'] < self::START_TIMEOUT_SECONDS => 'starting',
                default => 'not_started',
            };
        } elseif (in_array($state['task']['status'] ?? null, ['running', 'completed', 'failed'], true)) {
            $state['start'] = 'started';
        }
        $state['worker_command'] = 'php scripts/task_queue.php run --limit=1';
        $view = $state['view'];
        $state['steps'] = $state['busy'] ? self::steps($state) : [];
        // Until the import prints its first line, time counts from the request.
        $runStart = $view['started_at'] ?? (strtotime((string) ($state['task']['requested_at'] ?? '')) ?: null);
        $state['timing'] = $state['busy'] && $runStart !== null ? [
            'elapsed' => max(0, $this->now() - $runStart),
            'since_update' => max(0, $this->now() - (int) ($view['last_update_at'] ?? $runStart)),
            'step_seconds_left' => $view['step_seconds_left'] ?? null,
            'seconds_left' => $view['seconds_left'] ?? null,
        ] : null;
        // Image copying happens after the merge and is reported only in the command output.
        $state['images'] = null;
        if (($state['task']['status'] ?? null) === 'completed') {
            foreach ($state['progress'] as $line) {
                if (preg_match('/^Images: (\S+)$/', (string) $line, $match) === 1) {
                    $state['images'] = $match[1];
                }
            }
        }

        return $state;
    }

    /** The newest import request's command output, at most its last 128 KiB; null when none ran. */
    public function latestLog(): ?string
    {
        try {
            $latest = $this->existingStore()?->latestByType(SqliteTaskQueueStore::INSTANCE_IMPORT);
        } catch (\Throwable) {
            return null;
        }
        $log = $latest === null ? null : InstanceImportTask::forQueue($this->queueDatabasePath)->log((string) $latest['deduplication_key']);
        if ($log === null || $log === '') {
            return null;
        }

        return strlen($log) > 131072 ? "Earlier output omitted.\n" . substr($log, -131072) : $log;
    }

    /**
     * What an import's own output says about its result. The saved report is not used here:
     * InstanceImportStatus picks the newest run by name, which is ambiguous for runs started in
     * the same second, and the output always belongs to the request it came from.
     *
     * @return array{outcome: ?string, images: ?string, counts: array<string, int>, failure: ?string, seconds: ?float, run_id: ?string}
     */
    public static function resultFromLog(string $log): array
    {
        $counts = [];
        foreach (['import', 'duplicate', 'conflict', 'invalid', 'unsupported', 'excluded'] as $category) {
            if (preg_match('/^' . $category . ': (\d+)$/m', $log, $match) === 1) {
                $counts[$category] = (int) $match[1];
            }
        }

        return [
            'outcome' => preg_match('/^Import result: (\S+)$/m', $log, $match) === 1 ? $match[1] : null,
            'images' => preg_match('/^Images: (\S+)$/m', $log, $match) === 1 ? $match[1] : null,
            'counts' => $counts,
            'failure' => preg_match('/^Import failed: (.+)$/m', $log, $match) === 1 ? $match[1] : null,
            'seconds' => preg_match('/^Elapsed time: ([0-9.]+) seconds$/m', $log, $match) === 1 ? (float) $match[1] : null,
            // The saved report this output belongs to, as LastImportSummary names runs.
            'run_id' => preg_match('#^Review: .*/([^/]+)/report\.json$#m', $log, $match) === 1 ? $match[1] : null,
        ];
    }

    private function existingStore(): ?SqliteTaskQueueStore
    {
        if (!is_file($this->queueDatabasePath)) {
            return null;
        }
        try {
            $pdo = new PDO('sqlite:' . $this->queueDatabasePath);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            return new SqliteTaskQueueStore($pdo);
        } catch (\Throwable) {
            return null;
        }
    }
}
