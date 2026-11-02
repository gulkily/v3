<?php
declare(strict_types=1);
require_once __DIR__.'/../autoload.php';
use ForumRewrite\Messaging\{PrivateMessageStore,PrivateMessageHistorySyncStore,PrivateMessageHistorySyncService,PrivateMessageHistorySyncDatabaseConfig};
final class PrivateMessageHistorySyncTest
{
    private function fixture(int $accountKeys = 2): array
    {
        $profiles=new PDO('sqlite::memory:');
        $profiles->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
        $profiles->exec('CREATE TABLE profiles (identity_id TEXT PRIMARY KEY, profile_slug TEXT, username TEXT, username_token TEXT, fallback_label TEXT, signer_fingerprint TEXT, bootstrap_post_id TEXT, bootstrap_thread_id TEXT, public_key TEXT, is_approved INTEGER, approved_by_identity_id TEXT, approved_by_profile_slug TEXT, approved_by_label TEXT, post_count INTEGER, thread_count INTEGER)');
foreach(\ForumRewrite\Messaging\PrivateMessageHistoryRevision::profileStatements() as $sql) $profiles->exec($sql);
        foreach (['a'=>'alice','b'=>'alice','c'=>'bob','d'=>'mallory'] as $key=>$account) {
            $fp=str_repeat($key,40);
            $q=$profiles->prepare('INSERT INTO profiles (identity_id,profile_slug,username,username_token,public_key,is_approved) VALUES (?,?,?,?,?,1)');
            $q->execute(['openpgp:'.$fp,'openpgp-'.$fp,$account,$account,'KEY-'.$key]);
        }
        for($i=2;$i<$accountKeys;$i++) {
            $fp=sha1('fixture-key-'.$i);
            $q=$profiles->prepare('INSERT INTO profiles (identity_id,username_token,public_key,is_approved) VALUES (?,?,?,1)');
            $q->execute(['openpgp:'.$fp,'alice','KEY-'.$i]);
        }
        $db=new PDO('sqlite::memory:');$messages=new PrivateMessageStore($db);
        for($i=1;$i<=31;$i++) $messages->storeEnvelope(sprintf('m%02d',$i),'2026-10-10',$i%2?'alice':'bob',$i%2?'bob':'alice','source','cipher-'.$i);
        $messages->storeEnvelope('foreign','2026-10-10','mallory','bob','source','secret');
        $syncDb=new PDO('sqlite::memory:');$sync=new PrivateMessageHistorySyncStore($syncDb);
        return [$profiles,$messages,$sync,new PrivateMessageHistorySyncService($messages,$sync,$profiles),$db,$syncDb];
    }
    public function testSubmissionIdentityDoesNotExcludeOtherApprovedOriginalSigners(): void
    {
        [,,$sync,$service,$db]=$this->fixture();
        // The send endpoint records the authenticated transport identity without
        // inspecting the encrypted signature. Another approved account key may sign.
        $db->exec("UPDATE private_messages SET sender_identity_id='openpgp:".str_repeat('a',40)."' WHERE sender_username_token='alice'");
        $work=$service->work($this->viewer());
        $outgoing=array_values(array_filter($work['messages'],static fn($row)=>$row['sender_username_token']==='alice'));
        assertSame(true,in_array('KEY-b',$outgoing[0]['sender_keys'],true),'Submission identity must not exclude another approved original signer');
    }
    public function testBoundedVerificationVisitsLaterKeysAndRetainsTimeoutWork(): void
    {
        [$profiles,$messages,$sync,$service,$db]=$this->fixture(115);
        $db->exec("DELETE FROM private_messages");
        $messages->storeEnvelope('m1','now','alice','bob','openpgp:'.str_repeat('a',40),'cipher');
        $seen=[];$prior=null;$more=true;$rounds=0;
        while($more) {
            $work=$service->work($this->viewer());
            assertSame(true,strlen(json_encode($work))<=4*1048576);
            assertSame(true,$service->budgetUsage()['keys']<=64);
            $row=$work['messages'][0];
            if($rounds===0) {
                assertSame('KEY-a',$row['sender_keys'][0]);
                $service->acknowledge($this->viewer(),['checkpoint'=>['target'=>$work['target'],'after_id'=>'m1']]);
                assertSame($row['sender_keys'],$service->work($this->viewer())['messages'][0]['sender_keys'],'Timeout must retain untried verification work');
            }
            $seen=array_merge($seen,$row['sender_keys']);
            $more=$row['verification_more'];
            $service->acknowledge($this->viewer(),['checkpoint'=>$work['checkpoint']]);
            if($prior!==null) {
                $service->acknowledge($this->viewer(),['checkpoint'=>$prior]);
                // Duplicate old token cannot skip the newer pending key subset.
            }
            $prior=$work['checkpoint'];
            assertSame(true,++$rounds<30);
        }
        assertSame(115,count(array_unique($seen)));
        assertSame(null,$sync->receipt('alice',str_repeat('a',40),'m1',hash('sha256','cipher')),'Scan progress cannot fabricate readability');
    }
    public function testMaximumArmorVerificationMaterialYieldsBoundedProgress(): void
    {
        [$profiles,$messages,,$service,$db]=$this->fixture();
        $large='-----BEGIN PGP PUBLIC KEY BLOCK-----'.str_repeat('A',1048400).'-----END PGP PUBLIC KEY BLOCK-----';
        $q=$profiles->prepare("UPDATE profiles SET public_key=? WHERE username_token='alice'");$q->execute([$large]);
        $db->exec('DELETE FROM private_messages');
        $messages->storeEnvelope('m1','now','alice','bob','openpgp:'.str_repeat('a',40),str_repeat('A',1048500));
        $work=$service->work($this->viewer());
        assertSame(true,strlen(json_encode($work))<=4*1048576);
        assertSame(true,count($work['messages'][0]['sender_keys'])>0);
        assertSame(true,$work['messages'][0]['verification_more']);
        $service->acknowledge($this->viewer(),['checkpoint'=>$work['checkpoint']]);
        $next=$service->work($this->viewer());
        assertSame(false,$next['messages'][0]['verification_more']);
    }
    public function testRecentDestinationPriorityFairnessAndQuietRevisionInvalidation(): void
    {
        [$profiles,$messages,$sync,$service,$db,$syncDb]=$this->fixture(115);
        // An active key at the end of fingerprint order gets the first selection.
        $recent=$profiles->query("SELECT substr(identity_id,9) FROM profiles WHERE username_token='alice' ORDER BY identity_id DESC LIMIT 1")->fetchColumn();
        $sync->recordActivity('alice',$recent,100);
        $selected=[];
        for($i=0;$i<4;$i++) {
            $work=$service->combinedWork($this->viewer(),['sender'])['work'][0];$selected[]=$work['target'];
            $service->acknowledge($this->viewer(),['checkpoint'=>$work['checkpoint'],'items'=>array_map(fn($r)=>['message_id'=>$r['message_id'],'digest'=>$r['digest']],$work['messages'])]);
        }
        assertSame($recent,$selected[0]);assertSame(true,$selected[3]!==$recent,'Fourth selection protects stable-order destinations');
        // Finish a donor pass without claiming readability on other keys.
        for($i=0;$i<700;$i++) {
            $result=$service->combinedWork($this->viewer());
            foreach($result['work'] as $work) {
                $items=array_map(fn($r)=>['message_id'=>$r['message_id'],'digest'=>$r['digest']],$work['messages']);
                if($items!==[] && $work['target']!==str_repeat('a',40))$sync->upload($work['account'],str_repeat('a',40),$work['target'],'cipher',$items);
                $service->acknowledge($this->viewer(),['mode'=>$work['mode'],'checkpoint'=>$work['checkpoint'],'items'=>$items]);
            }
            if(!$result['continuation'])break;
        }
        assertSame(true,$i<700);
        $idle=$service->combinedWork($this->viewer());
        assertSame([],$idle['work']);assertSame(false,$idle['continuation']);
        assertSame(['keys'=>0,'decisions'=>0],$service->budgetUsage());
        assertSame(true,strlen(json_encode($idle))<=8192);
        $sync->recordActivity('alice',$recent,200);
        assertSame(false,$service->combinedWork($this->viewer())['continuation'],'Foreground activity must not reopen settled cryptographic work');
        $messages->storeEnvelope('new','now','bob','alice','source','new cipher');
        assertSame(true,$service->combinedWork($this->viewer())['continuation'],'New originals reopen work');
    }
    public function testRevisionTracksRelevantRecoveryChangesWithoutRecipientActivity(): void
    {
        [$profiles,$messages,$sync,$service,$db,$syncDb]=$this->fixture();
        $method=new ReflectionMethod($service,'revision');$source=str_repeat('a',40);
        $revision=fn()=>$method->invoke($service,'alice',$source);
        $before=$revision();
        $sync->recordActivity('bob',str_repeat('c',40),100);
        $sync->acknowledge('bob',str_repeat('c',40),[['message_id'=>'m01','digest'=>hash('sha256','cipher-1')]]);
        assertSame($before,$revision(),'Recipient activity/coverage must not affect sender scheduling');
        $profiles->exec("UPDATE profiles SET post_count=2");
        assertSame($before,$revision(),'Unrelated public profile activity is not a key change');
        $profiles->exec("UPDATE profiles SET is_approved=0 WHERE public_key='KEY-b'");
        assertSame(true,$before!==$revision());$before=$revision();
        $transfer=$sync->upload('alice',str_repeat('c',40),$source,'cipher',[['message_id'=>'m02','digest'=>hash('sha256','cipher-2')]]);
        assertSame(true,$before!==$revision());$before=$revision();
        $syncDb->exec("DELETE FROM history_sync_transfers WHERE transfer_id='".$transfer."'");
        assertSame(true,$before!==$revision());$before=$revision();
        $db->exec("UPDATE private_messages SET encrypted_envelope='changed' WHERE message_id='m01'");
        assertSame(true,$before!==$revision());
    }
    public function testMissingDecryptionSkipsVerifierPagesWithoutCreatingAReceipt(): void
    {
        [,,$sync,$service,$db]=$this->fixture(115);
        $db->exec("DELETE FROM private_messages WHERE message_id!='m01'");
        $work=$service->combinedWork($this->viewer(),['sender'])['work'][0];
        assertSame(false,$work['messages'][0]['candidate_available']);
        assertSame(true,$work['messages'][0]['verification_more']);
        $item=['message_id'=>'m01','digest'=>hash('sha256','cipher-1')];
        $service->acknowledge($this->viewer(),['checkpoint'=>$work['checkpoint'],'unavailable'=>[$item]]);
        assertSame(null,$sync->receipt('alice',str_repeat('a',40),'m01',$item['digest']));
        $next=$service->combinedWork($this->viewer(),['sender'])['work'][0];
        assertSame(true,$next['target']!==$work['target']);
    }
    public function testActivityIsPrivateCoalescedAndNotPromotedBySync(): void
    {
        [,,$sync,$service]=$this->fixture();
        $source=str_repeat('a',40);
        $sync->recordActivity('alice',$source,100);
        $sync->recordActivity('alice',$source,103);
        assertSame(100,(int)$sync->recentActivity('alice')[0]['active_at']);
        $sync->recordActivity('alice',str_repeat('b',40),104);
        assertSame(str_repeat('b',40),$sync->recentActivity('alice')[0]['source']);
        $service->combinedWork($this->viewer());
        assertSame(100,(int)$sync->recentActivity('alice')[1]['active_at']);
        assertSame([],$sync->recentActivity('bob'));
        $sync->recordActivity('alice',$source,106);
        assertSame($source,$sync->recentActivity('alice')[0]['source']);
    }
    public function testLargeAccountFixtureAndConcurrentAdmission(): void
    {
        [$profiles]=$this->fixture(115);
        assertSame(115,(int)$profiles->query("SELECT count(*) FROM profiles WHERE username_token='alice'")->fetchColumn());
        $path=tempnam(sys_get_temp_dir(),'sync-admission-');
        $pdo=new PDO('sqlite:'.$path);new PrivateMessageHistorySyncStore($pdo);unset($pdo);
        try {
            foreach([false,true] as $sameKey) {
                $jobs=[];
                for($i=0;$i<8;$i++) {
                    // Storage contention is retryable at the API boundary. Retry
                    // only SQLITE_BUSY, preserving the fixed admission clock.
                    $code='require '.var_export(dirname(__DIR__).'/autoload.php',true).'; $p=new PDO("sqlite:".$argv[1]);$p->exec("PRAGMA busy_timeout=1000");'
                        .'function retryBusy($call){for($n=0;;$n++){try{return $call();}catch(PDOException $e){if(($e->errorInfo[1]??null)!==5 || $n>=4)throw $e;usleep(20000);}}}'
                        .'$s=retryBusy(fn()=>new ForumRewrite\\Messaging\\PrivateMessageHistorySyncStore($p));$n=0;for($i=0;$i<16;$i++)if(retryBusy(fn()=>$s->admit($argv[2],$argv[3],100.0))===0.0)$n++;echo $n;';
                    $proc=proc_open(['php','-r',$code,$path,$sameKey?'shared':'distinct',$sameKey?'one':'key'.$i],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
                    $jobs[]=[$proc,$pipes];
                }
                $total=0;$errors=[];
                foreach($jobs as [$proc,$pipes]) {$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($proc)!==0)$errors[]=$err;$total+=(int)$out;}
                if($errors!==[])throw new RuntimeException('Admission workers failed: '.implode("\n",$errors));
                assertSame($sameKey?16:64,$total);
            }
        } finally {unlink($path);}
    }
    public function testAdmissionIsRollingAndSharedAcrossKeys(): void
    {
        [,,,$service]=$this->fixture();
        for($i=0;$i<16;$i++) assertSame(0.0,$service->admit($this->viewer(),100.0));
        assertSame(10.0,$service->admit($this->viewer(),100.0));
        assertSame(0.5,$service->admit($this->viewer(),109.5));
        assertSame(0.0,$service->admit($this->viewer(),110.0));
        for($i=0;$i<63;$i++) assertSame(0.0,$service->admit($this->viewer('key'.$i),110.0));
        assertSame(10.0,$service->admit($this->viewer('fresh'),110.0));
        assertSame(0.0,$service->admit($this->viewer('c','bob'),110.0));
        assertSame(0.0,$service->admit($this->viewer('fresh'),120.0));
    }
    public function testCombinedDiscoveryIsBoundedAndPreservesNecessaryContinuation(): void
    {
        [$profiles,$messages,$sync,$service,$db,$syncDb]=$this->fixture();
        $work=$service->combinedWork($this->viewer());
        assertSame('combined',$work['mode']);assertSame(2,count($work['work']));
        assertSame(true,$work['continuation']);
        assertSame(['target'=>'','after_id'=>''],$sync->checkpoint('alice',str_repeat('a',40)),'Do not advance unprocessed work');
        foreach ($db->query("SELECT * FROM private_messages WHERE sender_username_token='alice' OR recipient_username_token='alice'")->fetchAll(PDO::FETCH_ASSOC) as $row) {
            foreach (['a','b'] as $key) $sync->acknowledge('alice',str_repeat($key,40),[['message_id'=>$row['message_id'],'digest'=>hash('sha256',$row['encrypted_envelope'])]]);
            if($row['sender_username_token']==='alice') $sync->upload('bob',str_repeat('a',40),str_repeat('c',40),'cipher',[['message_id'=>$row['message_id'],'digest'=>hash('sha256',$row['encrypted_envelope'])]]);
        }
        $idle=$service->combinedWork($this->viewer());
        assertSame([],$idle['work']);assertSame(false,$idle['continuation']);
        assertSame(['account','sender'],$idle['complete']);
        // Many covered pages must yield bounded continuation, without access acknowledgments.
        for($i=100;$i<220;$i++) {
            $messages->storeEnvelope('m'.$i,'now','bob','alice','source','cipher');
            foreach(['a','b'] as $key)$sync->acknowledge('alice',str_repeat($key,40),[['message_id'=>'m'.$i,'digest'=>hash('sha256','cipher')]]);
        }
        $partial=$service->combinedWork($this->viewer());
        assertSame([],$partial['work']);assertSame(true,$partial['continuation']);
        $count=0;
        do {$partial=$service->combinedWork($this->viewer(),$partial['complete']);assertSame([],$partial['work']);assertSame(true,++$count<10);} while($partial['continuation']);
        $profiles->exec("INSERT INTO profiles (identity_id,username_token,public_key,is_approved) VALUES ('openpgp:".str_repeat('f',40)."','alice','KEY-f',1)");
        $found=false;
        for($i=0;$i<10;$i++) {
            $new=$service->combinedWork($this->viewer());
            foreach($new['work'] as $bundle)if($bundle['target']===str_repeat('f',40))$found=true;
            if($found)break;
        }
        assertSame(true,$found,'Newly approved target must reopen work');
    }
    public function testContributionEvidenceIsDonorOwnedAndDoesNotPreventRepair(): void
    {
        [,,$sync,$service,,$db]=$this->fixture();
        $input=array_replace($this->uploadInput('bad'),['account'=>'bob','target'=>str_repeat('c',40)]);
        $bad=$service->upload($this->viewer(),$input);
        $discover=new ReflectionMethod($service,'senderWork');
        $before=$discover->invoke($service,$this->viewer(),true);
        assertSame(false,in_array('m01',array_column($before['messages'],'message_id'),true));
        assertSame(true,in_array('m01',array_column($service->work($this->viewer(),'sender')['messages'],'message_id'),true),'Legacy explicit discovery permits repair');
        $service->acknowledge($this->viewer('c','bob'),['items'=>[$input['items'][0]+$bad]]);
        assertSame($before,$discover->invoke($service,$this->viewer(),true));
        assertSame(true,in_array('m01',array_column($discover->invoke($service,$this->viewer('b'),true)['messages'],'message_id'),true),'Another donor is not suppressed');
        $db->exec('DELETE FROM history_sync_coverage');
        $input['ciphertext']=$this->uploadInput('good')['ciphertext'];
        $good=$service->upload($this->viewer(),$input);
        assertSame($good['transfer_id'],$service->transfers($this->viewer('c','bob'),['message_id'=>'m01'])['transfers'][0]['transfer_id']);
        $db->exec('DELETE FROM history_sync_transfers');
        assertSame(true,in_array('m01',array_column($discover->invoke($service,$this->viewer(),true)['messages'],'message_id'),true));
    }
    public function testUnreadEligibilityUsesAccountHistory(): void
    {
        [, $messages] = $this->fixture();
        assertSame(false, $messages->unreadStateFor('unused')['has_history']);
        assertSame(true, $messages->unreadStateFor('alice')['has_history']);
        assertSame(true, $messages->unreadStateFor('mallory')['has_history']);
        $messages->storeEnvelope('first', '2026-10-29', 'unused', 'newrecipient', 'source', 'cipher');
        assertSame(true, $messages->unreadStateFor('unused')['has_history']);
        assertSame(true, $messages->unreadStateFor('newrecipient')['has_history']);
    }
    private function viewer(string $key='a',string $account='alice'): array { return ['identity_id'=>'openpgp:'.str_repeat($key,40),'username_token'=>$account,'is_approved'=>1]; }
    public function testEligibilityUsesOriginalDirectionAndCurrentMembership(): void
    {
        [$profiles,$messages,$sync,$service]=$this->fixture();
        $eligible=new ReflectionMethod($service,'eligibleSource');
        $sent=$messages->historySyncMessage('alice','m01');
        assertSame('alice',$eligible->invoke($service,$sent,'bob',str_repeat('b',40))['source_account']);
        assertSame(null,$eligible->invoke($service,$sent,'alice',str_repeat('c',40)));
        assertSame(null,$eligible->invoke($service,$sent,'mallory',str_repeat('a',40)));
        assertSame(null,$eligible->invoke($service,$sent,'bob',str_repeat('d',40)));
        $received=$messages->historySyncMessage('alice','m02');
        assertSame(null,$eligible->invoke($service,$received,'bob',str_repeat('a',40)));
        $profiles->exec("UPDATE profiles SET is_approved=0 WHERE public_key='KEY-b'");
        assertSame(null,$eligible->invoke($service,$sent,'bob',str_repeat('b',40)));
    }
    public function testSharedCandidateItemsRecheckEachOriginalBeforeBrowserReuse(): void
    {
        [,,$sync,$service,$db]=$this->fixture();
        $input=array_replace($this->uploadInput(),['account'=>'bob','target'=>str_repeat('c',40)]);
        $input['items'][]=['message_id'=>'m03','digest'=>hash('sha256','cipher-3')];
        $service->upload($this->viewer('b'),$input);
        $viewer=$this->viewer('c','bob');
        $page=$service->transfers($viewer,['message_id'=>'m01']);
        assertSame(2,count($page['transfers'][0]['items']));
        $db->exec("UPDATE private_messages SET sender_username_token='mallory' WHERE message_id='m03'");
        $page=$service->transfers($viewer,['message_id'=>'m01']);
        assertSame([$input['items'][0]],$page['transfers'][0]['items'],'A valid requested original must not authorize unrelated bundle entries');
        assertTrue($service->budgetUsage()['decisions']<=160);
        assertSame(null,$sync->receipt('bob',str_repeat('c',40),'m01',$input['items'][0]['digest']));
    }
    public function testSenderTransferRecoveryRechecksEveryOriginalAndMembership(): void
    {
        [$profiles,$messages,$sync,$service,$db]=$this->fixture();
        $input=array_replace($this->uploadInput(),['account'=>'bob','target'=>str_repeat('c',40)]);
        $transfer=$service->upload($this->viewer('b'),$input);
        $page=$service->transfers($this->viewer('c','bob'),['message_id'=>'m01']);
        assertSame('alice',$page['transfers'][0]['source_account']);
        assertSame('KEY-b',$page['transfers'][0]['source_key']);
        assertSame(null,$sync->receipt('bob',str_repeat('c',40),'m01',$input['items'][0]['digest']));
        $receipt=$input['items'][0]+$transfer;
        $service->acknowledge($this->viewer('c','bob'),['items'=>[$receipt]]);
        assertSame($transfer['transfer_id'],$service->transfers($this->viewer('c','bob'),['message_id'=>'m01'])['transfers'][0]['transfer_id']);
        $bad=$input;$bad['items'][]=['message_id'=>'m02','digest'=>hash('sha256','cipher-2')];
        foreach([$bad,array_replace($input,['account'=>'mallory','target'=>str_repeat('d',40)])] as $attempt) {
            try {$service->upload($this->viewer('b'),$attempt);throw new Exception('Invalid direction/recipient accepted');} catch(InvalidArgumentException $expected) {}
        }
        $profiles->exec("UPDATE profiles SET is_approved=0 WHERE public_key='KEY-b'");
        assertSame([],$service->transfers($this->viewer('c','bob'),['message_id'=>'m01'])['transfers']);
        try {$service->acknowledge($this->viewer('c','bob'),['items'=>[$receipt]]);throw new Exception('Revoked donor acknowledged');} catch(InvalidArgumentException $expected) {}
        $profiles->exec("UPDATE profiles SET is_approved=1 WHERE public_key='KEY-b'");
        $profiles->exec("UPDATE profiles SET is_approved=0 WHERE public_key='KEY-c'");
        try {$service->upload($this->viewer('b'),$input);throw new Exception('Revoked target accepted');} catch(InvalidArgumentException $expected) {}
        assertSame('cipher-1',$messages->historySyncMessage('alice','m01')['encrypted_envelope']);
    }
    public function testSenderDiscoveryIsBoundedFairAndIndependentOfRecipientReceipts(): void
    {
        [$profiles,$messages,$sync,$service]=$this->fixture();
        $profiles->exec("INSERT INTO profiles (identity_id,username_token,public_key,is_approved) VALUES ('openpgp:".str_repeat('e',40)."','bob','KEY-e',1)");
        $messages->storeEnvelope('m04x','now','alice','mallory','source','other-recipient');
        $seen=[];$first=$service->work($this->viewer(),'sender');
        assertSame('bob',$first['account']);assertSame('alice',$first['source_account']);
        assertSame(false,array_key_exists('total',$first));
        $input=array_replace($this->uploadInput(),['account'=>'bob','target'=>str_repeat('c',40)]);
        $transfer=$service->upload($this->viewer(),$input);
        $service->acknowledge($this->viewer('c','bob'),['items'=>[$input['items'][0]+$transfer]]);
        assertSame($first,$service->work($this->viewer(),'sender'),'Recipient confirmation must not affect donor work');
        assertSame($transfer,$service->upload($this->viewer(),$input),'Upload response must not reveal confirmation');
        for($n=0;$n<100;$n++) {
            $work=$service->work($this->viewer(),'sender');
            assertSame(true,count($work['messages'])<=10);
            foreach($work['messages'] as $row) {
                assertSame('alice',$row['sender_username_token']);
                assertSame($work['account'],$row['recipient_username_token']);
                assertSame(false,array_key_exists('covered',$row));
                $seen[$row['message_id']][$work['target']]=true;
            }
            $service->acknowledge($this->viewer(),['mode'=>'sender','checkpoint'=>$work['checkpoint']]);
            if($work['cycle_end']) break;
        }
        assertSame(17,count($seen));assertSame(2,count($seen['m01']));assertSame(2,count($seen['m31']));
        assertSame([str_repeat('d',40)],array_keys($seen['m04x']));
        assertSame(['target'=>'','after_id'=>''],$sync->checkpoint('alice',str_repeat('a',40)),'Account scan remains isolated');
        $messages->storeEnvelope('m00','now','alice','bob','old-key','stale-key-cipher');
        assertSame('m00',$service->work($this->viewer(),'sender')['messages'][0]['message_id']);
        $sync->advance('alice',str_repeat('a',40),'','missing-after-restore',true);
        assertSame('m00',$service->work($this->viewer(),'sender')['messages'][0]['message_id']);
        $profiles->exec("UPDATE profiles SET is_approved=0 WHERE username_token='bob'");
        $empty=$service->work($this->viewer(),'sender');assertSame([],$empty['messages']);
        $service->acknowledge($this->viewer(),['mode'=>'sender','checkpoint'=>$empty['checkpoint']]);
        assertSame('mallory',$service->work($this->viewer(),'sender')['account']);
        try {$service->work($this->viewer(),[]);throw new Exception('Invalid mode accepted');} catch(InvalidArgumentException $expected) {}
    }
    public function testSenderRecoverySurvivesBadCandidatesRestoreAndRevocation(): void
    {
        [$profiles,$messages,$sync,$service,$db,$syncDb]=$this->fixture();
        $input=array_replace($this->uploadInput('bad'),['account'=>'bob','target'=>str_repeat('c',40)]);
        $service->upload($this->viewer(),$input);
        $input['ciphertext']=$this->uploadInput('good')['ciphertext'];
        $good=$service->upload($this->viewer(),$input);
        assertSame($good['transfer_id'],$service->transfers($this->viewer('c','bob'),['message_id'=>'m01'])['transfers'][0]['transfer_id']);
        $service->acknowledge($this->viewer('c','bob'),['items'=>[$input['items'][0]+$good]]);
        $work=$service->work($this->viewer('c','bob'));
        assertSame(false,in_array('m01',array_column($work['messages'],'message_id'),true));
        $profiles->exec("UPDATE profiles SET is_approved=0 WHERE public_key='KEY-a'");
        assertSame(true,in_array('m01',array_column($service->work($this->viewer('c','bob'))['messages'],'message_id'),true));
        try {$service->upload($this->viewer(),$input);throw new Exception('Revoked source uploaded');} catch(RuntimeException $expected) {}
        $profiles->exec("UPDATE profiles SET is_approved=1 WHERE public_key='KEY-a'");
        $syncDb->exec('DELETE FROM history_sync_transfers');
        assertSame(true,in_array('m01',array_column($service->work($this->viewer('c','bob'))['messages'],'message_id'),true));
        $fresh=$service->upload($this->viewer(),$input);
        assertSame($fresh['transfer_id'],$service->transfers($this->viewer('c','bob'),['message_id'=>'m01'])['transfers'][0]['transfer_id']);
        $db->exec("UPDATE private_messages SET encrypted_envelope='different capture' WHERE message_id='m01'");
        assertSame([],$service->transfers($this->viewer('c','bob'),['message_id'=>'m01'])['transfers']);
        try {$service->acknowledge($this->viewer('c','bob'),['items'=>[$input['items'][0]+$fresh]]);throw new Exception('Changed original acknowledged');} catch(InvalidArgumentException $expected) {}
        $db->exec("DELETE FROM private_messages WHERE message_id='m01'");
        try {$service->transfers($this->viewer('c','bob'),['message_id'=>'m01']);throw new Exception('Missing original exposed');} catch(InvalidArgumentException $expected) {}
    }
    public function testBoundedDiscoveryFindsExistingAndNewlyApprovedKeysWithoutQueue(): void
    {
        [$profiles,$messages,$sync,$service]=$this->fixture();
        $seen=[];
        for($i=0;$i<4;$i++) {
            $page=$service->work($this->viewer());
            assertSame(31,$page['total']);assertSame(true,count($page['messages'])<=10);
            $seen=array_merge($seen,array_column($page['messages'],'message_id'));
            $sync->advance('alice',str_repeat('a',40),$page['checkpoint']['target'],$page['checkpoint']['after_id']);
        }
        assertSame(31,count(array_unique($seen)));assertSame(false,in_array('foreign',$seen,true));
        assertSame(str_repeat('b',40),$service->work($this->viewer())['target']);
        $profiles->exec("UPDATE profiles SET is_approved=0 WHERE public_key='KEY-b'");
        assertSame(str_repeat('a',40),$service->work($this->viewer())['target']);
        $profiles->exec("UPDATE profiles SET is_approved=1 WHERE public_key='KEY-b'");
        assertSame(10,count($service->work($this->viewer('b'))['messages']));
        try { $service->work($this->viewer('d')); throw new Exception('Foreign key accepted'); } catch (RuntimeException $expected) {}
    }
    public function testMissingCheckpointAnchorResetsAndDoesNotLoseOriginalHistory(): void
    {
        [$profiles,$messages,$sync,$service]=$this->fixture();
        $sync->advance('alice',str_repeat('a',40),str_repeat('a',40),'missing-after-restore');
        assertSame('m01',$service->work($this->viewer())['messages'][0]['message_id']);
        assertSame(31,$messages->historySyncCount('alice'));
    }
    public function testSeparateDatabaseConfigurationRejectsAliasesAndKeepsMessageStoreIndependent(): void
    {
        $root=sys_get_temp_dir().'/history-config-'.bin2hex(random_bytes(5));mkdir($root,0700);
        try {
            assertSame($root.'/state/private/message_history_sync.sqlite3',PrivateMessageHistorySyncDatabaseConfig::path($root));
            $messagePath=$root.'/messages.sqlite3';
            $messages=new PrivateMessageStore(new PDO('sqlite:'.$messagePath));
            foreach ([$messagePath,$root.'/./messages.sqlite3'] as $path) {
                try { PrivateMessageHistorySyncDatabaseConfig::open($root,['PRIVATE_MESSAGE_DATABASE_PATH'=>$messagePath,'PRIVATE_MESSAGE_HISTORY_SYNC_DATABASE_PATH'=>$path]); throw new Exception('Shared path accepted'); } catch (RuntimeException $expected) {}
            }
            link($messagePath,$root.'/alias.sqlite3');
            try { PrivateMessageHistorySyncDatabaseConfig::open($root,['PRIVATE_MESSAGE_DATABASE_PATH'=>$messagePath,'PRIVATE_MESSAGE_HISTORY_SYNC_DATABASE_PATH'=>$root.'/alias.sqlite3']); throw new Exception('Hardlink accepted'); } catch (RuntimeException $expected) {}
            $pdo=PrivateMessageHistorySyncDatabaseConfig::open($root,['PRIVATE_MESSAGE_DATABASE_PATH'=>$messagePath,'PRIVATE_MESSAGE_HISTORY_SYNC_DATABASE_PATH'=>$root.'/sync.sqlite3']);
            new PrivateMessageHistorySyncStore($pdo);
            $messages->storeEnvelope('ok','now','alice','bob','source','cipher');
            assertSame(1,$messages->historySyncCount('alice'));
            assertSame(1000,(int)$pdo->query('PRAGMA busy_timeout')->fetchColumn());
        } finally { foreach(glob($root.'/*') as $file) unlink($file);rmdir($root); }
    }
    private function uploadInput(string $cipher='one'): array
    {
        return ['target'=>str_repeat('b',40),'ciphertext'=>"-----BEGIN PGP MESSAGE-----\n".$cipher."\n-----END PGP MESSAGE-----",
            'items'=>[['message_id'=>'m01','digest'=>hash('sha256','cipher-1')]]];
    }
    public function testTransfersStayRetainedAndUploadsCannotClaimRecovery(): void
    {
        [$profiles,$messages,$sync,$service,$db,$syncDb]=$this->fixture();
        $input=$this->uploadInput();
        $first=$service->upload($this->viewer(),$input);
        assertSame($first,$service->upload($this->viewer(),$input));
        assertSame(null,$sync->receipt('alice',str_repeat('b',40),'m01',hash('sha256','cipher-1')));
        $page=$service->transfers($this->viewer('b'),['message_id'=>'m01']);
        assertSame(1,count($page['transfers']));
        $item=$input['items'][0]+['transfer_id'=>$first['transfer_id']];
        $service->acknowledge($this->viewer('b'),['items'=>[$item]]);
        $service->upload($this->viewer(),$this->uploadInput('replacement'));
        assertSame($first['transfer_id'],$service->transfers($this->viewer('b'),['message_id'=>'m01'])['transfers'][0]['transfer_id']);
        assertSame(1,(int)$syncDb->query('SELECT COUNT(*) FROM history_sync_transfers')->fetchColumn());
        $sync->advance('alice',str_repeat('a',40),str_repeat('b',40),'');
        assertSame(false,in_array('m01',array_column($service->work($this->viewer())['messages'],'message_id'),true));
        $syncDb->exec('DELETE FROM history_sync_transfers');
        assertSame(true,in_array('m01',array_column($service->work($this->viewer())['messages'],'message_id'),true));
        assertSame(31,$messages->historySyncCount('alice'));
    }
    public function testRejectsCrossAccountRevokedMalformedAndChangedOriginals(): void
    {
        [$profiles,$messages,$sync,$service,$db]=$this->fixture();
        $input=$this->uploadInput();
        $bad=[array_replace($input,['target'=>str_repeat('d',40)]),array_replace($input,['ciphertext'=>str_repeat('x',65537)]),array_replace($input,['items'=>[['message_id'=>'foreign','digest'=>hash('sha256','secret')]]]),array_replace($input,['items'=>array_fill(0,11,$input['items'][0])])];
        foreach($bad as $attempt) { try { $service->upload($this->viewer(),$attempt);throw new Exception('Invalid upload accepted'); } catch(InvalidArgumentException $expected) {} }
        $transfer=$service->upload($this->viewer(),$input);
        try { $service->acknowledge($this->viewer(),['items'=>[$input['items'][0]+['transfer_id'=>$transfer['transfer_id']]]]);throw new Exception('Wrong target acknowledged'); } catch(InvalidArgumentException $expected) {}
        $profiles->exec("UPDATE profiles SET is_approved=0 WHERE public_key='KEY-a'");
        assertSame([],$service->transfers($this->viewer('b'),['message_id'=>'m01'])['transfers']);
        try { $service->upload($this->viewer(),$input);throw new Exception('Revoked source accepted'); } catch(RuntimeException $expected) {}
        $profiles->exec("UPDATE profiles SET is_approved=1 WHERE public_key='KEY-a'");
        $db->exec("UPDATE private_messages SET encrypted_envelope='restored-different' WHERE message_id='m01'");
        assertSame([],$service->transfers($this->viewer('b'),['message_id'=>'m01'])['transfers']);
        try { $service->upload($this->viewer(),$input);throw new Exception('Changed digest accepted'); } catch(InvalidArgumentException $expected) {}
    }
    public function testUnconfirmedBadCandidateCanBeReplacedAndDirectReceiptsAreIdempotent(): void
    {
        [$profiles,$messages,$sync,$service,$db,$syncDb]=$this->fixture();
        $service->upload($this->viewer(),$this->uploadInput('bad'));
        $good=$service->upload($this->viewer(),$this->uploadInput('good'));
        assertSame($good['transfer_id'],$service->transfers($this->viewer('b'),['message_id'=>'m01'])['transfers'][0]['transfer_id']);
        assertSame(1,(int)$syncDb->query('SELECT COUNT(*) FROM history_sync_transfers')->fetchColumn());
        $receipt=['items'=>$this->uploadInput()['items']];
        assertSame(['confirmed'=>1],$service->acknowledge($this->viewer(),$receipt));
        assertSame(['confirmed'=>1],$service->acknowledge($this->viewer(),$receipt));
        assertSame(false,in_array('m01',array_column($service->work($this->viewer())['messages'],'message_id'),true));
    }
    public function testMismatchedRestoresReopenCoverageAndAllowFreshDonations(): void
    {
        [$profiles,$messages,$sync,$service,$db,$syncDb]=$this->fixture();
        $input=$this->uploadInput();
        $transfer=$service->upload($this->viewer(),$input);
        $service->acknowledge($this->viewer('b'),['items'=>[$input['items'][0]+$transfer]]);
        // A partial sync restore can leave a receipt/index with no retained ciphertext.
        $syncDb->exec('DELETE FROM history_sync_transfers');
        $replacement=$service->upload($this->viewer(),$this->uploadInput('fresh after lost bundle'));
        assertSame($replacement['transfer_id'],$service->transfers($this->viewer('b'),['message_id'=>'m01'])['transfers'][0]['transfer_id']);
        $service->acknowledge($this->viewer('b'),['items'=>[$input['items'][0]+$replacement]]);
        // The other database may be restored to a different envelope under the same stable ID.
        $db->exec("UPDATE private_messages SET encrypted_envelope='different capture' WHERE message_id='m01'");
        $changed=$this->uploadInput('fresh after different envelope');
        $changed['items'][0]['digest']=hash('sha256','different capture');
        $replacement=$service->upload($this->viewer(),$changed);
        assertSame($replacement['transfer_id'],$service->transfers($this->viewer('b'),['message_id'=>'m01'])['transfers'][0]['transfer_id']);
        $db->exec("DELETE FROM private_messages WHERE message_id='m01'");
        try {$service->transfers($this->viewer('b'),['message_id'=>'m01']);throw new Exception('Orphan exposed');} catch(InvalidArgumentException $expected) {}
    }
    public function testPartialDonorsRevisitGapsAndStaleKeyMessagesWithoutPermanentFailures(): void
    {
        [$profiles,$messages,$sync,$service]=$this->fixture();
        $profiles->exec("UPDATE profiles SET username_token='alice' WHERE public_key='KEY-d'");
        $target=str_repeat('b',40);
        // Two sources cover disjoint portions; nothing is covered until the target verifies it.
        foreach(['a'=>[1,3,5],'d'=>[2,4,6]] as $donor=>$ids) foreach($ids as $n) {
            $input=$this->uploadInput('partial-'.$n);
            $input['items']=[['message_id'=>sprintf('m%02d',$n),'digest'=>hash('sha256','cipher-'.$n)]];
            $transfer=$service->upload($this->viewer($donor),$input);
            $service->acknowledge($this->viewer('b'),['items'=>[$input['items'][0]+$transfer]]);
        }
        $sync->advance('alice',str_repeat('a',40),$target,'');
        assertSame(['m07','m08','m09','m10'],array_column($service->work($this->viewer())['messages'],'message_id'));
        // New work sorting behind the checkpoint is eventually reached after rotation.
        $messages->storeEnvelope('m00','later','bob','alice','old-key','stale-key-cipher');
        $seen=[];
        for($i=0;$i<13;$i++) {
            $page=$service->work($this->viewer());
            if($page['target']===$target) $seen=array_merge($seen,array_column($page['messages'],'message_id'));
            $service->acknowledge($this->viewer(),['items'=>[],'checkpoint'=>$page['checkpoint']]);
        }
        assertSame(true,in_array('m00',$seen,true));assertSame(true,in_array('m31',$seen,true));
    }
    public function testConcurrentSyncWritesLeaveForegroundMessageStoreResponsive(): void
    {
        $root=sys_get_temp_dir().'/history-concurrent-'.bin2hex(random_bytes(5));mkdir($root,0700);
        $children=[];
        try {
            $syncPath=$root.'/sync.sqlite3';
            $sync=new PrivateMessageHistorySyncStore(new PDO('sqlite:'.$syncPath));
            $store=new PrivateMessageStore(new PDO('sqlite:'.$root.'/messages.sqlite3'));
            $worker='require '.var_export(dirname(__DIR__).'/autoload.php',true).';'
                .'$pdo=new PDO("sqlite:".$argv[1]);$pdo->exec("PRAGMA busy_timeout=1000");'
                .'function retryBusy($call){for($n=0;;$n++){try{return $call();}catch(PDOException $e){if(($e->errorInfo[1]??null)!==5 || $n>=4)throw $e;usleep(20000);}}}'
                .'$store=retryBusy(fn()=>new ForumRewrite\\Messaging\\PrivateMessageHistorySyncStore($pdo));'
                .'for($i=0;$i<30;$i++){ retryBusy(fn()=>$store->upload("alice",$argv[2],"target",str_repeat("c",8192).$i,[["message_id"=>"m1","digest"=>str_repeat("a",64)]]));usleep(10000);}';
            for($i=0;$i<3;$i++) { $proc=proc_open([PHP_BINARY,'-r',$worker,$syncPath,'source'.$i],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$children[]=[$proc,$pipes]; }
            $latencies=[];
            for($i=0;$i<60;$i++) {
                $start=hrtime(true);
                $store->storeEnvelope('m'.$i,'now','bob','alice','key','cipher');$store->unreadStateFor('alice');
                $latencies[]=(hrtime(true)-$start)/1e6;usleep(10000);
            }
            $errors=[];
            foreach($children as [$proc,$pipes]) { $out=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($proc)!==0)$errors[]=$out; }
            if($errors!==[])throw new RuntimeException('Sync writers failed: '.implode("\n",$errors));
            sort($latencies);assertSame(true,$latencies[56]<250,'Local foreground p95 exceeded 250 ms: '.$latencies[56]);
            assertSame(60,$store->historySyncCount('alice'));
        } finally { foreach(glob($root.'/*') as $file) unlink($file);rmdir($root); }
    }
}
