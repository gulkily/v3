<?php
declare(strict_types=1);
require dirname(__DIR__, 2).'/autoload.php';
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\Support\LocalRepositoryBootstrap;
use ForumRewrite\Messaging\PrivateMessageStore;
if (PHP_SAPI !== 'cli') {http_response_code(404);exit;}
$root=$argv[1];
$input=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$action=$input['action'];
if($action==='init') {
    symlink(dirname(__DIR__,2).'/templates',$root.'/templates');
    symlink(dirname(__DIR__,2).'/public',$root.'/public');
    mkdir($root.'/state/private',0700,true);mkdir($root.'/sessions',0700);
    LocalRepositoryBootstrap::initializeLocalRepository(dirname(__DIR__,2),$root.'/repository');
}
if($action==='init' || $action==='seed-approvals') {
    foreach($input['fingerprints']??[] as $fingerprint) {
        if(!preg_match('/^[a-f0-9]{40}$/D',$fingerprint)) throw new RuntimeException('Invalid test key');
        $dir=$root.'/repository/records/approval-seeds';
        if(!is_dir($dir)) mkdir($dir,0700,true);
        file_put_contents($dir.'/openpgp-'.$fingerprint.'.txt',"Approved-Identity-ID: openpgp:$fingerprint\nSeed-Reason: initial approved fixture user\n\nInitial test bootstrap only; subsequent devices use signed approval APIs.\n");
    }
    (new ReadModelBuilder($root.'/repository',$root.'/read.sqlite3',new CanonicalRecordRepository($root.'/repository')))->rebuild();
}
if($action==='messages') {
    $store=new PrivateMessageStore(new PDO('sqlite:'.$root.'/state/private/messages.sqlite3'));
    foreach($input['messages'] as $message) $store->storeEnvelope($message['id'],$message['time'],$message['sender'],$message['recipient'],$message['fingerprint'],$message['envelope']);
}
if($action==='inspect') {
    $db=new PDO('sqlite:'.$root.'/state/private/messages.sqlite3');
    $sync=new PDO('sqlite:'.$root.'/state/private/message_history_sync.sqlite3');
    echo json_encode(['originals'=>$db->query('SELECT message_id,encrypted_envelope FROM private_messages ORDER BY message_id')->fetchAll(PDO::FETCH_ASSOC),
        'transfers'=>(int)$sync->query('SELECT COUNT(*) FROM history_sync_transfers')->fetchColumn(),
        'coverage'=>(int)$sync->query('SELECT COUNT(*) FROM history_sync_coverage')->fetchColumn()]);
}

if($action==='contributions') {
    $sync=new PDO('sqlite:'.$root.'/state/private/message_history_sync.sqlite3');
    $q=$sync->prepare('SELECT COUNT(DISTINCT message_id) FROM history_sync_items WHERE source=? AND target=?');
    $q->execute([$input['source'],$input['target']]);echo $q->fetchColumn();
}
