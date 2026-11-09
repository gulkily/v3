<?php

declare(strict_types=1);

namespace ForumRewrite\TaskQueue;

use ForumRewrite\ReadModel\ReadModelCandidateBehindHead;
use ForumRewrite\ReadModel\ReadModelCandidateBuilder;
use ForumRewrite\ReadModel\ReadModelCandidatePromoter;
use ForumRewrite\Support\ExecutionLock;

/**
 * Rebuilds the read model in the background without blocking readers or writers: it builds a
 * new file while the site keeps serving and accepting writes, then swaps it in under the write
 * lock. When writes landed during the build, the new file is behind and is not swapped in; it
 * builds again. After {@see self::UNLOCKED_ATTEMPTS} such misses it builds once under the lock,
 * which always finishes.
 */
final class ReadModelRebuildTaskHandler
{
    public const UNLOCKED_ATTEMPTS = 2;

    /** @var (callable():void)|null */
    private $enqueueOfflineSnapshotPublication;
    /** @var (callable(int, string):void)|null */
    private $progressReporter;

    public function __construct(
        private readonly string $repositoryRoot,
        private readonly string $databasePath,
        ?callable $enqueueOfflineSnapshotPublication = null,
        ?callable $progressReporter = null,
    ) {
        $this->enqueueOfflineSnapshotPublication = $enqueueOfflineSnapshotPublication;
        $this->progressReporter = $progressReporter;
    }

    public function __invoke(int $taskId): void
    {
        $report = function (string $message) use ($taskId): void {
            if ($this->progressReporter !== null) {
                ($this->progressReporter)($taskId, $message);
            }
        };

        $swapped = false;
        for ($attempt = 1; $attempt <= self::UNLOCKED_ATTEMPTS && !$swapped; $attempt++) {
            $swapped = $this->buildThenSwap($report);
            if (!$swapped) {
                $report('Read model: new commits landed during the build; building again');
            }
        }
        if (!$swapped) {
            $report('Read model: building under the write lock');
            $this->executionLock()->withExclusiveLock(function () use ($report): void {
                $this->promote($this->candidates($report)->build(), $report);
            });
        }

        $report('Read model: rebuild complete; new file in use');

        if ($this->enqueueOfflineSnapshotPublication !== null) {
            try {
                ($this->enqueueOfflineSnapshotPublication)();
            } catch (\Throwable) {
                error_log('Offline snapshot publication enqueue failed after a queued read-model rebuild.');
            }
        }
    }

    /** Builds without the lock, then swaps under it. False when the new file is behind HEAD. */
    private function buildThenSwap(\Closure $report): bool
    {
        try {
            $candidatePath = $this->candidates($report)->build();
        } catch (ReadModelCandidateBehindHead) {
            return false;
        }

        try {
            return $this->executionLock()->withExclusiveLock(function () use ($candidatePath, $report): bool {
                try {
                    $this->promote($candidatePath, $report);
                    return true;
                } catch (ReadModelCandidateBehindHead) {
                    return false;
                }
            });
        } finally {
            if (is_file($candidatePath)) {
                @unlink($candidatePath);
            }
        }
    }

    private function promote(string $candidatePath, \Closure $report): void
    {
        try {
            (new ReadModelCandidatePromoter($this->repositoryRoot, $this->databasePath, $report))->promoteWhileLocked($candidatePath);
        } finally {
            if (is_file($candidatePath)) {
                @unlink($candidatePath);
            }
        }
    }

    private function candidates(\Closure $report): ReadModelCandidateBuilder
    {
        return new ReadModelCandidateBuilder($this->repositoryRoot, $this->databasePath, 'task_queue', $report);
    }

    private function executionLock(): ExecutionLock
    {
        return new ExecutionLock(dirname($this->databasePath) . '/forum-rewrite.lock');
    }
}
