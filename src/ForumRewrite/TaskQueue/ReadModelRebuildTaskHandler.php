<?php

declare(strict_types=1);

namespace ForumRewrite\TaskQueue;

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelStaleMarker;
use ForumRewrite\Support\ExecutionLock;

final class ReadModelRebuildTaskHandler
{
    public function __construct(
        private readonly string $repositoryRoot,
        private readonly string $databasePath,
    ) {
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
    }
}
