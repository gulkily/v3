<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/autoload.php';
use ForumRewrite\Messaging\{PrivateMessageStore,PrivateMessageHistorySyncStore,PrivateMessageHistorySyncService};
$profiles=new PDO('sqlite::memory:');
$profiles->exec('CREATE TABLE profiles(identity_id TEXT PRIMARY KEY,username_token TEXT,is_approved INTEGER, was_approved INTEGER DEFAULT 0, retired_at TEXT,public_key TEXT)');
foreach(\ForumRewrite\Messaging\PrivateMessageHistoryRevision::profileStatements() as $sql) $profiles->exec($sql);
$messages=new PrivateMessageStore(new PDO('sqlite::memory:'));
$sync=new PrivateMessageHistorySyncStore(new PDO('sqlite::memory:'));
$service=new PrivateMessageHistorySyncService($messages,$sync,$profiles);
while(($line=fgets(STDIN))!==false) {
 try {
    $input=json_decode($line,true,64,JSON_THROW_ON_ERROR);
    if($input['action']==='setup') {
        foreach($input['keys'] as $key) {
            $q=$profiles->prepare('INSERT INTO profiles (identity_id,username_token,is_approved,public_key) VALUES (?,?,1,?)');
            $q->execute(['openpgp:'.$key['fp'],'alice',$key['publicKey']]);
        }
        $viewer=['username_token'=>'alice','identity_id'=>'openpgp:'.$input['source'],'is_approved'=>1];
        $messages->storeEnvelope('m1','now','alice','bob','openpgp:'.($input['submitting']??$input['source']),$input['envelope']);
        foreach ($input['retired']??[] as $fp) {
            $q=$profiles->prepare('UPDATE profiles SET is_approved=0,was_approved=1,retired_at=? WHERE identity_id=?');
            $q->execute(['2026-11-01T00:00:00Z','openpgp:'.$fp]);
        }
        $out=[];
    } else {
        $body=$input['body']??[];
        $out=match($input['action']) {
            'work'=>$service->combinedWork($viewer,$body['complete']??[]),
            'acknowledge'=>$service->acknowledge($viewer,$body),
            'transfers'=>isset($body['ciphertext'])?$service->upload($viewer,$body):$service->transfers($viewer,$body),
            'receipt'=>['receipt'=>$sync->receipt('alice',$input['target'],'m1',hash('sha256',$input['envelope']))],
        };
    }
    echo json_encode(['status'=>'ok']+$out+['usage'=>$service->budgetUsage()],JSON_THROW_ON_ERROR)."\n";
 } catch(Throwable $e) {echo json_encode(['status'=>'error','error'=>$e->getMessage()],JSON_THROW_ON_ERROR)."\n";}
}
