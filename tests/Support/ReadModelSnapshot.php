<?php

declare(strict_types=1);

/** Comparable read-model contents, for checking a targeted update against a full rebuild. */
final class ReadModelSnapshot
{
    /**
     * Read-model rows a rebuild and a targeted update must agree on. Left out: per-build
     * values (metadata, random revisions, autoincrement IDs, global sequence numbers) and
     * the commits table and its count, which incremental writes never maintain.
     *
     * @return array<string, list<string>>
     */
    public static function take(string $database): array
    {
        $pdo = new PDO('sqlite:' . $database);
        $skipTables = ['metadata', 'commits', 'history_keys_revision', 'activity_local_state'];
        $skipColumns = ['posts' => ['sequence_number'], 'activity' => ['id']];
        $snapshot = [];
        foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN) as $table) {
            if (in_array($table, $skipTables, true)) continue;
            $rows = [];
            foreach ($pdo->query('SELECT * FROM "' . $table . '"')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ($table === 'activity_counts' && $row['view'] === 'commits') continue;
                foreach ($skipColumns[$table] ?? [] as $column) unset($row[$column]);
                $rows[] = json_encode($row);
            }
            sort($rows);
            $snapshot[$table] = $rows;
        }
        // Sequence numbers are global and differ between builds; the order inside a thread must not.
        $order = [];
        foreach ($pdo->query('SELECT thread_id, post_id FROM posts ORDER BY thread_id, sequence_number')->fetchAll(PDO::FETCH_NUM) as [$thread, $post]) $order[$thread][] = $post;
        $snapshot['thread order'] = array_map(static fn (string $thread, array $posts): string => $thread . ': ' . implode(' ', $posts), array_keys($order), $order);

        return $snapshot;
    }

    /**
     * @param array<string, list<string>> $rebuilt
     * @param array<string, list<string>> $targeted
     * @return list<string> rows present in only one of the two
     */
    public static function differences(array $rebuilt, array $targeted): array
    {
        $differences = [];
        foreach (array_unique(array_merge(array_keys($rebuilt), array_keys($targeted))) as $table) {
            foreach (array_diff($rebuilt[$table] ?? [], $targeted[$table] ?? []) as $row) $differences[] = "{$table} rebuild: {$row}";
            foreach (array_diff($targeted[$table] ?? [], $rebuilt[$table] ?? []) as $row) $differences[] = "{$table} targeted: {$row}";
        }

        return $differences;
    }
}
