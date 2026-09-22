<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use ForumRewrite\Canonical\CanonicalRecordRepository;
use PDO;
use RuntimeException;

final class ReadModelCandidateBuilder
{
    public function __construct(
        private readonly string $repositoryRoot,
        private readonly string $liveDatabasePath,
        private readonly string $rebuildReason = 'manual',
    ) {
    }

    public function build(): string
    {
        $directory = dirname($this->liveDatabasePath);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create read-model candidate directory: ' . $directory);
        }

        $candidatePath = $directory . '/.' . basename($this->liveDatabasePath)
            . '.candidate-' . bin2hex(random_bytes(8)) . '.sqlite3';

        try {
            (new ReadModelBuilder(
                $this->repositoryRoot,
                $candidatePath,
                new CanonicalRecordRepository($this->repositoryRoot),
                $this->rebuildReason,
            ))->rebuild();
            self::assertValid($this->repositoryRoot, $candidatePath);

            return $candidatePath;
        } catch (\Throwable $throwable) {
            @unlink($candidatePath);
            throw $throwable;
        }
    }

    public static function assertValid(string $repositoryRoot, string $candidatePath): void
    {
        $pdo = (new ReadModelConnection($candidatePath))->open();
        $metadata = $pdo->query('SELECT key, value FROM metadata')->fetchAll(PDO::FETCH_KEY_PAIR);
        $expectedHead = ReadModelMetadata::repositoryHead($repositoryRoot);
        if (($metadata['schema_version'] ?? null) !== ReadModelMetadata::SCHEMA_VERSION
            || ($metadata['repository_root'] ?? null) !== $repositoryRoot
            || ($metadata['repository_head'] ?? null) !== $expectedHead) {
            throw new RuntimeException('Read-model candidate metadata does not match the current repository.');
        }

        $integrity = $pdo->query('PRAGMA quick_check')->fetchColumn();
        if ($integrity !== 'ok') {
            throw new RuntimeException('Read-model candidate failed SQLite integrity validation.');
        }
    }
}
