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
    public function testInvalidBundleDoesNotRegister(): void
    {
        [$root,$db,$artifacts]=SignedPostIdentityTest::helper('createTempEnvironment');
        $input=SignedPostIdentityTest::bundle(); $tx=new SignedPostTransaction($root); $before=$tx->git(['rev-parse','HEAD']);
        $input['bootstrap_signature']='invalid';
        try { (new LocalWriteService($root,$db,$artifacts,new CanonicalRecordRepository($root)))->createSignedPost($input); throw new LogicException('Expected rejection'); } catch(RuntimeException) {}
        assertSame($before,$tx->git(['rev-parse','HEAD'])); assertSame('',trim($tx->git(['status','--porcelain'])));
    }
}
