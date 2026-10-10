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
        }
        unset($message);
        return ['account'=>$account,'source'=>$source,'target'=>$target,'target_key'=>$keys[$target], 'messages'=>$messages,
            'checkpoint'=>$next,'cycle_end'=>!$page['more'] && $next['target']===$targets[0], 'total'=>$this->messages->historySyncCount($account)];
    }
}
