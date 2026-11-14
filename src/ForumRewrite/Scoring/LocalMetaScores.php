<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

use PDO;

/**
 * Local meta scores, in their own table of the private score database.
 * Kept apart from `post_fast_scores`, so LLM scores, rubric revisions and score transfer never see them.
 */
final class LocalMetaScores
{
    public const RUBRIC = 'meta';

    public function __construct(private readonly PDO $pdo, bool $create = true)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($create) $this->pdo->exec('CREATE TABLE IF NOT EXISTS local_rubric_scores (post_id TEXT NOT NULL, content_hash TEXT NOT NULL, rubric TEXT NOT NULL,
            model_build TEXT NOT NULL, probability REAL NOT NULL, top_words_json TEXT NOT NULL, signals_json TEXT NOT NULL, scored_at TEXT NOT NULL,
            PRIMARY KEY (post_id, content_hash, rubric, model_build))');
    }

    /** Opens the score database read-only; null when it or the table doesn't exist yet. Never writes. */
    public static function readOnly(string $path): ?self
    {
        if (!is_file($path)) return null;
        $pdo = new PDO('sqlite:file:' . $path . '?mode=ro');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $exists = $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'local_rubric_scores'")->fetchColumn();
        return $exists === false ? null : new self($pdo, false);
    }

    /** @param array{probability:float, top_words:list<string>, signals:list<string>} $score */
    public function save(string $postId, string $contentHash, string $build, array $score, ?string $scoredAt = null): void
    {
        $this->pdo->prepare('INSERT OR REPLACE INTO local_rubric_scores VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([
            $postId, $contentHash, self::RUBRIC, $build, $score['probability'],
            json_encode(array_values($score['top_words']), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            json_encode(array_values($score['signals']), JSON_THROW_ON_ERROR), $scoredAt ?? gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    }

    /**
     * Runs many saves in one transaction, so a full sweep doesn't sync the disk once per post.
     *
     * @template T
     * @param callable():T $work
     * @return T
     */
    public function transaction(callable $work): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $work();
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    /** @return array<string,true> "post_id content_hash" keys already scored by this build. */
    public function scoredKeys(string $build): array
    {
        $stmt = $this->pdo->prepare('SELECT post_id, content_hash FROM local_rubric_scores WHERE rubric = ? AND model_build = ?');
        $stmt->execute([self::RUBRIC, $build]);
        $keys = [];
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) $keys[$row[0] . ' ' . $row[1]] = true;
        return $keys;
    }

    /** @return array{probability:float, top_words:list<string>, signals:list<string>, model_build:string, scored_at:string}|null newest score for this exact content */
    public function latestForPostContent(string $postId, string $contentHash): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM local_rubric_scores WHERE post_id = ? AND content_hash = ? AND rubric = ? ORDER BY scored_at DESC, rowid DESC LIMIT 1');
        $stmt->execute([$postId, $contentHash, self::RUBRIC]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) return null;
        return ['probability' => (float) $row['probability'], 'top_words' => json_decode($row['top_words_json'], true) ?: [],
            'signals' => json_decode($row['signals_json'], true) ?: [], 'model_build' => (string) $row['model_build'], 'scored_at' => (string) $row['scored_at']];
    }

    public function countForBuild(string $build): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM local_rubric_scores WHERE rubric = ? AND model_build = ?');
        $stmt->execute([self::RUBRIC, $build]);
        return (int) $stmt->fetchColumn();
    }
}
