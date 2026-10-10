<?php

declare(strict_types=1);

namespace ForumRewrite\Messaging;

use PDO;
use InvalidArgumentException;

final class PrivateMessageStore
{
    public const MAILBOX_PAGE_SIZE = 25;
    private readonly PrivateMessageReadState $readState;

    public function __construct(
        private readonly PDO $pdo,
    ) {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->ensureSchema();
        $this->readState = new PrivateMessageReadState($this->pdo);
    }

    public function unreadStateFor(string $viewer, array $counterparts = []): array
    {
        return $this->readState->state($viewer, $counterparts);
    }

    public function markSeenThrough(string $viewer, string $counterpart, string $messageId): void
    {
        $this->readState->mark($viewer, $counterpart, $messageId);
    }

    public function readTokenFor(string $viewer, string $counterpart, string $pageCursor): string
    {
        $page = $this->conversationPageFor($viewer, $counterpart, $pageCursor);
        $position = json_decode(base64_decode(strtr($page['page_cursor'], '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);
        return $this->readState->token(strtolower(trim($viewer)), strtolower(trim($counterpart)), $position['snapshot']);
    }

    public function acknowledgeRead(string $viewer, string $counterpart, string $readToken): void
    {
        $this->readState->acknowledge($viewer, $counterpart, $readToken);
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

    /** Return an existing acknowledgment only for the exact authenticated attempt.
     * @param array<string, string> $attempt
     * @return array<string, string>|null
     */
    public function acceptedEnvelope(array $attempt): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM private_messages WHERE message_id = :id');
        $stmt->execute(['id' => $attempt['message_id']]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        foreach (['sender_username_token', 'sender_identity_id', 'recipient_username_token', 'encrypted_envelope'] as $field) {
            if (!hash_equals((string) $row[$field], $attempt[$field])) {
                throw new InvalidArgumentException('This send attempt conflicts with an existing message.');
            }
        }
        return array_intersect_key($row, array_flip(['message_id', 'created_at', 'sender_username_token', 'recipient_username_token']));
    }

    /** @param array<string, string> $attempt @return array<string, string> */
    public function acceptEnvelope(array $attempt): array
    {
        // The unique key arbitrates concurrent requests; never overwrite the first envelope.
        $stmt = $this->pdo->prepare('INSERT INTO private_messages
            (message_id, created_at, sender_username_token, recipient_username_token, sender_identity_id, encrypted_envelope)
            VALUES (:message_id, :created_at, :sender_username_token, :recipient_username_token, :sender_identity_id, :encrypted_envelope)
            ON CONFLICT(message_id) DO NOTHING');
        $stmt->execute($attempt);
        return $this->acceptedEnvelope($attempt) ?? throw new \RuntimeException('Unable to confirm private message.');
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
            'anchor' => (string) ($latest['message_id'] ?? ''), 'after_time' => '', 'after_row' => 0];
        if ($cursor !== null) {
            $decoded = strlen($cursor) <= 2048 ? base64_decode(strtr($cursor, '-_', '+/'), true) : false;
            $position = $decoded === false ? null : json_decode($decoded, true);
            if (!is_array($position) || ($position['viewer'] ?? null) !== $viewer
                || !is_int($position['snapshot'] ?? null) || $position['snapshot'] < 0
                || !is_string($position['anchor'] ?? null)
                || !is_string($position['after_time'] ?? null) || !is_int($position['after_row'] ?? null)
                || $position['after_row'] < 0 || $position['after_row'] > $position['snapshot']
                || (($position['after_time'] === '') !== ($position['after_row'] === 0))) {
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
                SELECT rowid AS activity_rowid, message_id, created_at,
                    CASE WHEN sender_username_token = :viewer THEN recipient_username_token ELSE sender_username_token END AS counterpart,
                    ROW_NUMBER() OVER (
                        PARTITION BY CASE WHEN sender_username_token = :viewer THEN recipient_username_token ELSE sender_username_token END
                        ORDER BY created_at DESC, rowid DESC
                    ) AS position
                FROM private_messages WHERE rowid <= :snapshot
                    AND (sender_username_token = :viewer OR recipient_username_token = :viewer)
             )
             SELECT scoped.counterpart, scoped.activity_rowid, message.message_id, message.created_at, message.sender_username_token,
                    message.recipient_username_token, message.sender_identity_id, message.encrypted_envelope
             FROM scoped JOIN private_messages AS message ON message.message_id = scoped.message_id
             WHERE scoped.position = 1
                AND (:after_time = \'\' OR scoped.created_at < :after_time
                    OR (scoped.created_at = :after_time AND scoped.activity_rowid < :after_row))
             ORDER BY scoped.created_at DESC, scoped.activity_rowid DESC LIMIT ' . (self::MAILBOX_PAGE_SIZE + 1)
        );
        $stmt->execute(['viewer' => $viewer, 'snapshot' => $position['snapshot'],
            'after_time' => $position['after_time'], 'after_row' => $position['after_row']]);
        $rows = $stmt->fetchAll();
        $hasMore = count($rows) > self::MAILBOX_PAGE_SIZE;
        $rows = array_slice($rows, 0, self::MAILBOX_PAGE_SIZE);
        $last = $rows[count($rows) - 1] ?? null;
        if ($last !== null) {
            $position['after_time'] = $last['created_at'];
            $position['after_row'] = (int) $last['activity_rowid'];
        }
        foreach ($rows as &$row) {
            unset($row['activity_rowid']);
        }
        unset($row);
        return ['conversations' => $rows, 'page_cursor' => $pageCursor, 'next_cursor' => $hasMore ? $encode($position) : null];
    }

    /** @return list<array<string, string>> */
    public function conversationFor(string $viewerUsernameToken, string $counterpartUsernameToken): array
    {
        return $this->conversationPageFor($viewerUsernameToken, $counterpartUsernameToken)['messages'];
    }

    /** @return array{messages:list<array<string, string>>,page_cursor:string,next_cursor:?string} */
    public function conversationPageFor(string $viewer, string $counterpart, ?string $cursor = null): array
    {
        $viewer = strtolower(trim($viewer));
        $counterpart = strtolower(trim($counterpart));
        $scope = '((sender_username_token = :viewer AND recipient_username_token = :counterpart)
            OR (sender_username_token = :counterpart AND recipient_username_token = :viewer))';
        $params = ['viewer' => $viewer, 'counterpart' => $counterpart];
        if ($cursor === null) {
            $boundary = $this->pdo->prepare('SELECT rowid, message_id FROM private_messages WHERE ' . $scope . ' ORDER BY rowid DESC LIMIT 1');
            $boundary->execute($params);
            $latest = $boundary->fetch();
            $position = ['version' => 1, 'viewer' => $viewer, 'counterpart' => $counterpart,
                'snapshot' => (int) ($latest['rowid'] ?? 0), 'anchor' => (string) ($latest['message_id'] ?? ''),
                'before_row' => 0, 'before_id' => '', 'before_time' => ''];
        } else {
            $decoded = strlen($cursor) <= 2048 ? base64_decode(strtr($cursor, '-_', '+/'), true) : false;
            $position = $decoded === false ? null : json_decode($decoded, true);
            if (!is_array($position) || ($position['version'] ?? null) !== 1
                || ($position['viewer'] ?? null) !== $viewer || ($position['counterpart'] ?? null) !== $counterpart
                || !is_int($position['snapshot'] ?? null) || $position['snapshot'] < 0
                || !is_string($position['anchor'] ?? null) || !is_string($position['before_id'] ?? null)
                || !is_string($position['before_time'] ?? null) || !is_int($position['before_row'] ?? null)
                || $position['before_row'] < 0 || $position['before_row'] > $position['snapshot']
                || (($position['before_row'] === 0) !== ($position['before_time'] === ''))
                || (($position['before_row'] === 0) !== ($position['before_id'] === ''))
                || (($position['snapshot'] === 0) !== ($position['anchor'] === ''))) {
                throw new InvalidHistoryCursor();
            }
            $check = $this->pdo->prepare('SELECT message_id, created_at FROM private_messages WHERE rowid = :row AND ' . $scope);
            if ($position['snapshot'] !== 0) {
                $check->execute($params + ['row' => $position['snapshot']]);
                if ($check->fetchColumn() !== $position['anchor']) throw new InvalidHistoryCursor();
            }
            if ($position['before_row'] !== 0) {
                $check->execute($params + ['row' => $position['before_row']]);
                $prior = $check->fetch();
                if (!$prior || $prior['message_id'] !== $position['before_id'] || $prior['created_at'] !== $position['before_time']) {
                    throw new InvalidHistoryCursor();
                }
            }
        }
        $encode = static fn (array $value): string => rtrim(strtr(base64_encode(json_encode($value, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $pageCursor = $encode($position);
        $stmt = $this->pdo->prepare(
            'SELECT rowid AS history_row, message_id, created_at, sender_username_token, recipient_username_token,
                    sender_identity_id, encrypted_envelope
             FROM private_messages WHERE ' . $scope . '
                AND rowid <= :snapshot AND (:before_time = \'\' OR created_at < :before_time
                    OR (created_at = :before_time AND rowid < :before_row))
             ORDER BY created_at DESC, rowid DESC LIMIT ' . (self::MAILBOX_PAGE_SIZE + 1)
        );
        $stmt->execute($params + [
            'snapshot' => $position['snapshot'], 'before_row' => $position['before_row'], 'before_time' => $position['before_time'],
        ]);
        $rows = $stmt->fetchAll();
        $hasMore = count($rows) > self::MAILBOX_PAGE_SIZE;
        $rows = array_slice($rows, 0, self::MAILBOX_PAGE_SIZE);
        if ($rows !== []) {
            $last = $rows[count($rows) - 1];
            $position['before_row'] = (int) $last['history_row'];
            $position['before_id'] = $last['message_id'];
            $position['before_time'] = $last['created_at'];
        }
        foreach ($rows as &$row) unset($row['history_row']);
        unset($row);
        return ['messages' => array_reverse($rows), 'page_cursor' => $pageCursor, 'next_cursor' => $hasMore ? $encode($position) : null];
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

    /** Bounded account-wide enumeration; durable references use IDs, not rowids. */
    public function historySyncPage(string $account, string $after, int $limit): array
    {
        $q=$this->pdo->prepare('SELECT * FROM private_messages WHERE (sender_username_token=:account OR recipient_username_token=:account) AND message_id>:after ORDER BY message_id LIMIT ' . (min(10,max(1,$limit))+1));
        $q->execute(['account'=>$account,'after'=>$after]);
        return $this->boundedHistoryPage($q->fetchAll(),$limit);
    }
    /** Outgoing-only discovery; targets are approved keys of each original recipient. */
    public function historySyncSentPage(string $sender, string $after, int $limit): array
    {
        $q=$this->pdo->prepare('SELECT * FROM private_messages WHERE sender_username_token=? AND recipient_username_token!=sender_username_token AND message_id>? ORDER BY message_id LIMIT '.(min(10,max(1,$limit))+1));
        $q->execute([$sender,$after]);
        return $this->boundedHistoryPage($q->fetchAll(),$limit);
    }
    private function boundedHistoryPage(array $rows, int $limit): array
    {
        $messages=[]; $bytes=0;
        foreach ($rows as $row) {
            if (count($messages)>=$limit || ($messages!==[] && $bytes+strlen($row['encrypted_envelope'])>2097152)) break;
            $messages[]=$row; $bytes+=strlen($row['encrypted_envelope']);
        }
        return ['messages'=>$messages,'more'=>count($rows)>count($messages),'last'=>$messages===[]?'':$messages[count($messages)-1]['message_id']];
    }
    public function historySyncMessage(string $account, string $id): ?array
    {
        $q=$this->pdo->prepare('SELECT * FROM private_messages WHERE message_id=? AND (sender_username_token=? OR recipient_username_token=?)');
        $q->execute([$id,$account,$account]);
        return $q->fetch() ?: null;
    }
    public function historySyncCount(string $account): int
    {
        $q=$this->pdo->prepare('SELECT COUNT(*) FROM private_messages WHERE sender_username_token=? OR recipient_username_token=?');
        $q->execute([$account,$account]); return (int)$q->fetchColumn();
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
