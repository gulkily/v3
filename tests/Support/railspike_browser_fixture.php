<?php

declare(strict_types=1);
require dirname(__DIR__,2).'/autoload.php';
if (PHP_SAPI!=='cli') exit(1);
$root=$argv[1]; $input=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
$queuePath=$root.'/state/private/tasks.sqlite3';
if (($input['operation'] ?? '')==='worker') {
    $pdo=new PDO('sqlite:'.$queuePath); $store=new \ForumRewrite\Railspike\WorkflowStore($pdo);
    $runner=static function ($row) use ($input): array {
        if (!empty($input['fail'])) throw new RuntimeException('Deliberate fixture failure');
        if ($row['history']===[]) return ['kind'=>'questions','questions'=>['Which navigation should improve?'],'assessment'=>''];
        return ['kind'=>'assessment','questions'=>[],'assessment'=>"## Problem Statement\nPhone navigation\n## Option A\nToolbar\n## Option B\nMenu\n## Recommendation\nToolbar. Literal <script>window.pwned=true</script>"];
    };
    $worker=new \ForumRewrite\TaskQueue\TaskQueueWorker(new \ForumRewrite\TaskQueue\SqliteTaskQueueStore($pdo),static function () {},null,null,null,new \ForumRewrite\Railspike\AssessmentTask($store,$runner, static fn (): bool => \ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator::forApplication($root.'/repository',$root)->isEnabled(\ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry::RAILSPIKE_ENABLED)));
    $taskId=(int)$pdo->query("SELECT id FROM internal_tasks WHERE type='railspike_assessment' AND status='queued' ORDER BY id LIMIT 1")->fetchColumn();
    echo json_encode($worker->run(1,null,$taskId)); exit;
}
if (($input['operation'] ?? '')==='rebuild') {
    (new \ForumRewrite\ReadModel\ReadModelBuilder($root.'/repository',$root.'/read.sqlite3',new \ForumRewrite\Canonical\CanonicalRecordRepository($root.'/repository'),'railspike-browser'))->rebuild(); exit;
}
$project=dirname(__DIR__,2);
symlink($project.'/templates',$root.'/templates'); symlink($project.'/public',$root.'/public');
mkdir($root.'/sessions',0700); mkdir($root.'/state/private',0700,true);
\ForumRewrite\Support\LocalRepositoryBootstrap::initializeLocalRepository($project,$root.'/repository');
$repository=new \ForumRewrite\Canonical\CanonicalRecordRepository($root.'/repository');
$writer=new \ForumRewrite\Write\LocalWriteService($root.'/repository',$root.'/read.sqlite3',$root.'/static',$repository);
$identity=$writer->linkIdentity(['public_key'=>$input['publicKey']]);
$id=$identity['identity_id'];
$path=\ForumRewrite\Canonical\CanonicalPathResolver::approvalSeed(substr($id,8));
file_put_contents($root.'/repository/'.$path,"Approved-Identity-ID: $id\nSeed-Reason: browser fixture\n\nOperator.\n");
exec('git -C '.escapeshellarg($root.'/repository').' add '.escapeshellarg($path),$out,$exit);
if ($exit!==0) throw new RuntimeException('Fixture staging failed');
exec('git -C '.escapeshellarg($root.'/repository').' commit -qm "Approve fixture operator"',$out,$exit);
if ($exit!==0) throw new RuntimeException('Fixture commit failed');
$direct=$writer->createThread(['board_tags'=>'railspike','subject'=>'Direct request','body'=>'Improve navigation on phones']);
$ordinary=$writer->createThread(['board_tags'=>'general','subject'=>'Ordinary thread','body'=>'No development request']);
$thread=$writer->createThread(['board_tags'=>'general','subject'=>'Thread request','body'=>'Improve the site']);
$reply=$writer->createReply(['thread_id'=>$thread['thread_id'],'parent_id'=>$thread['post_id'],'body'=>'Improve phone menus #railspike']);
(new \ForumRewrite\ReadModel\ReadModelBuilder($root.'/repository',$root.'/read.sqlite3',$repository,'railspike-browser'))->rebuild();
echo json_encode(['ordinary'=>$ordinary['post_id'],'identity'=>$id,'direct'=>$direct['post_id'],'thread'=>$thread['thread_id'],'reply'=>$reply['post_id']]);
