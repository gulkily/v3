<?php

declare(strict_types=1);

namespace ForumRewrite\Railspike;

use PDO;
use RuntimeException;

/** Private durable evidence, deliberately independent of the public read model and task retention. */
final class WorkflowStore
{
    public function __construct(private readonly PDO $pdo)
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA busy_timeout=5000');
        $pdo->exec('CREATE TABLE IF NOT EXISTS railspike_workflows (id TEXT PRIMARY KEY, post_id TEXT UNIQUE NOT NULL, thread_id TEXT NOT NULL, version INTEGER NOT NULL, data TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS railspike_actions (id TEXT PRIMARY KEY, workflow_id TEXT NOT NULL, signed_text TEXT NOT NULL, signature TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS railspike_metadata (name TEXT PRIMARY KEY, value TEXT NOT NULL)');
        $q=$pdo->prepare('INSERT OR IGNORE INTO railspike_metadata VALUES (?, ?)');
        $q->execute(['instance', bin2hex(random_bytes(24))]);
    }

    public function schedule(array $row): void
    {
        (new \ForumRewrite\TaskQueue\SqliteTaskQueueStore($this->pdo))->enqueueWithinTransaction(
            \ForumRewrite\TaskQueue\SqliteTaskQueueStore::RAILSPIKE_ASSESSMENT, $row['id'].'@'.$row['version'], 1);
    }

    /** Called only while the normal queue execution lock is held, after abandoned-task recovery. */
    public function reconcileTasks(): void
    {
        $this->transaction(function (): void {
            foreach ($this->pdo->query('SELECT data FROM railspike_workflows')->fetchAll(PDO::FETCH_COLUMN) as $data) {
                $row=json_decode($data,true,64,JSON_THROW_ON_ERROR);
                if (!in_array($row['state'],['queued','running'],true)) continue;
                $q=$this->pdo->prepare('SELECT status FROM internal_tasks WHERE type=? AND deduplication_key=? ORDER BY id DESC LIMIT 1');
                $q->execute([\ForumRewrite\TaskQueue\SqliteTaskQueueStore::RAILSPIKE_ASSESSMENT,$row['id'].'@'.$row['version']]);
                $status=$q->fetchColumn();
                if ($status===false && $row['state']==='queued') { $this->schedule($row); continue; }
                if ($status===false || in_array($status,['failed','completed'],true)) {
                    $row['state']='failed'; $row['failure']='Assessment interrupted. Check worker status, then retry.';
                    $row['version']++; unset($row['run_token']); $this->save($row);
                }
            }
        });
    }

    public function instance(): string
    {
        return (string) $this->pdo->query("SELECT value FROM railspike_metadata WHERE name='instance'")->fetchColumn();
    }

    public function find(string $id): ?array
    {
        $q=$this->pdo->prepare('SELECT data FROM railspike_workflows WHERE id=?'); $q->execute([$id]);
        $data=$q->fetchColumn();
        return $data === false ? null : json_decode($data, true, 64, JSON_THROW_ON_ERROR);
    }

    public function forPost(string $id): ?array
    {
        $q=$this->pdo->prepare('SELECT id FROM railspike_workflows WHERE post_id=?'); $q->execute([$id]);
        $id=$q->fetchColumn(); return $id === false ? null : $this->find($id);
    }

    public function recent(): array
    {
        return array_map(static fn ($s) => json_decode($s,true,64,JSON_THROW_ON_ERROR),
            $this->pdo->query('SELECT data FROM railspike_workflows ORDER BY rowid DESC LIMIT 50')->fetchAll(PDO::FETCH_COLUMN));
    }

    public function forThread(string $id): array
    {
        $q=$this->pdo->prepare('SELECT data FROM railspike_workflows WHERE thread_id=? ORDER BY rowid'); $q->execute([$id]);
        return array_map(static fn ($s) => json_decode($s, true, 64, JSON_THROW_ON_ERROR), $q->fetchAll(PDO::FETCH_COLUMN));
    }

    public function transaction(callable $work): mixed
    {
        $this->pdo->exec('BEGIN IMMEDIATE');
        try { $result=$work(); $this->pdo->exec('COMMIT'); return $result; }
        catch (\Throwable $e) { $this->pdo->exec('ROLLBACK'); throw $e; }
    }

    public function save(array $row): array
    {
        $q=$this->pdo->prepare('INSERT INTO railspike_workflows VALUES (?, ?, ?, ?, ?) ON CONFLICT(id) DO UPDATE SET version=excluded.version, data=excluded.data');
        $q->execute([$row['id'], $row['post_id'], $row['thread_id'], $row['version'], json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)]);
        return $row;
    }

    public function receipt(string $id): ?string
    {
        $q=$this->pdo->prepare('SELECT workflow_id FROM railspike_actions WHERE id=?'); $q->execute([$id]);
        $value=$q->fetchColumn(); return $value === false ? null : $value;
    }

    public function record(string $id, string $workflowId, string $text, string $signature): void
    {
        $q=$this->pdo->prepare('INSERT INTO railspike_actions VALUES (?, ?, ?, ?)'); $q->execute([$id,$workflowId,$text,$signature]);
    }

    public function requireVersion(string $id, int $version): array
    {
        $row=$this->find($id);
        if ($row === null || $row['version'] !== $version) throw new RuntimeException('Workflow changed. Refresh before acting.');
        return $row;
    }
}
