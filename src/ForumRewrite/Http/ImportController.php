<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Import\InstanceImportStatus;
use ForumRewrite\Import\InstanceImportTask;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;
use ForumRewrite\Tools\ToolsPageSupport;
use PDO;

/**
 * `/tools/import/`: import another instance's public content from the site.
 *
 * Modeled on Tools → Deploy. The page and its state render only for a session-authenticated
 * root-approved operator, decided on the server. Imports run in the task worker through
 * InstanceImportTask; the page starts them and shows their progress and the last result.
 */
final class ImportController
{
    /** Seconds after a launch with the task still queued before the page says it didn't start. */
    public const START_TIMEOUT_SECONDS = 15;

    /**
     * @param (\Closure():string)|null $launchWorker starts one detached worker; returns launched|unavailable|failed
     * @param (\Closure():int)|null $clock
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly string $repositoryRoot,
        private readonly string $queueDatabasePath,
        private readonly ?\Closure $launchWorker = null,
        private readonly ?\Closure $clock = null,
    ) {
    }

    public function render(?string $notice = null): string
    {
        $canImport = $this->routeServices->securityReviewAllowed();

        return $this->routeServices->renderPageTemplate(
            'tools_import.php',
            [
                'toolNavOptions' => ToolsPageSupport::navOptions('import'),
                'canImport' => $canImport,
                'importState' => $canImport ? $this->state() : null,
                'importNotice' => $notice,
            ],
            'Import',
            'tools',
            $canImport ? ['/assets/import_control.js'] : [],
        );
    }

    /**
     * POST /tools/import/ with `action` = status | import | resume | cancel; `import` takes
     * `source`. The page script sends JSON with `X-Requested-With: ForumImport` and gets JSON
     * back; a plain form post (no script) gets the page with a notice.
     */
    public function submit(array $query): void
    {
        $wantsJson = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'ForumImport';
        $headers = ['Cache-Control: no-store'];
        if (!SameOriginRequest::check()) {
            $this->respond($wantsJson, 403, ['status' => 'error', 'error' => 'Same-origin request required.'], $headers);
            return;
        }
        if (!$this->routeServices->securityReviewAllowed()) {
            $this->respond($wantsJson, 403, ['status' => 'error', 'error' => 'Importing requires a signed-in root-approved operator.'], $headers);
            return;
        }
        $input = $this->routeServices->requestData($query);
        $action = (string) ($input['action'] ?? '');
        if (!in_array($action, ['status', 'start_worker'], true) && !in_array($action, InstanceImportTask::ACTIONS, true)) {
            $this->respond($wantsJson, 400, ['status' => 'error', 'error' => 'Unknown import action.'], $headers);
            return;
        }
        $message = '';
        if ($action === 'start_worker') {
            // Try again after a worker didn't start: only for a request still waiting in the queue.
            $state = $this->state();
            if (($state['task']['status'] ?? null) !== 'queued') {
                $this->respond($wantsJson, 409, ['status' => 'error', 'error' => 'No import is waiting for a worker.', 'state' => $this->publicState()], $headers);
                return;
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
                $this->respond($wantsJson, 409, ['status' => 'error', 'error' => $refusal, 'state' => $this->publicState()], $headers);
                return;
            }
            try {
                $queued = InstanceImportTask::forQueue($this->queueDatabasePath)->queue(
                    $this->store(),
                    $action,
                    $action === 'import' ? (string) ($input['source'] ?? '') : null,
                    $this->routeServices->viewerSessions()->identityId(),
                );
            } catch (\InvalidArgumentException $error) {
                $this->respond($wantsJson, 400, ['status' => 'error', 'error' => $error->getMessage(), 'state' => $this->publicState()], $headers);
                return;
            } catch (\Throwable $error) {
                $this->respond($wantsJson, 500, ['status' => 'error', 'error' => 'Could not queue the import: ' . $error->getMessage()], $headers);
                return;
            }
            $message = $queued['enqueued'] ? 'Queued for the task worker.' : $queued['message'];
            if ($queued['enqueued'] && $state['worker'] !== 'fresh') {
                $message = $this->startWorker($queued['task_id']) === 'launched' ? 'Starting a worker…' : 'The worker could not be started here.';
            }
        }
        $this->respond($wantsJson, 200, ['status' => 'ok', 'action' => $action, 'message' => $message, 'state' => $this->publicState()], $headers);
    }

    /**
     * The fields the page script polls; the per-file report stays on the page.
     *
     * @return array<string, mixed>
     */
    private function publicState(): array
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
        if ($queued) {
            if (($state['task']['action'] ?? '') !== 'import') {
                return [$worker];
            }
            $pending = [];
            foreach (\ForumRewrite\Import\ImportProgressView::STEPS as $key => $label) {
                $pending[] = ['key' => $key, 'label' => $label, 'status' => 'pending', 'done' => null, 'total' => null];
            }

            return [$worker, ...$pending];
        }
        $worker['label'] = 'Task worker started';

        return [$worker, ...($state['view']['steps'] ?? [])];
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

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $headers
     */
    private function respond(bool $json, int $status, array $payload, array $headers): void
    {
        if ($json) {
            $this->routeServices->sendJson($payload, $status, $headers);
            return;
        }
        $notice = $payload['status'] === 'ok' ? (string) ($payload['message'] ?? '') : (string) ($payload['error'] ?? 'Request refused.');
        $this->routeServices->sendHtml($this->render($notice), $status, $headers);
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
                    $state['view'] = \ForumRewrite\Import\ImportProgressView::build(
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
