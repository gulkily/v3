<?php
declare(strict_types=1);
namespace ForumRewrite\Messaging;
use PDO;
use RuntimeException;
use InvalidArgumentException;
final class PrivateMessageHistorySyncService
{
    public const BATCH_SIZE = 10;
    public function __construct(private readonly PrivateMessageStore $messages, private readonly PrivateMessageHistorySyncStore $sync, private readonly PDO $profiles) {}
    private function context(array $viewer): array
    {
        $account=strtolower(trim((string)($viewer['username_token']??'')));
        $source=strtolower(substr((string)($viewer['identity_id']??''),8));
        $keys=$this->keys($account);
        if (($viewer['is_approved']??0)!=1 || !str_starts_with((string)($viewer['identity_id']??''),'openpgp:') || !isset($keys[$source])) throw new RuntimeException('An approved authenticated account key is required.');
        return [$account,$source,$keys];
    }
    private function keys(string $account): array
    {
        $keys=[];
        foreach ((new ApprovedUserKeyResolver())->keysForUsernameToken($this->profiles,$account) as $key) {
            $fp=strtolower(substr($key['identity_id'],8));
            if (str_starts_with($key['identity_id'],'openpgp:') && preg_match('/^(?:[a-f0-9]{40}|[a-f0-9]{64})$/D',$fp)) $keys[$fp]=$key['public_key'];
        }
        ksort($keys);
        return $keys;
    }
    // Derive authorization from the original, never from donor-supplied account claims.
    private function eligibleSource(array $message, string $destinationAccount, string $sourceFingerprint): ?array
    {
        if (!in_array($destinationAccount, [$message['sender_username_token'], $message['recipient_username_token']], true)) return null;
        $accounts=[$destinationAccount];
        if ($message['recipient_username_token']===$destinationAccount) $accounts[]=$message['sender_username_token'];
        foreach (array_unique($accounts) as $account) {
            $keys=$this->keys($account);
            if (isset($keys[$sourceFingerprint])) return ['source_account'=>$account, 'source_key'=>$keys[$sourceFingerprint]];
        }
        return null;
    }
    public function work(array $viewer): array
    {
        [$account,$source,$keys]=$this->context($viewer);
        $position=$this->sync->checkpoint($account,$source);
        $targets=array_keys($keys);
        $target=isset($keys[$position['target']])?$position['target']:$targets[0];
        $after=$target===$position['target']?$position['after_id']:'';
        if ($after!=='' && $this->messages->historySyncMessage($account,$after)===null) $after='';
        $page=$this->messages->historySyncPage($account,$after,self::BATCH_SIZE);
        $next=$page['more']?['target'=>$target,'after_id'=>$page['last']]:['target'=>$targets[(array_search($target,$targets,true)+1)%count($targets)],'after_id'=>''];
        $messages=$page['messages'];
        $senderKeys=[];
        foreach ($messages as &$message) {
            $sender=$message['sender_username_token'];
            $senderKeys[$sender]??=array_values($this->keys($sender));
            $message['sender_keys']=$senderKeys[$sender];
            $message['digest']=hash('sha256',$message['encrypted_envelope']);
            $receipt=$this->sync->receipt($account,$target,$message['message_id'],$message['digest']);
            $message['covered']=$receipt!==null && ($receipt['transfer_id']==='' || isset($keys[$receipt['source']??'']));
        }
        unset($message);
        return ['account'=>$account,'source'=>$source,'target'=>$target,'target_key'=>$keys[$target], 'messages'=>array_values(array_filter($messages,static fn($row)=>!$row['covered'])),
            'checkpoint'=>$next,'cycle_end'=>!$page['more'] && $next['target']===$targets[0], 'total'=>$this->messages->historySyncCount($account)];
    }
    private function items(string $account, mixed $items): array
    {
        if(!is_array($items) || !array_is_list($items) || count($items)>self::BATCH_SIZE) throw new InvalidArgumentException('Invalid history batch.');
        $seen=[];
        foreach($items as &$item) {
            if(!is_array($item) || !is_string($item['message_id']??null) || !is_string($item['digest']??null) || isset($seen[$item['message_id']])) throw new InvalidArgumentException('Invalid history item.');
            $row=$this->messages->historySyncMessage($account,$item['message_id']);
            if(!$row || !hash_equals(hash('sha256',$row['encrypted_envelope']),$item['digest'])) throw new InvalidArgumentException('History changed. Retry synchronization.');
            $seen[$item['message_id']]=true;
            $item=['message_id'=>$item['message_id'],'digest'=>$item['digest'],'transfer_id'=>$item['transfer_id']??''];
            if(!is_string($item['transfer_id'])) throw new InvalidArgumentException('Invalid transfer reference.');
        }
        unset($item);return $items;
    }
    public function upload(array $viewer, array $input): array
    {
        [$account,$source,$keys]=$this->context($viewer);
        $target=$input['target']??null;$ciphertext=$input['ciphertext']??null;
        if(!is_string($target) || !isset($keys[$target]) || $target===$source) throw new InvalidArgumentException('Target must be another approved account key.');
        if(!is_string($ciphertext) || strlen($ciphertext)>65536 || !str_starts_with($ciphertext,'-----BEGIN PGP MESSAGE-----') || !str_contains($ciphertext,'-----END PGP MESSAGE-----')) throw new InvalidArgumentException('Invalid encrypted history transfer.');
        $items=$this->items($account,$input['items']??null);
        if($items===[]) throw new InvalidArgumentException('Transfer is empty.');
        $items=array_map(static fn($item)=>['message_id'=>$item['message_id'],'digest'=>$item['digest']],$items);
        return ['transfer_id'=>$this->sync->upload($account,$source,$target,$ciphertext,$items)];
    }
    public function transfers(array $viewer, array $query): array
    {
        [$account,$target,$keys]=$this->context($viewer);
        $id=$query['message_id']??null;$after=$query['after']??'';
        if(!is_string($id) || strlen($id)>128 || !is_string($after) || ($after!=='' && !preg_match('/^[a-f0-9]{64}$/D',$after))) throw new InvalidArgumentException('Invalid transfer query.');
        $row=$this->messages->historySyncMessage($account,$id);
        if(!$row) throw new InvalidArgumentException('History message unavailable.');
        $digest=hash('sha256',$row['encrypted_envelope']);
        $rows=$this->sync->candidates($account,$target,$id,$digest,$after);
        $more=count($rows)>10;$rows=array_slice($rows,0,10);
        $next=$more?$rows[count($rows)-1]['transfer_id']:null;
        $rows=array_values(array_filter($rows,static fn($r)=>isset($keys[$r['source']])));
        foreach($rows as &$r) $r['source_key']=$keys[$r['source']];
        unset($r);
        return ['account'=>$account,'target'=>$target,'message_id'=>$id,'digest'=>$digest,'transfers'=>$rows,'next'=>$next];
    }
    public function acknowledge(array $viewer, array $input): array
    {
        [$account,$source,$keys]=$this->context($viewer);
        $items=$this->items($account,$input['items']??[]);
        foreach($items as $item) {
            if($item['transfer_id']==='') continue; // authenticated target reports verified direct access
            $candidate=$this->sync->candidate($account,$source,$item['message_id'],$item['digest'],$item['transfer_id']);
            if(!$candidate || !isset($keys[$candidate['source']])) throw new InvalidArgumentException('Transfer is no longer eligible.');
        }
        $checkpoint=$input['checkpoint']??null;
        if($checkpoint!==null && (!is_array($checkpoint) || !is_string($checkpoint['target']??null) || !isset($keys[$checkpoint['target']]) || !is_string($checkpoint['after_id']??null) || strlen($checkpoint['after_id'])>128)) throw new InvalidArgumentException('Invalid scan checkpoint.');
        if($items!==[]) $this->sync->acknowledge($account,$source,$items);
        if($checkpoint!==null) $this->sync->advance($account,$source,$checkpoint['target'],$checkpoint['after_id']);
        return ['confirmed'=>count($items)];
    }
}
