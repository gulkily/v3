<?php

declare(strict_types=1);
require_once __DIR__.'/../autoload.php';
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry as Registry;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator as Evaluator;

final class FastmodTaggingFlagTest
{
    public function testRuntimeUsesExplicitRepositoryAndFreshSiteDecision(): void
    {
        $root=sys_get_temp_dir().'/tagging-policy-'.bin2hex(random_bytes(5));
        $repo=$root.'/selected-instance';mkdir($repo.'/records/instance',0700,true);
        $env=['FORUM_SECRETS_PATH'=>$root.'/secrets.php','FORUM_REPOSITORY_ROOT'=>$root.'/wrong-instance',
            'FORUM_TASK_QUEUE_DATABASE_PATH'=>$root.'/tasks.sqlite3','FORUM_PUBLIC_ARTIFACT_ROOT'=>$root.'/public'];
        $old=[];foreach($env as $n=>$v){$old[$n]=getenv($n);putenv($n.'='.$v);}
        foreach([Registry::FASTMOD_AUTOMATIC_TAGGING_ENABLED,'FASTMOD_META_TAGGING_ENABLED',Registry::FAST_SCORING_ENABLED] as $n){$old[$n]=getenv($n);putenv($n);}
        try {
            $private=['FAST_SCORING_ENABLED'=>true,'FASTMOD_META_TAGGING_ENABLED'=>false,'FAST_SCORING_DATABASE_PATH'=>$root.'/scores.sqlite3'];
            file_put_contents($root.'/secrets.php','<?php return '.var_export($private,true).';');
            $write=static fn(bool $on)=>file_put_contents($repo.'/records/instance/feature-flags.txt',"Schema: site-feature-flags-v1\n\nFASTMOD_AUTOMATIC_TAGGING_ENABLED: ".($on?'true':'false')."\n");
            $write(true);
            assertSame(true,\ForumRewrite\Scoring\FastmodTaggingPolicy::enabled($root,$repo));
            assertSame(false,\ForumRewrite\Scoring\FastmodTaggingPolicy::enabled($root));
            $runtime=\ForumRewrite\Scoring\FastmodMetaRuntime::open($root,$private,$root.'/static',$repo);
            $config=\ForumRewrite\Scoring\FastScoringConfig::fromPrivateConfig($private);
            $read=new PDO('sqlite::memory:');$read->exec("CREATE TABLE posts(post_id TEXT,thread_id TEXT,parent_id TEXT,subject TEXT,body TEXT,is_hidden INTEGER,retracted_at TEXT); INSERT INTO posts VALUES('root','root',NULL,'Help','Support',0,NULL)");
            $post=$read->query('SELECT * FROM posts')->fetch(PDO::FETCH_ASSOC);
            $hash=(new \ForumRewrite\Scoring\FastScoreContextFactory(static fn($id)=>null))->contentHashForPost($post);
            $runtime->store->recordEligibleRoot('root',$hash,'r',\ForumRewrite\Scoring\FastmodMetaConfig::identity($config),0.9);
            $runtime->retain(['post_id'=>'root','content_hash'=>$hash,'rubric_revision'=>'r'],['status'=>'scored','scores'=>['meta'=>1]]);
            $write(false);assertSame(0,$runtime->recover($read,'r'));
            $write(true);$runtime=\ForumRewrite\Scoring\FastmodMetaRuntime::open($root,$private,$root.'/static',$repo);
            assertSame(1,$runtime->recover($read,'r'));
            $write(false);assertSame(1,(int)$runtime->store->find('root')['active']);
            $runtime->store->removeAndSuppress('root');$runtime->flush();assertSame(1,(int)$runtime->store->find('root')['suppressed']);
            // Worker started off must honor a later site-on for newly eligible publication.
            $runtime=\ForumRewrite\Scoring\FastmodMetaRuntime::open($root,$private,$root.'/static',$repo);
            $write(true);$read->exec("INSERT INTO posts VALUES('later','later',NULL,'Help','Later support',0,NULL)");
            $post['post_id']=$post['thread_id']='later';$post['body']='Later support';
            $hash=(new \ForumRewrite\Scoring\FastScoreContextFactory(static fn($id)=>null))->contentHashForPost($post);
            $runtime->store->recordEligibleRoot('later',$hash,'r',\ForumRewrite\Scoring\FastmodMetaConfig::identity($config),0.9);
            $runtime->retain(['post_id'=>'later','content_hash'=>$hash,'rubric_revision'=>'r'],['status'=>'scored','scores'=>['meta'=>1]]);
            assertSame(1,$runtime->recover($read,'r'));

        } finally {
            foreach($old as $n=>$v)putenv($v===false?$n:$n.'='.$v);
            $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
            foreach($it as $f){if($f->isDir())rmdir($f->getPathname());else unlink($f->getPathname());}rmdir($root);
        }
    }

    public function testSharedPrecedenceAndDependency(): void
    {
        $key=Registry::FASTMOD_AUTOMATIC_TAGGING_ENABLED;
        $names=[$key,'FASTMOD_META_TAGGING_ENABLED',Registry::FAST_SCORING_ENABLED];$old=[];
        foreach($names as $n){$old[$n]=getenv($n);putenv($n);}
        try {
            $base=[Registry::FAST_SCORING_ENABLED=>true];
            assertSame(false,(new Evaluator(privateConfigValues:$base))->isEnabled($key));
            assertSame(true,(new Evaluator(privateConfigValues:$base))->withSiteValues([$key=>true])->isEnabled($key));
            foreach([false,true] as $legacy) {
                $private=$base+['FASTMOD_META_TAGGING_ENABLED'=>$legacy];
                $state=(new Evaluator(privateConfigValues:$private))->evaluate($key);
                assertSame($legacy,$state->effectiveValue);assertSame(true,$state->canChangeFromSite());
                assertSame('legacy-private-config',$state->source);
                foreach([false,true] as $site) {
                    $state=(new Evaluator(siteValues:[$key=>$site],privateConfigValues:$private))->evaluate($key);
                    assertSame($site,$state->effectiveValue);assertSame('site',$state->source);
                }
            }
            putenv('FASTMOD_META_TAGGING_ENABLED=true');
            assertSame('legacy-environment',(new Evaluator(privateConfigValues:$base))->evaluate($key)->source);
            assertSame(false,(new Evaluator(siteValues:[$key=>false],privateConfigValues:$base))->isEnabled($key));
            putenv($key.'=false');
            $state=(new Evaluator(siteValues:[$key=>true],privateConfigValues:$base))->evaluate($key);
            assertSame(false,$state->effectiveValue);assertSame(true,$state->isLocked());assertSame('environment',$state->source);
            putenv($key.'=true');
            assertSame(true,(new Evaluator(siteValues:[$key=>false],privateConfigValues:$base))->isEnabled($key));
            putenv($key);putenv('FASTMOD_META_TAGGING_ENABLED');
            $state=(new Evaluator(siteValues:[$key=>true]))->evaluate($key);
            assertSame(false,$state->effectiveValue);assertSame(true,$state->isBlockedByDependency());
            $state=(new Evaluator(siteError:'invalid record',privateConfigValues:$base+['FASTMOD_META_TAGGING_ENABLED'=>true]))->evaluate($key);
            assertSame(false,$state->effectiveValue);assertSame('invalid-site-value',$state->source);
            $keys=array_map(static fn($d)=>$d->key,(new Registry())->all());
            assertSame(1,count(array_filter($keys,static fn($k)=>$k===Registry::FASTMOD_AUTOMATIC_TAGGING_ENABLED)));
            assertSame(false,in_array('FASTMOD_META_TAGGING_ENABLED',$keys,true));
        } finally {foreach($old as $n=>$value)putenv($value===false?$n:$n.'='.$value);}
    }
}
