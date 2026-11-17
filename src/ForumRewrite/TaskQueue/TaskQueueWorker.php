<?php

declare(strict_types=1);

namespace ForumRewrite\TaskQueue;

use InvalidArgumentException;
use Throwable;

final class TaskQueueWorker
{
    /** @var callable(int):void */
    private $rebuildReadModel;
    /** @var (callable():array<string, mixed>)|null */
    private $runFastScoreSweep;
    /** @var (callable():void)|null */
    private $publishOfflineSnapshot;
    /** @var (callable(array<string, mixed>):void)|null */
    private $fulfillAgentReply;

    /**
     * @param callable(int):void $rebuildReadModel
     * @param (callable():array<string, mixed>)|null $runFastScoreSweep
     * @param (callable():void)|null $publishOfflineSnapshot
     * @param (callable(array<string, mixed>):array)|null $fulfillAgentReply
     */
    public function __construct(
        private readonly SqliteTaskQueueStore $store,
        callable $rebuildReadModel,
        ?callable $runFastScoreSweep = null,
        ?callable $publishOfflineSnapshot = null,
        ?callable $fulfillAgentReply = null,
        /** @var (callable(int, Throwable):void)|null */
        private readonly mixed $onTaskThrowable = null,
        private readonly mixed $runRetractionSweep = null,
        /** @var (callable():array<string, mixed>)|null returns an AppUpdate::deploy() attempt */
        private readonly mixed $runAppUpdate = null,
        /** @var (callable():array<string, mixed>)|null returns a LocalMetaSweep::run() result, or null when no model exists */
        private readonly mixed $runLocalScoreSweep = null,
        /** @var (callable():array<string, mixed>)|null trains and saves the local meta model, returning its report */
        private readonly mixed $runLocalMetaTrain = null,
        /** @var (callable(array<string, mixed>):array{exit_code:int,message:string})|null runs InstanceImportTask */
        private readonly mixed $runInstanceImport = null,
    ) {
        $this->rebuildReadModel = $rebuildReadModel;
        $this->runFastScoreSweep = $runFastScoreSweep;
        $this->publishOfflineSnapshot = $publishOfflineSnapshot;
        $this->fulfillAgentReply = $fulfillAgentReply;
    }

    /**
     * @return array{recovered:int,claimed:int,completed:int,continued:int,retried:int,failed:int}
     */
    public function run(int $limit = 1, ?callable $report = null, ?int $taskId = null): array
    {
        $summary = [
            'recovered' => $taskId === null ? $this->store->recoverAbandonedRunning() : 0,
            'claimed' => 0,
            'completed' => 0,
            'continued' => 0,
            'retried' => 0,
            'failed' => 0,
        ];
        if ($report !== null && $summary['recovered'] > 0) {
            $report('recovered', ['count' => $summary['recovered']]);
        }

        foreach ($this->store->claimNext($limit, $taskId) as $task) {
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
            SqliteTaskQueueStore::RETRACTION_SWEEP => $this->runRetractionSweepTask($id),
            SqliteTaskQueueStore::REBUILD_READ_MODEL => $this->runReadModelRebuild($id),
            SqliteTaskQueueStore::FAST_SCORE_SWEEP => $this->runFastScoreSweep($id),
            SqliteTaskQueueStore::LOCAL_SCORE_SWEEP => $this->runLocalScoreSweep($id),
            SqliteTaskQueueStore::LOCAL_META_TRAIN => $this->runLocalMetaTrain($id),
            SqliteTaskQueueStore::PUBLISH_OFFLINE_SNAPSHOT => $this->runOfflineSnapshotPublication($id),
            SqliteTaskQueueStore::AGENT_REPLY => $this->runAgentReply($id, $task),
            SqliteTaskQueueStore::APP_UPDATE => $this->runAppUpdateTask($id),
            SqliteTaskQueueStore::INSTANCE_IMPORT => $this->runInstanceImportTask($id, $task),
            default => $this->store->markFailed($id, 'unsupported_task_type', 'The worker does not support this task type.', false),
        };
    }

    private function runRetractionSweepTask(int $id): array
    {
        try {
            if ($this->runRetractionSweep === null) throw new \RuntimeException('Sweep unavailable.');
            $sweep = ($this->runRetractionSweep)();
            $this->store->recordTaskProgress($id, json_encode($sweep, JSON_THROW_ON_ERROR));
            if ($sweep['remaining'] ?? false) return [...$this->store->requeueClaimed($id), 'continued' => true];
            return $this->store->markCompleted($id);
        } catch (Throwable) {
            return $this->store->markFailed($id, 'retraction_sweep_failed', 'Cleanup failed. Inspect retraction-sweep status and retry.', true);
        }
    }

    /** @return array<string, mixed> */
    private function runReadModelRebuild(int $id): array
    {
        try {
            ($this->rebuildReadModel)($id);
        } catch (Throwable $throwable) {
            $this->reportThrowable($id, $throwable);
            return $this->store->markFailed($id, 'read_model_rebuild_failed', $throwable->getMessage(), true);
        }

        return $this->store->markCompleted($id);
    }

    /** @return array<string, mixed> */
    /**
     * A busy refusal (an instant deploy holds the lock) is retried on a later
     * run; every other refusal or failure is final and shown on the Deploy page.
     *
     * @return array<string, mixed>
     */
    private function runAppUpdateTask(int $id): array
    {
        if (!is_callable($this->runAppUpdate)) {
            return $this->store->markFailed($id, 'app_update_unavailable', 'Deploys are not configured for this worker.', false);
        }
        try {
            $attempt = ($this->runAppUpdate)();
        } catch (Throwable $throwable) {
            $this->reportThrowable($id, $throwable);
            return $this->store->markFailed($id, 'app_update_failed', $throwable->getMessage(), false);
        }
        if (in_array($attempt['outcome'] ?? null, ['deployed', 'up_to_date'], true)) {
            $task = $this->store->markCompleted($id);
        } else {
            $reason = (string) ($attempt['reason'] ?? 'app_update_failed');
            $task = $this->store->markFailed($id, $reason, (string) ($attempt['message'] ?? 'Deploy did not complete.'), $reason === 'busy');
        }
        $task['app_update'] = $attempt;

        return $task;
    }

    /**
     * Exit code 2 (partial result needing review) still counts as a finished import; the page
     * shows the review causes. Imports are never retried automatically.
     *
     * @param array<string, mixed> $task
     * @return array<string, mixed>
     */
    private function runInstanceImportTask(int $id, array $task): array
    {
        if (!is_callable($this->runInstanceImport)) {
            return $this->store->markFailed($id, 'instance_import_unavailable', 'Imports are not configured for this worker.', false);
        }
        try {
            $result = ($this->runInstanceImport)($task);
        } catch (Throwable $throwable) {
            $this->reportThrowable($id, $throwable);
            return $this->store->markFailed($id, 'instance_import_failed', $throwable->getMessage(), false);
        }
        $exitCode = (int) ($result['exit_code'] ?? 1);
        $finished = in_array($exitCode, [0, 2], true)
            ? $this->store->markCompleted($id)
            : $this->store->markFailed($id, 'instance_import_failed', (string) ($result['message'] ?? '') ?: 'Import did not complete.', false);
        $finished['instance_import'] = ['exit_code' => $exitCode];

        return $finished;
    }

    private function runFastScoreSweep(int $id): array
    {
        if ($this->runFastScoreSweep === null) {
            return $this->store->markFailed($id, 'fast_score_sweep_unavailable', 'Fastmod sweep is not configured for this worker.', false);
        }

        try {
            $sweep = ($this->runFastScoreSweep)();
        } catch (Throwable $throwable) {
            $this->reportThrowable($id, $throwable);
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

    /** @return array<string, mixed> */
    private function runLocalMetaTrain(int $id): array
    {
        if ($this->runLocalMetaTrain === null) {
            return $this->store->markFailed($id, 'local_meta_train_unavailable', 'Local meta training is not configured for this worker.', false);
        }
        try {
            $report = ($this->runLocalMetaTrain)();
        } catch (\ForumRewrite\Scoring\LocalMetaTrainingRefused $refused) {
            return $this->store->markFailed($id, 'local_meta_too_few_labels', $refused->getMessage(), false);
        } catch (Throwable $throwable) {
            $this->reportThrowable($id, $throwable);
            return $this->store->markFailed($id, 'local_meta_train_failed', $throwable->getMessage(), true);
        }
        return [...$this->store->markCompleted($id), 'training' => $report];
    }

    /** @return array<string, mixed> */
    private function runLocalScoreSweep(int $id): array
    {
        if ($this->runLocalScoreSweep === null) {
            return $this->store->markFailed($id, 'local_score_sweep_unavailable', 'Local score sweep is not configured for this worker.', false);
        }
        try {
            $sweep = ($this->runLocalScoreSweep)();
        } catch (Throwable $throwable) {
            $this->reportThrowable($id, $throwable);
            return $this->store->markFailed($id, 'local_score_sweep_failed', $throwable->getMessage(), true);
        }
        if ($sweep === null) {
            return $this->store->markFailed($id, 'local_meta_model_missing', 'No local meta model yet. Press Retrain on Tools > Fastmod or run ./v3 fast-score local-train.', false);
        }
        if (($sweep['remaining'] ?? false) === true) {
            return [...$this->store->requeueClaimed($id), 'continued' => true, 'sweep' => $sweep];
        }
        return [...$this->store->markCompleted($id), 'sweep' => $sweep];
    }

    /** @return array<string, mixed> */
    private function runOfflineSnapshotPublication(int $id): array
    {
        if ($this->publishOfflineSnapshot === null) {
            return $this->store->markFailed($id, 'offline_snapshot_publication_unavailable', 'Offline snapshot publication is not configured for this worker.', false);
        }

        try {
            ($this->publishOfflineSnapshot)();
        } catch (Throwable $throwable) {
            $this->reportThrowable($id, $throwable);
            return $this->store->markFailed($id, 'offline_snapshot_publication_failed', $throwable->getMessage(), true);
        }

        return $this->store->markCompleted($id);
    }

    private function runAgentReply(int $id, array $task): array
    {
        if ($this->fulfillAgentReply === null) {
            return $this->store->markFailed($id, 'agent_reply_unavailable', 'Agent-reply fulfillment is not configured for this worker.', false);
        }

        try {
            $outcome = ($this->fulfillAgentReply)($task);
            $status = (string) ($outcome['generation_status'] ?? 'unavailable');
            if ($status === 'failed') {
                $code = (string) ($outcome['failure_code'] ?? 'agent_reply_fulfillment_failed');
                // Fulfillment records failures durably. Recovery must reset that record
                // before another claim; repeating this queue task alone cannot retry it.
                return $this->store->markFailed($id, $code, \ForumRewrite\Agent\AgentReplyFailure::message($code), false);
            }
            if (!in_array($status, ['generated', 'already_posted', 'not_recommended'], true)) {
                return $this->store->markFailed($id, 'agent_reply_unavailable', 'Reply request is unavailable for fulfillment; inspect its stored state.', false);
            }
            $reason = (string) ($outcome['reason'] ?? '');
            $this->store->recordTaskProgress($id, 'Agent reply outcome: ' . $status . ($reason === '' ? '' : ' (' . $reason . ')'));
            return array_merge($this->store->markCompleted($id), ['generation_status' => $status, 'reason' => $reason]);
        } catch (InvalidArgumentException $exception) {
            $this->reportThrowable($id, $exception);
            return $this->store->markFailed($id, 'agent_reply_task_invalid', $exception->getMessage(), false);
        } catch (Throwable $throwable) {
            $this->reportThrowable($id, $throwable);
            return $this->store->markFailed($id, 'agent_reply_fulfillment_failed', $throwable->getMessage(), true);
        }
    }

    private function reportThrowable(int $id, Throwable $throwable): void
    {
        if (is_callable($this->onTaskThrowable)) {
            try {
                ($this->onTaskThrowable)($id, $throwable);
            } catch (Throwable) {
                // A failing observer must not change how the task itself is recorded.
            }
        }
    }
}
