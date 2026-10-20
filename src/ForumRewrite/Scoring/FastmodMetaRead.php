<?php

declare(strict_types=1);
namespace ForumRewrite\Scoring;

use PDO;

/** Request-local public tag join; the private database is never attached to user SQL. */
final class FastmodMetaRead
{
    public static function forApplication(PDO $read, string $root, ?string $repositoryRoot = null): PDO
    {
        $private=\ForumRewrite\Support\PrivateConfig::load($root);
        $path=FastScoreDatabaseConfig::path($root,$private);
        return self::decorate($read,$path,FastmodTaggingPolicy::enabled($root,$repositoryRoot));
    }

    public static function activeIds(string $path, bool $required=false): array
    {
        if (!file_exists($path)) {
            if ($required) error_log('Fastmod meta state is not initialized. Run ./v3 fast-score meta-status; restore private state if this instance previously had meta tags.');
            return [];
        }
        $private=new PDO('sqlite:file:'.$path.'?mode=ro');
        $private->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $exists=$private->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='fastmod_meta'")->fetchColumn();
        if (!$exists) return [];
        return $private->query('SELECT post_id FROM fastmod_meta WHERE active=1 ORDER BY post_id')->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function decorate(PDO $read, string $path, bool $required=false): PDO
    {
        $ids=self::activeIds($path,$required);
        if ($ids === []) return $read;
        $columns=$read->query('PRAGMA main.table_info(threads)')->fetchAll(PDO::FETCH_ASSOC);
        if ($columns === []) return $read;
        $read->exec('CREATE TEMP TABLE fastmod_public_meta(post_id TEXT PRIMARY KEY)');
        $q=$read->prepare('INSERT INTO temp.fastmod_public_meta VALUES(?)');
        foreach($ids as $id) $q->execute([$id]);
        $fields=[];
        foreach($columns as $column) {
            $name='"'.str_replace('"','""',$column['name']).'"';
            $fields[]=$column['name'] === 'thread_labels_json'
                ? "CASE WHEN m.post_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM json_each(t.thread_labels_json) WHERE value='meta') THEN json_insert(t.thread_labels_json,'$[#]','meta') ELSE t.thread_labels_json END AS thread_labels_json"
                : 't.'.$name;
        }
        $read->exec('CREATE TEMP VIEW threads AS SELECT '.implode(',',$fields).' FROM main.threads t LEFT JOIN temp.fastmod_public_meta m ON m.post_id=t.root_post_id');
        return $read;
    }
}
