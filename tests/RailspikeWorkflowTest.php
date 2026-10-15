<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
use ForumRewrite\Railspike\WorkflowStore;
use ForumRewrite\Railspike\WorkflowService;

final class RailspikeWorkflowTest
{
    public function fixture(): array
    {
        $root=sys_get_temp_dir().'/railspike-'.bin2hex(random_bytes(5));
        mkdir($root.'/records/approval-seeds',0700,true);
        $id='openpgp:'.str_repeat('a',40);
        file_put_contents($root.'/records/approval-seeds/openpgp-'.str_repeat('a',40).'.txt',"Approved-Identity-ID: $id\nSeed-Reason: test\n\nOperator.\n");
        $read=new PDO('sqlite::memory:');
        $read->exec('CREATE TABLE posts (post_id TEXT,thread_id TEXT,body TEXT,author_label TEXT,board_tags_json TEXT,created_at TEXT,retracted_at TEXT)');
        $read->exec('CREATE TABLE threads (root_post_id TEXT,thread_labels_json TEXT)');
        $read->exec("INSERT INTO threads VALUES ('root','[\"railspike\"]')");
        $read->exec("INSERT INTO posts VALUES ('reply','root','Improve phone navigation','ilyag','[]','2026',NULL)");
        $pdo=new PDO('sqlite::memory:'); $store=new WorkflowStore($pdo);
        $service=new WorkflowService($store,$read,$root,static fn ($actor,$text,$signature) => $signature==='valid');
        return [$store,$service,['identity_id'=>$id,'is_approved'=>1,'_authenticated_identity'=>true],$root,$pdo];
    }
    private function rejects(callable $fn): void
    {
        try { $fn(); } catch (RuntimeException) { return; }
        throw new RuntimeException('Expected rejection.');
    }
    public function testSignedStartDeduplicatesAndRejectsForgedActors(): void
    {
        [$store,$service,$actor]=$this->fixture();
        $text=$service->prepare(['action'=>'start','post_id'=>'reply'],$actor);
        $this->rejects(fn () => $service->apply($text,'invalid',$actor));
        $this->rejects(fn () => $service->prepare(['action'=>'start'],array_merge($actor,['_authenticated_identity'=>false])));
        $this->rejects(fn () => $service->prepare(['action'=>'start'],array_merge($actor,['identity_id'=>'openpgp:'.str_repeat('b',40)])));
        $row=$service->apply($text,'valid',$actor);
        assertSame('queued',$row['state']); assertSame('root',$row['thread_id']);
        assertSame($row['id'],$service->apply($text,'valid',$actor)['id']);
        $again=$service->prepare(['action'=>'start','post_id'=>'reply'],$actor);
        assertSame($row['id'],$service->apply($again,'valid',$actor)['id']);
        assertSame(1,count($store->forThread('root')));
        $foreign=json_decode($text,true); $foreign['instance']='other';
        $this->rejects(fn () => $service->apply(json_encode($foreign),'valid',$actor));
        $foreign=json_decode($text,true); $foreign['expires']=time()-1;
        $this->rejects(fn () => $service->apply(json_encode($foreign),'valid',$actor));
        $this->rejects(fn () => $store->requireVersion($row['id'],0));
    }
    public function testThreadLabelIncludesRepliesButNotRetractedOrAgentPosts(): void
    {
        assertSame(true,WorkflowService::eligible(['board_tags_json'=>'[]'],['thread_labels_json'=>'["railspike"]']));
        assertSame(true,WorkflowService::eligible(['board_tags_json'=>'["railspike"]'],[]));
        assertSame(false,WorkflowService::eligible(['board_tags_json'=>'[]'],[]));
        assertSame(false,WorkflowService::eligible(['author_label'=>'reply-agent','board_tags_json'=>'["railspike"]'],[]));
        assertSame(false,WorkflowService::eligible(['retracted_at'=>'now','board_tags_json'=>'["railspike"]'],[]));
    }
    public function testQueueWorkerPersistsValidatedAssessment(): void
    {
        [$store,$service,$actor,$root,$pdo]=$this->fixture();
        $row=$service->apply($service->prepare(['action'=>'start','post_id'=>'reply'],$actor),'valid',$actor);
        $queue=new \ForumRewrite\TaskQueue\SqliteTaskQueueStore($pdo);
        $runner=static fn ($row) => ['kind'=>'assessment','questions'=>[],'assessment'=>"## Problem Statement\nPhone navigation\n## Option A\nToolbar\n## Option B\nMenu\n## Recommendation\nToolbar"];
        $worker=new \ForumRewrite\TaskQueue\TaskQueueWorker($queue,static function () {},null,null,null,new \ForumRewrite\Railspike\AssessmentTask($store,$runner));
        assertSame(1,$worker->run()['completed']);
        $current=$store->find($row['id']);
        assertSame('review',$current['state']);
        assertStringContains('Improve phone navigation',$current['revisions'][0]['artifact']);
        assertSame(1,count($current['revisions']));
        $this->rejects(fn () => \ForumRewrite\Railspike\CodexAssessmentRunner::validate(['kind'=>'assessment','questions'=>[],'assessment'=>'Looks fine']));
        $this->rejects(fn () => (new \ForumRewrite\Railspike\CodexAssessmentRunner($root))->readiness());
    }

    public function testClarificationAndRevisionActionsAreBoundToCurrentQuestion(): void
    {
        [$store,$service,$actor,$root,$pdo]=$this->fixture();
        $row=$service->apply($service->prepare(['action'=>'start','post_id'=>'reply'],$actor),'valid',$actor);
        $task=new \ForumRewrite\Railspike\AssessmentTask($store,static fn ($r) => ['kind'=>'questions','questions'=>['Which navigation?'],'assessment'=>'']);
        $task(['deduplication_key'=>$row['id'].'@1']);
        $row=$store->find($row['id']); assertSame('questions',$row['state']);
        $stale=$service->prepare(['action'=>'answer','workflow_id'=>$row['id'],'version'=>$row['version'],'payload'=>['revision'=>2,'answers'=>['Main']]],$actor);
        $this->rejects(fn () => $service->apply($stale,'valid',$actor));
        $text=$service->prepare(['action'=>'answer','workflow_id'=>$row['id'],'version'=>$row['version'],'payload'=>['revision'=>1,'answers'=>['Main']]],$actor);
        $next=$service->apply($text,'valid',$actor);
        assertSame('queued',$next['state']); assertSame(['Main'],$next['history'][0]['answers']);
        $task=new \ForumRewrite\Railspike\AssessmentTask($store,static fn ($r) => ['kind'=>'assessment','questions'=>[],'assessment'=>"## Problem Statement\nMain\n## Option A\nMenu\n## Option B\nToolbar\n## Recommendation\nMenu"]);
        $task(['deduplication_key'=>$row['id'].'@'.$next['version']]);
        $row=$store->find($row['id']);
        $next=$service->apply($service->prepare(['action'=>'changes','workflow_id'=>$row['id'],'version'=>$row['version'],'payload'=>['revision'=>2,'text'=>'Prefer toolbar']],$actor),'valid',$actor);
        assertSame('queued',$next['state']); assertSame('Prefer toolbar',$next['history'][1]['text']);
        $this->rejects(fn () => $service->apply($stale,'valid',$actor));
    }

    public function testApprovalBindsDigestStopsAndCanBeInvalidated(): void
    {
        [$store,$service,$actor,$root,$pdo]=$this->fixture();
        $row=$service->apply($service->prepare(['action'=>'start','post_id'=>'reply'],$actor),'valid',$actor);
        $row['state']='review'; $row['revisions']=[['number'=>1,'digest'=>'digest','artifact'=>'assessment','questions'=>[],'kind'=>'assessment']]; $store->save($row);
        $text=$service->prepare(['action'=>'approve','workflow_id'=>$row['id'],'version'=>1,'payload'=>['revision'=>1,'digest'=>'wrong']],$actor);
        $this->rejects(fn () => $service->apply($text,'valid',$actor));
        $before=(int)$pdo->query('SELECT COUNT(*) FROM internal_tasks')->fetchColumn();
        $text=$service->prepare(['action'=>'approve','workflow_id'=>$row['id'],'version'=>1,'payload'=>['revision'=>1,'digest'=>'digest']],$actor);
        $approved=$service->apply($text,'valid',$actor); assertSame('approved',$approved['state']);
        assertSame($actor['identity_id'],$approved['approval']['actor']);
        assertSame($before,(int)$pdo->query('SELECT COUNT(*) FROM internal_tasks')->fetchColumn());
        assertSame($approved['version'],$service->apply($text,'valid',$actor)['version']);
        $changes=$service->prepare(['action'=>'changes','workflow_id'=>$row['id'],'version'=>$approved['version'],'payload'=>['revision'=>1,'text'=>'Reconsider']],$actor);
        $changed=$service->apply($changes,'valid',$actor); assertSame(false,isset($changed['approval']));
        unlink($root.'/records/approval-seeds/openpgp-'.str_repeat('a',40).'.txt');
        $this->rejects(fn () => $service->apply($text,'valid',$actor));
    }

}
