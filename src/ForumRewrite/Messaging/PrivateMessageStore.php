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
        foreach(PrivateMessageHistoryRevision::statements('private_messages','history_messages',['@.sender_username_token','@.recipient_username_token']) as $sql) $this->pdo->exec($sql);
        PrivateMessageHistoryRevision::ensureEpoch($this->pdo,'history_messages');
        $this->readState = new PrivateMessageReadState($this->pdo);
    }

    public function historyRevision(string $account): string { return PrivateMessageHistoryRevision::token($this->pdo,'history_messages',$account); }

    public function unreadStateFor(string $viewer, array $counterparts = []): array
    {
        return $this->readState->state($viewer, $counterparts) + ['has_history' => $this->hasHistory($viewer)];
    }

    /** @return list<array{counterpart:string,unread_count:int,latest_at:string}> */
    public function unreadConversationsFor(string $viewer, int $limit = 25): array
    {
        return $this->readState->unreadConversations($viewer, $limit);
    }

    private function hasHistory(string $account): bool
    {
        $q=$this->pdo->prepare('SELECT 1 FROM private_messages WHERE sender_username_token=? OR recipient_username_token=? LIMIT 1');
        $account=strtolower(trim($account));
        $q->execute([$account,$account]);
        return $q->fetchColumn() !== false;
    }

    public function readTokenFor(string $viewer, string $counterpart, string $pageCursor): string
    {
        $page = $this->conversationPageFor($viewer, $counterpart, $pageCursor);
        $position = json_decode(base64_decode(strtr($page['page_cursor'], '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);
        return $this->readState->token(strtolower(trim($viewer)), strtolower(trim($counterpart)), $position['snapshot']);
    }

    public function isSeenBy(string $viewer, string $counterpart, string $messageId): bool
    {
        return $this->readState->isSeen($viewer, $counterpart, $messageId);
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

    public const EXPORT_PAGE_SIZE = 25;

    /**
     * Stores a pulled envelope under its original ID without ever replacing a local one.
     * @param array<string, mixed> $row one message from exportPage()
     * @return 'added'|'existing'|'mismatch'
     */
    public function importEnvelope(array $row): string
    {
        $fields = ['message_id', 'created_at', 'sender_username_token', 'recipient_username_token', 'sender_identity_id', 'encrypted_envelope'];
        $attempt = [];
        foreach ($fields as $field) {
            if (!is_string($row[$field] ?? null) || $row[$field] === '') throw new InvalidArgumentException('Pulled message is missing ' . $field . '.');
            $attempt[$field] = $row[$field];
        }
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/D', $attempt['message_id']) !== 1) throw new InvalidArgumentException('Pulled message ID is invalid.');
        $stmt = $this->pdo->prepare('INSERT INTO private_messages (' . implode(', ', $fields) . ')
            VALUES (:' . implode(', :', $fields) . ') ON CONFLICT(message_id) DO NOTHING');
        $stmt->execute($attempt);
        if ($stmt->rowCount() === 1) return 'added';
        $existing = $this->pdo->prepare('SELECT ' . implode(', ', $fields) . ' FROM private_messages WHERE message_id = ?');
        $existing->execute([$attempt['message_id']]);
        $local = $existing->fetch();
        foreach ($fields as $field) {
            if (!is_array($local) || !hash_equals((string) $local[$field], $attempt[$field])) return 'mismatch';
        }
        return 'existing';
    }

    /** Copies "already read" for a pulled received message; boundaries only move forward. */
    public function importReadMark(string $viewer, string $sender, string $messageId): void
    {
        $this->readState->mark($viewer, $sender, $messageId);
    }

    /**
     * The account's own sent and received envelopes, oldest first, after a readable
     * (created_at, message_id) position. A received message is read when it sits at or
     * below the tracking baseline or the viewer's seen boundary for its sender.
     *
     * @return array{messages:list<array<string, mixed>>,next:?array{after_time:string,after_id:string}}
     */
    public function exportPage(string $account, string $afterTime = '', string $afterId = '', int $limit = self::EXPORT_PAGE_SIZE): array
    {
        $account = strtolower(trim($account));
        $limit = min(self::EXPORT_PAGE_SIZE, max(1, $limit));
        $q = $this->pdo->prepare(
            'SELECT m.message_id, m.created_at, m.sender_username_token, m.recipient_username_token,
                    m.sender_identity_id, m.encrypted_envelope,
                    CASE WHEN m.recipient_username_token != :account THEN 1
                         WHEN m.sender_username_token = :account THEN 1
                         WHEN m.rowid <= t.baseline_row THEN 1
                         WHEN m.rowid <= COALESCE(s.seen_row, 0) THEN 1 ELSE 0 END AS is_read
             FROM private_messages m
             JOIN private_message_tracking t ON t.singleton = 1
             LEFT JOIN private_message_seen s ON s.viewer = :account AND s.counterpart = m.sender_username_token
             WHERE (m.sender_username_token = :account OR m.recipient_username_token = :account)
               AND (m.created_at > :after_time OR (m.created_at = :after_time AND m.message_id > :after_id))
             ORDER BY m.created_at, m.message_id LIMIT ' . ($limit + 1)
        );
        $q->execute(['account' => $account, 'after_time' => $afterTime, 'after_id' => $afterId]);
        $rows = $q->fetchAll();
        $more = count($rows) > $limit;
        $messages = [];
        $bytes = 0;
        foreach (array_slice($rows, 0, $limit) as $row) {
            if ($messages !== [] && $bytes + strlen((string) $row['encrypted_envelope']) > 2097152) {
                $more = true;
                break;
            }
            $row['read'] = (int) $row['is_read'] === 1;
            unset($row['is_read']);
            $messages[] = $row;
            $bytes += strlen((string) $row['encrypted_envelope']);
        }
        $last = $messages[count($messages) - 1] ?? null;
        return ['messages' => $messages, 'next' => $more && $last !== null
            ? ['after_time' => (string) $last['created_at'], 'after_id' => (string) $last['message_id']] : null];
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
