<?php
require_once __DIR__.'/SignedPostIdentityTest.php';
use ForumRewrite\Write\{LocalWriteService,SignedPostTransaction};
use ForumRewrite\Canonical\CanonicalRecordRepository;
final class SingleRequestPostingTest
{
    public function testFirstPostAndReplayKeepSeparateBootstrap(): void
    {
        [$root,$db,$artifacts]=SignedPostIdentityTest::helper('createTempEnvironment');
        $writer=new LocalWriteService($root,$db,$artifacts,new CanonicalRecordRepository($root));
        $input=SignedPostIdentityTest::bundle(); $tx=new SignedPostTransaction($root);
        $before=(int)trim($tx->git(['rev-list','--count','HEAD']));
        $result=$writer->createSignedPost($input);
        assertSame(false,$result['replayed']); assertSame(false,isset($result['refresh_pending']));
        assertSame($before+1,(int)trim($tx->git(['rev-list','--count','HEAD'])));
        assertSame($input['canonical_record'],file_get_contents($root.'/'.$result['record_path']));
        assertSame(false,$result['bootstrap_post_id']===$result['post_id']);
        $input['detached_signature']=str_replace("\n","\r\n",$input['detached_signature']);
        $input['public_key']=str_replace("\n","\r\n",$input['public_key']);
        $retry=$writer->createSignedPost($input); assertSame(true,$retry['replayed']); assertSame($result['commit_sha'],$retry['commit_sha']);
        unset($input['public_key'],$input['bootstrap_record'],$input['bootstrap_signature']);
        assertSame(true,$writer->createSignedPost($input)['replayed']);
        assertSame($before+1,(int)trim($tx->git(['rev-list','--count','HEAD'])));
        $pdo=new PDO('sqlite:'.$db); assertSame(1,(int)$pdo->query("SELECT post_count FROM profiles WHERE identity_id='".$result['identity_id']."'")->fetchColumn());
    }
    public function testReplyReusesIdentityAndRefreshFailureIsAccepted(): void
    {
        [$root,$db,$artifacts]=SignedPostIdentityTest::helper('createTempEnvironment');
        $writer=new LocalWriteService($root,$db,$artifacts,new CanonicalRecordRepository($root));
        $input=SignedPostIdentityTest::bundle(); $first=$writer->createSignedPost($input);
        $reply=str_replace(['Post-ID: thread-',"Subject: Test\n"],['Post-ID: reply-',''],$input['canonical_record']);
        $reply=str_replace('Author-Identity-ID:',"Thread-ID: {$first['post_id']}\nParent-ID: {$first['post_id']}\nAuthor-Identity-ID:",$reply);
        $input['canonical_record']=$reply; $input['detached_signature']=SignedPostIdentityTest::helper('signCanonicalRecord',SignedPostIdentityTest::$home,$reply);
        $input['bootstrap_record']=str_replace('bootstrap-', 'bootstrap-', $input['bootstrap_record']);
        $result=$writer->createSignedPost($input); assertSame($first['bootstrap_post_id'],$result['bootstrap_post_id']);
        $pdo=new PDO('sqlite:'.$db); assertSame(2,(int)$pdo->query("SELECT post_count FROM profiles WHERE identity_id='".$first['identity_id']."'")->fetchColumn());
        $broken=new class($root,$db,$artifacts,new CanonicalRecordRepository($root)) extends LocalWriteService {
            protected function rebuildReadModel(): array { throw new RuntimeException('injected refresh failure'); }
        };
        $result=$broken->createSignedPost($input); assertSame(true,$result['refresh_pending']); assertSame('ok',$result['status']);
        assertSame(false,isset($writer->createSignedPost($input)['refresh_pending']));
    }
    public function testAgedReplayAndConflictingSignedContent(): void
    {
        [$root,$db,$artifacts]=SignedPostIdentityTest::helper('createTempEnvironment');
        $input=SignedPostIdentityTest::bundle();
        foreach(['canonical_record','bootstrap_record'] as $field) {
            $input[$field]=preg_replace('/Created-At: [^\n]+/', 'Created-At: 2020-01-01T00:00:00Z',$input[$field]);
            $input[$field]=preg_replace('/(thread|bootstrap)-[0-9]{14}-/','$1-20200101000000-',$input[$field]);
            $sig=$field==='canonical_record' ? 'detached_signature' : 'bootstrap_signature';
            $input[$sig]=SignedPostIdentityTest::helper('signCanonicalRecord',SignedPostIdentityTest::$home,$input[$field]);
        }
        $bundle=(new \ForumRewrite\Write\SignedPostIdentity($root,new \ForumRewrite\Write\SignedPostBundleValidator()))->resolve($input);
        $path=\ForumRewrite\Canonical\CanonicalPathResolver::datedPost($bundle['post']->postId,$bundle['post']->createdAt);
        $files=$bundle['files']+[$path=>$input['canonical_record'],$path.'.asc'=>$input['detached_signature']];
        (new SignedPostTransaction($root))->accept($files);
        $writer=new LocalWriteService($root,$db,$artifacts,new CanonicalRecordRepository($root));
        assertSame(true,$writer->createSignedPost($input)['replayed']);
        $input['canonical_record']=str_replace('Hello','Different',$input['canonical_record']);
        $input['detached_signature']=SignedPostIdentityTest::helper('signCanonicalRecord',SignedPostIdentityTest::$home,$input['canonical_record']);
        try { $writer->createSignedPost($input); throw new LogicException('Expected conflict'); } catch(RuntimeException $e) { assertSame(409,$e->getCode()); }
    }
    public function testConcurrentFirstPostsKeepOneBootstrap(): void
    {
        [$root,$db,$artifacts]=SignedPostIdentityTest::helper('createTempEnvironment');
        $one=SignedPostIdentityTest::bundle(); $two=$one;
        foreach(['canonical_record','bootstrap_record'] as $field) {
            $two[$field]=preg_replace('/(-[a-f0-9]{32})\n/', '-'.bin2hex(random_bytes(16))."\n",$two[$field],1);
            $sig=$field==='canonical_record' ? 'detached_signature' : 'bootstrap_signature';
            $two[$sig]=SignedPostIdentityTest::helper('signCanonicalRecord',SignedPostIdentityTest::$home,$two[$field]);
        }
        $children=[];
        foreach([$one,$two] as $i=>$input) {
            $pid=pcntl_fork(); if($pid===0) {
                try { $r=(new LocalWriteService($root,$db,$artifacts,new CanonicalRecordRepository($root)))->createSignedPost($input); file_put_contents($root.'/result-'.$i,serialize($r)); exit(0); }
                catch(Throwable $e) { file_put_contents($root.'/result-'.$i,$e->getMessage()); exit(1); }
            } $children[]=$pid;
        }
        foreach($children as $i=>$pid) { pcntl_waitpid($pid,$status); assertSame(0,pcntl_wexitstatus($status),(string)file_get_contents($root.'/result-'.$i)); }
        $a=unserialize(file_get_contents($root.'/result-0')); $b=unserialize(file_get_contents($root.'/result-1'));
        assertSame($a['bootstrap_post_id'],$b['bootstrap_post_id']);
        $pdo=new PDO('sqlite:'.$db); assertSame(2,(int)$pdo->query("SELECT post_count FROM profiles WHERE identity_id='".$a['identity_id']."'")->fetchColumn());
        assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM profiles WHERE identity_id='".$a['identity_id']."'")->fetchColumn());
    }
    public function testRemovalKeepsBootstrapAndRejectsReplay(): void
    {
        [$root,$db,$artifacts]=SignedPostIdentityTest::helper('createTempEnvironment');
        $input=SignedPostIdentityTest::bundle(); $writer=new LocalWriteService($root,$db,$artifacts,new CanonicalRecordRepository($root));
        $r=$writer->createSignedPost($input);
        $prepared=$writer->prepareRetraction(['author_identity_id'=>$r['identity_id'],'post_id'=>$r['post_id']]);
        $prepared['detached_signature']=SignedPostIdentityTest::helper('signCanonicalRecord',SignedPostIdentityTest::$home,$prepared['canonical_record']);
        $prepared['author_identity_id']=$r['identity_id'];
        $writer->finalizePreparedRetraction($prepared);
        try { $writer->createSignedPost($input); throw new LogicException('Expected removed rejection'); } catch(RuntimeException $e) { assertSame(410,$e->getCode()); }
        assertSame(false,is_file($root.'/'.$r['record_path']));
        $identity=(new CanonicalRecordRepository($root))->loadIdentity(\ForumRewrite\Canonical\CanonicalPathResolver::identity(substr($r['identity_id'],8)));
        assertSame($r['bootstrap_post_id'],$identity->bootstrapByPost);
        assertSame('account bootstrap',(new CanonicalRecordRepository($root))->loadPost(\ForumRewrite\Canonical\CanonicalPathResolver::post($r['bootstrap_post_id']))->subject);
        (new \ForumRewrite\ReadModel\ReadModelBuilder($root,$db,new CanonicalRecordRepository($root)))->rebuild();
        $pdo=new PDO('sqlite:'.$db); assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM profiles WHERE identity_id='".$r['identity_id']."'")->fetchColumn());
    }
    public function testApprovalRebuildAndSnapshotRestore(): void
    {
        [$root,$db,$artifacts]=SignedPostIdentityTest::helper('createTempEnvironment');
        $writer=new LocalWriteService($root,$db,$artifacts,new CanonicalRecordRepository($root));
        $targetInput=SignedPostIdentityTest::bundle(); $target=$writer->createSignedPost($targetInput);
        $approver=$writer->createSignedPost(SignedPostIdentityTest::bundle()); $home=SignedPostIdentityTest::$home;
        SignedPostIdentityTest::helper('seedApprovedIdentity',$root,$approver['identity_id']);
        (new \ForumRewrite\ReadModel\ReadModelBuilder($root,$db,new CanonicalRecordRepository($root)))->rebuild();
        $prepared=$writer->prepareApproval(['approver_identity_id'=>$approver['identity_id'],'target_identity_id'=>$target['identity_id'],'target_profile_slug'=>$target['profile_slug'],'thread_id'=>$target['bootstrap_post_id'],'parent_id'=>$target['bootstrap_post_id']]);
        $prepared['detached_signature']=SignedPostIdentityTest::helper('signCanonicalRecord',$home,$prepared['canonical_record']);
        $prepared['author_identity_id']=$approver['identity_id'];
        $writer->finalizePreparedApproval($prepared);
        $pdo=new PDO('sqlite:'.$db); assertSame(1,(int)$pdo->query("SELECT is_approved FROM profiles WHERE identity_id='".$target['identity_id']."'")->fetchColumn());
        $before=file_get_contents($root.'/records/identity/identity-'.str_replace(':','-',$target['identity_id']).'.txt');
        $writer->createSignedPost($targetInput);
        assertSame($before,file_get_contents($root.'/records/identity/identity-'.str_replace(':','-',$target['identity_id']).'.txt'));
        $archive=(new \ForumRewrite\Images\ImageBackup($root,$db))->export('tar.gz');
        $dest=$root.'-restore'; mkdir($dest);
        $r=\ForumRewrite\Support\BoundedProcess::run(['tar','-xzf',$archive['path'],'-C',$dest],new \ForumRewrite\Support\OperationBudget(20),1024*1024,'extract');
        assertSame(0,$r['exit_code']); $restored=$dest.'/repository';
        assertSame($targetInput['canonical_record'],file_get_contents($restored.'/'.$target['record_path']));
        assertSame($targetInput['detached_signature'],file_get_contents($restored.'/'.$target['signature_path']));
        (new \ForumRewrite\ReadModel\ReadModelBuilder($restored,$dest.'/read.sqlite3',new CanonicalRecordRepository($restored)))->rebuild();
        $restoredPdo=new PDO('sqlite:'.$dest.'/read.sqlite3');
        assertSame(1,(int)$restoredPdo->query("SELECT is_approved FROM profiles WHERE identity_id='".$target['identity_id']."'")->fetchColumn());
        file_put_contents($dest.'/unused.php','<?php return [];');
        $command=['env','FORUM_REPOSITORY_ROOT='.$root,'FORUM_SECRETS_PATH='.$dest.'/unused.php','FORUM_STATIC_HTML_ROOT='.$dest.'/static','FORUM_PUBLIC_ARTIFACT_ROOT='.$artifacts,dirname(__DIR__).'/v3','rebuild',$root,$db];
        $rebuilt=\ForumRewrite\Support\BoundedProcess::run($command,new \ForumRewrite\Support\OperationBudget(60),2*1024*1024,'CLI rebuild');
        assertSame(0,$rebuilt['exit_code'],$rebuilt['stdout'].$rebuilt['stderr']);
        assertSame(true,$writer->createSignedPost($targetInput)['replayed']);
        // Existing importer expects records/ at archive root, unlike snapshot exports.
        $repack=\ForumRewrite\Support\BoundedProcess::run(['tar','-czf',$dest.'/import.tar.gz','-C',$restored,'records'],new \ForumRewrite\Support\OperationBudget(20),1024*1024,'repack unchanged records');
        assertSame(0,$repack['exit_code']);
        [$importRoot,$importDb,$importArtifacts]=SignedPostIdentityTest::helper('createTempEnvironment');
        $import=\ForumRewrite\Support\BoundedProcess::run(['env','FORUM_REPOSITORY_ROOT='.$importRoot,'FORUM_SECRETS_PATH='.$dest.'/unused.php',dirname(__DIR__).'/v3','import-repository',$dest.'/import.tar.gz',$importRoot,$importDb,$importArtifacts],new \ForumRewrite\Support\OperationBudget(60),2*1024*1024,'CLI import');
        assertSame(0,$import['exit_code'],'import exit '.var_export($import,true));
        assertSame($targetInput['canonical_record'],file_get_contents($importRoot.'/'.$target['record_path']),'imported post bytes');
        $imported=(new CanonicalRecordRepository($importRoot))->loadIdentity(\ForumRewrite\Canonical\CanonicalPathResolver::identity(substr($target['identity_id'],8)));
        assertSame($target['bootstrap_post_id'],$imported->bootstrapByPost,'imported bootstrap pointer');
        unlink($archive['path']);
    }
    public function testInvalidBundleDoesNotRegister(): void
    {
        [$root,$db,$artifacts]=SignedPostIdentityTest::helper('createTempEnvironment');
        $input=SignedPostIdentityTest::bundle(); $tx=new SignedPostTransaction($root); $before=$tx->git(['rev-parse','HEAD']);
        $input['bootstrap_signature']='invalid';
        try { (new LocalWriteService($root,$db,$artifacts,new CanonicalRecordRepository($root)))->createSignedPost($input); throw new LogicException('Expected rejection'); } catch(RuntimeException) {}
        assertSame($before,$tx->git(['rev-parse','HEAD'])); assertSame('',trim($tx->git(['status','--porcelain'])));
    }
}
