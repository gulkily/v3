<?php

declare(strict_types=1);
namespace ForumRewrite\ReadModel;

use ForumRewrite\Activity\ActivityService;
use PDO;

/** Called within the existing indexing transaction; readers never populate missing evidence. */
final class ActivityEvidenceIndex
{
    public static function refresh(PDO $pdo, string $root, string $database): void
    {
        $rows = $pdo->query('SELECT * FROM activity WHERE evidence_json IS NULL ORDER BY source_commit_sha, id')->fetchAll(PDO::FETCH_ASSOC);
        $update = $pdo->prepare('UPDATE activity SET evidence_json = ? WHERE id = ?');
        $signatures = $pdo->query('SELECT source_path, signature_status FROM reaction_signatures')->fetchAll(PDO::FETCH_KEY_PAIR);
        $service = null;
        $commit = null;
        foreach ($rows as $row) {
            // Keep one commit's paths in memory; sharing a bulk commit never repeats its Git work.
            if ($service === null || $commit !== $row['source_commit_sha']) {
                $service = new ActivityService(static fn (): PDO => $pdo, $root, $database);
                $commit = $row['source_commit_sha'];
            }
            $update->execute([$service->buildIndexedEvidence($row, $signatures), $row['id']]);
        }
    }
}
