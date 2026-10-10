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
        private readonly ?\Closure $progressReporter = null,
    ) {
    }

    public function promote(string $candidatePath): void
    {
        $liveDirectory = dirname($this->liveDatabasePath);
        if (dirname($candidatePath) !== $liveDirectory || !is_file($candidatePath)) {
            throw new RuntimeException('Read-model candidate must be an existing file beside the live database.');
        }

        $this->reportProgress('Waiting for the exclusive read-model lock...');
        (new ExecutionLock($liveDirectory . '/forum-rewrite.lock'))->withExclusiveLock(function () use ($candidatePath): void {
            $this->promoteWhileLocked($candidatePath);
        });
    }

    /** Caller must already hold the exclusive forum-rewrite.lock for the live database. */
    public function promoteWhileLocked(string $candidatePath): void
    {
        if (dirname($candidatePath) !== dirname($this->liveDatabasePath) || !is_file($candidatePath)) {
            throw new RuntimeException('Read-model candidate must be an existing file beside the live database.');
        }
        $this->reportProgress('Validating candidate before promotion...');
        ReadModelCandidateBuilder::assertValid($this->repositoryRoot, $candidatePath);
        $this->reportProgress('Replacing the live read model...');
        $this->assertNoSqliteSidecars();
        if (!rename($candidatePath, $this->liveDatabasePath)) {
            throw new RuntimeException('Unable to promote read-model candidate.');
        }

        (new ReadModelStaleMarker($this->liveDatabasePath))->clear();
        $this->reportProgress('Read-model promotion complete.');
    }

    private function reportProgress(string $message): void
    {
        if ($this->progressReporter !== null) {
            ($this->progressReporter)($message);
        }
    }

    private function assertNoSqliteSidecars(): void
    {
        foreach (['-journal', '-wal', '-shm'] as $suffix) {
            $sidecarPath = $this->liveDatabasePath . $suffix;
            if (is_file($sidecarPath)) {
                throw new RuntimeException(
                    "A SQLite sidecar prevents safe read-model promotion: {$sidecarPath}\n"
                    . "The live database may have an active or interrupted transaction. Stop processes using it, then back up the database and this sidecar together.\n"
                    . "Open the database with SQLite to recover and run PRAGMA integrity_check before rerunning ./v3 rebuild.\n"
                    . "Do not delete the sidecar manually; SQLite may need it to restore the database consistently."
                );
            }
        }
    }
}
