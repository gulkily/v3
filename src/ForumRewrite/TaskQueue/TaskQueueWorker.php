<?php

declare(strict_types=1);

namespace ForumRewrite\TaskQueue;

use Throwable;

final class TaskQueueWorker
{
    /** @var callable():void */
    private $rebuildReadModel;
    /** @var (callable():array<string, mixed>)|null */
    private $runFastScoreSweep;

    /**
     * @param callable():void $rebuildReadModel
     * @param (callable():array<string, mixed>)|null $runFastScoreSweep
     */
    public function __construct(
        private readonly SqliteTaskQueueStore $store,
        callable $rebuildReadModel,
        ?callable $runFastScoreSweep = null,
    ) {
        $this->rebuildReadModel = $rebuildReadModel;
        $this->runFastScoreSweep = $runFastScoreSweep;
    }

    /**
     * @return array{recovered:int,claimed:int,completed:int,continued:int,retried:int,failed:int}
     */
    public function run(int $limit = 1, ?callable $report = null): array
    {
        $summary = [
            'recovered' => $this->store->recoverAbandonedRunning(),
            'claimed' => 0,
            'completed' => 0,
            'continued' => 0,
            'retried' => 0,
            'failed' => 0,
        ];
        if ($report !== null && $summary['recovered'] > 0) {
            $report('recovered', ['count' => $summary['recovered']]);
        }

        foreach ($this->store->claimNext($limit) as $task) {
            $summary['claimed']++;
            if ($report !== null) {
                $report('started', $task);
            }
            $result = $this->runClaimed($task);
            if ($report !== null) {
                $report('finished', $result);
            }
            if ($result['status'] === 'completed') {
                $summary['completed']++;
            } elseif (($result['continued'] ?? false) === true) {
                $summary['continued']++;
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

        return match ($task['type'] ?? null) {
            SqliteTaskQueueStore::REBUILD_READ_MODEL => $this->runReadModelRebuild($id),
            SqliteTaskQueueStore::FAST_SCORE_SWEEP => $this->runFastScoreSweep($id),
            default => $this->store->markFailed($id, 'unsupported_task_type', 'The worker does not support this task type.', false),
        };
    }

    /** @return array<string, mixed> */
    private function runReadModelRebuild(int $id): array
    {
        try {
            ($this->rebuildReadModel)();
        } catch (Throwable $throwable) {
            return $this->store->markFailed($id, 'read_model_rebuild_failed', $throwable->getMessage(), true);
        }

        return $this->store->markCompleted($id);
    }

    /** @return array<string, mixed> */
    private function runFastScoreSweep(int $id): array
    {
        if ($this->runFastScoreSweep === null) {
            return $this->store->markFailed($id, 'fast_score_sweep_unavailable', 'Fast score sweep is not configured for this worker.', false);
        }

        try {
            $sweep = ($this->runFastScoreSweep)();
        } catch (Throwable $throwable) {
            return $this->store->markFailed($id, 'fast_score_sweep_failed', $throwable->getMessage(), true);
        }

        if (($sweep['remaining'] ?? false) === true) {
            $task = $this->store->requeueClaimed($id);
            $task['continued'] = true;
            $task['sweep'] = $sweep;
            return $task;
        }

        $task = $this->store->markCompleted($id);
        $task['sweep'] = $sweep;
        return $task;
    }
}
