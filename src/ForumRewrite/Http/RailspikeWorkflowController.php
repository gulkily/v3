<?php

declare(strict_types=1);
namespace ForumRewrite\Http;

use ForumRewrite\Railspike\WorkflowFactory;
use ForumRewrite\Railspike\WorkflowService;
use ForumRewrite\Security\ApprovalPolicy;

final class RailspikeWorkflowController
{
    public function __construct(private readonly RouteServices $routes, private readonly \Closure $viewer, private readonly string $projectRoot, private readonly string $repositoryRoot) {}

    public function handle(string $method, array $query): void
    {
        $headers=$this->routes->noStoreHeaders();
        if (!$this->routes->featureFlags()->isEnabled(\ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry::RAILSPIKE_ENABLED)) {
            $this->routes->sendJson(['status'=>'error','code'=>'railspike_disabled','error'=>'Railspike development is disabled. Re-enable it in Feature Flags and reload to resume. Stored workflows are retained.'],403,$headers); return;
        }
        $actor=($this->viewer)();
        if ($actor===null || (int)($actor['is_approved'] ?? 0)!==1 || !ApprovalPolicy::isOperator($this->repositoryRoot,(string)$actor['identity_id'])) {
            $this->routes->sendJson(['status'=>'error','error'=>'Approved operator authentication required.'],403,$headers); return;
        }
        $actor['_authenticated_identity']=true;
        if (!in_array($method,['GET','POST'],true)) { $this->routes->sendJson(['error'=>'Method not allowed.'],405,$headers); return; }
        try {
            $store=WorkflowFactory::open($this->projectRoot,$this->repositoryRoot,$this->routes->databasePath());
            $service=new WorkflowService($store,$this->routes->pdo(),$this->repositoryRoot);
            if ($method==='GET') {
                if (($query['activity'] ?? '')==='1') {
                    $items=array_map(static fn ($r) => array_intersect_key($r,array_flip(['id','post_id','thread_id','state','version','created_at'])), $store->recent());
                    $this->routes->sendJson(['status'=>'ok','workflows'=>$items],200,$headers); return;
                }
                $threadId=(string)($query['thread_id'] ?? '');
                $q=$this->routes->pdo()->prepare('SELECT * FROM threads WHERE root_post_id=?'); $q->execute([$threadId]); $thread=$q->fetch(\PDO::FETCH_ASSOC) ?: [];
                $q=$this->routes->pdo()->prepare('SELECT * FROM posts WHERE thread_id=?'); $q->execute([$threadId]);
                $eligible=[]; foreach ($q->fetchAll(\PDO::FETCH_ASSOC) as $post) if (WorkflowService::eligible($post,$thread)) $eligible[]=$post['post_id'];
                $this->routes->sendJson(['status'=>'ok','eligible'=>$eligible,'workflows'=>$store->forThread($threadId)],200,$headers); return;
            }
            if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''),'application/json') || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')!=='ForumRailspike' || in_array($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '',['cross-site','same-site'],true)) throw new \RuntimeException('Same-origin JSON action required.');
            $input=$this->routes->requestData($query);
            if (($input['operation'] ?? '')==='prepare') {
                $this->routes->sendJson(['signed_text'=>$service->prepare($input,$actor)],200,$headers); return;
            }
            $row=$service->apply((string)($input['signed_text'] ?? ''),(string)($input['signature'] ?? ''),$actor);
            $this->routes->sendJson(['status'=>'ok','workflow'=>$row],200,$headers);
        } catch (\Throwable $e) {
            $this->routes->sendJson(['status'=>'error','error'=>$e instanceof \RuntimeException ? $e->getMessage() : 'Workflow request unavailable.'],400,$headers);
        }
    }
}
