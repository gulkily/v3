<?php

declare(strict_types=1);

namespace ForumRewrite\Messaging;

use PDO;
use InvalidArgumentException;

final class PrivateMessageStore
{
    public const MAILBOX_PAGE_SIZE = 25;

    public function __construct(
        private readonly PDO $pdo,
    ) {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->ensureSchema();
    }

    public function storeEnvelope(
        string $messageId,
        string $createdAt,
        string $senderUsernameToken,
        string $recipientUsernameToken,
        string $senderIdentityId,
        string $encryptedEnvelope,
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO private_messages (
                message_id, created_at, sender_username_token, recipient_username_token,
                sender_identity_id, encrypted_envelope
             ) VALUES (
                :message_id, :created_at, :sender_username_token, :recipient_username_token,
                :sender_identity_id, :encrypted_envelope
             )'
        );
        $stmt->execute([
            'message_id' => $messageId,
            'created_at' => $createdAt,
            'sender_username_token' => $senderUsernameToken,
            'recipient_username_token' => $recipientUsernameToken,
            'sender_identity_id' => $senderIdentityId,
            'encrypted_envelope' => $encryptedEnvelope,
        ]);
    }

    /** @return list<array<string, string>> */
    public function inboxFor(string $recipientUsernameToken): array
    {
        return $this->mailbox('recipient_username_token', $recipientUsernameToken);
    }

    /** @return list<array<string, string>> */
    public function sentBy(string $senderUsernameToken): array
    {
        return $this->mailbox('sender_username_token', $senderUsernameToken);
    }

    /** @return array{conversations:list<array<string, string>>,page_cursor:string,next_cursor:?string} */
    public function conversationsFor(string $viewer, ?string $cursor = null): array
    {
        $viewer = strtolower(trim($viewer));
        $boundary = $this->pdo->prepare(
            'SELECT rowid, message_id FROM private_messages
             WHERE sender_username_token = :viewer OR recipient_username_token = :viewer
             ORDER BY rowid DESC LIMIT 1'
        );
        $boundary->execute(['viewer' => $viewer]);
        $latest = $boundary->fetch();
        $position = ['viewer' => $viewer, 'snapshot' => (int) ($latest['rowid'] ?? 0),
            'anchor' => (string) ($latest['message_id'] ?? ''), 'after_time' => '', 'after_id' => ''];
        if ($cursor !== null) {
            $decoded = strlen($cursor) <= 2048 ? base64_decode(strtr($cursor, '-_', '+/'), true) : false;
            $position = $decoded === false ? null : json_decode($decoded, true);
            if (!is_array($position) || ($position['viewer'] ?? null) !== $viewer
                || !is_int($position['snapshot'] ?? null) || $position['snapshot'] < 0
                || !is_string($position['anchor'] ?? null)
                || !is_string($position['after_time'] ?? null) || !is_string($position['after_id'] ?? null)
                || (($position['after_time'] === '') !== ($position['after_id'] === ''))) {
                throw new InvalidArgumentException('This message list has expired. Reload Messages to start again.');
            }
            $anchor = $this->pdo->prepare(
                'SELECT message_id FROM private_messages WHERE rowid = :snapshot
                 AND (sender_username_token = :viewer OR recipient_username_token = :viewer)'
            );
            $anchor->execute(['snapshot' => $position['snapshot'], 'viewer' => $viewer]);
            if (($position['snapshot'] === 0 && $position['anchor'] !== '')
                || ($position['snapshot'] !== 0 && $anchor->fetchColumn() !== $position['anchor'])) {
                throw new InvalidArgumentException('This message list has expired. Reload Messages to start again.');
            }
        }
        $encode = static fn (array $value): string => rtrim(strtr(base64_encode(json_encode($value, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $pageCursor = $encode($position);
        $stmt = $this->pdo->prepare(
            'WITH scoped AS (
                SELECT message_id, created_at,
                    CASE WHEN sender_username_token = :viewer THEN recipient_username_token ELSE sender_username_token END AS counterpart,
                    ROW_NUMBER() OVER (
                        PARTITION BY CASE WHEN sender_username_token = :viewer THEN recipient_username_token ELSE sender_username_token END
                        ORDER BY created_at DESC, message_id DESC
                    ) AS position
                FROM private_messages WHERE rowid <= :snapshot
                    AND (sender_username_token = :viewer OR recipient_username_token = :viewer)
             )
             SELECT scoped.counterpart, message.message_id, message.created_at, message.sender_username_token,
                    message.recipient_username_token, message.sender_identity_id, message.encrypted_envelope
             FROM scoped JOIN private_messages AS message ON message.message_id = scoped.message_id
             WHERE scoped.position = 1
                AND (:after_time = \'\' OR scoped.created_at < :after_time
                    OR (scoped.created_at = :after_time AND scoped.message_id < :after_id))
             ORDER BY scoped.created_at DESC, scoped.message_id DESC LIMIT ' . (self::MAILBOX_PAGE_SIZE + 1)
        );
        $stmt->execute(['viewer' => $viewer, 'snapshot' => $position['snapshot'],
            'after_time' => $position['after_time'], 'after_id' => $position['after_id']]);
        $rows = $stmt->fetchAll();
        $hasMore = count($rows) > self::MAILBOX_PAGE_SIZE;
        $rows = array_slice($rows, 0, self::MAILBOX_PAGE_SIZE);
        $last = $rows[count($rows) - 1] ?? null;
        if ($last !== null) {
            $position['after_time'] = $last['created_at'];
            $position['after_id'] = $last['message_id'];
        }
        return ['conversations' => $rows, 'page_cursor' => $pageCursor, 'next_cursor' => $hasMore ? $encode($position) : null];
    }

    /** @return list<array<string, string>> */
    public function conversationFor(string $viewerUsernameToken, string $counterpartUsernameToken): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT message_id, created_at, sender_username_token, recipient_username_token,
                    sender_identity_id, encrypted_envelope
             FROM private_messages
             WHERE (sender_username_token = :viewer AND recipient_username_token = :counterpart)
                OR (sender_username_token = :counterpart AND recipient_username_token = :viewer)
             ORDER BY created_at DESC, message_id DESC
             LIMIT ' . self::MAILBOX_PAGE_SIZE
        );
        $stmt->execute([
            'viewer' => strtolower(trim($viewerUsernameToken)),
            'counterpart' => strtolower(trim($counterpartUsernameToken)),
        ]);

        return array_reverse($stmt->fetchAll());
    }

    /** @return list<array<string, string>> */
    private function mailbox(string $column, string $usernameToken): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT message_id, created_at, sender_username_token, recipient_username_token,
                    sender_identity_id, encrypted_envelope
             FROM private_messages
             WHERE ' . $column . ' = :username_token
             ORDER BY created_at DESC, message_id DESC
             LIMIT ' . self::MAILBOX_PAGE_SIZE
        );
        $stmt->execute(['username_token' => strtolower(trim($usernameToken))]);

        return $stmt->fetchAll();
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS private_messages (
                message_id TEXT PRIMARY KEY,
                created_at TEXT NOT NULL,
                sender_username_token TEXT NOT NULL,
                recipient_username_token TEXT NOT NULL,
                sender_identity_id TEXT NOT NULL,
                encrypted_envelope TEXT NOT NULL
            )'
        );
        $this->pdo->exec(
            'CREATE INDEX IF NOT EXISTS private_messages_inbox_idx
             ON private_messages (recipient_username_token, created_at DESC, message_id DESC)'
        );
        $this->pdo->exec(
            'CREATE INDEX IF NOT EXISTS private_messages_sent_idx
             ON private_messages (sender_username_token, created_at DESC, message_id DESC)'
        );
    }
}
