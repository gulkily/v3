<?php

declare(strict_types=1);

namespace ForumRewrite\Messaging;

use PDO;
use RuntimeException;
use InvalidArgumentException;

/** Private metadata only. Message insertion positions are always checked against stable IDs. */
final class PrivateMessageReadState
{
    public function __construct(private readonly PDO $pdo)
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS private_message_tracking (
            singleton INTEGER PRIMARY KEY CHECK(singleton = 1), baseline_row INTEGER NOT NULL,
            baseline_id TEXT NOT NULL, signing_secret TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS private_message_seen (
            viewer TEXT NOT NULL, counterpart TEXT NOT NULL, seen_row INTEGER NOT NULL, seen_id TEXT NOT NULL,
            PRIMARY KEY(viewer, counterpart)
        )');
        if ($pdo->query('SELECT COUNT(*) FROM private_message_tracking')->fetchColumn() == 0) {
            $this->write(function (): void {
                if ($this->pdo->query('SELECT COUNT(*) FROM private_message_tracking')->fetchColumn() != 0) return;
                if ($this->pdo->query('SELECT COUNT(*) FROM private_message_seen')->fetchColumn() != 0) {
                    throw new RuntimeException('Private message tracking metadata needs repair.');
                }
                $last = $this->pdo->query('SELECT rowid AS position, message_id FROM private_messages ORDER BY rowid DESC LIMIT 1')->fetch();
                $stmt = $this->pdo->prepare('INSERT INTO private_message_tracking VALUES (1, :row, :id, :secret)');
                $stmt->execute(['row' => $last['position'] ?? 0, 'id' => $last['message_id'] ?? '', 'secret' => bin2hex(random_bytes(32))]);
            });
        }
    }

    /** @return array<string, mixed> */
    public function state(string $viewer, array $counterparts = []): array
    {
        $viewer = strtolower(trim($viewer));
        $counterparts = array_values(array_unique($counterparts));
        if (count($counterparts) > 25) throw new InvalidArgumentException('Request at most 25 conversation states.');
        foreach ($counterparts as $counterpart) {
            if (!is_string($counterpart) || !preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/', $counterpart)) {
                throw new InvalidArgumentException('Conversation counterpart is invalid.');
            }
        }
        $this->pdo->beginTransaction();
        try {
            $metadata = $this->validatedMetadata($viewer);
            $stmt = $this->pdo->prepare('SELECT DISTINCT m.sender_username_token FROM private_messages m
                LEFT JOIN private_message_seen s ON s.viewer = :viewer AND s.counterpart = m.sender_username_token
                WHERE m.recipient_username_token = :viewer AND m.sender_username_token != :viewer
                    AND m.rowid > :baseline AND m.rowid > COALESCE(s.seen_row, 0)');
            $stmt->execute(['viewer' => $viewer, 'baseline' => $metadata['baseline_row']]);
            $unread = array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);
            $rows = [];
            foreach ($counterparts as $counterpart) $rows[$counterpart] = isset($unread[$counterpart]);
            $last = $this->pdo->query('SELECT rowid AS position, message_id FROM private_messages ORDER BY rowid DESC LIMIT 1')->fetch();
            $progress = $this->pdo->prepare('SELECT counterpart, seen_row, seen_id FROM private_message_seen WHERE viewer = :viewer ORDER BY counterpart');
            $progress->execute(['viewer' => $viewer]);
            $revision = hash('sha256', json_encode([$viewer, $metadata['baseline_id'], $last, $progress->fetchAll()], JSON_THROW_ON_ERROR));
            $this->pdo->commit();
            return ['viewer' => $viewer, 'unread_count' => count($unread), 'conversations' => (object) $rows, 'revision' => $revision];
        } catch (\Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function mark(string $viewer, string $counterpart, string $messageId): void
    {
        $viewer = strtolower(trim($viewer));
        $counterpart = strtolower(trim($counterpart));
        $this->write(function () use ($viewer, $counterpart, $messageId): void {
            $this->validatedMetadata($viewer);
            if ($messageId === '') return;
            $stmt = $this->pdo->prepare('SELECT rowid FROM private_messages WHERE message_id = :id
                AND recipient_username_token = :viewer AND sender_username_token = :counterpart');
            $stmt->execute(['id' => $messageId, 'viewer' => $viewer, 'counterpart' => $counterpart]);
            $row = $stmt->fetchColumn();
            if ($row === false || $viewer === $counterpart) throw new InvalidArgumentException('Received message boundary is unavailable. Reopen the conversation.');
            $stmt = $this->pdo->prepare('INSERT INTO private_message_seen (viewer, counterpart, seen_row, seen_id)
                VALUES (:viewer, :counterpart, :row, :id)
                ON CONFLICT(viewer, counterpart) DO UPDATE SET seen_row = excluded.seen_row, seen_id = excluded.seen_id
                WHERE excluded.seen_row > private_message_seen.seen_row');
            $stmt->execute(['viewer' => $viewer, 'counterpart' => $counterpart, 'row' => $row, 'id' => $messageId]);
        });
    }

    /** @return array<string, mixed> */
    private function validatedMetadata(string $viewer): array
    {
        $metadata = $this->pdo->query('SELECT * FROM private_message_tracking WHERE singleton = 1')->fetch();
        if (!$metadata || !preg_match('/^[a-f0-9]{64}$/', $metadata['signing_secret'])) throw new RuntimeException('Private message tracking metadata needs repair.');
        $baseline = $this->pdo->prepare('SELECT message_id FROM private_messages WHERE rowid = :row');
        $baseline->execute(['row' => $metadata['baseline_row']]);
        if (((int) $metadata['baseline_row'] === 0 && $metadata['baseline_id'] !== '')
            || ((int) $metadata['baseline_row'] !== 0 && $baseline->fetchColumn() !== $metadata['baseline_id'])) {
            throw new RuntimeException('Private message tracking baseline needs repair.');
        }
        $invalid = $this->pdo->prepare('SELECT COUNT(*) FROM private_message_seen s LEFT JOIN private_messages m ON m.rowid = s.seen_row
            WHERE s.viewer = :viewer AND (m.message_id IS NULL OR m.message_id != s.seen_id
                OR m.recipient_username_token != s.viewer OR m.sender_username_token != s.counterpart)');
        $invalid->execute(['viewer' => $viewer]);
        if ($invalid->fetchColumn() != 0) throw new RuntimeException('Private message seen position needs repair.');
        return $metadata;
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
