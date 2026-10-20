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
        $allowed=false;
        $runtime=new \ForumRewrite\Scoring\FastmodMetaRuntime($meta,$scores,$config,true,null,function () use (&$allowed): bool {return $allowed;});
        $work=['post_id'=>'root','content_hash'=>$hash,'rubric_revision'=>'r'];
        $runtime->retain($work+['backfill_batch_id'=>1],['status'=>'scored','scores'=>['meta'=>1]]);
        assertSame(0,$runtime->recover($read,'r'));
        $runtime->retain($work,['status'=>'scored','scores'=>['meta'=>0.95]]);
        assertSame(0,$runtime->recover($read,'r'));$allowed=true;
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

    public function testCliRemoveAndRollbackPreserveSuppression(): void
    {
        $dir=sys_get_temp_dir().'/meta-cli-'.bin2hex(random_bytes(5));mkdir($dir);
        try {
            $path=$dir.'/scores.sqlite3';$m=new FastmodMetaStore(new PDO('sqlite:'.$path));
            $m->recordEligibleRoot('root','h','r','m',0.9);$m->apply(['post_id'=>'root','content_hash'=>'h','rubric_revision'=>'r'],['status'=>'scored','scores'=>['meta'=>1]],'m');
            file_put_contents($dir.'/secrets.php','<?php return '.var_export(['FAST_SCORING_DATABASE_PATH'=>$path,'TASK_QUEUE_DATABASE_PATH'=>$dir.'/tasks.sqlite3'],true).';');
            $run=static function(string $args) use ($dir): array {
                $cmd='FORUM_PUBLIC_ARTIFACT_ROOT='.escapeshellarg($dir.'/public').' FORUM_STATIC_HTML_ROOT='.escapeshellarg($dir.'/static').' FORUM_TASK_QUEUE_DATABASE_PATH='.escapeshellarg($dir.'/tasks.sqlite3').' FORUM_SECRETS_PATH='.escapeshellarg($dir.'/secrets.php').' php '.escapeshellarg(dirname(__DIR__).'/scripts/fast_score.php').' '.$args;
                exec($cmd,$output,$exit);assertSame(0,$exit);return json_decode(implode("\n",$output),true,512,JSON_THROW_ON_ERROR);
            };
            assertSame(1,(int)$run('meta-status')['items'][0]['active']);
            assertSame(1,(int)$run('meta-remove --post-id=root')['items'][0]['suppressed']);
            assertSame(1,(int)$run('meta-rollback')['items'][0]['suppressed']);
        } finally {foreach(glob($dir.'/*') as $f) { if(is_file($f))unlink($f); } rmdir($dir);}
    }

    public function testArtifactGuardRejectsConcurrentCorrectionAndOldRelease(): void
    {
        $dir=sys_get_temp_dir().'/meta-publication-'.bin2hex(random_bytes(5));mkdir($dir);
        try {
            $path=$dir.'/private.sqlite3';$m=new FastmodMetaStore(new PDO('sqlite:'.$path));
            $m->recordEligibleRoot('root','h','r','m',0.9);
            $token=['path'=>$path,'generation'=>\ForumRewrite\Scoring\FastmodMetaPublication::generation($path)];
            $m->apply(['post_id'=>'root','content_hash'=>'h','rubric_revision'=>'r'],['status'=>'scored','scores'=>['meta'=>1]],'m');
            try { \ForumRewrite\Scoring\FastmodMetaPublication::publish($token,$dir.'/artifact',static fn()=>file_put_contents($dir.'/artifact','stale'));throw new LogicException('Stale build accepted'); }
            catch(RuntimeException $e) {assertStringContains('changed during',$e->getMessage());}
            assertSame(false,is_file($dir.'/artifact'));
            $token=['path'=>$path,'generation'=>\ForumRewrite\Scoring\FastmodMetaPublication::generation($path)];
            \ForumRewrite\Scoring\FastmodMetaPublication::publish($token,$dir.'/artifact',static fn()=>file_put_contents($dir.'/artifact','current'));
            \ForumRewrite\Scoring\FastmodMetaPublication::assertArtifact($token,$dir.'/artifact');
            $m->removeAndSuppress('root');
            $new=['path'=>$path,'generation'=>\ForumRewrite\Scoring\FastmodMetaPublication::generation($path)];
            try {\ForumRewrite\Scoring\FastmodMetaPublication::assertArtifact($new,$dir.'/artifact');throw new LogicException('Old release accepted');}
            catch(RuntimeException $e){assertStringContains('older Fastmod',$e->getMessage());}
        } finally {foreach(glob($dir.'/*') as $f)unlink($f);rmdir($dir);}
    }

    public function testScoreAndApplicationEvidenceCommitTogether(): void
    {
        $pdo=new PDO('sqlite::memory:');$scores=new SqliteFastScoreStore($pdo);$meta=new FastmodMetaStore($pdo);
        $meta->recordEligibleRoot('root','h','r','m',0.9);$scores->enqueueWork('root','h','r');
        $work=$scores->claimPendingWork(1)[0];$result=['status'=>'scored','source'=>'llm','scores'=>['meta'=>0.95]];
        try {$scores->completeWork($work,$result,static function(PDO $p): void {throw new RuntimeException('simulated interruption');});throw new LogicException('Failure accepted');}
        catch(RuntimeException $e){assertSame('simulated interruption',$e->getMessage());}
        assertSame(null,$scores->find('root','h','r'));assertSame(null,$meta->find('root')['evidence_json']);
        $scores->completeWork($work,$result,static fn(PDO $p)=>(new FastmodMetaStore($p))->retainOrdinaryResult($work,$result,'m'));
        assertSame('scored',$scores->find('root','h','r')['status']);
        assertSame(0.95,json_decode($meta->find('root')['evidence_json'],true)['scores']['meta']);
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
