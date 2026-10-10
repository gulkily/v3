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
        $pdo->exec('CREATE TABLE IF NOT EXISTS history_sync_scan (account TEXT NOT NULL, source TEXT NOT NULL, target TEXT NOT NULL, after_id TEXT NOT NULL, PRIMARY KEY(account,source))');
    }
    public function checkpoint(string $account, string $source): array
    {
        $q=$this->pdo->prepare('SELECT target,after_id FROM history_sync_scan WHERE account=? AND source=?');
        $q->execute([$account,$source]);
        return $q->fetch() ?: ['target'=>'','after_id'=>''];
    }
    public function advance(string $account, string $source, string $target, string $after): void
    {
        $q=$this->pdo->prepare('INSERT INTO history_sync_scan VALUES (?,?,?,?) ON CONFLICT(account,source) DO UPDATE SET target=excluded.target,after_id=excluded.after_id WHERE target!=excluded.target OR after_id!=excluded.after_id');
        $q->execute([$account,$source,$target,$after]);
    }
}
