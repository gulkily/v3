<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

use PDO;

/** Read-only score-to-label mappings. Only public labels leave this boundary. */
final class FastmodDerivedTags
{
    public static function mappings(array $private): array
    {
        return ['meta' => ['rubric' => 'meta', 'threshold' => FastmodMetaConfig::threshold($private)]];
    }

    /** @return array<string,array<string,list<string>>> Post ID => content hash => labels. */
    public static function candidates(string $path, string $revision, array $mappings): array
    {
        if (!is_file($path) || $mappings === []) return [];
        $private = new PDO('sqlite:file:' . $path . '?mode=ro');
        $private->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $columns = $private->query('PRAGMA table_info(post_fast_scores)')->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('scores_json', $columns, true)) return [];
        $hasLegacy = $private->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='fastmod_meta'")->fetchColumn();
        $suppressed = $hasLegacy
            ? array_fill_keys($private->query('SELECT post_id FROM fastmod_meta WHERE suppressed=1')->fetchAll(PDO::FETCH_COLUMN), true)
            : [];
        $query = $private->prepare("SELECT post_id, content_hash, scores_json FROM post_fast_scores WHERE rubric_revision=? AND status='scored'");
        $query->execute([$revision]);
        $tags = [];
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $scores = json_decode($row['scores_json'] ?? '', true);
            if (!is_array($scores)) continue;
            foreach ($mappings as $tag => $mapping) {
                if ($tag === 'meta' && isset($suppressed[$row['post_id']])) continue;
                $score = $scores[$mapping['rubric']] ?? null;
                if ((!is_int($score) && !is_float($score)) || !is_finite((float)$score)
                    || $score < 0 || $score > 1 || $score < $mapping['threshold']) continue;
                $tags[$row['post_id']][$row['content_hash']][] = $tag;
            }
        }
        return $tags;
    }
}
