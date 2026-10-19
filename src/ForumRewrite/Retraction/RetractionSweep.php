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
