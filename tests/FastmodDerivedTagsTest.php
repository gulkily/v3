<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Scoring\FastmodDerivedTags;
use ForumRewrite\Scoring\SqliteFastScoreStore;

final class FastmodDerivedTagsTest
{
    public function testScoresAreDynamicAndLegacyCorrectionsRemainReadOnly(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'derived-tags-');
        try {
            $pdo = new PDO('sqlite:' . $path);
            $store = new SqliteFastScoreStore($pdo);
            $pdo->exec('CREATE TABLE fastmod_meta(post_id TEXT, suppressed INTEGER, active INTEGER)');
            $pdo->exec("INSERT INTO fastmod_meta VALUES('suppressed',1,0),('old-active',0,1)");
            foreach (['low'=>0.89, 'equal'=>0.9, 'high'=>1, 'suppressed'=>1] as $id=>$score) {
                $store->save($id, 'hash', 'r', ['status'=>'scored','source'=>'llm','scores'=>['meta'=>$score]]);
            }
            $store->save('stale', 'hash', 'old', ['status'=>'scored','source'=>'llm','scores'=>['meta'=>1]]);
            $store->save('missing', 'hash', 'r', ['status'=>'scored','source'=>'llm','scores'=>['moderation'=>1]]);
            $read = static fn()=>FastmodDerivedTags::candidates($path, 'r', FastmodDerivedTags::mappings([]));
            $before = $pdo->query('SELECT * FROM fastmod_meta')->fetchAll();
            assertSame(['equal'=>['hash'=>['meta']], 'high'=>['hash'=>['meta']]], $read());
            $store->save('high', 'hash', 'r', ['status'=>'provider_error','source'=>'none','signals'=>[]]);
            assertSame(['equal'=>['hash'=>['meta']]], $read());
            $pdo->exec("UPDATE post_fast_scores SET scores_json='{invalid' WHERE post_id='equal'");
            assertSame([], $read());
            $store->pruneBefore('2100-01-01');
            assertSame([], $read());
            assertSame($before, $pdo->query('SELECT * FROM fastmod_meta')->fetchAll());
        } finally { unlink($path); }
    }

    public function testMissingAndOldStoresNeedNoInitialization(): void
    {
        $path = sys_get_temp_dir() . '/derived-missing-' . bin2hex(random_bytes(6));
        assertSame([], FastmodDerivedTags::candidates($path, 'r', FastmodDerivedTags::mappings([])));
        assertSame(false, file_exists($path));
        try {
            $pdo = new PDO('sqlite:' . $path);
            $pdo->exec('CREATE TABLE post_fast_scores(post_id TEXT)');
            assertSame([], FastmodDerivedTags::candidates($path, 'r', FastmodDerivedTags::mappings([])));
        } finally { unlink($path); }
    }
}
