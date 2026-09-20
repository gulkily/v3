<?php

declare(strict_types=1);

namespace ForumRewrite\TaskQueue;

final class TaskQueueDatabaseConfig
{
    public static function path(string $projectRoot, ?string $configuredPath = null): string
    {
        $path = trim((string) ($configuredPath ?? getenv('FORUM_TASK_QUEUE_DATABASE_PATH') ?: ''));
        if ($path !== '') {
            return $path;
        }

        return rtrim($projectRoot, '/\\') . '/state/private/internal_tasks.sqlite3';
    }
}
