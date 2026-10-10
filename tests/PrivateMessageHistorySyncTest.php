<?php
declare(strict_types=1);
require_once __DIR__.'/../autoload.php';
use ForumRewrite\Messaging\{PrivateMessageStore,PrivateMessageHistorySyncStore,PrivateMessageHistorySyncService,PrivateMessageHistorySyncDatabaseConfig};
final class PrivateMessageHistorySyncTest
{
    private function fixture(): array
    {
        $profiles=new PDO('sqlite::memory:');
        $profiles->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
        $profiles->exec('CREATE TABLE profiles (identity_id TEXT PRIMARY KEY, profile_slug TEXT, username TEXT, username_token TEXT, fallback_label TEXT, signer_fingerprint TEXT, bootstrap_post_id TEXT, bootstrap_thread_id TEXT, public_key TEXT, is_approved INTEGER, approved_by_identity_id TEXT, approved_by_profile_slug TEXT, approved_by_label TEXT, post_count INTEGER, thread_count INTEGER)');
        foreach (['a'=>'alice','b'=>'alice','c'=>'bob','d'=>'mallory'] as $key=>$account) {
            $fp=str_repeat($key,40);
            $q=$profiles->prepare('INSERT INTO profiles (identity_id,profile_slug,username,username_token,public_key,is_approved) VALUES (?,?,?,?,?,1)');
            $q->execute(['openpgp:'.$fp,'openpgp-'.$fp,$account,$account,'KEY-'.$key]);
        }
        $db=new PDO('sqlite::memory:');$messages=new PrivateMessageStore($db);
        for($i=1;$i<=31;$i++) $messages->storeEnvelope(sprintf('m%02d',$i),'2026-10-10',$i%2?'alice':'bob',$i%2?'bob':'alice','source','cipher-'.$i);
        $messages->storeEnvelope('foreign','2026-10-10','mallory','bob','source','secret');
        $syncDb=new PDO('sqlite::memory:');$sync=new PrivateMessageHistorySyncStore($syncDb);
        return [$profiles,$messages,$sync,new PrivateMessageHistorySyncService($messages,$sync,$profiles),$db,$syncDb];
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
                .'$store=new ForumRewrite\\Messaging\\PrivateMessageHistorySyncStore($pdo);'
                .'for($i=0;$i<30;$i++){ $store->upload("alice",$argv[2],"target",str_repeat("c",8192).$i,[["message_id"=>"m1","digest"=>str_repeat("a",64)]]);usleep(10000);}';
            for($i=0;$i<3;$i++) { $proc=proc_open([PHP_BINARY,'-r',$worker,$syncPath,'source'.$i],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$children[]=[$proc,$pipes]; }
            $latencies=[];
            for($i=0;$i<60;$i++) {
                $start=hrtime(true);
                $store->storeEnvelope('m'.$i,'now','bob','alice','key','cipher');$store->unreadStateFor('alice');
                $latencies[]=(hrtime(true)-$start)/1e6;usleep(10000);
            }
            foreach($children as [$proc,$pipes]) { $out=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);assertSame(0,proc_close($proc),$out); }
            sort($latencies);assertSame(true,$latencies[56]<250,'Local foreground p95 exceeded 250 ms: '.$latencies[56]);
            assertSame(60,$store->historySyncCount('alice'));
        } finally { foreach(glob($root.'/*') as $file) unlink($file);rmdir($root); }
    }
}
