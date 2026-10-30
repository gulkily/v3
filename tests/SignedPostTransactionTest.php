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
    public function testKilledWriterRecoversBeforeApplicationReads(): void
    {
        foreach(['file','staged','committed'] as $point) {
            [$root,$db,$artifacts]=SignedPostIdentityTest::helper('createTempEnvironment');
            $pid=pcntl_fork();
            if($pid===0) {
                (new SignedPostTransaction($root,static function($at) use($point) { if($at===$point) posix_kill(getmypid(),SIGKILL); }))->accept(['records/posts/interrupted.txt'=>"private pending text\n"]);
                exit(2);
            }
            pcntl_waitpid($pid,$status); assertSame(SIGKILL,pcntl_wtermsig($status));
            assertSame(true,is_dir($root.'/.git/signed-post-pending'));
            // Application startup runs recovery before indexing. Use a valid empty fixture record only for precommit cases.
            if($point==='committed') (new SignedPostTransaction($root))->recover();
            else { ob_start(); try { (new \ForumRewrite\Application(dirname(__DIR__),$root,$db,$artifacts))->handle('GET','/api/'); } finally { ob_end_clean(); } }
            assertSame($point==='committed',is_file($root.'/records/posts/interrupted.txt'));
            assertSame(false,is_dir($root.'/.git/signed-post-pending'));
        }
    }
    public function testRejectedGitCommitLeavesNoBundle(): void
    {
        [$root]=SignedPostIdentityTest::helper('createTempEnvironment');
        file_put_contents($root.'/.git/hooks/pre-commit',"#!/bin/sh\nexit 1\n"); chmod($root.'/.git/hooks/pre-commit',0700);
        $tx=new SignedPostTransaction($root); $head=$tx->git(['rev-parse','HEAD']);
        try { $tx->accept(['records/posts/rejected.txt'=>"text\n"]); throw new LogicException('Expected commit rejection'); }
        catch(RuntimeException $e) { assertStringContains('Git operation failed',$e->getMessage()); }
        assertSame($head,$tx->git(['rev-parse','HEAD'])); assertSame('',trim($tx->git(['status','--porcelain'])));
        assertSame(false,is_dir($root.'/.git/signed-post-pending'));
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
