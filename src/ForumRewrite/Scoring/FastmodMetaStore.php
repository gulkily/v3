<?php

declare(strict_types=1);
namespace ForumRewrite\Scoring;

use PDO;

/** Persistent instance-owned classifications, independent of score pruning. */
final class FastmodMetaStore
{
    public function __construct(private readonly PDO $pdo)
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA busy_timeout=5000');
        $pdo->exec("CREATE TABLE IF NOT EXISTS fastmod_meta (
            post_id TEXT PRIMARY KEY, content_hash TEXT NOT NULL, rubric_revision TEXT NOT NULL,
            model_identity TEXT NOT NULL, threshold REAL NOT NULL, probability REAL,
            state TEXT NOT NULL DEFAULT 'pending', active INTEGER NOT NULL DEFAULT 0,
            suppressed INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");
        if (!in_array('evidence_json',array_column($pdo->query('PRAGMA table_info(fastmod_meta)')->fetchAll(),'name'),true)) {
            $pdo->exec('ALTER TABLE fastmod_meta ADD COLUMN evidence_json TEXT');
        }
        $pdo->exec('CREATE INDEX IF NOT EXISTS fastmod_meta_active ON fastmod_meta(active,post_id)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS fastmod_meta_generation (singleton INTEGER PRIMARY KEY CHECK(singleton=1), generation INTEGER NOT NULL, published INTEGER NOT NULL)');
        if (!in_array('epoch',array_column($pdo->query('PRAGMA table_info(fastmod_meta_generation)')->fetchAll(),'name'),true)) {
            $pdo->exec("ALTER TABLE fastmod_meta_generation ADD COLUMN epoch TEXT NOT NULL DEFAULT ''");
        }
        $pdo->exec('INSERT OR IGNORE INTO fastmod_meta_generation(singleton,generation,published,epoch) VALUES (1,0,0,lower(hex(randomblob(16))))');
        $pdo->exec("UPDATE fastmod_meta_generation SET epoch=lower(hex(randomblob(16))) WHERE epoch=''");
    }

    public function recordEligibleRoot(string $id, string $hash, string $revision, string $model, float $threshold): void
    {
        if ($id === '' || $hash === '' || $revision === '' || $model === '' || !is_finite($threshold) || $threshold < 0 || $threshold > 1) {
            throw new \InvalidArgumentException('Invalid meta eligibility.');
        }
        $q = $this->pdo->prepare('INSERT OR IGNORE INTO fastmod_meta(post_id,content_hash,rubric_revision,model_identity,threshold,created_at,updated_at) VALUES(?,?,?,?,?,?,?)');
        $q->execute([$id,$hash,$revision,$model,$threshold,gmdate('c'),gmdate('c')]);
    }

    public function find(string $id): ?array
    {
        $q=$this->pdo->prepare('SELECT * FROM fastmod_meta WHERE post_id=?'); $q->execute([$id]);
        return $q->fetch() ?: null;
    }

    public function retainOrdinaryResult(array $work, array $result, string $model): void
    {
        if (isset($work['backfill_batch_id']) || ($result['status'] ?? '') !== 'scored') return;
        $q=$this->pdo->prepare("UPDATE fastmod_meta SET evidence_json=? WHERE post_id=? AND content_hash=? AND rubric_revision=? AND model_identity=? AND state='pending' AND suppressed=0 AND evidence_json IS NULL");
        $q->execute([json_encode($result,JSON_THROW_ON_ERROR),$work['post_id'],$work['content_hash'],$work['rubric_revision'],$model]);
    }

    public function apply(array $work, array $result, string $model): bool
    {
        if (isset($work['backfill_batch_id']) || ($result['status'] ?? '') !== 'scored' || !isset($result['scores']['meta'])) return false;
        $probability=$result['scores']['meta'];
        if (!is_numeric($probability) || !is_finite((float)$probability) || $probability < 0 || $probability > 1) return false;
        return $this->transaction(function () use ($work,$result,$model,$probability): bool {
            $row=$this->find((string)$work['post_id']);
            if ($row === null || $row['state'] !== 'pending' || (int)$row['suppressed'] !== 0
                || $row['content_hash'] !== $work['content_hash'] || $row['rubric_revision'] !== $work['rubric_revision']
                || $row['model_identity'] !== $model) return false;
            $active=(float)$probability >= (float)$row['threshold'];
            $q=$this->pdo->prepare("UPDATE fastmod_meta SET probability=?,state='complete',active=?,updated_at=? WHERE post_id=?");
            $q->execute([(float)$probability,(int)$active,gmdate('c'),$row['post_id']]);
            if ($active) $this->advance();
            return $active;
        });
    }

    public function removeAndSuppress(string $id): void
    {
        $this->transaction(function () use ($id): void {
            $q=$this->pdo->prepare("UPDATE fastmod_meta SET active=0,suppressed=1,state='suppressed',updated_at=? WHERE post_id=? AND suppressed=0");
            $q->execute([gmdate('c'),$id]);
            if ($q->rowCount() > 0) $this->advance();
        });
    }

    public function rollback(): void
    {
        $this->transaction(function (): void {
            $q=$this->pdo->prepare("UPDATE fastmod_meta SET active=0,suppressed=1,state='suppressed',updated_at=? WHERE suppressed=0");
            $q->execute([gmdate('c')]);
            if ($q->rowCount() > 0) $this->advance();
        });
    }

    public function status(): array
    {
        return ['generation'=>$this->generation(), 'dirty'=>$this->dirty(), 'items'=>$this->pdo->query('SELECT post_id,state,active,suppressed,updated_at FROM fastmod_meta ORDER BY updated_at DESC,post_id')->fetchAll()];
    }

    public function pending(): array
    {
        return $this->pdo->query("SELECT * FROM fastmod_meta WHERE state='pending' AND suppressed=0 AND evidence_json IS NOT NULL")->fetchAll();
    }

    public function generation(): int { return (int)$this->pdo->query('SELECT generation FROM fastmod_meta_generation WHERE singleton=1')->fetchColumn(); }
    public function dirty(): bool { return (bool)$this->pdo->query('SELECT generation != published FROM fastmod_meta_generation WHERE singleton=1')->fetchColumn(); }
    public function acknowledge(int $generation): void
    {
        $q=$this->pdo->prepare('UPDATE fastmod_meta_generation SET published=? WHERE singleton=1 AND generation=?'); $q->execute([$generation,$generation]);
    }
    private function advance(): void { $this->pdo->exec('UPDATE fastmod_meta_generation SET generation=generation+1 WHERE singleton=1'); }
    private function transaction(callable $fn): mixed
    {
        $run=function () use ($fn): mixed {
            $this->pdo->exec('BEGIN IMMEDIATE');
            try { $result=$fn(); $this->pdo->exec('COMMIT'); return $result; }
            catch (\Throwable $e) { $this->pdo->exec('ROLLBACK'); throw $e; }
        };
        $path=(string)($this->pdo->query('PRAGMA database_list')->fetch()['file'] ?? '');
        return $path === '' ? $run() : (new \ForumRewrite\Support\ExecutionLock($path.'.publication.lock'))->withExclusiveLock($run);
    }
}
