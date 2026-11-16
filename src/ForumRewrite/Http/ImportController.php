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
            [],
        );
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
