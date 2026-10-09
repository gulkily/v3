<?php

declare(strict_types=1);

namespace ForumRewrite\View;

use PDO;

/**
 * Disposable, rebuildable cache of fetched media-embed preview metadata
 * (title/thumbnail). A failed fetch is still recorded, with null title and
 * thumbnailUrl, so callers can tell "recently attempted and failed" apart
 * from "never attempted" without a second outbound fetch.
 */
final class MediaEmbedPreviewCacheStore
{
    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->ensureSchema();
    }

    /**
     * @return ?array{title: ?string, thumbnailUrl: ?string, fetchedAt: string}
     */
    public function get(string $provider, string $embedId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT title, thumbnail_url, fetched_at FROM media_embed_previews WHERE provider = :provider AND embed_id = :embed_id'
        );
        $statement->execute(['provider' => $provider, 'embed_id' => $embedId]);
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return [
            'title' => $row['title'] !== null ? (string) $row['title'] : null,
            'thumbnailUrl' => $row['thumbnail_url'] !== null ? (string) $row['thumbnail_url'] : null,
            'fetchedAt' => (string) $row['fetched_at'],
        ];
    }

    public function put(string $provider, string $embedId, ?string $title, ?string $thumbnailUrl): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO media_embed_previews (provider, embed_id, title, thumbnail_url, fetched_at)
             VALUES (:provider, :embed_id, :title, :thumbnail_url, :fetched_at)
             ON CONFLICT(provider, embed_id) DO UPDATE SET
                title = excluded.title,
                thumbnail_url = excluded.thumbnail_url,
                fetched_at = excluded.fetched_at'
        );
        $statement->execute([
            'provider' => $provider,
            'embed_id' => $embedId,
            'title' => $title,
            'thumbnail_url' => $thumbnailUrl,
            'fetched_at' => (new \DateTimeImmutable())->format('c'),
        ]);
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS media_embed_previews (
                provider TEXT NOT NULL,
                embed_id TEXT NOT NULL,
                title TEXT,
                thumbnail_url TEXT,
                fetched_at TEXT NOT NULL,
                PRIMARY KEY (provider, embed_id)
            )'
        );
    }
}
