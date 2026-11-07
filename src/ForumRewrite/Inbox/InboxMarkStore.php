<?php

declare(strict_types=1);

namespace ForumRewrite\Inbox;

use InvalidArgumentException;
use PDO;

/**
 * Caught-up marks for Inbox sections, one per username and section, in private storage.
 *
 * A mark is the newest post time a member has caught up to. Signed posts may arrive up to
 * ten minutes after their own timestamp, so a mark also keeps the IDs it acknowledged in the
 * ten minutes before it: a late post inside that window stays unread unless it was shown.
 * Marks hold post IDs and times, never read-model row numbers, so a read-model rebuild
 * leaves them valid. Marks only move forward, and only when the member asks.
 */
final class InboxMarkStore
{
    public const SECTIONS = ['replies', 'new'];
    public const LATE_ARRIVAL_SECONDS = 600;

    /** @param \Closure(): string|null $clock returns the current UTC time as Y-m-d\TH:i:s\Z */
    public function __construct(private readonly PDO $pdo, private readonly ?\Closure $clock = null)
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE IF NOT EXISTS inbox_marks (
            viewer TEXT NOT NULL, section TEXT NOT NULL, mark_at TEXT NOT NULL,
            PRIMARY KEY(viewer, section)
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS inbox_acknowledged (
            viewer TEXT NOT NULL, section TEXT NOT NULL, post_id TEXT NOT NULL, created_at TEXT NOT NULL,
            PRIMARY KEY(viewer, section, post_id)
        )');
    }

    /**
     * The mark for a section, created at the current time on first use so earlier posts are history.
     *
     * @return array{mark_at:string,window_start:string,acknowledged:array<string,true>}
     */
    public function mark(string $viewer, string $section): array
    {
        [$viewer, $section] = $this->key($viewer, $section);
        $stmt = $this->pdo->prepare('SELECT mark_at FROM inbox_marks WHERE viewer = ? AND section = ?');
        $stmt->execute([$viewer, $section]);
        $markAt = $stmt->fetchColumn();
        if ($markAt === false) {
            $this->write(function () use ($viewer, $section): void {
                $this->pdo->prepare('INSERT OR IGNORE INTO inbox_marks (viewer, section, mark_at) VALUES (?, ?, ?)')
                    ->execute([$viewer, $section, $this->now()]);
            });
            $stmt->execute([$viewer, $section]);
            $markAt = $stmt->fetchColumn();
        }
        $markAt = (string) $markAt;
        $windowStart = self::shift($markAt, -self::LATE_ARRIVAL_SECONDS);
        $acknowledged = $this->pdo->prepare('SELECT post_id FROM inbox_acknowledged WHERE viewer = ? AND section = ? AND created_at > ?');
        $acknowledged->execute([$viewer, $section, $windowStart]);

        return [
            'mark_at' => $markAt,
            'window_start' => $windowStart,
            'acknowledged' => array_fill_keys($acknowledged->fetchAll(PDO::FETCH_COLUMN), true),
        ];
    }

    /**
     * Acknowledges exactly the posts that were shown. The mark moves to the newest of them,
     * never backward and never past a post the member did not see.
     *
     * @param array<string, string> $shown post ID => created_at, already checked to be unread in this section
     */
    public function catchUp(string $viewer, string $section, array $shown): void
    {
        [$viewer, $section] = $this->key($viewer, $section);
        if ($shown === []) return;
        foreach ($shown as $postId => $createdAt) {
            if (!is_string($postId) || $postId === '' || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $createdAt)) {
                throw new InvalidArgumentException('Shown posts are invalid.');
            }
        }
        $this->write(function () use ($viewer, $section, $shown): void {
            $this->pdo->prepare('INSERT OR IGNORE INTO inbox_marks (viewer, section, mark_at) VALUES (?, ?, ?)')
                ->execute([$viewer, $section, $this->now()]);
            $newest = max($shown);
            $this->pdo->prepare('UPDATE inbox_marks SET mark_at = ? WHERE viewer = ? AND section = ? AND mark_at < ?')
                ->execute([$newest, $viewer, $section, $newest]);
            $current = $this->pdo->prepare('SELECT mark_at FROM inbox_marks WHERE viewer = ? AND section = ?');
            $current->execute([$viewer, $section]);
            $windowStart = self::shift((string) $current->fetchColumn(), -self::LATE_ARRIVAL_SECONDS);
            $insert = $this->pdo->prepare('INSERT OR IGNORE INTO inbox_acknowledged (viewer, section, post_id, created_at) VALUES (?, ?, ?, ?)');
            foreach ($shown as $postId => $createdAt) {
                if ($createdAt > $windowStart) $insert->execute([$viewer, $section, $postId, $createdAt]);
            }
            $this->pdo->prepare('DELETE FROM inbox_acknowledged WHERE viewer = ? AND section = ? AND created_at <= ?')
                ->execute([$viewer, $section, $windowStart]);
        });
    }

    public static function shift(string $timestamp, int $seconds): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', (int) strtotime($timestamp) + $seconds);
    }

    /** @return array{0:string,1:string} */
    private function key(string $viewer, string $section): array
    {
        $viewer = strtolower(trim($viewer));
        if ($viewer === '') throw new InvalidArgumentException('Inbox needs a username.');
        if (!in_array($section, self::SECTIONS, true)) throw new InvalidArgumentException('Unknown Inbox section.');
        return [$viewer, $section];
    }

    private function now(): string
    {
        return $this->clock !== null ? ($this->clock)() : gmdate('Y-m-d\TH:i:s\Z');
    }

    private function write(callable $operation): void
    {
        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            $operation();
            $this->pdo->exec('COMMIT');
        } catch (\Throwable $error) {
            $this->pdo->exec('ROLLBACK');
            throw $error;
        }
    }
}
