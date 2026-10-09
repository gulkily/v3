<?php

declare(strict_types=1);

namespace ForumRewrite\Messaging;

use PDO;

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
