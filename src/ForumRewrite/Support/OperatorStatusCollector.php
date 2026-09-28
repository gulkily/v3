<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

use ForumRewrite\ReadModel\ReadModelCapabilityInspector;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\ReadModel\ReadModelStaleMarker;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;
use PDO;

final class OperatorStatusCollector
{
    /**
     * @param \Closure(): PDO $readModelPdo
     */
    public function __construct(
        private readonly string $repositoryRoot,
        private readonly string $databasePath,
        private readonly string $queuePath,
        private readonly ExecutionLock $executionLock,
        private readonly ReadModelStaleMarker $staleMarker,
        private readonly \Closure $readModelPdo,
    ) {
    }

    /**
     * @return array{
     *   read_model:array{status:string,freshness_status:string,database_exists:bool,metadata_readable:bool,schema_version:string,repository_root:string,repository_head:string,current_repository_head:string,rebuilt_at:string,rebuild_reason:string,lock_status:string,stale_marker:string,stale_reason:string,stale_commit_sha:string,commits_capability:string},
     *   task_queue:array{status:string,queued:int,running:int,completed:int,failed:int,rebuild_task_status:string}
     * }
     */
    public function collect(): array
    {
        $metadata = [];
        $metadataReadable = false;
        $commitsAvailable = false;
        $databaseExists = is_file($this->databasePath);

        if ($databaseExists) {
            try {
                $pdo = ($this->readModelPdo)();
                $metadata = ReadModelMetadata::readMetadata($pdo);
                $metadataReadable = true;
                $commitsAvailable = (new ReadModelCapabilityInspector())->commitsAvailable($pdo);
            } catch (\Throwable) {
                $metadata = [];
            }
        }

        $currentRepositoryHead = ReadModelMetadata::repositoryHead($this->repositoryRoot);
        $staleMarker = $this->staleMarker->read();
        $freshnessStatus = !$databaseExists || !$metadataReadable
            ? 'unavailable'
            : ((($metadata['repository_root'] ?? null) === $this->repositoryRoot)
                && (($metadata['schema_version'] ?? null) === ReadModelMetadata::SCHEMA_VERSION)
                && (($metadata['repository_head'] ?? null) === $currentRepositoryHead)
                && $staleMarker === null
                ? 'ready'
                : 'stale');
        $status = $freshnessStatus === 'ready' && $commitsAvailable
            ? 'ready'
            : ($freshnessStatus === 'ready' ? 'stale' : $freshnessStatus);

        return [
            'read_model' => [
                'status' => $status,
                'freshness_status' => $freshnessStatus,
                'database_exists' => $databaseExists,
                'metadata_readable' => $metadataReadable,
                'schema_version' => $metadata['schema_version'] ?? 'missing',
                'repository_root' => $metadata['repository_root'] ?? 'missing',
                'repository_head' => $metadata['repository_head'] ?? 'missing',
                'current_repository_head' => $currentRepositoryHead,
                'rebuilt_at' => $metadata['rebuilt_at'] ?? 'missing',
                'rebuild_reason' => $metadata['rebuild_reason'] ?? 'missing',
                'lock_status' => $this->executionLock->isLocked() ? 'locked' : 'unlocked',
                'stale_marker' => $staleMarker === null ? 'absent' : 'present',
                'stale_reason' => $staleMarker['reason'] ?? 'none',
                'stale_commit_sha' => $staleMarker['commit_sha'] ?? 'none',
                'commits_capability' => $commitsAvailable ? 'available' : 'unavailable',
            ],
            'task_queue' => $this->taskQueueStatus(),
        ];
    }

    /**
     * @return array{status:string,queued:int,running:int,completed:int,failed:int,rebuild_task_status:string}
     */
    private function taskQueueStatus(): array
    {
        $empty = ['queued' => 0, 'running' => 0, 'completed' => 0, 'failed' => 0];
        if (!is_file($this->queuePath)) {
            return ['status' => 'not_initialized', 'rebuild_task_status' => 'absent'] + $empty;
        }

        try {
            $store = new SqliteTaskQueueStore(new PDO('sqlite:' . $this->queuePath), false);
            $task = $store->latestByType(SqliteTaskQueueStore::REBUILD_READ_MODEL);
            $taskStatus = (string) ($task['status'] ?? 'absent');
            if ($taskStatus === 'completed') {
                $taskStatus = 'absent';
            }

            return ['status' => 'available', 'rebuild_task_status' => $taskStatus] + $store->counts();
        } catch (\Throwable) {
            return ['status' => 'unavailable', 'rebuild_task_status' => 'unavailable'] + $empty;
        }
    }
}
