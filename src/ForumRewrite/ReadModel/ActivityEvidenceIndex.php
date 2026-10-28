<?php

declare(strict_types=1);
namespace ForumRewrite\ReadModel;

use ForumRewrite\Activity\ActivityService;
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
        $rows = $pdo->query('SELECT * FROM activity WHERE evidence_json IS NULL ORDER BY source_commit_sha, id')->fetchAll(PDO::FETCH_ASSOC);
        $update = $pdo->prepare('UPDATE activity SET evidence_json = ? WHERE id = ?');
        if ($rows === []) return;
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
