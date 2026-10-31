<?php
declare(strict_types=1);
namespace ForumRewrite\Messaging;
use PDO;
/** Mutation tokens survive reconnects and change after rebuilds/restores. No content is stored. */
final class PrivateMessageHistoryRevision
{
    public static function statements(string $table, string $prefix, array $accounts, ?string $when = null): array
    {
        $sql=["CREATE TABLE IF NOT EXISTS {$prefix}_revision (account TEXT PRIMARY KEY, token TEXT NOT NULL)"];
        if($prefix==='history_keys')$sql[]="INSERT INTO {$prefix}_revision VALUES ('*',lower(hex(randomblob(16))))";
        foreach(['INSERT'=>['NEW'],'UPDATE'=>['OLD','NEW'],'DELETE'=>['OLD']] as $event=>$versions) {
            $body='';
            foreach($versions as $version) foreach($accounts as $account) {
                $expr=$account==='*'?"'*'":str_replace('@',$version,$account);
                $body.="INSERT INTO {$prefix}_revision VALUES ({$expr},lower(hex(randomblob(16)))) ON CONFLICT(account) DO UPDATE SET token=excluded.token;";
            }
            $condition=$event==='UPDATE' && $when!==null?' WHEN '.$when:'';
            $sql[]="CREATE TRIGGER IF NOT EXISTS {$prefix}_".strtolower($event)." AFTER {$event} ON {$table}{$condition} BEGIN {$body} END";
        }
        return $sql;
    }
    public static function profileStatements(): array
    {
        $statements=self::statements('profiles','history_keys',['*'], 'OLD.identity_id IS NOT NEW.identity_id OR OLD.username_token IS NOT NEW.username_token OR OLD.public_key IS NOT NEW.public_key OR OLD.is_approved IS NOT NEW.is_approved');
        // ReadModelSchema::tableNames derives its rebuild DROP list from CREATE TABLE.
        $statements[0]=str_replace('CREATE TABLE IF NOT EXISTS','CREATE TABLE',$statements[0]);
        return $statements;
    }
    public static function ensureEpoch(PDO $pdo, string $prefix): void
    {
        $q=$pdo->query("SELECT token FROM {$prefix}_revision WHERE account='*'");
        if($q->fetchColumn()===false)$pdo->exec("INSERT OR IGNORE INTO {$prefix}_revision VALUES ('*',lower(hex(randomblob(16))))");
    }
    public static function token(PDO $pdo, string $prefix, string $account): string
    {
        $q=$pdo->prepare("SELECT token FROM {$prefix}_revision WHERE account=?");$q->execute([$account]);$token=$q->fetchColumn()?:'';
        if($account!=='*'){$q->execute(['*']);$token=($q->fetchColumn()?:'').':'.$token;}
        return $token;
    }
}
