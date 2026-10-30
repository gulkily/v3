<?php

declare(strict_types=1);
namespace ForumRewrite\ReadModel;

use ForumRewrite\Activity\ActivityService;
use ForumRewrite\Activity\CommitEvidenceLookup;
use PDO;

/** Called within the existing indexing transaction; readers never populate missing evidence. */
final class ActivityEvidenceIndex
{
    /** A newly available identity/key may change evidence on its earlier events. */
    public static function invalidateIdentity(PDO $pdo, string $root, string $identity): void
    {
        $query = $pdo->prepare('SELECT source_path FROM reaction_signatures WHERE author_identity_id = ?');
        $query->execute([$identity]);
        $update = $pdo->prepare('UPDATE reaction_signatures SET signature_status = ? WHERE source_path = ?');
        foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $path) {
            if (!is_file($root . '/' . $path)) continue;
            $contents = \ForumRewrite\Canonical\RecordLimits::read($root . '/' . $path);
            $update->execute([\ForumRewrite\Security\ReactionSignature::status($root, $path, $contents, $identity), $path]);
        }
        $pdo->prepare("UPDATE activity SET evidence_json = NULL WHERE author_identity_id = ? AND COALESCE(source_path, '') != ''")->execute([$identity]);
    }

    public static function refresh(PDO $pdo, string $root, string $database): void
    {
        $update = $pdo->prepare('UPDATE activity SET evidence_json = ? WHERE id = ?');
        // Completed rows leave the existing pending-evidence index. Fetch a
        // bounded batch and close its cursor before updating the same table.
        $lookup = new CommitEvidenceLookup($root);
        try {
            while (true) {
                $query = $pdo->query('SELECT * FROM activity WHERE evidence_json IS NULL ORDER BY source_commit_sha, id LIMIT 200');
                $rows = $query->fetchAll(PDO::FETCH_ASSOC);
                $query->closeCursor();
                if ($rows === []) return;
                self::refreshBatch($pdo, $root, $database, $rows, $update, $lookup);
                unset($rows);
            }
        } finally { $lookup->close(); }
    }

    private static function refreshBatch(PDO $pdo, string $root, string $database, array $rows, \PDOStatement $update, CommitEvidenceLookup $lookup): void
    {
        $signatures = [];
        foreach (array_chunk(array_unique(array_column($rows, 'source_path')), 200) as $paths) {
            $query = $pdo->prepare('SELECT source_path, signature_status FROM reaction_signatures WHERE source_path IN (' . implode(',', array_fill(0, count($paths), '?')) . ')');
            $query->execute($paths);
            $signatures += $query->fetchAll(PDO::FETCH_KEY_PAIR);
        }
        $groups = [];
        foreach ($rows as $row) $groups[(string) $row['source_commit_sha']][] = $row;
        // Bound manifest lifetime to a small commit batch, independent of total repository size.
        foreach (array_chunk($groups, 32, true) as $batch) {
            $service = new ActivityService(static fn (): PDO => $pdo, $root, $database, evidenceLookup: $lookup);
            $lookup->prepareCommits(array_keys($batch));
            foreach ($batch as $group) foreach ($group as $row) {
                $update->execute([$service->buildIndexedEvidence($row, $signatures), $row['id']]);
            }
        }
    }
}
