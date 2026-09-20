<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use PDO;

final class ReadModelCapabilityInspector
{
    public function commitsAvailable(PDO $pdo): bool
    {
        $table = $pdo->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'commits' LIMIT 1"
        )->fetchColumn();
        if ($table === false) {
            return false;
        }

        $columns = [];
        foreach ($pdo->query('PRAGMA table_info(commits)')->fetchAll() as $column) {
            $columns[(string) $column['name']] = true;
        }

        foreach (['id', 'sha', 'author_name', 'author_email', 'committed_at', 'subject', 'file_count'] as $required) {
            if (!isset($columns[$required])) {
                return false;
            }
        }

        return true;
    }
}
