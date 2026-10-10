<?php
declare(strict_types=1);
namespace ForumRewrite\Messaging;
use PDO;
final class PrivateMessageHistorySyncStore
{
    public function __construct(private readonly PDO $pdo)
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE IF NOT EXISTS history_sync_transfers (transfer_id TEXT PRIMARY KEY, account TEXT NOT NULL, source TEXT NOT NULL, target TEXT NOT NULL, ciphertext TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS history_sync_items (account TEXT NOT NULL, source TEXT NOT NULL, target TEXT NOT NULL, message_id TEXT NOT NULL, digest TEXT NOT NULL, transfer_id TEXT NOT NULL, PRIMARY KEY(account,source,target,message_id))');
        $pdo->exec('CREATE INDEX IF NOT EXISTS history_sync_items_target ON history_sync_items(account,target,message_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS history_sync_items_transfer ON history_sync_items(transfer_id)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS history_sync_coverage (account TEXT NOT NULL, target TEXT NOT NULL, message_id TEXT NOT NULL, digest TEXT NOT NULL, transfer_id TEXT NOT NULL, PRIMARY KEY(account,target,message_id))');
        $pdo->exec('CREATE INDEX IF NOT EXISTS history_sync_coverage_transfer ON history_sync_coverage(transfer_id)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS history_sync_scan (account TEXT NOT NULL, source TEXT NOT NULL, target TEXT NOT NULL, after_id TEXT NOT NULL, PRIMARY KEY(account,source))');
        $pdo->exec('CREATE TABLE IF NOT EXISTS history_sync_sender_scan (account TEXT NOT NULL, source TEXT NOT NULL, target TEXT NOT NULL, after_id TEXT NOT NULL, PRIMARY KEY(account,source))');
    }
    public function checkpoint(string $account, string $source, bool $sender = false): array
    {
        $table=$sender?'history_sync_sender_scan':'history_sync_scan';
        $q=$this->pdo->prepare('SELECT target,after_id FROM '.$table.' WHERE account=? AND source=?');
        $q->execute([$account,$source]);
        return $q->fetch() ?: ['target'=>'','after_id'=>''];
    }
    public function advance(string $account, string $source, string $target, string $after, bool $sender = false): void
    {
        if($this->checkpoint($account,$source,$sender)===['target'=>$target,'after_id'=>$after]) return;
        $table=$sender?'history_sync_sender_scan':'history_sync_scan';
        $q=$this->pdo->prepare('INSERT INTO '.$table.' VALUES (?,?,?,?) ON CONFLICT(account,source) DO UPDATE SET target=excluded.target,after_id=excluded.after_id WHERE target!=excluded.target OR after_id!=excluded.after_id');
        $q->execute([$account,$source,$target,$after]);
    }
    public function candidates(string $account, string $target, string $id, string $digest, string $after = ''): array
    {
        $q=$this->pdo->prepare('SELECT t.* FROM history_sync_items i JOIN history_sync_transfers t ON t.transfer_id=i.transfer_id WHERE i.account=? AND i.target=? AND i.message_id=? AND i.digest=? AND t.transfer_id>? ORDER BY t.transfer_id LIMIT 11');
        $q->execute([$account,$target,$id,$digest,$after]);return $q->fetchAll();
    }
    public function receipt(string $account, string $target, string $id, string $digest): ?array
    {
        $q=$this->pdo->prepare('SELECT c.transfer_id,t.source FROM history_sync_coverage c LEFT JOIN history_sync_transfers t ON c.transfer_id=t.transfer_id AND t.account=c.account AND t.target=c.target WHERE c.account=? AND c.target=? AND c.message_id=? AND c.digest=?');
        $q->execute([$account,$target,$id,$digest]);return $q->fetch() ?: null;
    }
    public function upload(string $account, string $source, string $target, string $ciphertext, array $items): string
    {
        $id=hash('sha256',json_encode([$account,$source,$target,$ciphertext,$items],JSON_THROW_ON_ERROR));
        $this->pdo->beginTransaction();
        try {
            $q=$this->pdo->prepare('INSERT INTO history_sync_transfers VALUES (?,?,?,?,?) ON CONFLICT(transfer_id) DO NOTHING');
            $q->execute([$id,$account,$source,$target,$ciphertext]);
            $old=[];
            foreach($items as $item) {
                $q=$this->pdo->prepare('SELECT transfer_id FROM history_sync_items WHERE account=? AND source=? AND target=? AND message_id=?');
                $q->execute([$account,$source,$target,$item['message_id']]);
                if($prior=$q->fetchColumn()) $old[]=$prior;
                // Keep acknowledged ciphertext reachable; unconfirmed bad uploads can be replaced.
                $q=$this->pdo->prepare("INSERT INTO history_sync_items VALUES (?,?,?,?,?,?) ON CONFLICT(account,source,target,message_id) DO UPDATE SET digest=excluded.digest,transfer_id=excluded.transfer_id WHERE history_sync_items.digest!=excluded.digest OR NOT EXISTS(SELECT 1 FROM history_sync_coverage c JOIN history_sync_transfers t ON t.transfer_id=c.transfer_id WHERE c.account=history_sync_items.account AND c.target=history_sync_items.target AND c.message_id=history_sync_items.message_id AND c.digest=history_sync_items.digest AND c.transfer_id=history_sync_items.transfer_id)");
                $q->execute([$account,$source,$target,$item['message_id'],$item['digest'],$id]);
            }
            foreach(array_unique(array_merge($old,[$id])) as $candidate) {
                $q=$this->pdo->prepare('DELETE FROM history_sync_transfers WHERE transfer_id=? AND NOT EXISTS(SELECT 1 FROM history_sync_items WHERE transfer_id=?) AND NOT EXISTS(SELECT 1 FROM history_sync_coverage WHERE transfer_id=?)');
                $q->execute([$candidate,$candidate,$candidate]);
            }
            $this->pdo->commit();return $id;
        } catch (\Throwable $e) { $this->pdo->rollBack();throw $e; }
    }
    public function acknowledge(string $account, string $target, array $items): void
    {
        $this->pdo->beginTransaction();
        try {
            $q=$this->pdo->prepare('INSERT INTO history_sync_coverage VALUES (?,?,?,?,?) ON CONFLICT(account,target,message_id) DO UPDATE SET digest=excluded.digest,transfer_id=excluded.transfer_id WHERE digest!=excluded.digest OR transfer_id!=excluded.transfer_id');
            foreach($items as $item) $q->execute([$account,$target,$item['message_id'],$item['digest'],$item['transfer_id']??'']);
            $this->pdo->commit();
        } catch (\Throwable $e) { $this->pdo->rollBack();throw $e; }
    }
    public function candidate(string $account, string $target, string $id, string $digest, string $transfer): ?array
    {
        $q=$this->pdo->prepare('SELECT t.* FROM history_sync_items i JOIN history_sync_transfers t ON t.transfer_id=i.transfer_id WHERE i.account=? AND i.target=? AND i.message_id=? AND i.digest=? AND i.transfer_id=?');
        $q->execute([$account,$target,$id,$digest,$transfer]);return $q->fetch() ?: null;
    }
}
