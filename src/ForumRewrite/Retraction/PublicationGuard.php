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
        $removals = \ForumRewrite\Canonical\LabelRemoval::all($repositoryRoot);
        return hash('sha256', json_encode($removals === [] ? array_keys($ids) : [array_keys($ids), $removals], JSON_THROW_ON_ERROR));
    }

    /** Snapshot-only validation: pin absent targets without granting removal authority. */
    public static function snapshotGeneration(string $root, array $excluded): string
    {
        $state = ExchangeRetractionState::snapshot($root, $excluded);
        if ($state === null || array_diff_key($excluded, $state['absent']) !== []) {
            throw new RuntimeException('Cannot validate snapshot retractions; inspect canonical evidence and retry.');
        }
        return hash('sha256', json_encode([$state, \ForumRewrite\Canonical\LabelRemoval::all($root)], JSON_THROW_ON_ERROR));
    }

    public static function validate(array $token): void
    {
        $current = isset($token['excluded'])
            ? self::snapshotGeneration($token['root'], $token['excluded']) : self::generation($token['root']);
        if ($current !== ($token['snapshot_generation'] ?? $token['generation'])) throw new RuntimeException('Retraction changed during publication; rebuild and retry.');
    }

    public static function beginSnapshot(string $databasePath): ?array
    {
        return self::beginContext($databasePath, null, true);
    }

    public static function begin(string $databasePath, ?string $repositoryRoot = null): ?array
    {
        return self::beginContext($databasePath, $repositoryRoot, false);
    }

    private static function beginContext(string $databasePath, ?string $repositoryRoot, bool $snapshot): ?array
    {
        $pdo = new PDO('sqlite:file:' . $databasePath . '?mode=ro');
        if ($repositoryRoot === null) {
            try { $repositoryRoot = ReadModelMetadata::readMetadata($pdo)['repository_root'] ?? null; }
            catch (\Throwable) { return null; } // Standalone legacy snapshots have no canonical repository.
        }
        if ($repositoryRoot === null || $repositoryRoot === '') return null;
        $state = $snapshot ? ExchangeRetractionState::snapshot($repositoryRoot) : null;
        if ($snapshot && $state === null) throw new RuntimeException('Cannot validate snapshot retractions; inspect canonical evidence and retry.');
        $generation = $snapshot ? hash('sha256', json_encode([$state, \ForumRewrite\Canonical\LabelRemoval::all($repositoryRoot)], JSON_THROW_ON_ERROR)) : self::generation($repositoryRoot);
        $removals = \ForumRewrite\Canonical\LabelRemoval::all($repositoryRoot);
        if ($removals !== [] && (ReadModelMetadata::readMetadata($pdo)['source_removal_generation'] ?? null) !== \ForumRewrite\Canonical\LabelRemoval::generation($repositoryRoot)) {
            throw new RuntimeException('Read model needs source-removal refresh before publication.');
        }
        $ids = $snapshot ? $state['ids'] : ExchangeRetractionState::read($repositoryRoot);
        foreach (array_keys($ids ?? []) as $id) {
            $q = $pdo->prepare('SELECT retracted_at FROM posts WHERE post_id=?');
            $q->execute([$id]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if ($row !== false && ($row['retracted_at'] ?? null) === null) throw new RuntimeException('Read model needs retraction refresh before publication.');
        }
        $context = $snapshot ? ['excluded' => $state['absent'], 'snapshot_generation' => $generation] : [];
        if ($snapshot && $state['absent'] === []) {
            // Ordinary artifacts must remain current to strict cleanup/static consumers.
            $generation = hash('sha256', json_encode($removals === [] ? array_keys($state['ids']) : [array_keys($state['ids']), $removals], JSON_THROW_ON_ERROR));
        }
        return ['root' => $repositoryRoot, 'generation' => $generation] + $context;
    }

    public static function publish(?array $token, string $path, callable $publish): void
    {
        if ($token === null) { $publish(); return; }
        self::withLock($token['root'], function () use ($token, $path, $publish): void {
            self::validate($token);
            $publish();
            if (file_put_contents($path . '.retraction-generation', $token['generation']) === false) throw new RuntimeException('Cannot record artifact generation.');
        });
    }
}
