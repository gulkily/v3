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
}
