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
