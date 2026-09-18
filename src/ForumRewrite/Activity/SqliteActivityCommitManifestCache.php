<?php

declare(strict_types=1);

namespace ForumRewrite\Activity;

use PDO;

/**
 * Persists the (expensive-to-compute: a `git diff-tree` exec plus a
 * signature/OpenPGP lookup per file) commit file manifest used by the
 * Forte Activity detail pane, keyed by commit sha. Commits are immutable
 * and content-addressed, so once a manifest is cached it's cached
 * correctly forever - except that a file's signing key may be added to
 * the repository *after* its signing commit, which a cached entry won't
 * pick up; deleting this cache's database file forces a fresh rebuild.
 */
final class SqliteActivityCommitManifestCache
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
        $this->ensureSchema();
    }

    /**
     * @return list<array{status:string,path:string,previous_path:string,role:string,signature_signer_identity:string,signature_public_key_path:string,signature_key_status:string}>|null
     */
    public function get(string $commitSha): ?array
    {
        $stmt = $this->pdo->prepare('SELECT files_json FROM activity_commit_manifests WHERE commit_sha = :commit_sha');
        $stmt->execute(['commit_sha' => $commitSha]);
        $filesJson = $stmt->fetchColumn();
        if ($filesJson === false) {
            return null;
        }

        $decoded = json_decode((string) $filesJson, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param list<array{status:string,path:string,previous_path:string,role:string,signature_signer_identity:string,signature_public_key_path:string,signature_key_status:string}> $files
     */
    public function put(string $commitSha, array $files): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO activity_commit_manifests (commit_sha, files_json, cached_at)
             VALUES (:commit_sha, :files_json, :cached_at)
             ON CONFLICT(commit_sha) DO UPDATE SET
                files_json = excluded.files_json,
                cached_at = excluded.cached_at'
        );
        $stmt->execute([
            'commit_sha' => $commitSha,
            'files_json' => json_encode($files, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'cached_at' => gmdate('c'),
        ]);
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS activity_commit_manifests (
                commit_sha TEXT PRIMARY KEY,
                files_json TEXT NOT NULL,
                cached_at TEXT NOT NULL
            )'
        );
    }
}
