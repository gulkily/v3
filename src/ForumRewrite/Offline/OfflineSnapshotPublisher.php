<?php

declare(strict_types=1);

namespace ForumRewrite\Offline;

use RuntimeException;

/**
 * Publishes the bounded public offline snapshot independently of static HTML
 * releases. The underlying builder replaces the target atomically.
 */
final class OfflineSnapshotPublisher
{
    public function __construct(
        private readonly string $staticHtmlRoot,
        private readonly PublicOfflineSnapshotBuilder $builder = new PublicOfflineSnapshotBuilder(),
    ) {
    }

    /**
     * @return array{generated_at:string,thread_count:int,post_count:int,size_bytes:int,path:string}
     */
    public function publish(string $databasePath): array
    {
        $path = $this->snapshotPath();
        $result = $this->builder->build($databasePath, $path);

        return [...$result, 'path' => $path];
    }

    /** @return array{generated_at:string,thread_count:int,post_count:int,public_key_count:int,size_bytes:int,path:string} */
    public function publishUpdate(string $databasePath): array
    {
        $path = $this->updatePath();
        $result = $this->builder->buildUpdate($databasePath, $path);

        return [...$result, 'path' => $path];
    }

    public function snapshotPath(): string
    {
        $root = rtrim($this->staticHtmlRoot, '/');
        if ($root === '') {
            throw new RuntimeException('Offline snapshot publication requires a static HTML root.');
        }

        return $root . '/offline/snapshot.sqlite3';
    }

    public function updatePath(): string
    {
        $root = rtrim($this->staticHtmlRoot, '/');
        if ($root === '') {
            throw new RuntimeException('Offline snapshot publication requires a static HTML root.');
        }

        return $root . '/offline/update.sqlite3';
    }
}
