<?php

declare(strict_types=1);

namespace ForumRewrite\Work;

use PDO;

/**
 * Work items are threads tagged #work. Each item's current status is derived at read
 * time from `status:` lines (see WorkStatusDirective) in its posts: the latest line
 * from a verified, approved member wins, and an item with none is `open`. Nothing is
 * stored separately, so a read-model rebuild always yields the same status.
 */
final class WorkItems
{
    public const TAG = 'work';
    public const DEFAULT_STALE_DAYS = 7;

    public static function isWorkThread(array $threadRow): bool
    {
        foreach (['board_tags_json', 'thread_labels_json'] as $column) {
            $tags = json_decode((string) ($threadRow[$column] ?? '[]'), true);
            if (is_array($tags) && in_array(self::TAG, array_map(static fn ($t): string => strtolower((string) $t), $tags), true)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed>|null */
    public static function item(PDO $pdo, string $threadId, ?int $now = null, int $staleDays = self::DEFAULT_STALE_DAYS): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM threads WHERE root_post_id = :id AND retracted_at IS NULL');
        $stmt->execute(['id' => $threadId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        if ($row === false || !self::isWorkThread($row)) {
            return null;
        }

        return self::build($row, self::latestStatuses($pdo, [$threadId])[$threadId] ?? null, $now ?? time(), $staleDays, self::sources($pdo, [$threadId])[$threadId] ?? '');
    }

    /**
     * @param list<string> $words matched case-insensitively against title and preview; all must match
     * @return list<array<string, mixed>> ordered by thread ID (stable for export)
     */
    public static function list(PDO $pdo, array $words = [], ?string $status = null, ?int $now = null, int $staleDays = self::DEFAULT_STALE_DAYS): array
    {
        $rows = $pdo->query(
            "SELECT * FROM threads WHERE retracted_at IS NULL
             AND (board_tags_json LIKE '%work%' OR thread_labels_json LIKE '%work%')
             ORDER BY root_post_id"
        )->fetchAll(PDO::FETCH_ASSOC);
        $rows = array_values(array_filter($rows, self::isWorkThread(...)));
        $latest = self::latestStatuses($pdo, array_column($rows, 'root_post_id'));
        $sources = self::sources($pdo, array_column($rows, 'root_post_id'));
        $words = array_values(array_filter(array_map(static fn ($w): string => mb_strtolower(trim((string) $w)), $words), static fn (string $w): bool => $w !== ''));
        $items = [];
        foreach ($rows as $row) {
            $item = self::build($row, $latest[$row['root_post_id']] ?? null, $now ?? time(), $staleDays, $sources[$row['root_post_id']] ?? '');
            if ($status !== null && $status !== '' && $item['status'] !== strtolower($status) && !($status === 'stale' && $item['stale'])) {
                continue;
            }
            $haystack = mb_strtolower($item['title'] . ' ' . (string) ($row['body_preview'] ?? ''));
            foreach ($words as $word) {
                if (!str_contains($haystack, $word)) {
                    continue 2;
                }
            }
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param list<string> $threadIds
     * @return array<string, array{status: string, note: string, set_at: string, set_by: string, post_id: string}>
     */
    private static function latestStatuses(PDO $pdo, array $threadIds): array
    {
        if ($threadIds === []) {
            return [];
        }
        $latest = [];
        foreach (array_chunk($threadIds, 400) as $chunk) {
            $marks = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $pdo->prepare(
                "SELECT p.post_id, p.thread_id, p.created_at, p.body, pr.username
                 FROM posts p JOIN profiles pr ON pr.identity_id = p.author_identity_id
                 WHERE p.thread_id IN ($marks) AND p.body LIKE '%status:%'
                   AND p.retracted_at IS NULL AND p.is_hidden = 0
                   AND p.attribution_verified = 1 AND pr.is_approved = 1 AND pr.retired_at IS NULL
                 ORDER BY p.created_at, p.sequence_number"
            );
            $stmt->execute($chunk);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $post) {
                $directive = WorkStatusDirective::extract((string) $post['body']);
                if ($directive === null) {
                    continue;
                }
                $latest[(string) $post['thread_id']] = $directive + [
                    'set_at' => (string) $post['created_at'],
                    'set_by' => (string) $post['username'],
                    'post_id' => (string) $post['post_id'],
                ];
            }
        }

        return $latest;
    }

    /**
     * Threads tagged #request, not yet retracted, in thread-ID order: the site inbox that
     * `./v3 work sweep` turns into work items.
     *
     * @return list<array{thread_id: string, title: string}>
     */
    public static function requests(PDO $pdo): array
    {
        $rows = $pdo->query(
            "SELECT * FROM threads WHERE retracted_at IS NULL
             AND (board_tags_json LIKE '%request%' OR thread_labels_json LIKE '%request%')
             ORDER BY root_post_id"
        )->fetchAll(PDO::FETCH_ASSOC);
        $requests = [];
        foreach ($rows as $row) {
            foreach (['board_tags_json', 'thread_labels_json'] as $column) {
                $tags = json_decode((string) ($row[$column] ?? '[]'), true);
                if (is_array($tags) && in_array('request', array_map(static fn ($t): string => strtolower((string) $t), $tags), true)) {
                    $requests[] = ['thread_id' => (string) $row['root_post_id'], 'title' => self::title($row)];
                    break;
                }
            }
        }

        return $requests;
    }

    /**
     * The `Source:` line of each item's root post, which records where a swept item came from.
     *
     * @param list<string> $threadIds
     * @return array<string, string>
     */
    private static function sources(PDO $pdo, array $threadIds): array
    {
        $sources = [];
        foreach (array_chunk($threadIds, 400) as $chunk) {
            $marks = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $pdo->prepare("SELECT post_id, body FROM posts WHERE post_id IN ($marks)");
            $stmt->execute($chunk);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $post) {
                if (preg_match('/^\s*Source:\s*(\S+)\s*$/mi', (string) $post['body'], $match) === 1) {
                    $sources[(string) $post['post_id']] = $match[1];
                }
            }
        }

        return $sources;
    }

    private static function title(array $row): string
    {
        $title = trim((string) ($row['subject'] ?? ''));

        return $title !== '' ? $title : mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($row['body_preview'] ?? '')) ?? ''), 0, 80);
    }

    /** @return array<string, mixed> */
    private static function build(array $row, ?array $latest, int $now, int $staleDays, string $source = ''): array
    {
        $status = $latest['status'] ?? 'open';
        $lastActivity = strtotime((string) $row['last_activity_at']) ?: $now;
        $title = self::title($row);

        return [
            'thread_id' => (string) $row['root_post_id'],
            'title' => $title,
            'status' => $status,
            'note' => $latest['note'] ?? '',
            'set_by' => $latest['set_by'] ?? '',
            'set_at' => $latest['set_at'] ?? '',
            'last_activity_at' => (string) $row['last_activity_at'],
            'stale' => $status === 'active' && ($now - $lastActivity) > $staleDays * 86400,
            'source' => $source,
        ];
    }
}
