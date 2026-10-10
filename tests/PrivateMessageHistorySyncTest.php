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
}
