<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use ForumRewrite\Support\ExecutionLock;
use RuntimeException;

final class ReadModelCandidatePromoter
{
    public function __construct(
        private readonly string $repositoryRoot,
        private readonly string $liveDatabasePath,
    ) {
    }

    public function promote(string $candidatePath): void
    {
        $liveDirectory = dirname($this->liveDatabasePath);
        if (dirname($candidatePath) !== $liveDirectory || !is_file($candidatePath)) {
            throw new RuntimeException('Read-model candidate must be an existing file beside the live database.');
        }

        (new ExecutionLock($liveDirectory . '/forum-rewrite.lock'))->withExclusiveLock(function () use ($candidatePath): void {
            ReadModelCandidateBuilder::assertValid($this->repositoryRoot, $candidatePath);
            $this->assertNoSqliteSidecars();
            if (!rename($candidatePath, $this->liveDatabasePath)) {
                throw new RuntimeException('Unable to promote read-model candidate.');
            }

            (new ReadModelStaleMarker($this->liveDatabasePath))->clear();
        });
    }

    private function assertNoSqliteSidecars(): void
    {
        foreach (['-journal', '-wal', '-shm'] as $suffix) {
            if (is_file($this->liveDatabasePath . $suffix)) {
                throw new RuntimeException('Cannot promote while live SQLite sidecar exists: ' . $this->liveDatabasePath . $suffix);
            }
        }
    }
}
