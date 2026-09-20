<?php

declare(strict_types=1);

namespace ForumRewrite\TaskQueue;

use Throwable;

final class TaskQueueWorker
{
    /** @var callable():void */
    private $rebuildReadModel;

    /**
     * @param callable():void $rebuildReadModel
     */
    public function __construct(
        private readonly SqliteTaskQueueStore $store,
        callable $rebuildReadModel,
    ) {
        $this->rebuildReadModel = $rebuildReadModel;
    }

    /**
     * @return array{recovered:int,claimed:int,completed:int,retried:int,failed:int}
     */
    public function run(int $limit = 1): array
    {
        $summary = [
            'recovered' => $this->store->recoverAbandonedRunning(),
            'claimed' => 0,
            'completed' => 0,
            'retried' => 0,
            'failed' => 0,
        ];

        foreach ($this->store->claimNext($limit) as $task) {
            $summary['claimed']++;
            $result = $this->runClaimed($task);
            if ($result['status'] === 'completed') {
                $summary['completed']++;
            } elseif ($result['status'] === 'queued') {
                $summary['retried']++;
            } else {
                $summary['failed']++;
            }
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $task
     * @return array<string, mixed>
     */
    public function runClaimed(array $task): array
    {
        $id = (int) ($task['id'] ?? 0);
        if ($id < 1 || ($task['status'] ?? null) !== 'running') {
            throw new \InvalidArgumentException('A running task is required.');
        }

        if (($task['type'] ?? null) !== SqliteTaskQueueStore::REBUILD_READ_MODEL) {
            return $this->store->markFailed($id, 'unsupported_task_type', 'The worker does not support this task type.', false);
        }

        try {
            ($this->rebuildReadModel)();
        } catch (Throwable $throwable) {
            return $this->store->markFailed(
                $id,
                'read_model_rebuild_failed',
                $throwable->getMessage(),
                true,
            );
        }

        return $this->store->markCompleted($id);
    }
}
