<?php

declare(strict_types=1);

namespace ForumRewrite\TaskQueue;

use PDO;
use Throwable;

/**
 * Hands a read-model rebuild to the task queue from a page request, so the request itself
 * never rebuilds. A worker runs it: cron when its heartbeat is fresh, otherwise one detached
 * worker when the host allows it ({@see DetachedTaskQueueLauncher}).
 */
final class AutomaticReadModelRebuild
{
    /** The read model is older than the repository but still readable. */
    public const STALE_REASON = 'read_model_stale';
    /** The read model cannot be read by this code (schema, root or file problem). */
    public const SCHEMA_REASON = SqliteTaskQueueStore::READ_MODEL_SCHEMA_RECOVERY_REASON;

    private ?SqliteTaskQueueStore $store = null;

    public function __construct(
        private readonly string $projectRoot,
        private readonly ?string $queuePath = null,
    ) {
    }

    /** True when a queued rebuild will run soon without this request doing it. */
    public function workerAvailable(): bool
    {
        if (getenv('FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED') === 'true') {
            return true;
        }
        try {
            return is_file($this->path()) && $this->store()->executorHeartbeatStatus()['status'] === 'fresh';
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Queues one rebuild (repeat requests reuse it) and starts a worker when cron is not running.
     *
     * @return 'updating'|'rebuilding'|'maintenance' what a visitor should be told meanwhile
     */
    public function request(string $reason): string
    {
        try {
            $queueDirectory = dirname($this->path());
            if (!is_dir($queueDirectory) && !mkdir($queueDirectory, 0777, true) && !is_dir($queueDirectory)) {
                return 'maintenance';
            }

            $store = $this->store();
            $request = $store->requestAutomaticRebuild($reason);
            if ($request['status'] === 'blocked') {
                return 'maintenance';
            }

            if ($store->executorHeartbeatStatus()['status'] === 'fresh') {
                return 'updating';
            }

            $taskId = (int) $request['task']['id'];
            $existingLaunch = $store->automaticRecoveryLaunchStatus($reason, $taskId);
            if ($existingLaunch === 'launched') {
                return 'rebuilding';
            }
            if ($existingLaunch !== null) {
                return 'maintenance';
            }

            $launchId = $store->reserveAutomaticRecoveryLaunch($reason, $taskId);
            if ($launchId === null) {
                return 'maintenance';
            }

            $launchStatus = (new DetachedTaskQueueLauncher($this->projectRoot, $this->path()))->launchQueuedWorker();
            $store->completeAutomaticRecoveryLaunch($launchId, $launchStatus);

            return $launchStatus === 'launched' ? 'rebuilding' : 'maintenance';
        } catch (Throwable) {
            return 'maintenance';
        }
    }

    private function path(): string
    {
        return TaskQueueDatabaseConfig::path($this->projectRoot, $this->queuePath);
    }

    private function store(): SqliteTaskQueueStore
    {
        return $this->store ??= new SqliteTaskQueueStore(new PDO('sqlite:' . $this->path()));
    }
}
