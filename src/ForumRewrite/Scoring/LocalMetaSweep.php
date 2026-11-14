<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

use ForumRewrite\TaskQueue\SqliteTaskQueueStore;
use ForumRewrite\TaskQueue\TaskQueueDatabaseConfig;
use PDO;

/** Scores every visible thread root that has no score from the current model for its current text. */
final class LocalMetaSweep
{
    public const DEDUPLICATION_KEY = 'local-score-sweep';

    public function __construct(
        private readonly PDO $read,
        private readonly LocalMetaScores $scores,
        private readonly LocalMetaModel $model,
    ) {}

    /** @return array{scored:int, skipped:int, remaining:bool, model_build:string} */
    public function run(int $limit = 5000): array
    {
        return $this->scores->transaction(fn (): array => $this->sweep($limit));
    }

    /** @return array{scored:int, skipped:int, remaining:bool, model_build:string} */
    private function sweep(int $limit): array
    {
        $build = $this->model->buildId();
        $done = $this->scores->scoredKeys($build);
        $hasher = new FastScoreContextFactory(static fn (string $_): ?array => null);
        $rows = $this->read->query('SELECT post_id, subject, body FROM posts
            WHERE post_id = thread_id AND is_hidden = 0 AND retracted_at IS NULL ORDER BY post_id');
        $scored = 0;
        $skipped = 0;
        $remaining = false;
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            $hash = $hasher->contentHashForPost($row);
            if (isset($done[$row['post_id'] . ' ' . $hash])) {
                $skipped++;
                continue;
            }
            if ($scored >= $limit) {
                $remaining = true;
                break;
            }
            $this->scores->save((string) $row['post_id'], $hash, $build, $this->model->score((string) ($row['subject'] ?? ''), (string) $row['body']));
            $scored++;
        }
        return ['scored' => $scored, 'skipped' => $skipped, 'remaining' => $remaining, 'model_build' => $build];
    }

    /** @return array<string,mixed> the queued (or already queued) task */
    public static function enqueue(string $projectRoot): array
    {
        $queuePath = TaskQueueDatabaseConfig::path($projectRoot);
        $directory = dirname($queuePath);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Task queue directory is not writable.');
        }
        return (new SqliteTaskQueueStore(new PDO('sqlite:' . $queuePath)))->enqueue(SqliteTaskQueueStore::LOCAL_SCORE_SWEEP, self::DEDUPLICATION_KEY);
    }

    /** Builds a sweep from this project's private files, or null when no model has been trained. */
    public static function forProject(string $projectRoot, PDO $read, string $scorePath): ?self
    {
        $model = (new LocalMetaFiles($projectRoot))->loadModel();
        if ($model === null) return null;
        $directory = dirname($scorePath);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Fastmod database directory is not writable.');
        }
        return new self($read, new LocalMetaScores(new PDO('sqlite:' . $scorePath)), $model);
    }
}
