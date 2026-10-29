<?php

declare(strict_types=1);
namespace ForumRewrite\Retraction;

use ForumRewrite\Llm\{ExchangeDependencies, ExchangeRetractionState};
use PDO;
use RuntimeException;

/** Repeatable local cleanup. Task queue owns scheduling/retries; no separate progress ledger. */
final class RetractionSweep
{
    public function __construct(
        private readonly string $repositoryRoot,
        private readonly string $exchangePath,
        private readonly string $statePath,
        private readonly array $artifactRoots = [],
    ) {}

    public function hasRetractions(): bool { return ExchangeRetractionState::read($this->repositoryRoot) !== []; }
    public function state(): array { return $this->preview(); }
    public function preview(int $limit = 250): array { return $this->scan(max(1, $limit), true); }

    public function run(int $limit = 250): array
    {
        return (new \ForumRewrite\Support\ExecutionLock($this->statePath . '.lock'))->withExclusiveLock(function () use ($limit): array {
            $result = $this->scan(max(1, $limit), false);
            if (is_file($this->statePath)) {
                $old = json_decode((string) file_get_contents($this->statePath), true);
                if (is_array($old) && (isset($old['generation']) || isset($old['cursor']) || isset($old['configuration']))) {
                    if (!unlink($this->statePath)) throw new RuntimeException('Cannot remove obsolete sweep ledger.');
                }
            }
            return $result;
        });
    }

    private function scan(int $batchSize, bool $preview): array
    {
        $ids = ExchangeRetractionState::read($this->repositoryRoot);
        $result = ['status' => $preview ? 'preview' : 'complete', 'retractions' => count($ids),
            'examined' => 0, 'affected' => 0, 'cleaned' => 0, 'uncertain' => 0, 'remaining' => false,
            'exchange_store' => is_file($this->exchangePath) ? 'available' : 'absent'];
        if ($ids === []) return $result;
        // Existing artifacts retire immediately; late stale publishers age out on a later scan.
        $cutoff = time() - 300;
        $receiptPaths = [];
        foreach (\ForumRewrite\Canonical\AcceptedRetraction::all($this->repositoryRoot) as $id => $record) {
            $receiptPaths[] = \ForumRewrite\Canonical\AcceptedRetraction::path($id);
        }
        foreach (\ForumRewrite\Canonical\LabelRemoval::all($this->repositoryRoot) as $id => $record) {
            if ($record['operation'] === 'delete') $receiptPaths[] = \ForumRewrite\Canonical\LabelRemoval::path($id);
        }
        foreach ($receiptPaths as $path) $cutoff = max($cutoff, (int) filemtime($this->repositoryRoot . '/' . $path));
        $result['artifacts'] = ArtifactSweep::run($this->artifactRoots, $cutoff, $preview);
        if (file_exists($this->exchangePath) && !is_file($this->exchangePath)) throw new RuntimeException('Exchange store is not a regular database.');
        if (!is_file($this->exchangePath)) return $result;
        $pdo = new PDO($preview ? 'sqlite:file:' . $this->exchangePath . '?mode=ro' : 'sqlite:' . $this->exchangePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (!$preview) ExchangeScrubber::prepare($pdo);
        $cursor = 0; // In-memory paging only: every run scans the complete store, without starvation.
        do {
            $query = $pdo->prepare('SELECT * FROM llm_exchanges WHERE id > ? ORDER BY id LIMIT ' . $batchSize);
            $query->execute([$cursor]); $rows = $query->fetchAll(PDO::FETCH_ASSOC); $query->closeCursor();
            foreach ($rows as $row) {
                $cursor = (int) $row['id']; ++$result['examined'];
                $deps = self::dependencies($row);
                if ($deps === null) ++$result['uncertain'];
                // Older payloads without reliable dependencies cannot be selectively retained.
                if (($deps === null || array_intersect($deps, array_keys($ids)) !== []) && !ExchangeScrubber::isClean($row, $deps ?? [])) {
                    $deps ??= [];
                    ++$result['affected'];
                    if (!$preview) { ExchangeScrubber::scrub($pdo, $cursor, $deps); ++$result['cleaned']; }
                }
            }
        } while (count($rows) === $batchSize);
        if (!$preview) ExchangeScrubber::checkpoint($pdo);
        return $result;
    }

    public static function dependencies(array $row): ?array
    {
        foreach (['request', 'context'] as $field) {
            $decoded = json_decode((string) ($row[$field . '_json'] ?? ''), true);
            if (!is_array($decoded)) return null;
            $row[$field] = $decoded;
        }
        return ExchangeDependencies::classify($row);
    }
}
