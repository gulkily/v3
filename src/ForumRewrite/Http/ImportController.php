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
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly string $repositoryRoot,
        private readonly string $queueDatabasePath,
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
        if ($action !== 'status' && !in_array($action, InstanceImportTask::ACTIONS, true)) {
            $this->respond($wantsJson, 400, ['status' => 'error', 'error' => 'Unknown import action.'], $headers);
            return;
        }
        $message = '';
        if ($action !== 'status') {
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
        ];
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
                    ];
                    $state['progress'] = array_column($store->taskProgress((int) $latest['id'], 40), 'message');
                }
            } catch (\Throwable) {
                $state['worker'] = 'unavailable';
            }
        }
        $state['busy'] = in_array($state['task']['status'] ?? null, ['queued', 'running'], true);
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
