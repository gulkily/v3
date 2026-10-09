<?php

declare(strict_types=1);

namespace ForumRewrite\View;

final class MediaEmbedPreviewDatabaseConfig
{
    public static function path(string $projectRoot): string
    {
        return rtrim($projectRoot, '/\\') . '/state/cache/media_embed_previews.sqlite3';
    }
}
