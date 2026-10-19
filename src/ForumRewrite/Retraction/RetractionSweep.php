<?php

declare(strict_types=1);
namespace ForumRewrite\Retraction;

use ForumRewrite\Llm\ExchangeDependencies;
use ForumRewrite\Llm\ExchangeRetractionState;
use PDO;
use RuntimeException;

/** Local logical cleanup only; canonical evidence is never modified. */
final class RetractionSweep
{
    public function __construct(
        private readonly string $repositoryRoot,
        private readonly string $exchangePath,
        private readonly string $statePath,
        private readonly array $artifactRoots = [],
    ) {}

    public function preview(int $limit = 250): array
    {
        $ids = ExchangeRetractionState::read($this->repositoryRoot);
        if ($ids === null) throw new RuntimeException('Retraction evidence unavailable; repair canonical state before cleanup.');
        $result = ['status' => 'preview', 'retractions' => count($ids), 'examined' => 0, 'affected' => 0,
            'uncertain' => 0, 'cleaned' => 0, 'remaining' => false,
            'stores' => ['generated_html', 'offline_copies', 'llm_exchanges'],
            'excluded' => ['canonical_and_history', 'analysis_caches', 'other_logs', 'backups', 'attachments', 'published_replies', 'external_copies']];
        if (!is_file($this->exchangePath)) return $result;
        $pdo = new PDO('sqlite:file:' . $this->exchangePath . '?mode=ro');
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $query = $pdo->query('SELECT * FROM llm_exchanges ORDER BY id LIMIT ' . (max(1, $limit) + 1));
        while ($row = $query->fetch()) {
            if ($result['examined'] === max(1, $limit)) { $result['remaining'] = true; break; }
            ++$result['examined'];
            $dependencies = self::dependencies($row);
            if ($dependencies === null) ++$result['uncertain'];
            elseif (array_intersect($dependencies, array_keys($ids)) !== []) ++$result['affected'];
        }
        return $result;
    }

    public function run(int $limit = 250): array
    {
        return (new \ForumRewrite\Support\ExecutionLock($this->statePath . '.lock'))->withExclusiveLock(function () use ($limit): array {
            $state = $this->state();
            try {
                $ids = ExchangeRetractionState::read($this->repositoryRoot);
                if ($ids === null) throw new RuntimeException('Retraction evidence unavailable.');
                ksort($ids);
                $generation = hash('sha256', json_encode(array_keys($ids), JSON_THROW_ON_ERROR));
                $cursor = ($state['generation'] ?? '') === $generation && ($state['remaining'] ?? false) ? (int) ($state['cursor'] ?? 0) : 0;
                $result = ['status' => 'complete', 'generation' => $generation, 'examined' => 0, 'cleaned' => 0,
                    'uncertain' => 0, 'cursor' => $cursor, 'remaining' => false, 'updated_at' => gmdate('c')];
                if (is_file($this->exchangePath)) {
                    $pdo = new PDO('sqlite:' . $this->exchangePath);
                    ExchangeScrubber::prepare($pdo);
                    $pdo->exec('BEGIN IMMEDIATE');
                    try {
                        $query = $pdo->prepare('SELECT * FROM llm_exchanges WHERE id > ? ORDER BY id LIMIT ' . (max(1, $limit) + 1));
                        $query->execute([$cursor]);
                        $rows = $query->fetchAll(PDO::FETCH_ASSOC);
                        $query->closeCursor();
                        foreach ($rows as $row) {
                            if ($result['examined'] >= max(1, $limit)) { $result['remaining'] = true; break; }
                            ++$result['examined'];
                            $result['cursor'] = (int) $row['id'];
                            $deps = self::dependencies($row);
                            $context = json_decode($row['context_json'], true);
                            if ($deps === null) ++$result['uncertain'];
                            elseif (array_intersect($deps, array_keys($ids)) !== [] && ($context['retraction_scrubbed'] ?? false) !== true) {
                                ExchangeScrubber::scrub($pdo, (int) $row['id'], $deps);
                                ++$result['cleaned'];
                            }
                        }
                        $pdo->exec('COMMIT');
                    } catch (\Throwable $error) { $pdo->exec('ROLLBACK'); throw $error; }
                    ExchangeScrubber::checkpoint($pdo);
                }
                $result['status'] = $result['remaining'] ? 'pending' : 'complete';
                $this->save($result);
                return $result;
            } catch (\Throwable $error) {
                $this->save(['status' => 'failed', 'remaining' => true, 'updated_at' => gmdate('c'), 'failure' => 'cleanup_failed']);
                throw $error;
            }
        });
    }

    public function state(): array
    {
        if (!is_file($this->statePath)) return ['status' => 'pending', 'remaining' => true];
        $state = json_decode((string) file_get_contents($this->statePath), true);
        return is_array($state) ? $state : ['status' => 'failed', 'remaining' => true];
    }

    private function save(array $state): void
    {
        $path = $this->statePath . '.tmp';
        if (file_put_contents($path, json_encode($state, JSON_THROW_ON_ERROR), LOCK_EX) === false
            || !chmod($path, 0600) || !rename($path, $this->statePath)) throw new RuntimeException('Cannot save cleanup progress.');
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
