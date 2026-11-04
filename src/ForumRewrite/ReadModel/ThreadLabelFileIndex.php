<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use PDO;

/**
 * Which `records/thread-labels/` files target each thread, so a one-thread lookup
 * does not parse every label file. Rows cover every parseable file, active or not.
 * Lookups trust the index only when the read model is current for the repository
 * HEAD; otherwise callers fall back to scanning the directory.
 */
final class ThreadLabelFileIndex
{
    /** @param array<string, string> $threadByPath relative path => thread ID */
    public static function write(PDO $pdo, array $threadByPath): void
    {
        $insert = $pdo->prepare('INSERT OR REPLACE INTO thread_label_files (record_path, thread_id) VALUES (:path, :thread)');
        foreach ($threadByPath as $path => $threadId) {
            $insert->execute(['path' => $path, 'thread' => $threadId]);
        }
    }

    /** @param list<string> $paths every label file currently targeting the thread */
    public static function replaceThread(PDO $pdo, string $threadId, array $paths): void
    {
        $pdo->prepare('DELETE FROM thread_label_files WHERE thread_id = :thread')->execute(['thread' => $threadId]);
        self::write($pdo, array_fill_keys($paths, $threadId));
    }

    /** @param list<string> $paths */
    public static function forget(PDO $pdo, array $paths): void
    {
        $delete = $pdo->prepare('DELETE FROM thread_label_files WHERE record_path = :path');
        foreach ($paths as $path) {
            $delete->execute(['path' => $path]);
        }
    }

    /**
     * Label files for a thread, or null when the read model cannot answer: missing,
     * an older schema, or built for a different repository HEAD.
     *
     * @return list<string>|null
     */
    public static function paths(?string $databasePath, string $repositoryRoot, string $threadId): ?array
    {
        if ($databasePath === null || !is_file($databasePath)) {
            return null;
        }
        try {
            $pdo = new PDO('sqlite:' . $databasePath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $metadata = $pdo->query('SELECT key, value FROM metadata')->fetchAll(PDO::FETCH_KEY_PAIR);
            if (!ReadModelMetadata::hasExpectedSchemaIdentity($metadata)
                || ($metadata['repository_head'] ?? null) !== ReadModelMetadata::repositoryHead($repositoryRoot)) {
                return null;
            }
            $select = $pdo->prepare('SELECT record_path FROM thread_label_files WHERE thread_id = :thread ORDER BY record_path');
            $select->execute(['thread' => $threadId]);

            return array_map('strval', $select->fetchAll(PDO::FETCH_COLUMN));
        } catch (\PDOException) {
            return null;
        }
    }
}
