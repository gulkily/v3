<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Deploy\AppUpdate;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;
use ForumRewrite\Tools\ToolsPageSupport;
use PDO;

/**
 * `/tools/deploy/`: update the application checkout from the site.
 *
 * Opening the page never contacts GitHub: it shows AppUpdate::inspect(), the
 * latest queued deploy task and the worker heartbeat. The controls render only
 * for a session-authenticated root-approved operator; this is a security gate,
 * so it is decided on the server like the other operator tool pages.
 */
final class DeployController
{
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly AppUpdate $update,
        private readonly string $queueDatabasePath,
    ) {
    }

    public function render(): string
    {
        $canDeploy = $this->routeServices->securityReviewAllowed();

        return $this->routeServices->renderPageTemplate(
            'deploy.php',
            [
                'toolNavOptions' => ToolsPageSupport::navOptions('deploy'),
                'canDeploy' => $canDeploy,
                'deployState' => $this->state(),
            ],
            'Deploy',
            'tools',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function state(): array
    {
        $state = $this->update->inspect();
        $state['worker'] = 'not_observed';
        $state['worker_last_completed_at'] = null;
        $state['task'] = null;
        $store = $this->existingStore();
        if ($store !== null) {
            try {
                $heartbeat = $store->executorHeartbeatStatus();
                $state['worker'] = $heartbeat['status'];
                $state['worker_last_completed_at'] = $heartbeat['last_completed_at'];
                $state['task'] = $store->latestByType(SqliteTaskQueueStore::APP_UPDATE);
            } catch (\Throwable) {
                $state['worker'] = 'unavailable';
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
            return new SqliteTaskQueueStore(new PDO('sqlite:' . $this->queueDatabasePath));
        } catch (\Throwable) {
            return null;
        }
    }
}
