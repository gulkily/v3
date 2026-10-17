<?php

declare(strict_types=1);
namespace ForumRewrite\Railspike;

final class AssessmentTask
{
    public function __construct(private readonly WorkflowStore $store, private readonly mixed $runner) {}

    public function reconcile(): void { $this->store->reconcileTasks(); }

    public function __invoke(array $task): void
    {
        [$id,$version]=array_pad(explode('@',$task['deduplication_key'],2),2,'');
        $row=$this->store->transaction(function () use ($id,$version): ?array {
            $row=$this->store->find($id);
            if ($row===null || $row['version']!==(int)$version || !in_array($row['state'],['queued','running'],true)) return null;
            $row['state']='running'; $row['run_token']=bin2hex(random_bytes(16));
            return $this->store->save($row);
        });
        if ($row===null) return;
        try { $result=CodexAssessmentRunner::validate(($this->runner)($row)); }
        catch (\Throwable $e) {
            $this->finish($row,['state'=>'failed','failure'=>'Assessment failed. Check worker configuration or retry.','failure_detail'=>substr($e->getMessage(),0,500)]);
            throw new \RuntimeException('Railspike assessment failed; inspect the private workflow status.');
        }
        $revision=count($row['revisions'])+1;
        $artifact='';
        if ($result['kind']==='assessment') {
            $base='assessment_'.$row['id'];
            $artifact='> **Feature plan:** [Step 1](./'.$base.'_step1_solution_assessment.md) · [Step 2](./'.$base.'_step2_feature_description.md) · [Step 3](./'.$base.'_step3_development_plan.md) · [Step 4](./'.$base.'_step4_implementation_summary.md)'."\n\nDebug mode: disabled\n\n## Original Query\n\n".$row['request']."\n\n".$result['assessment'];
        }
        $revisions=$row['revisions'];
        $revisions[]=['number'=>$revision,'kind'=>$result['kind'],'questions'=>$result['questions'],'artifact'=>$artifact,'digest'=>hash('sha256',$artifact),'created_at'=>gmdate('c')];
        $this->finish($row,['state'=>$result['kind']==='questions' ? 'questions' : 'review','revisions'=>$revisions]);
    }

    private function finish(array $row, array $changes): void
    {
        $this->store->transaction(function () use ($row,$changes): void {
            $current=$this->store->find($row['id']);
            if ($current===null || $current['version']!==$row['version'] || ($current['run_token'] ?? '')!==$row['run_token']) return;
            $current=array_merge($current,$changes); $current['version']++;
            unset($current['run_token']); $this->store->save($current);
        });
    }
}
