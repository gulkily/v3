<?php

declare(strict_types=1);
namespace ForumRewrite\Scoring;

use PDO;

/** Request-local effective labels; private scores never enter the public connection. */
final class FastmodMetaRead
{
    public static function forApplication(PDO $read, string $root, ?string $repositoryRoot = null): PDO
    {
        if (!FastmodTaggingPolicy::enabled($root, $repositoryRoot)) return $read;
        try {
            $private = \ForumRewrite\Support\PrivateConfig::load($root);
            $config = FastScoringConfig::fromPrivateConfig($private);
            return self::decorate($read, FastScoreDatabaseConfig::path($root, $private),
                FastScoringRubricRevision::fromConfig($config, $root), FastmodDerivedTags::mappings($private));
        } catch (\PDOException | \InvalidArgumentException $error) {
            error_log('Fastmod derived tags unavailable; check private scores/configuration: ' . $error->getMessage());
            return $read;
        }
    }

    public static function decorate(PDO $read, string $path, string $revision, array $mappings): PDO
    {
        $candidates = FastmodDerivedTags::candidates($path, $revision, $mappings);
        if ($candidates === []) return $read;
        $columns = $read->query('PRAGMA main.table_info(threads)')->fetchAll(PDO::FETCH_ASSOC);
        if ($columns === []) return $read;
        $labels = [];
        $hasher = new FastScoreContextFactory(static fn(string $id) => null);
        foreach (array_chunk(array_keys($candidates), 500) as $ids) {
            $query = $read->prepare('SELECT p.post_id,p.subject,p.body,t.thread_labels_json FROM main.posts p
                JOIN main.threads t ON t.root_post_id=p.post_id
                WHERE p.post_id=p.thread_id AND p.is_hidden=0 AND p.retracted_at IS NULL
                AND p.post_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
            $query->execute($ids);
            while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
                $derived = $candidates[$post['post_id']][$hasher->contentHashForPost($post)] ?? [];
                if ($derived === []) continue;
                $human = json_decode($post['thread_labels_json'], true, 512, JSON_THROW_ON_ERROR);
                $labels[$post['post_id']] = json_encode(array_values(array_unique(array_merge($human, $derived))), JSON_THROW_ON_ERROR);
            }
        }
        if ($labels === []) return $read;
        $read->exec('CREATE TEMP TABLE fastmod_public_tags(post_id TEXT PRIMARY KEY, labels_json TEXT NOT NULL)');
        $insert = $read->prepare('INSERT INTO temp.fastmod_public_tags VALUES(?,?)');
        foreach ($labels as $id => $json) $insert->execute([$id, $json]);
        $fields = [];
        foreach ($columns as $column) {
            $name = '"' . str_replace('"', '""', $column['name']) . '"';
            $fields[] = $column['name'] === 'thread_labels_json'
                ? 'COALESCE(m.labels_json,t.thread_labels_json) AS thread_labels_json' : 't.' . $name;
        }
        $read->exec('CREATE TEMP VIEW threads AS SELECT ' . implode(',', $fields) . '
            FROM main.threads t LEFT JOIN temp.fastmod_public_tags m ON m.post_id=t.root_post_id');
        return $read;
    }
}
