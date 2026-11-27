<?php

declare(strict_types=1);

namespace ForumRewrite\Push;

use PDO;

/**
 * Devices that turned notifications on, one row per push endpoint, in a private SQLite file
 * (state/private/push.sqlite3) apart from forum content, so backups and exports never see it.
 *
 * Each row names the member (username and key) and the session that subscribed it. A send
 * checks that the session is still signed in as that key; otherwise the row is deleted.
 */
final class PushSubscriptionStore
{
    public const MAX_DEVICES_PER_USERNAME = 10;
    public const MAX_REPORT_BYTES = 4096;
    public const REPORTS_PER_HOUR = 10;
    public const REPORTS_KEPT = 500;

    public function __construct(private readonly PDO $pdo)
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('CREATE TABLE IF NOT EXISTS push_subscriptions (
            endpoint TEXT NOT NULL PRIMARY KEY,
            username TEXT NOT NULL,
            identity_id TEXT NOT NULL,
            session_key TEXT NOT NULL,
            p256dh TEXT NOT NULL,
            auth TEXT NOT NULL,
            created_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL,
            last_sent_at INTEGER NOT NULL DEFAULT 0
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS push_subscriptions_username ON push_subscriptions (username, updated_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS push_subscriptions_session ON push_subscriptions (session_key)');
        // Notices waiting to be sent (DMs and replies), one per event and recipient.
        $pdo->exec('CREATE TABLE IF NOT EXISTS push_pending (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            kind TEXT NOT NULL,
            source_id TEXT NOT NULL,
            source_created_at TEXT NOT NULL DEFAULT \'\',
            recipient TEXT NOT NULL,
            tag TEXT NOT NULL,
            actor TEXT NOT NULL,
            body TEXT NOT NULL,
            url TEXT NOT NULL,
            site TEXT NOT NULL,
            created_at INTEGER NOT NULL,
            status TEXT NOT NULL DEFAULT \'pending\',
            done_at INTEGER NULL,
            route TEXT NULL,
            UNIQUE (kind, source_id, recipient)
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS push_pending_open ON push_pending (status, recipient, tag, id)');
        // One line per notice actually sent to a member, for the hold window and the cap.
        $pdo->exec('CREATE TABLE IF NOT EXISTS push_sends (
            recipient TEXT NOT NULL,
            tag TEXT NOT NULL,
            sent_at INTEGER NOT NULL
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS push_sends_recent ON push_sends (recipient, sent_at)');
        // Turn on failures a browser reported (push_turn_on_diagnostics), for operators.
        $pdo->exec('CREATE TABLE IF NOT EXISTS push_reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL,
            received_at INTEGER NOT NULL,
            body TEXT NOT NULL
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS push_reports_username ON push_reports (username, received_at)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS push_meta (name TEXT NOT NULL PRIMARY KEY, value TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS push_lease (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            owner TEXT NOT NULL,
            expires_at INTEGER NOT NULL
        )');
    }

    public static function open(string $path): self
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            @mkdir($directory, 0700, true);
        }

        return new self(new PDO('sqlite:' . $path));
    }

    /**
     * Adds or refreshes a device. A second member on the same device replaces the row, because a
     * browser has one endpoint. Beyond the device cap, the member's least recently refreshed rows go.
     */
    public function save(PushSubscription $subscription, string $username, string $identityId, string $sessionKey, int $now): void
    {
        $username = strtolower($username);
        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            $existing = $this->pdo->prepare('SELECT created_at, username FROM push_subscriptions WHERE endpoint = ?');
            $existing->execute([$subscription->endpoint]);
            $row = $existing->fetch();
            $createdAt = is_array($row) && $row['username'] === $username ? (int) $row['created_at'] : $now;
            $this->pdo->prepare('INSERT OR REPLACE INTO push_subscriptions
                (endpoint, username, identity_id, session_key, p256dh, auth, created_at, updated_at, last_sent_at)
                VALUES (:endpoint, :username, :identity_id, :session_key, :p256dh, :auth, :created_at, :updated_at, 0)')
                ->execute([
                    'endpoint' => $subscription->endpoint,
                    'username' => $username,
                    'identity_id' => strtolower($identityId),
                    'session_key' => $sessionKey,
                    'p256dh' => WebPushCrypto::base64UrlEncode($subscription->userPublicKey),
                    'auth' => WebPushCrypto::base64UrlEncode($subscription->authSecret),
                    'created_at' => $createdAt,
                    'updated_at' => $now,
                ]);
            $this->pdo->prepare('DELETE FROM push_subscriptions WHERE username = :username AND endpoint NOT IN (
                SELECT endpoint FROM push_subscriptions WHERE username = :username ORDER BY updated_at DESC, created_at DESC LIMIT :cap)')
                ->execute(['username' => $username, 'cap' => self::MAX_DEVICES_PER_USERNAME]);
            $this->pdo->exec('COMMIT');
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->exec('ROLLBACK');
            }
            throw $exception;
        }
    }

    /** @return array{endpoint:string,username:string,identity_id:string,session_key:string,subscription:PushSubscription}|null */
    public function find(string $endpoint): ?array
    {
        $statement = $this->pdo->prepare('SELECT endpoint, username, identity_id, session_key, p256dh, auth FROM push_subscriptions WHERE endpoint = ?');
        $statement->execute([$endpoint]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            return null;
        }

        return [
            'endpoint' => (string) $row['endpoint'],
            'username' => (string) $row['username'],
            'identity_id' => (string) $row['identity_id'],
            'session_key' => (string) $row['session_key'],
            'subscription' => PushSubscription::fromBrowser((string) $row['endpoint'], (string) $row['p256dh'], (string) $row['auth']),
        ];
    }

    public function delete(string $endpoint): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM push_subscriptions WHERE endpoint = ?');
        $statement->execute([$endpoint]);

        return $statement->rowCount() > 0;
    }

    /** Signing out removes every device the session subscribed. */
    public function deleteForSession(string $sessionKey): int
    {
        if ($sessionKey === '') {
            return 0;
        }
        $statement = $this->pdo->prepare('DELETE FROM push_subscriptions WHERE session_key = ?');
        $statement->execute([$sessionKey]);

        return $statement->rowCount();
    }

    public function markSent(string $endpoint, int $now): void
    {
        $this->pdo->prepare('UPDATE push_subscriptions SET last_sent_at = ? WHERE endpoint = ?')->execute([$now, $endpoint]);
    }

    public function countForUsername(string $username): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM push_subscriptions WHERE username = ?');
        $statement->execute([strtolower($username)]);

        return (int) $statement->fetchColumn();
    }

    /** @return list<array{endpoint:string,username:string,identity_id:string,session_key:string,subscription:PushSubscription}> */
    public function devicesFor(string $username): array
    {
        $statement = $this->pdo->prepare('SELECT endpoint, username, identity_id, session_key, p256dh, auth FROM push_subscriptions WHERE username = ? ORDER BY created_at, endpoint');
        $statement->execute([strtolower($username)]);
        $devices = [];
        foreach ($statement->fetchAll() as $row) {
            try {
                $subscription = PushSubscription::fromBrowser((string) $row['endpoint'], (string) $row['p256dh'], (string) $row['auth']);
            } catch (\InvalidArgumentException) {
                continue;
            }
            $devices[] = [
                'endpoint' => (string) $row['endpoint'],
                'username' => (string) $row['username'],
                'identity_id' => (string) $row['identity_id'],
                'session_key' => (string) $row['session_key'],
                'subscription' => $subscription,
            ];
        }

        return $devices;
    }

    /**
     * Records a notice to send. A repeat of the same event for the same member is ignored.
     *
     * @param array{kind:string,source_id:string,source_created_at?:string,recipient:string,tag:string,actor:string,body:string,url:string,site:string} $notice
     * @return bool true when the notice is new
     */
    public function addPending(array $notice, int $now): bool
    {
        $statement = $this->pdo->prepare('INSERT OR IGNORE INTO push_pending
            (kind, source_id, source_created_at, recipient, tag, actor, body, url, site, created_at)
            VALUES (:kind, :source_id, :source_created_at, :recipient, :tag, :actor, :body, :url, :site, :created_at)');
        $statement->execute([
            'kind' => $notice['kind'],
            'source_id' => $notice['source_id'],
            'source_created_at' => $notice['source_created_at'] ?? '',
            'recipient' => strtolower($notice['recipient']),
            'tag' => $notice['tag'],
            'actor' => $notice['actor'],
            'body' => $notice['body'],
            'url' => $notice['url'],
            'site' => $notice['site'],
            'created_at' => $now,
        ]);

        return $statement->rowCount() > 0;
    }

    /** @return list<array<string, mixed>> pending notices, oldest first */
    public function pending(): array
    {
        return $this->pdo->query("SELECT id, kind, source_id, source_created_at, recipient, tag, actor, body, url, site, created_at
            FROM push_pending WHERE status = 'pending' ORDER BY id")->fetchAll();
    }

    public function hasPending(): bool
    {
        return $this->pdo->query("SELECT 1 FROM push_pending WHERE status = 'pending' LIMIT 1")->fetchColumn() !== false;
    }

    /** @param list<int> $ids */
    public function finishPending(array $ids, string $status, int $now, string $route): void
    {
        if ($ids === []) {
            return;
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $this->pdo->prepare("UPDATE push_pending SET status = ?, done_at = ?, route = ? WHERE status = 'pending' AND id IN ($marks)")
            ->execute([$status, $now, $route, ...array_map('intval', $ids)]);
    }

    public function recordSend(string $recipient, string $tag, int $now): void
    {
        $this->pdo->prepare('INSERT INTO push_sends (recipient, tag, sent_at) VALUES (:recipient, :tag, :sent_at)')
            ->execute(['recipient' => strtolower($recipient), 'tag' => $tag, 'sent_at' => $now]);
    }

    public function lastSendAt(string $recipient, string $tag): ?int
    {
        $statement = $this->pdo->prepare('SELECT MAX(sent_at) FROM push_sends WHERE recipient = ? AND tag = ?');
        $statement->execute([strtolower($recipient), $tag]);
        $value = $statement->fetchColumn();

        return $value === null || $value === false ? null : (int) $value;
    }

    public function sendsSince(string $recipient, int $since, ?string $exceptTag = null): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM push_sends WHERE recipient = ? AND sent_at > ? AND tag != ?');
        $statement->execute([strtolower($recipient), $since, $exceptTag ?? '']);

        return (int) $statement->fetchColumn();
    }

    /** Drops finished notices and send lines older than the cutoff. */
    public function prune(int $before): void
    {
        $this->pdo->prepare("DELETE FROM push_pending WHERE status != 'pending' AND done_at < ?")->execute([$before]);
        $this->pdo->prepare('DELETE FROM push_sends WHERE sent_at < ?')->execute([$before]);
    }

    /** One sender at a time: true when this caller now holds the lease. */
    public function takeLease(string $owner, int $now, int $seconds): bool
    {
        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            $row = $this->pdo->query('SELECT owner, expires_at FROM push_lease WHERE id = 1')->fetch();
            $free = !is_array($row) || (int) $row['expires_at'] <= $now || $row['owner'] === $owner;
            if ($free) {
                $this->pdo->prepare('INSERT OR REPLACE INTO push_lease (id, owner, expires_at) VALUES (1, :owner, :expires_at)')
                    ->execute(['owner' => $owner, 'expires_at' => $now + $seconds]);
            }
            $this->pdo->exec('COMMIT');

            return $free;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->exec('ROLLBACK');
            }
            throw $exception;
        }
    }

    public function releaseLease(string $owner): void
    {
        $this->pdo->prepare('DELETE FROM push_lease WHERE id = 1 AND owner = ?')->execute([$owner]);
    }

    /** The site's https address, remembered from web requests so the task worker can sign for it. */
    public function rememberSite(string $site): void
    {
        $this->pdo->prepare("INSERT INTO push_meta (name, value) VALUES ('site', :value)
            ON CONFLICT(name) DO UPDATE SET value = excluded.value WHERE push_meta.value != excluded.value")
            ->execute(['value' => $site]);
    }

    public function site(): ?string
    {
        $value = $this->pdo->query("SELECT value FROM push_meta WHERE name = 'site'")->fetchColumn();

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Keeps a browser's Turn on failure report. Returns false when the member has sent
     * REPORTS_PER_HOUR in the last hour. The oldest reports past REPORTS_KEPT are dropped.
     */
    public function addReport(string $username, string $body, int $now): bool
    {
        $username = strtolower($username);
        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            $recent = $this->pdo->prepare('SELECT COUNT(*) FROM push_reports WHERE username = ? AND received_at > ?');
            $recent->execute([$username, $now - 3600]);
            if ((int) $recent->fetchColumn() >= self::REPORTS_PER_HOUR) {
                $this->pdo->exec('COMMIT');

                return false;
            }
            $this->pdo->prepare('INSERT INTO push_reports (username, received_at, body) VALUES (:username, :received_at, :body)')
                ->execute(['username' => $username, 'received_at' => $now, 'body' => $body]);
            $this->pdo->prepare('DELETE FROM push_reports WHERE id NOT IN (SELECT id FROM push_reports ORDER BY id DESC LIMIT ?)')
                ->execute([self::REPORTS_KEPT]);
            $this->pdo->exec('COMMIT');

            return true;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->exec('ROLLBACK');
            }
            throw $exception;
        }
    }

    /** @return list<array{id:int, username:string, received_at:int, body:string}> newest first */
    public function reports(int $limit = 100): array
    {
        $statement = $this->pdo->prepare('SELECT id, username, received_at, body FROM push_reports ORDER BY id DESC LIMIT ?');
        $statement->execute([$limit]);

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'username' => (string) $row['username'],
            'received_at' => (int) $row['received_at'],
            'body' => (string) $row['body'],
        ], $statement->fetchAll());
    }
}
