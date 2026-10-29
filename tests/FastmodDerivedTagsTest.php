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
    public function testLiveOverlayMatchesContentAndRootsAndKeepsPrivateScoresIsolated(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'derived-live-');
        try {
            $store = new SqliteFastScoreStore(new PDO('sqlite:' . $path));
            $read = new PDO('sqlite::memory:');
            $read->exec('CREATE TABLE posts(post_id TEXT,thread_id TEXT,subject TEXT,body TEXT,is_hidden INTEGER,retracted_at TEXT)');
            $read->exec('CREATE TABLE threads(root_post_id TEXT,thread_labels_json TEXT,score_total INTEGER)');
            $hash = new \ForumRewrite\Scoring\FastScoreContextFactory(static fn($id)=>null);
            foreach (['root','human','edited','reply','hidden','retracted'] as $id) {
                $post = ['post_id'=>$id,'subject'=>'Subject','body'=>'Body'];
                $read->prepare('INSERT INTO posts VALUES(?,?,?,?,?,NULL)')->execute([$id,$id==='reply'?'root':$id,'Subject','Body',(int)($id==='hidden')]);
                if ($id !== 'reply') $read->prepare('INSERT INTO threads VALUES(?,?,7)')->execute([$id,$id==='human'?'["meta","human"]':'["human"]']);
                $store->save($id,$hash->contentHashForPost($post),'r',['status'=>'scored','source'=>'llm','scores'=>['meta'=>0.9]]);
            }
            $read->exec("UPDATE posts SET body='Changed' WHERE post_id='edited'");
            $read->exec("UPDATE posts SET retracted_at='2026-10-29' WHERE post_id='retracted'");
            \ForumRewrite\Scoring\FastmodMetaRead::decorate($read,$path,'r',FastmodDerivedTags::mappings([]));
            assertSame('["human","meta"]',$read->query("SELECT thread_labels_json FROM threads WHERE root_post_id='root'")->fetchColumn());
            assertSame('["meta","human"]',$read->query("SELECT thread_labels_json FROM threads WHERE root_post_id='human'")->fetchColumn());
            assertSame('["human"]',$read->query("SELECT thread_labels_json FROM threads WHERE root_post_id='edited'")->fetchColumn());
            assertSame('["human"]',$read->query("SELECT thread_labels_json FROM threads WHERE root_post_id='hidden'")->fetchColumn());
            assertSame('["human"]',$read->query("SELECT thread_labels_json FROM threads WHERE root_post_id='retracted'")->fetchColumn());
            assertSame(2,(int)$read->query("SELECT count(*) FROM threads WHERE EXISTS(SELECT 1 FROM json_each(thread_labels_json) WHERE value='meta')")->fetchColumn());
            assertSame('["human"]',$read->query("SELECT thread_labels_json FROM main.threads WHERE root_post_id='root'")->fetchColumn());
            assertSame(2,count($read->query('PRAGMA database_list')->fetchAll()));
            assertSame(false,$read->query("SELECT name FROM sqlite_master WHERE name='post_fast_scores'")->fetchColumn());
        } finally { unlink($path); }
    }

    public function testPriorSchemaRebuildsBeforeNormalThreadRead(): void
    {
        require_once __DIR__ . '/Support/RebuildHistoryFixture.php';
        $fixture = new RebuildHistoryFixture(0);
        try {
            $path=$fixture->root.'/model.sqlite';
            (new \ForumRewrite\ReadModel\ReadModelBuilder($fixture->repository,$path,new \ForumRewrite\Canonical\CanonicalRecordRepository($fixture->repository)))->rebuild();
            $pdo=new PDO('sqlite:'.$path);
            $pdo->exec("UPDATE metadata SET value='37' WHERE key='schema_version'");
            $pdo->exec("UPDATE posts SET body='stale before startup' WHERE post_id='reply-001'");
            $app=new \ForumRewrite\Application(dirname(__DIR__),$fixture->repository,$path);
            ob_start();
            try {$app->handle('GET','/threads/root-001');$html=ob_get_contents();} finally {ob_end_clean();}
            assertStringContains('Reply body.',$html);
            assertStringNotContains('stale before startup',$html);
            $command='FORUM_STATIC_HTML_ROOT='.escapeshellarg($fixture->root.'/static').' FORUM_REPOSITORY_ROOT='.escapeshellarg($fixture->repository).' '.escapeshellarg(dirname(__DIR__).'/v3').' rebuild '.escapeshellarg($fixture->repository).' '.escapeshellarg($path).' 2>&1';
            exec($command,$output,$exit);
            assertSame(0,$exit,implode("\n",$output));
            assertSame(\ForumRewrite\ReadModel\ReadModelMetadata::SCHEMA_VERSION,(new PDO('sqlite:'.$path))->query("SELECT value FROM metadata WHERE key='schema_version'")->fetchColumn());
        } finally {$fixture->remove();}
    }
    public function testRootMatchingUsesBatchesAndMappingsShareOneOverlay(): void
    {
        $path=tempnam(sys_get_temp_dir(),'derived-bulk-');
        try {
            $private=new PDO('sqlite:'.$path);$store=new SqliteFastScoreStore($private);
            $read=new class('sqlite::memory:') extends PDO {
                public int $rootQueries=0;
                public function prepare(string $query, array $options=[]): PDOStatement|false {
                    if (str_contains($query,'FROM main.posts')) $this->rootQueries++;
                    return parent::prepare($query,$options);
                }
            };
            $read->exec('CREATE TABLE posts(post_id TEXT PRIMARY KEY,thread_id TEXT,subject TEXT,body TEXT,is_hidden INTEGER,retracted_at TEXT)');
            $read->exec('CREATE TABLE threads(root_post_id TEXT PRIMARY KEY,thread_labels_json TEXT)');
            $post=$read->prepare('INSERT INTO posts VALUES(?,?,?,?,0,NULL)');
            $thread=$read->prepare('INSERT INTO threads VALUES(?,?)');
            $hash=new \ForumRewrite\Scoring\FastScoreContextFactory(static fn($id)=>null);
            $private->beginTransaction();
            for($i=0;$i<1001;$i++) {
                $id='root-'.$i;$post->execute([$id,$id,'Subject','Body']);$thread->execute([$id,'["human"]']);
                $store->save($id,$hash->contentHashForPost(['post_id'=>$id,'subject'=>'Subject','body'=>'Body']),'r',['status'=>'scored','source'=>'llm','scores'=>['meta'=>0.95,'moderation'=>0.8]]);
            }
            $private->commit();
            $mappings=FastmodDerivedTags::mappings([])+['test-only'=>['rubric'=>'moderation','threshold'=>0.8]];
            \ForumRewrite\Scoring\FastmodMetaRead::decorate($read,$path,'r',$mappings);
            assertSame(3,$read->rootQueries);
            assertSame(1001,(int)$read->query("SELECT count(*) FROM threads WHERE EXISTS(SELECT 1 FROM json_each(thread_labels_json) WHERE value='meta')")->fetchColumn());
            assertSame('["human","meta","test-only"]',$read->query("SELECT thread_labels_json FROM threads WHERE root_post_id='root-0'")->fetchColumn());
            assertSame(false,$private->query("SELECT name FROM sqlite_master WHERE name='fastmod_meta'")->fetchColumn());
        } finally {unlink($path);}
    }

    public function testInvalidRubricConfigurationKeepsHumanReadsAvailable(): void
    {
        $root=sys_get_temp_dir().'/derived-invalid-'.bin2hex(random_bytes(5));
        mkdir($root.'/state/private',0700,true);
        $previous=getenv('FASTMOD_AUTOMATIC_TAGGING_ENABLED');$scoring=getenv('FAST_SCORING_ENABLED');
        try {
            file_put_contents($root.'/state/private/fastmod_rubrics.json','invalid');
            putenv('FASTMOD_AUTOMATIC_TAGGING_ENABLED=true');putenv('FAST_SCORING_ENABLED=true');
            $read=new PDO('sqlite::memory:');
            $read->exec('CREATE TABLE threads(thread_labels_json TEXT)');
            $read->prepare('INSERT INTO threads VALUES(?)')->execute(['["human"]']);
            assertSame($read,\ForumRewrite\Scoring\FastmodMetaRead::forApplication($read,$root,$root.'/repository'));
            assertSame('["human"]',$read->query('SELECT thread_labels_json FROM threads')->fetchColumn());
        } finally {
            putenv($previous===false?'FASTMOD_AUTOMATIC_TAGGING_ENABLED':'FASTMOD_AUTOMATIC_TAGGING_ENABLED='.$previous);
            putenv($scoring===false?'FAST_SCORING_ENABLED':'FAST_SCORING_ENABLED='.$scoring);
            unlink($root.'/state/private/fastmod_rubrics.json');rmdir($root.'/state/private');rmdir($root.'/state');rmdir($root);
        }
    }
}
