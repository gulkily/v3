<?php
require_once __DIR__ . '/../autoload.php';
use ForumRewrite\Canonical\RemovalReceipt;
final class RemovalReceiptTest
{
    private function headers(): array
    {
        return ['Format'=>'removal-acceptance-v1', 'Post-ID'=>'root', 'Operation'=>'resolve', 'Authority'=>'local-operator',
            'Accepted-At'=>'2026-10-29T00:00:00Z', 'Actor'=>'local-operator', 'Source-SHA256'=>str_repeat('a',64),
            'Thread-ID'=>'root', 'Parent-ID'=>'', 'Post-Created-At'=>'2026-10-28T00:00:00Z', 'Author-Identity-ID'=>'',
            'Thread-Type'=>'', 'Retraction-Class'=>'', 'Retraction-Attribution'=>'', 'Request-SHA256'=>'', 'Signature-SHA256'=>''];
    }
    public function testAnonymousResolutionAndRepeatedLabelsRoundTrip(): void
    {
        $h=$this->headers(); $labels=['records/thread-labels/a.txt'=>str_repeat('b',64),'records/thread-labels/b.txt'=>str_repeat('c',64)];
        $bytes=RemovalReceipt::encode($h,$labels);
        assertSame(['headers'=>$h,'labels'=>$labels],RemovalReceipt::decode($bytes));
        assertSame(false,str_contains($bytes,'{'));
    }
    public function testContradictoryAndMalformedReceiptsFail(): void
    {
        $h=$this->headers(); $text=RemovalReceipt::encode($h);
        $bad=[$text.'prose\n',str_replace('removal-acceptance-v1','1',$text),str_replace('Operation: resolve','Operation: retract',$text),
            str_replace('Actor: local-operator',"Actor: local-operator\nActor: local-operator",$text),
            str_replace('2026-10-29','2026-02-31',$text),str_replace('Request-SHA256: ', 'Request-SHA256: '.str_repeat('b',64),$text),
            str_replace('Authority: local-operator','Authority: signed-retraction',$text),str_replace('Thread-Type: ', 'Thread-Type: unknown',$text)];
        $block='Label-Path: records/thread-labels/a.txt'."\nLabel-SHA256: ".str_repeat('b',64)."\n\n";
        $bad[]=$text.$block.$block;
        foreach($bad as $bytes) { $failed=false;try{RemovalReceipt::decode($bytes);}catch(RuntimeException){$failed=true;}assertSame(true,$failed); }
    }
}
