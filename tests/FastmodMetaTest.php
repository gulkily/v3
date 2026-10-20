<?php

declare(strict_types=1);
require_once __DIR__.'/../autoload.php';
use ForumRewrite\Scoring\FastmodMetaStore;
use ForumRewrite\Scoring\SqliteFastScoreStore;

final class FastmodMetaTest
{
    public function testThresholdDuplicateSuppressionAndPruning(): void
    {
        $pdo=new PDO('sqlite::memory:'); $scores=new SqliteFastScoreStore($pdo); $meta=new FastmodMetaStore($pdo);
        foreach (['below'=>0.89,'equal'=>0.9,'above'=>0.99] as $id=>$p) {
            $meta->recordEligibleRoot($id,'hash','revision','model',0.9);
            $work=['post_id'=>$id,'content_hash'=>'hash','rubric_revision'=>'revision'];
            assertSame($id!=='below',$meta->apply($work,['status'=>'scored','scores'=>['meta'=>$p]],'model'));
            assertSame(false,$meta->apply($work,['status'=>'scored','scores'=>['meta'=>1]],'model'));
        }
        $meta->removeAndSuppress('equal');$meta->removeAndSuppress('equal');
        $meta->recordEligibleRoot('equal','new','new','model',0);
        assertSame(1,(int)$meta->find('equal')['suppressed']);
        $scores->pruneBefore('2100-01-01T00:00:00Z');
        assertSame(1,(int)$meta->find('above')['active']);
        $meta->rollback();$meta->rollback();
        assertSame(0,(int)$meta->find('above')['active']);
        assertSame(true,$meta->dirty()); $meta->acknowledge($meta->generation());assertSame(false,$meta->dirty());
    }
    public function testRuntimeRecoversOrdinaryEvidenceWithoutProviderOrScoreRows(): void
    {
        $read=new PDO('sqlite::memory:');
        $read->exec("CREATE TABLE posts(post_id TEXT,thread_id TEXT,parent_id TEXT,subject TEXT,body TEXT,is_hidden INTEGER,retracted_at TEXT); INSERT INTO posts VALUES('root','root',NULL,'Support','Help',0,NULL)");
        $private=new PDO('sqlite::memory:');$scores=new SqliteFastScoreStore($private);$meta=new FastmodMetaStore($private);
        $config=new \ForumRewrite\Scoring\FastScoringConfig(true,new \ForumRewrite\Llm\LlmProviderConfig('stub','','','test-model',1,''),'');
        $post=$read->query('SELECT * FROM posts')->fetch(PDO::FETCH_ASSOC);
        $hash=(new \ForumRewrite\Scoring\FastScoreContextFactory(static fn($id)=>null))->contentHashForPost($post);
        $meta->recordEligibleRoot('root',$hash,'r',\ForumRewrite\Scoring\FastmodMetaConfig::identity($config),0.9);
        $runtime=new \ForumRewrite\Scoring\FastmodMetaRuntime($meta,$scores,$config,true);
        $work=['post_id'=>'root','content_hash'=>$hash,'rubric_revision'=>'r'];
        $runtime->retain($work+['backfill_batch_id'=>1],['status'=>'scored','scores'=>['meta'=>1]]);
        assertSame(0,$runtime->recover($read,'r'));
        $runtime->retain($work,['status'=>'scored','scores'=>['meta'=>0.95]]);
        assertSame(1,$runtime->recover($read,'r'));assertSame(0,$runtime->recover($read,'r'));
        $meta->removeAndSuppress('root');assertSame(0,$runtime->recover($read,'r'));
    }

    public function testLiveJoinPreservesHumanTagsAndDoesNotExposePrivateTables(): void
    {
        $path=tempnam(sys_get_temp_dir(),'meta-read-');
        try {
            $m=new FastmodMetaStore(new PDO('sqlite:'.$path));
            $m->recordEligibleRoot('root','h','r','m',0.9);$m->apply(['post_id'=>'root','content_hash'=>'h','rubric_revision'=>'r'],['status'=>'scored','scores'=>['meta'=>1]],'m');
            $read=new PDO('sqlite::memory:');
            $read->exec("CREATE TABLE threads(root_post_id TEXT PRIMARY KEY,thread_labels_json TEXT,score_total INTEGER); INSERT INTO threads VALUES('root','[\"human\"]',7),('other','[\"meta\"]',3)");
            \ForumRewrite\Scoring\FastmodMetaRead::decorate($read,$path);
            assertSame(['human','meta'],json_decode($read->query("SELECT thread_labels_json FROM threads WHERE root_post_id='root'")->fetchColumn(),true));
            assertSame('["human"]',$read->query("SELECT thread_labels_json FROM main.threads WHERE root_post_id='root'")->fetchColumn());
            assertSame(7,(int)$read->query("SELECT score_total FROM threads WHERE root_post_id='root'")->fetchColumn());
            assertSame(false,$read->query("SELECT name FROM sqlite_master WHERE name='fastmod_meta'")->fetchColumn());
            assertSame(2,count($read->query('PRAGMA database_list')->fetchAll()));
            $m->removeAndSuppress('root');
            assertSame([],\ForumRewrite\Scoring\FastmodMetaRead::activeIds($path));
        } finally {unlink($path);}
    }

    public function testIneligibleEvidenceCannotCreateTags(): void
    {
        $m=new FastmodMetaStore(new PDO('sqlite::memory:'));$m->recordEligibleRoot('root','h','r','m',0.9);
        $w=['post_id'=>'root','content_hash'=>'h','rubric_revision'=>'r'];
        foreach ([['status'=>'provider_error'],['status'=>'scored','scores'=>['moderation'=>1]]] as $r) assertSame(false,$m->apply($w,$r,'m'));
        $r=['status'=>'scored','scores'=>['meta'=>1]];
        assertSame(false,$m->apply($w,$r,'other'));
        assertSame(false,$m->apply($w+['backfill_batch_id'=>1],$r,'m'));
        assertSame(false,$m->apply(array_replace($w,['content_hash'=>'new']),$r,'m'));
        assertSame(0,$m->generation());
    }
}
