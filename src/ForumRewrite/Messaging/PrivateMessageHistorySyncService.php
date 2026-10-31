<?php
declare(strict_types=1);
namespace ForumRewrite\Messaging;
use PDO;
use RuntimeException;
use InvalidArgumentException;
final class PrivateMessageHistorySyncService
{
    public const BATCH_SIZE = 10;
    private array $keyCache=[];
    private int $keyLoads=0;
    private int $decisions=0;
    private function resetBudget(): void { $this->keyCache=[];$this->keyLoads=0;$this->decisions=0; }
    public function budgetUsage(): array { return ['keys'=>$this->keyLoads,'decisions'=>$this->decisions]; }
    private function key(string $account, string $fingerprint): ?string
    {
        if (!preg_match('/^(?:[a-f0-9]{40}|[a-f0-9]{64})$/D',$fingerprint)) return null;
        $id=$account.':'.$fingerprint;
        if (array_key_exists($id,$this->keyCache)) {
            $q=$this->profiles->prepare('SELECT 1 FROM profiles WHERE identity_id=? AND username_token=? AND is_approved=1');
            $q->execute(['openpgp:'.$fingerprint,$account]);
            return $q->fetchColumn()===false?null:$this->keyCache[$id];
        }
        if ($this->keyLoads>=64) throw new RuntimeException('History key budget exhausted. Retry bounded discovery.');
        $q=$this->profiles->prepare('SELECT substr(public_key,1,1048577) FROM profiles WHERE identity_id=? AND username_token=? AND is_approved=1');
        $q->execute(['openpgp:'.$fingerprint,$account]);
        $key=$q->fetchColumn();$this->keyLoads++;
        if (is_string($key) && strlen($key)>1048576) throw new RuntimeException('Public key exceeds the canonical armor limit.');
        return $this->keyCache[$id]=$key===false || trim($key)===''?null:$key;
    }
    private function nextKey(string $account, string $after = ''): ?string
    {
        $q=$this->profiles->prepare("SELECT identity_id FROM profiles WHERE username_token=? AND is_approved=1 AND identity_id>? AND identity_id LIKE 'openpgp:%' AND public_key!='' ORDER BY identity_id LIMIT 1");
        $q->execute([$account,'openpgp:'.$after]);
        $id=$q->fetchColumn();return $id===false?null:substr($id,8);
    }
    private array $verificationTokens=[];
    private bool $verificationMore=false;
    private function boundedMessages(array $rows, string $account, string $source, string $mode, string $target): array
    {
        $messages=[];$bytes=0;$this->verificationTokens=[];$this->verificationMore=false;
        $state=$this->sync->verification($account,$source,$mode);
        $updated=[];
        foreach($rows as $row) {
            if($this->decisions>=160 || $this->keyLoads>=48) break;
            $this->decisions++;
            $digest=hash('sha256',$row['encrypted_envelope']);
            $binding=hash('sha256',$target.':'.$row['message_id'].':'.$digest);
            $prior=$state[$binding]??['after'=>''];
            $page=$this->verificationKeys($row,$prior['after']);
            $row['sender_keys']=$page['keys'];$row['digest']=$digest;
            $row['verification_more']=$page['more'];
            $row['candidate_available']=$this->sync->hasCandidate($account,$source,$row['message_id'],$digest);
            $size=strlen(json_encode($row,JSON_THROW_ON_ERROR));
            // Canonical armor is ASCII; reserve a maximum target key and framing.
            if ($bytes+$size>3*1048576-32768) break;
            $messages[]=$row;$bytes+=$size;
            $token=hash('sha256',$binding.':'.$prior['after'].':'.$page['after']);
            $updated[$binding]=['after'=>$prior['after'],'pending'=>$page['after'],'more'=>$page['more'],'token'=>$token,'id'=>$row['message_id'],'target'=>$target];
            $this->verificationTokens[$binding]=$token;
            $this->verificationMore=$this->verificationMore || $page['more'];
        }
        $this->sync->saveVerification($account,$source,$mode,$updated);
        return $messages;
    }
    private function verificationKeys(array $message, string $after): array
    {
        $account=$message['sender_username_token'];$keys=[];$bytes=0;
        $hint=str_starts_with($message['sender_identity_id']??'','openpgp:')?substr($message['sender_identity_id'],8):'';
        if ($after==='' && $hint!=='') {
            $key=$this->key($account,$hint);
            if($key!==null) { $keys[]=$key;$bytes=strlen(json_encode($key,JSON_THROW_ON_ERROR)); }
        }
        // Keep the hint first, but never treat it as proof of the encrypted signer.
        $cursor=$after;
        while(count($keys)<8 && $this->keyLoads<48) {
            $next=$this->nextKey($account,$cursor);
            if($next===null) return ['keys'=>$keys,'after'=>$cursor,'more'=>false];
            if($next===$hint) {$cursor=$next;continue;}
            $key=$this->key($account,$next);
            $size=strlen(json_encode($key,JSON_THROW_ON_ERROR));
            if($bytes+$size>1048576+32768)break;
            if($key!==null){$keys[]=$key;$bytes+=$size;}
            $cursor=$next;
        }
        return ['keys'=>$keys,'after'=>$cursor,'more'=>$this->nextKey($account,$cursor)!==null];
    }
    public function __construct(private readonly PrivateMessageStore $messages, private readonly PrivateMessageHistorySyncStore $sync, private readonly PDO $profiles) {}
    public function admit(array $viewer, ?float $now = null): float
    {
        return $this->sync->admit(strtolower((string)$viewer['username_token']), (string)$viewer['identity_id'], $now ?? microtime(true));
    }
    private function revision(string $account, string $source): string
    {
        return hash('sha256',json_encode([$this->messages->historyRevision($account),PrivateMessageHistoryRevision::token($this->profiles,'history_keys','*'),$this->sync->arrivalRevision($account,$source),$this->sync->donorRevision($source)],JSON_THROW_ON_ERROR));
    }
    private function hasKey(string $account, string $fingerprint): bool
    {
        $q=$this->profiles->prepare("SELECT 1 FROM profiles WHERE username_token=? AND identity_id=? AND is_approved=1 AND public_key!=''");
        $q->execute([$account,'openpgp:'.$fingerprint]);return $q->fetchColumn()!==false;
    }
    private function scheduledTarget(string $account, string $source, string $revision): ?string
    {
        $state=$this->sync->schedule($account,$source,'account');
        if(($state['revision']??'')!==$revision) {
            $state=['revision'=>$revision,'cursor'=>'','enumerated'=>false,'turn'=>0,'fair'=>''];
            $this->sync->saveVerification($account,$source,'account',[]);
        }
        if(($state['done']??false))return null;
        foreach($this->sync->verification($account,$source,'account') as $entry) if($entry['more'] && isset($entry['target']) && $this->hasKey($account,$entry['target']))return $entry['target'];
        $target=null;$fair=$state['turn']%4===3;
        if(!$fair) foreach($this->sync->recentActivity($account) as $activity) {
            if($this->decisions>=150)break;
            $this->decisions++;
            $candidate=$activity['source'];
            if($this->hasKey($account,$candidate) && !$this->sync->destination($account,$source,$candidate,$revision)['finished']) {$target=$candidate;break;}
        }
        if($target===null && !$state['enumerated']) {
            // One metadata destination per selection; no hidden full-account enumeration.
            $target=$this->nextKey($account,$state['cursor']);$this->decisions++;
            if($target===null)$state['enumerated']=true;
            else $state['cursor']=$target;
        }
        if($target===null) {
            $target=$this->sync->pendingDestination($account,$source,$revision,$state['fair'])??$this->sync->pendingDestination($account,$source,$revision);
            if($target!==null && !$this->hasKey($account,$target)) {
                $this->sync->saveDestination($account,$source,$target,$revision,'',true);$target=null;
            }
        }
        if($target!==null) {
            $state['turn']++;if($fair)$state['fair']=$target;
            $position=$this->sync->destination($account,$source,$target,$revision);
            $this->sync->saveDestination($account,$source,$target,$revision,$position['after_id'],(bool)$position['finished']);
        } else $state['done']=true;
        $this->sync->saveSchedule($account,$source,'account',$state);
        return $target;
    }
    private function finishScheduled(string $account, string $source, string $mode, array $checkpoint): void
    {
        $schedule=$checkpoint['schedule']??null;
        if(!is_array($schedule) || !is_string($schedule['revision']??null) || $schedule['revision']!==$this->revision($account,$source))return;
        if($mode==='account') {
            $target=$schedule['target']??'';$after=$schedule['after_id']??'';
            if(!is_string($target) || !is_string($after) || strlen($after)>128 || !$this->hasKey($account,$target))return;
            $this->sync->saveDestination($account,$source,$target,$schedule['revision'],$after,($schedule['finished']??false)===true);
        } elseif(($schedule['finished']??false)===true) $this->sync->saveSchedule($account,$source,'sender',['revision'=>$schedule['revision'],'done'=>true]);
    }
    private function context(array $viewer): array
    {
        $account=strtolower(trim((string)($viewer['username_token']??'')));
        $source=strtolower(substr((string)($viewer['identity_id']??''),8));
        $key=$this->hasKey($account,$source);
        if (($viewer['is_approved']??0)!=1 || !str_starts_with((string)($viewer['identity_id']??''),'openpgp:') || !$key) throw new RuntimeException('An approved authenticated account key is required.');
        return [$account,$source];
    }
    // Derive authorization from the original, never from donor-supplied account claims.
    private function eligibleSource(array $message, string $destinationAccount, string $sourceFingerprint): ?array
    {
        if (!in_array($destinationAccount, [$message['sender_username_token'], $message['recipient_username_token']], true)) return null;
        $accounts=[$destinationAccount];
        if ($message['recipient_username_token']===$destinationAccount) $accounts[]=$message['sender_username_token'];
        foreach (array_unique($accounts) as $account) {
            $key=$this->key($account,$sourceFingerprint);
            if ($key!==null) return ['source_account'=>$account, 'source_key'=>$key];
        }
        return null;
    }
    public function work(array $viewer, mixed $mode = 'account'): array
    {
        $this->resetBudget();
        return $this->accountWork($viewer,$mode);
    }
    private function accountWork(array $viewer, mixed $mode = 'account', bool $scheduled = false): array
    {
        if(!in_array($mode,['account','sender'],true)) throw new InvalidArgumentException('Invalid history work mode.');
        if($mode==='sender') return $this->senderWork($viewer);
        [$account,$source]=$this->context($viewer);
        $revision=$scheduled?$this->revision($account,$source):'';
        $chosen=$scheduled?$this->scheduledTarget($account,$source,$revision):null;
        if($scheduled && $chosen===null) return ['account'=>$account,'source'=>$source,'target'=>'','messages'=>[],'checkpoint'=>['target'=>'','after_id'=>''],'cycle_end'=>true];
        $position=$scheduled?['target'=>$chosen]+$this->sync->destination($account,$source,$chosen,$revision):$this->sync->checkpoint($account,$source);
        $first=$this->nextKey($account);
        $target=$this->key($account,$position['target'])!==null?$position['target']:$first;
        $after=$target===$position['target']?$position['after_id']:'';
        if ($after!=='' && $this->messages->historySyncMessage($account,$after)===null) $after='';
        $page=$this->messages->historySyncPage($account,$after,self::BATCH_SIZE);
        $prepared=$this->boundedMessages($page['messages'],$account,$source,'account',$target);
        if(count($prepared)<count($page['messages'])) { $page['more']=true;$page['last']=$prepared===[]?$after:$prepared[count($prepared)-1]['message_id']; }
        $next=$page['more']?['target'=>$target,'after_id'=>$page['last']]:['target'=>$this->nextKey($account,$target)??$first,'after_id'=>''];
        if($scheduled) $next['schedule']=['revision'=>$revision,'target'=>$target,'after_id'=>$page['more']?$page['last']:'','finished'=>!$page['more']];
        $messages=$prepared;
        foreach ($messages as &$message) {
            $receipt=$this->sync->receipt($account,$target,$message['message_id'],$message['digest']);
            $message['covered']=$receipt!==null && ($receipt['transfer_id']==='' || $this->eligibleSource($message,$account,$receipt['source']??'')!==null);
        }
        unset($message);
        return ['account'=>$account,'source'=>$source,'target'=>$target,'target_key'=>$this->key($account,$target), 'messages'=>array_values(array_filter($messages,static fn($row)=>!$row['covered'])),
            'checkpoint'=>$next+['verification'=>$this->verificationTokens],'cycle_end'=>!$scheduled && !$this->verificationMore && !$page['more'] && $next['target']===$first, 'total'=>$this->messages->historySyncCount($account)];
    }
    public function combinedWork(array $viewer, mixed $finished = []): array
    {
        $this->resetBudget();
        if (!is_array($finished) || !array_is_list($finished) || count($finished)>2 || array_filter($finished,fn($mode)=>!is_string($mode) || !in_array($mode,['account','sender'],true))!==[]) throw new InvalidArgumentException('Invalid completed modes.');
        [$account,$source]=$this->context($viewer);
        $bundles=[];$complete=$finished;
        $revision=$this->revision($account,$source);
        foreach (['account','sender'] as $mode) {
            if (in_array($mode,$finished,true)) continue;
            if($mode==='sender') {
                $state=$this->sync->schedule($account,$source,'sender');
                if(($state['revision']??'')===$revision && ($state['done']??false)) {$complete[]=$mode;continue;}
                if(($state['revision']??'')!==$revision) {
                    $this->sync->advance($account,$source,'','',true);
                    $this->sync->saveSchedule($account,$source,'sender',['revision'=>$revision,'done'=>false]);
                }
            }
            // Bounded discovery can advance covered pages, but never client work or access receipts.
            for ($page=0;$page<8 && $this->decisions<150 && $this->keyLoads<40;$page++) {
                $work=$mode==='sender'?$this->senderWork($viewer,true):$this->accountWork($viewer,'account',true);
                if($mode==='sender') {
                    $work['checkpoint']['schedule']=['revision'=>$revision,'finished'=>$work['scan_end']];
                    if($work['messages']===[])$work['cycle_end']=$work['scan_end'];
                }
                if ($mode==='account' && $work['target']!==$source) {
                    $work['messages']=array_values(array_filter($work['messages'],fn($row)=>!$this->sync->hasContribution($account,$source,$work['target'],$row['message_id'],$row['digest'])));
                }
                $offered=array_column($work['messages'],'message_id');
                $verification=$this->sync->verification($account,$source,$mode);
                $this->sync->saveVerification($account,$source,$mode,array_filter($verification,fn($entry)=>in_array($entry['id'],$offered,true)));
                $work['mode']=$mode;
                if ($work['messages']!==[]) {
                    if(strlen(json_encode($bundles,JSON_THROW_ON_ERROR))+strlen(json_encode($work,JSON_THROW_ON_ERROR))<4*1048576-8192) $bundles[]=$work;
                    break;
                }
                $next=$work['checkpoint'];
                $this->sync->saveVerification($account,$source,$mode,[]);
                $this->finishScheduled($account,$source,$mode,$next);
                $this->sync->advance($account,$source,$next['target'],$next['after_id'],$mode==='sender');
                if ($work['cycle_end']) { $complete[]=$mode;break; }
            }
            if($mode==='account') {
                $state=$this->sync->schedule($account,$source,'account');
                if(($state['enumerated']??false) && $this->sync->pendingDestination($account,$source,$revision)===null) {
                    $state['done']=true;$this->sync->saveSchedule($account,$source,'account',$state);$complete[]='account';
                }
            }
        }
        return ['mode'=>'combined','account'=>$account,'source'=>$source,'revision'=>$revision,'work'=>$bundles,
            'complete'=>array_values(array_unique($complete)), 'continuation'=>count(array_unique($complete))<2];
    }
    private function senderWork(array $viewer, bool $skipContributed = false): array
    {
        [$account,$source]=$this->context($viewer);
        $position=$this->sync->checkpoint($account,$source,true);
        $anchor=$position['after_id']===''?null:$this->messages->historySyncMessage($account,$position['after_id']);
        if($position['after_id']!=='' && (!$anchor || $anchor['sender_username_token']!==$account)) $position=['target'=>'','after_id'=>''];
        $page=$this->messages->historySyncSentPage($account,$position['after_id'],self::BATCH_SIZE);
        $destination=$page['messages'][0]['recipient_username_token']??'';
        $target=$destination===''?'':($this->key($destination,$position['target'])!==null?$position['target']:($this->nextKey($destination)??''));
        $prepared=$this->boundedMessages($page['messages'],$account,$source,'sender',$target);
        if(count($prepared)<count($page['messages'])) { $page['more']=true;$page['last']=$prepared===[]?$position['after_id']:$prepared[count($prepared)-1]['message_id']; }
        $messages=[];
        foreach($prepared as $message) {
            // A bundle has exactly one recipient account; all keys get a turn before advancing history.
            if($message['recipient_username_token']!==$destination) break;
            $messages[]=$message;
        }
        $last=$messages===[]?'':$messages[count($messages)-1]['message_id'];
        $nextKey=$target===''?null:$this->nextKey($destination,$target);
        $end=$nextKey===null && !$page['more'] && $last===$page['last'];
        $next=$nextKey!==null?['target'=>$nextKey,'after_id'=>$position['after_id']]:['target'=>'','after_id'=>$end?'':$last];
        // Never consult recipient coverage: sender work must not reveal recovery or read activity.
        return ['mode'=>'sender','source_account'=>$account,'account'=>$destination,'source'=>$source,'target'=>$target,
            'target_key'=>$target===''?null:$this->key($destination,$target),'messages'=>$target===''?[]:array_values(array_filter($messages, fn($row)=>!$skipContributed || !$this->sync->hasContribution($destination,$source,$target,$row['message_id'],$row['digest']))),'checkpoint'=>$next+['verification'=>$this->verificationTokens],'scan_end'=>$end,'cycle_end'=>!$this->verificationMore && $end];
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
        $this->resetBudget();
        [$account,$source]=$this->context($viewer);
        $destination=$input['account']??$account;
        if(!is_string($destination)) throw new InvalidArgumentException('Invalid destination account.');
        $target=$input['target']??null;$ciphertext=$input['ciphertext']??null;
        if(!is_string($target) || $this->key($destination,$target)===null || $target===$source) throw new InvalidArgumentException('Target must be another approved account key.');
        if(!is_string($ciphertext) || strlen($ciphertext)>65536 || !str_starts_with($ciphertext,'-----BEGIN PGP MESSAGE-----') || !str_contains($ciphertext,'-----END PGP MESSAGE-----')) throw new InvalidArgumentException('Invalid encrypted history transfer.');
        $items=$this->items($destination,$input['items']??null);
        foreach($items as $item) {
            $message=$this->messages->historySyncMessage($destination,$item['message_id']);
            $eligible=$this->eligibleSource($message,$destination,$source);
            if(!$eligible || $eligible['source_account']!==$account) throw new InvalidArgumentException('Source is not eligible for this original recipient.');
        }
        if($items===[]) throw new InvalidArgumentException('Transfer is empty.');
        $items=array_map(static fn($item)=>['message_id'=>$item['message_id'],'digest'=>$item['digest']],$items);
        return ['transfer_id'=>$this->sync->upload($destination,$source,$target,$ciphertext,$items)];
    }
    public function transfers(array $viewer, array $query): array
    {
        $this->resetBudget();
        [$account,$target]=$this->context($viewer);
        $id=$query['message_id']??null;$after=$query['after']??'';
        if(!is_string($id) || strlen($id)>128 || !is_string($after) || ($after!=='' && !preg_match('/^[a-f0-9]{64}$/D',$after))) throw new InvalidArgumentException('Invalid transfer query.');
        $row=$this->messages->historySyncMessage($account,$id);
        if(!$row) throw new InvalidArgumentException('History message unavailable.');
        $digest=hash('sha256',$row['encrypted_envelope']);
        $rows=$this->sync->candidates($account,$target,$id,$digest,$after);
        $more=count($rows)>10;$rows=array_slice($rows,0,10);
        $result=[];$bytes=0;$last=$after;
        foreach($rows as $candidate) {
            $eligible=$this->eligibleSource($row,$account,$candidate['source']);
            if($eligible===null){$last=$candidate['transfer_id'];continue;}
            $candidate+=$eligible;
            $size=strlen(json_encode($candidate,JSON_THROW_ON_ERROR));
            if($bytes+$size>4*1048576-32768){$more=true;break;}
            $result[]=$candidate;$bytes+=$size;$last=$candidate['transfer_id'];
        }
        return ['account'=>$account,'target'=>$target,'message_id'=>$id,'digest'=>$digest,'transfers'=>$result,'next'=>$more?$last:null];
    }
    public function acknowledge(array $viewer, array $input): array
    {
        $this->resetBudget();
        [$account,$source]=$this->context($viewer);
        $items=$this->items($account,$input['items']??[]);
        $unavailable=$this->items($account,$input['unavailable']??[]);
        $verified=$this->items($account,$input['verified']??[]);
        $knownVerified=[];
        foreach($verified as $item) {
            $receipt=$this->sync->receipt($account,$source,$item['message_id'],$item['digest']);
            if($receipt!==null && ($receipt['transfer_id']==='' || $this->eligibleSource($this->messages->historySyncMessage($account,$item['message_id']),$account,$receipt['source']??'')!==null))$knownVerified[]=$item['message_id'];
        }
        foreach($items as $item) {
            if($item['transfer_id']==='') continue; // authenticated target reports verified direct access
            $candidate=$this->sync->candidate($account,$source,$item['message_id'],$item['digest'],$item['transfer_id']);
            if(!$candidate || $this->eligibleSource($this->messages->historySyncMessage($account,$item['message_id']),$account,$candidate['source'])===null) throw new InvalidArgumentException('Transfer is no longer eligible.');
        }
        $checkpoint=$input['checkpoint']??null;
        $mode=$input['mode']??'account';
        if(!in_array($mode,['account','sender'],true)) throw new InvalidArgumentException('Invalid history work mode.');
        if($checkpoint!==null) {
            if(!is_array($checkpoint) || !is_string($checkpoint['target']??null) || !is_string($checkpoint['after_id']??null) || strlen($checkpoint['after_id'])>128) throw new InvalidArgumentException('Invalid scan checkpoint.');
            if($mode==='account' && $this->key($account,$checkpoint['target'])===null) throw new InvalidArgumentException('Invalid scan target.');
            if($mode==='sender') {
                if($checkpoint['target']!=='' && !preg_match('/^(?:[a-f0-9]{40}|[a-f0-9]{64})$/D',$checkpoint['target'])) throw new InvalidArgumentException('Invalid scan target.');
                $anchor=$checkpoint['after_id']===''?null:$this->messages->historySyncMessage($account,$checkpoint['after_id']);
                if($checkpoint['after_id']!=='' && (!$anchor || $anchor['sender_username_token']!==$account)) throw new InvalidArgumentException('Invalid outgoing scan anchor.');
            }
        }
        if($items!==[]) $this->sync->acknowledge($account,$source,$items);
        if($checkpoint!==null) {
            $state=$this->sync->verification($account,$source,$mode);$pending=false;
            foreach($state as $binding=>&$entry) {
                $token=$checkpoint['verification'][$binding]??null;
                $verified=in_array($entry['id'],array_column($items,'message_id'),true) || in_array($entry['id'],$knownVerified,true);
                if($verified || in_array($entry['id'],array_column($unavailable,'message_id'),true)) {$entry['more']=false;continue;}
                if($entry['more']) {
                    $pending=true;
                    if(is_string($token) && hash_equals($entry['token'],$token)) $entry['after']=$entry['pending'];
                }
            }
            unset($entry);
            $this->sync->saveVerification($account,$source,$mode,$pending?$state:[]);
            // Old timeout checkpoints omit the token: retain work rather than skip a key page.
            if(!$pending) {
                $this->finishScheduled($account,$source,$mode,$checkpoint);
                $this->sync->advance($account,$source,$checkpoint['target'],$checkpoint['after_id'],$mode==='sender');
            }
        }
        return ['confirmed'=>count($items)];
    }
}
