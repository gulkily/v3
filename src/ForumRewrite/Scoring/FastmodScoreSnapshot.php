<?php

declare(strict_types=1);
namespace ForumRewrite\Scoring;

use ForumRewrite\Canonical\CanonicalRecordRepository;
use PDO;
use RuntimeException;

final class FastmodScoreSnapshot
{
    /** Read one SQLite statement snapshot (including WAL), without initializing private state. */
    public static function capture(string $repository, string $path): string
    {
        if (!is_file($path)) return FastmodScoreTransfer::encode([]);
        $pdo = new PDO('sqlite:file:' . str_replace(['%', '?', '#'], ['%25', '%3f', '%23'], $path) . '?mode=ro');
        $columns = $pdo->query('PRAGMA table_info(post_fast_scores)')->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('scores_json', $columns, true)) return FastmodScoreTransfer::encode([]);
        $query = $pdo->query("SELECT post_id, content_hash, rubric_revision, scores_json, created_at, updated_at FROM post_fast_scores WHERE status = 'scored' AND scores_json IS NOT NULL ORDER BY post_id, content_hash, rubric_revision");
        $rows = []; $bytes = 0;
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $bytes += strlen($row['scores_json']) + 512;
            if (count($rows) >= FastmodScoreTransfer::MAX_ROWS || $bytes > FastmodScoreTransfer::MAX_BYTES) throw new RuntimeException('Fastmod scores exceed bounded export capacity.');
            $row['scores'] = json_decode($row['scores_json'], true, 512, JSON_THROW_ON_ERROR);
            unset($row['scores_json']);
            FastmodScoreTransfer::validate($row);
            $rows[] = $row;
        }
        $query->closeCursor();
        if ($rows === []) return FastmodScoreTransfer::encode([]);
        // Only committed post paths are included by the content archive.
        exec('git -C ' . escapeshellarg($repository) . ' ls-tree -r --name-only HEAD -- records/posts', $paths, $status);
        if ($status !== 0) throw new RuntimeException('Cannot enumerate exported score posts.');
        $wanted = array_fill_keys(array_column($rows, 'post_id'), true); $hashes = [];
        $canonical = new CanonicalRecordRepository($repository);
        $factory = new FastScoreContextFactory(static fn (string $id): ?array => null);
        foreach ($paths as $relative) {
            if (!str_ends_with($relative, '.txt') || !isset($wanted[basename($relative, '.txt')])) continue;
            $post = $canonical->loadPost($relative);
            $hashes[$post->postId] = $factory->contentHashForPost(['post_id' => $post->postId, 'subject' => $post->subject, 'body' => $post->body]);
        }
        return FastmodScoreTransfer::encode(array_values(array_filter($rows, static fn (array $row): bool => ($hashes[$row['post_id']] ?? null) === $row['content_hash'])));
    }
}
