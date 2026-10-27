<?php
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Canonical\{AcceptedRetraction, LabelRemoval, PostRecordParser, PostRetractionRecordParser};
use ForumRewrite\Write\RetractionDeletion;

final class RetractionTransactionTest
{
    private function fixture(): array
    {
        $root = sys_get_temp_dir() . '/retraction-files-' . bin2hex(random_bytes(6));
        mkdir($root . '/records/posts', 0775, true);
        foreach ([['init'], ['config','user.name','Test'], ['config','user.email','test@example.invalid']] as $args) LabelRemoval::git($root, $args);
        $author = 'openpgp:' . str_repeat('a',40);
        $source = "Post-ID: root\nCreated-At: 2026-10-25T12:00:00Z\nAuthor-Identity-ID: $author\nBoard-Tags: general\n\nprivate prose\n";
        file_put_contents($root . '/records/posts/root.txt', $source);
        file_put_contents($root . '/records/posts/root.txt.asc', "original signature\n");
        LabelRemoval::git($root, ['add','.']); LabelRemoval::git($root,['commit','-m','initial']);
        file_put_contents($root . '/unrelated.txt', 'keep staged'); LabelRemoval::git($root,['add','unrelated.txt']);
        $request = "Record-ID: post-retraction-root\nCreated-At: 2026-10-25T13:00:00Z\nPost-ID: root\nOperation: retract\nAuthor-Identity-ID: $author\n\n";
        $record = AcceptedRetraction::prepare((new PostRecordParser())->parse($source),(new PostRetractionRecordParser())->parse($request),$request,"signature\n",'author', hash('sha256',$source));
        return [$root,$record,$source];
    }

    public function testEveryWriteBoundaryRestoresOnFailureAndPreservesUnrelatedIndex(): void
    {
        for ($boundary = 1; $boundary <= 6; $boundary++) {
            [$root,$r,$source] = $this->fixture(); $seen = 0; $failed = false;
            try { RetractionDeletion::commit($root,$r,static function () use (&$seen,$boundary): void { if (++$seen === $boundary) throw new RuntimeException('injected failure'); }); }
            catch (RuntimeException $e) { $failed = $e->getMessage() === 'injected failure'; }
            assertSame(true,$failed); assertSame($source,file_get_contents($root.'/records/posts/root.txt'));
            assertSame(null,AcceptedRetraction::read($root,'root'));
            foreach (AcceptedRetraction::files($r) as $path => $_) assertSame(false,is_file($root.'/'.$path));
            assertSame('unrelated.txt',trim(LabelRemoval::git($root,['diff','--cached','--name-only'])));
        }
    }

    public function testRejectedCommitAndSuccessfulAtomicRetry(): void
    {
        [$root,$r,$source] = $this->fixture();
        file_put_contents($root.'/.git/hooks/pre-commit',"#!/bin/sh\nexit 1\n");chmod($root.'/.git/hooks/pre-commit',0755);
        $failed=false;try {RetractionDeletion::commit($root,$r);}catch(RuntimeException){$failed=true;}
        assertSame(true,$failed);assertSame($source,file_get_contents($root.'/records/posts/root.txt'));assertSame(null,AcceptedRetraction::read($root,'root'));
        unlink($root.'/.git/hooks/pre-commit');
        $commit=RetractionDeletion::commit($root,$r);
        assertSame($r,AcceptedRetraction::read($root,'root'));
        assertSame(false,is_file($root.'/records/posts/root.txt'));
        assertSame(false,is_file($root.'/records/posts/root.txt.asc'));
        assertSame(5,count(explode("\n",trim(LabelRemoval::git($root,['diff-tree','--no-commit-id','--name-only','-r',$commit])))));
        assertSame('unrelated.txt',trim(LabelRemoval::git($root,['diff','--cached','--name-only'])));
        assertSame($commit,RetractionDeletion::commit($root,$r));
        unlink($root.'/'.AcceptedRetraction::requestPath('root').'.asc');
        $failed=false;try {AcceptedRetraction::read($root,'root');}catch(RuntimeException){$failed=true;}assertSame(true,$failed);
    }

    public function testProcessExitAtEveryBoundaryLeavesRecoverableState(): void
    {
        for($boundary=1;$boundary<=6;$boundary++) {
            [$root,$r,$source]=$this->fixture();
            $script=$root.'/interrupt.php';
            file_put_contents($script,"<?php\nrequire " . var_export(dirname(__DIR__).'/autoload.php',true) . ";\n"
                . '$r=' . var_export($r,true) . ";\n"
                . '$seen=0; \\ForumRewrite\\Write\\RetractionDeletion::commit(' . var_export($root,true) . ', $r, static function () use (&$seen): void { if (++$seen === ' . $boundary . ') exit(23); });');
            $pipes=[];$p=proc_open([PHP_BINARY,$script],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
            stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);assertSame(23,proc_close($p),$err);
            $accepted=AcceptedRetraction::read($root,'root');
            if($boundary<3){assertSame(null,$accepted);assertSame($source,file_get_contents($root.'/records/posts/root.txt'));}
            else assertSame($r,$accepted);
            $commit=RetractionDeletion::commit($root,$accepted ?? $r);
            assertSame($r,AcceptedRetraction::read($root,'root'));
            assertSame(false,is_file($root.'/records/posts/root.txt'));
            assertSame($commit,RetractionDeletion::commit($root,$r));
            assertSame('unrelated.txt',trim(LabelRemoval::git($root,['diff','--cached','--name-only'])));
        }
    }
}
