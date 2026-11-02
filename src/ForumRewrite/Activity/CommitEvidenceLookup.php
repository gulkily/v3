<?php

declare(strict_types=1);
namespace ForumRewrite\Activity;

use ForumRewrite\Canonical\SourcePathValidator;
use PDO;
use RuntimeException;

/** Disposable operation-local manifests. Only requested paths enter the PHP heap. */
final class CommitEvidenceLookup
{
    private ?PDO $pdo = null;
    private ?\PDOStatement $select = null;
    private string $path;
    /** @var array<string,true> At most the current small batch, including failed/missing commits. */
    private array $loaded = [];
    /** Bounded cache of requested paths only; includes misses. */
    private array $requested = [];
    private int $manifestReads = 0;
    private int $processes = 0;

    public function __construct(private readonly string $repositoryRoot)
    {
        $path = tempnam(sys_get_temp_dir(), 'activity-evidence-');
        if ($path === false) throw new RuntimeException('Cannot prepare Activity evidence storage.');
        $this->path = $path;
        try {
            $this->pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
            $this->pdo->exec('PRAGMA cache_size = -1024');
            // This operation-local file is discarded after any crash; logical rollback
            // still uses the journal, but durable fsync of scratch data has no value.
            $this->pdo->exec('PRAGMA synchronous = OFF');
            $this->pdo->exec('PRAGMA locking_mode = EXCLUSIVE');
            $this->pdo->exec('CREATE TABLE files (sha TEXT NOT NULL, path TEXT NOT NULL, status TEXT NOT NULL, previous_path TEXT NOT NULL, PRIMARY KEY (sha,path)) WITHOUT ROWID');
            $this->select = $this->pdo->prepare('SELECT status,path,previous_path FROM files WHERE sha = ? AND path = ?');
            // Nothing in this database is published. One operation transaction
            // avoids a disk commit for every small batch; savepoints isolate loads.
            $this->pdo->beginTransaction();
        } catch (\Throwable $error) { $this->close(); throw $error; }
    }

    public function prepareCommit(string $sha): void
    {
        if (!isset($this->loaded[$sha])) $this->prepareCommits([$sha]);
    }

    /** Keep batching small commits while retaining a shared commit across adjacent activity batches. */
    public function prepareCommits(array $commits): void
    {
        $this->requested = [];
        $commits = array_values(array_filter(array_unique($commits), SourcePathValidator::isValidCommitSha(...)));
        $keep = array_fill_keys($commits, true);
        $delete = $this->pdo->prepare('DELETE FROM files WHERE sha = ?');
        foreach ($this->loaded as $sha => $_) {
            if (!isset($keep[$sha])) { $delete->execute([$sha]); unset($this->loaded[$sha]); }
        }
        $missing = array_values(array_filter($commits, fn(string $sha): bool => !isset($this->loaded[$sha])));
        if ($missing === []) return;
        if (is_dir($this->repositoryRoot . '/.git') && !$this->load($missing, true)) {
            // Legacy batch failure falls back to each individual commit.
            foreach ($missing as $sha) $this->load([$sha], false);
        }
        foreach ($missing as $sha) $this->loaded[$sha] = true;
    }

    /** @return array<string,array{status:string,path:string,previous_path:string}> */
    public function filesForPaths(string $sha, array $paths): array
    {
        if (!SourcePathValidator::isValidCommitSha($sha)) return [];
        $this->prepareCommit($sha);
        $files = [];
        foreach (array_unique($paths) as $path) {
            $key = $sha . ':' . $path;
            if (!array_key_exists($key, $this->requested)) {
                if (count($this->requested) >= 512) $this->requested = [];
                $this->select->execute([$sha, $path]);
                $this->requested[$key] = $this->select->fetch();
                $this->select->closeCursor();
            }
            $row = $this->requested[$key];
            if ($row !== false) $files[$path] = $row;
        }
        return $files;
    }

    private function load(array $commits, bool $batch): bool
    {
        $input = tmpfile();
        if ($input === false) throw new RuntimeException('Cannot prepare Activity commit input.');
        $process = null; $output = null; $savepoint = false;
        try {
            $text = implode("\n", $commits) . "\n";
            if (fwrite($input, $text) !== strlen($text)) throw new RuntimeException('Cannot write Activity commit input.');
            rewind($input);
            $command = ['git', '-C', $this->repositoryRoot, 'diff-tree', '--root', '--name-status', '-r', '-M'];
            $command = array_merge($command, $batch ? ['--stdin'] : ['--no-commit-id', $commits[0]]);
            $process = proc_open($command, [0=>$input, 1=>['pipe','w'], 2=>['file','/dev/null','w']], $pipes);
            if (!is_resource($process)) return false;
            $this->processes++;
            $output = $pipes[1];
            $this->pdo->exec('SAVEPOINT manifest'); $savepoint = true;
            $insert = $this->pdo->prepare('INSERT OR REPLACE INTO files (sha,path,status,previous_path) VALUES (?,?,?,?)');
            $known = array_fill_keys($commits, true);
            $sha = $batch ? null : $commits[0];
            while (($line = fgets($output)) !== false) {
                $line = rtrim($line);
                if ($batch && isset($known[$line])) { $sha = $line; continue; }
                if ($sha === null) continue;
                foreach (ActivityService::parseCommitFiles([$line]) as $file) {
                    $insert->execute([$sha,$file['path'],$file['status'],$file['previous_path']]);
                }
            }
            if (!feof($output)) throw new RuntimeException('Cannot read Activity commit output.');
            fclose($output); $output = null;
            $code = proc_close($process); $process = null;
            if ($code !== 0) return false;
            $this->pdo->exec('RELEASE manifest'); $savepoint = false;
            $this->manifestReads += count($commits);
            return true;
        } finally {
            if (is_resource($output)) fclose($output);
            if (is_resource($process)) { proc_terminate($process); proc_close($process); }
            if ($savepoint) {
                $this->pdo->exec('ROLLBACK TO manifest');
                $this->pdo->exec('RELEASE manifest');
            }
            fclose($input);
        }
    }

    public function manifestReads(): int { return $this->manifestReads; }
    public function processes(): int { return $this->processes; }

    public function close(): void
    {
        $this->select = null;
        if ($this->pdo?->inTransaction()) $this->pdo->rollBack();
        $this->pdo = null;
        $this->loaded = [];
        $this->requested = [];
        if (isset($this->path)) { @unlink($this->path); @unlink($this->path . '-journal'); }
    }

    public function __destruct() { $this->close(); }
}
