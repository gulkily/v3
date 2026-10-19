<?php

declare(strict_types=1);
namespace ForumRewrite\Retraction;

use ForumRewrite\Llm\ExchangeRetractionState;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\Support\ExecutionLock;
use PDO;
use RuntimeException;

/** Lock order: read-model write lock (if held), then this narrow publication lock.
 * Rendering/provider calls never run under this lock. */
final class PublicationGuard
{
    private static array $held = [];
    public static function lock(string $repositoryRoot): ExecutionLock
    {
        $root = realpath($repositoryRoot) ?: $repositoryRoot;
        $path = is_dir($root . '/.git') ? $root . '/.git/retraction-publication.lock'
            : dirname($root) . '/.retraction-publication-' . hash('sha256', $root) . '.lock';
        return new ExecutionLock($path);
    }

    public static function withLock(string $repositoryRoot, callable $callback): mixed
    {
        $key = realpath($repositoryRoot) ?: $repositoryRoot;
        if (isset(self::$held[$key])) return $callback();
        return self::lock($repositoryRoot)->withExclusiveLock(function () use ($key, $callback): mixed {
            self::$held[$key] = true;
            try { return $callback(); }
            finally { unset(self::$held[$key]); }
        });
    }

    public static function generation(string $repositoryRoot): string
    {
        $ids = ExchangeRetractionState::read($repositoryRoot);
        if ($ids === null) throw new RuntimeException('Cannot validate retraction state for publication.');
        ksort($ids);
        return hash('sha256', json_encode(array_keys($ids), JSON_THROW_ON_ERROR));
    }

    public static function begin(string $databasePath, ?string $repositoryRoot = null): ?array
    {
        $pdo = new PDO('sqlite:file:' . $databasePath . '?mode=ro');
        if ($repositoryRoot === null) {
            try { $repositoryRoot = ReadModelMetadata::readMetadata($pdo)['repository_root'] ?? null; }
            catch (\Throwable) { return null; } // Standalone legacy snapshots have no canonical repository.
        }
        if ($repositoryRoot === null || $repositoryRoot === '') return null;
        $generation = self::generation($repositoryRoot);
        $ids = ExchangeRetractionState::read($repositoryRoot);
        foreach (array_keys($ids ?? []) as $id) {
            $q = $pdo->prepare('SELECT retracted_at FROM posts WHERE post_id=?');
            $q->execute([$id]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if ($row !== false && ($row['retracted_at'] ?? null) === null) throw new RuntimeException('Read model needs retraction refresh before publication.');
        }
        return ['root' => $repositoryRoot, 'generation' => $generation];
    }

    public static function publish(?array $token, string $path, callable $publish): void
    {
        if ($token === null) { $publish(); return; }
        self::withLock($token['root'], function () use ($token, $path, $publish): void {
            if (self::generation($token['root']) !== $token['generation']) throw new RuntimeException('Retraction changed during publication; rebuild and retry.');
            $publish();
            if (file_put_contents($path . '.retraction-generation', $token['generation']) === false) throw new RuntimeException('Cannot record artifact generation.');
        });
    }
}
