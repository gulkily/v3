<?php

declare(strict_types=1);

namespace ForumRewrite\TaskQueue;

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelStaleMarker;
use ForumRewrite\Support\ExecutionLock;

final class ReadModelRebuildTaskHandler
{
    /** @var (callable():void)|null */
    private $enqueueOfflineSnapshotPublication;

    public function __construct(
        private readonly string $repositoryRoot,
        private readonly string $databasePath,
        ?callable $enqueueOfflineSnapshotPublication = null,
    ) {
        $this->enqueueOfflineSnapshotPublication = $enqueueOfflineSnapshotPublication;
    }

    public function __invoke(): void
    {
        (new ExecutionLock(dirname($this->databasePath) . '/forum-rewrite.lock', 0))->withExclusiveLock(function (): void {
            (new ReadModelBuilder(
                $this->repositoryRoot,
                $this->databasePath,
                new CanonicalRecordRepository($this->repositoryRoot),
                'task_queue',
            ))->rebuild();
            (new ReadModelStaleMarker($this->databasePath))->clear();
        });

        if ($this->enqueueOfflineSnapshotPublication !== null) {
            try {
                ($this->enqueueOfflineSnapshotPublication)();
            } catch (\Throwable) {
                error_log('Offline snapshot publication enqueue failed after a queued read-model rebuild.');
            }
        }
    }
}
