<?php
require_once __DIR__.'/SignedPostIdentityTest.php';
use ForumRewrite\Write\SignedPostTransaction;
final class SignedPostTransactionTest
{
    public function testFailureRollbackAndAcceptedCommit(): void
    {
        foreach(['journal','file','staged','committed'] as $point) {
            [$root]=SignedPostIdentityTest::helper('createTempEnvironment');
            $path='records/posts/transaction-test.txt';
            $tx=new SignedPostTransaction($root,static function($at) use($point) { if($at===$point) throw new RuntimeException('injected'); });
            $before=trim($tx->git(['rev-parse','HEAD']));
            try { $tx->accept([$path=>"test\n",$path.'.asc'=>"signature\n"]); assertSame('committed',$point); }
            catch(RuntimeException $e) { assertSame('injected',$e->getMessage()); }
            assertSame($point==='committed',is_file($root.'/'.$path));
            assertSame($point!=='committed',$before===trim($tx->git(['rev-parse','HEAD'])));
            assertSame('',trim($tx->git(['status','--porcelain'])));
            assertSame(false,is_dir($root.'/.git/signed-post-pending'));
        }
    }
    public function testUnrelatedStagedAndSymlinkTargetsRefused(): void
    {
        [$root]=SignedPostIdentityTest::helper('createTempEnvironment'); $tx=new SignedPostTransaction($root);
        file_put_contents($root.'/unrelated.txt','keep'); $tx->git(['add','unrelated.txt']);
        try { $tx->accept(['records/posts/new.txt'=>'x']); throw new LogicException('Expected refusal'); } catch(RuntimeException $e) { assertStringContains('staged',$e->getMessage()); }
        assertSame("A  unrelated.txt\n",$tx->git(['status','--porcelain']));
        $tx->git(['reset','-q','HEAD','--','unrelated.txt']);
        symlink($root.'/unrelated.txt',$root.'/records/posts/unsafe.txt');
        try { $tx->accept(['records/posts/unsafe.txt'=>'x']); throw new LogicException('Expected symlink refusal'); } catch(RuntimeException $e) { assertStringContains('symlink',$e->getMessage()); }
        assertSame('keep',file_get_contents($root.'/unrelated.txt'));
    }
}
