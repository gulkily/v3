<?php

declare(strict_types=1);

namespace ForumRewrite\Session;

use PDO;

/**
 * App-owned sign-in sessions and sign-in challenges.
 *
 * The browser holds a random token; only its SHA-256 hash is stored. A session lasts
 * LIFETIME_SECONDS unless signed out; nothing expires it for being idle. Signing in
 * again as the same identity keeps the session. Signing in as someone else (or from
 * a signed-out session) issues a new token, and the old one forwards to it for
 * FORWARD_SECONDS so requests already in flight act on the new session instead of
 * a stale one. Challenges are single-use and need no session.
 */
final class ViewerSessionStore
{
    public const LIFETIME_SECONDS = 34560000; // 400 days, the cookie's lifetime
    public const CHALLENGE_SECONDS = 300;
    public const FORWARD_SECONDS = 30;
    private const TOUCH_INTERVAL_SECONDS = 3600;
    private const MAX_FORWARD_HOPS = 3;
    private const MAX_OPEN_CHALLENGES = 10000;

    /** @var \Closure(): int */
    private readonly \Closure $clock;

    /** @param (\Closure(): int)|null $clock */
    public function __construct(private readonly PDO $pdo, ?\Closure $clock = null)
    {
        $this->clock = $clock ?? static fn (): int => time();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->ensureSchema();
    }

    public static function isWellFormedToken(string $token): bool
    {
        return preg_match('/^[a-f0-9]{64}$/D', $token) === 1;
    }

    /** The session a token stands for, following a recent replacement; null when unknown or expired. */
    public function resolve(string $token): ?ViewerSession
    {
        if (!self::isWellFormedToken($token)) {
            return null;
        }
        $now = $this->now();
        $hash = self::hash($token);
        for ($hops = 0; $hops <= self::MAX_FORWARD_HOPS; $hops++) {
            $row = $this->row($hash);
            if ($row === null || (int) $row['expires_at'] <= $now) {
                return null;
            }
            $replacedBy = (string) $row['replaced_by_hash'];
            if ($replacedBy === '') {
                $this->touch($hash, (int) $row['last_seen_at'], $now);
                return new ViewerSession($token, (string) $row['identity_id'], (string) $row['lobby_identity_id'], (int) $row['expires_at']);
            }
            if ($now - (int) $row['replaced_at'] > self::FORWARD_SECONDS) {
                return null;
            }
            $hash = $replacedBy;
        }
        return null;
    }

    /**
     * Records a successful sign-in. Returns the session the browser should hold: the
     * current one when it is already signed in as this identity, otherwise a new one.
     */
    public function signIn(string $identityId, ?string $currentToken = null): ViewerSession
    {
        $identityId = strtolower(trim($identityId));
        if ($identityId === '') {
            throw new \InvalidArgumentException('Sign-in needs an identity.');
        }
        $now = $this->now();
        $current = $currentToken !== null ? $this->resolveDirect($currentToken) : null;
        if ($current !== null && $current['identity_id'] === $identityId) {
            $expiresAt = $now + self::LIFETIME_SECONDS;
            $this->pdo->prepare('UPDATE viewer_sessions SET expires_at = :expires, last_seen_at = :now WHERE token_hash = :hash')
                ->execute(['expires' => $expiresAt, 'now' => $now, 'hash' => $current['token_hash']]);
            return new ViewerSession((string) $currentToken, $identityId, '', $expiresAt);
        }

        $token = bin2hex(random_bytes(32));
        $hash = self::hash($token);
        $expiresAt = $now + self::LIFETIME_SECONDS;
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('INSERT INTO viewer_sessions (token_hash, identity_id, lobby_identity_id, created_at, last_seen_at, expires_at, replaced_by_hash, replaced_at)
                VALUES (:hash, :identity, \'\', :now, :now, :expires, \'\', 0)')
                ->execute(['hash' => $hash, 'identity' => $identityId, 'now' => $now, 'expires' => $expiresAt]);
            if ($current !== null) {
                // The old token forwards briefly, then stops working.
                $this->pdo->prepare('UPDATE viewer_sessions SET identity_id = \'\', lobby_identity_id = \'\', replaced_by_hash = :new, replaced_at = :now,
                    expires_at = MIN(expires_at, :until) WHERE token_hash = :old')
                    ->execute(['new' => $hash, 'now' => $now, 'until' => $now + self::FORWARD_SECONDS + 1, 'old' => $current['token_hash']]);
            }
            $this->pdo->commit();
        } catch (\Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
        $this->purgeExpired();
        return new ViewerSession($token, $identityId, '', $expiresAt);
    }

    /**
     * Ends the signed-in state of the session a token stands for. The session keeps the
     * identity it signed out of as its Lobby identity. Returns the session after sign-out,
     * or null when the token stood for nothing.
     */
    public function signOut(string $token): ?ViewerSession
    {
        $session = $this->resolve($token);
        if ($session === null) {
            return null;
        }
        $hash = $this->currentHash($token);
        $lobby = $session->identityId !== '' ? $session->identityId : $session->lobbyIdentityId;
        $this->pdo->prepare('UPDATE viewer_sessions SET identity_id = \'\', lobby_identity_id = :lobby WHERE token_hash = :hash')
            ->execute(['lobby' => $lobby, 'hash' => $hash]);
        return new ViewerSession($token, '', $lobby, $session->expiresAt);
    }

    /** Forgets the Lobby identity of a signed-out session (its profile no longer exists). */
    public function forgetLobbyIdentity(string $token): void
    {
        $hash = $this->currentHash($token);
        if ($hash !== null) {
            $this->pdo->prepare('UPDATE viewer_sessions SET lobby_identity_id = \'\' WHERE token_hash = :hash')->execute(['hash' => $hash]);
        }
    }

    public function createChallenge(): string
    {
        $now = $this->now();
        $this->pdo->prepare('DELETE FROM viewer_auth_challenges WHERE expires_at < :now')->execute(['now' => $now]);
        $open = (int) $this->pdo->query('SELECT COUNT(*) FROM viewer_auth_challenges')->fetchColumn();
        if ($open >= self::MAX_OPEN_CHALLENGES) {
            $this->pdo->exec('DELETE FROM viewer_auth_challenges WHERE challenge IN (SELECT challenge FROM viewer_auth_challenges ORDER BY expires_at LIMIT ' . ($open - self::MAX_OPEN_CHALLENGES + 1) . ')');
        }
        $challenge = bin2hex(random_bytes(32));
        $this->pdo->prepare('INSERT INTO viewer_auth_challenges (challenge, expires_at) VALUES (:challenge, :expires)')
            ->execute(['challenge' => $challenge, 'expires' => $now + self::CHALLENGE_SECONDS]);
        return $challenge;
    }

    /** True while a challenge was issued here, is unexpired and unused. Does not use it up. */
    public function challengeIsOpen(string $challenge): bool
    {
        if ($challenge === '') {
            return false;
        }
        $statement = $this->pdo->prepare('SELECT 1 FROM viewer_auth_challenges WHERE challenge = :challenge AND expires_at >= :now');
        $statement->execute(['challenge' => $challenge, 'now' => $this->now()]);
        return $statement->fetchColumn() !== false;
    }

    /** Uses a challenge up. False when it was already used, expired or never issued. */
    public function consumeChallenge(string $challenge): bool
    {
        if ($challenge === '') {
            return false;
        }
        $statement = $this->pdo->prepare('DELETE FROM viewer_auth_challenges WHERE challenge = :challenge AND expires_at >= :now');
        $statement->execute(['challenge' => $challenge, 'now' => $this->now()]);
        return $statement->rowCount() === 1;
    }

    public function purgeExpired(): void
    {
        $now = $this->now();
        $this->pdo->prepare('DELETE FROM viewer_sessions WHERE expires_at <= :now')->execute(['now' => $now]);
        $this->pdo->prepare('DELETE FROM viewer_auth_challenges WHERE expires_at < :now')->execute(['now' => $now]);
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function now(): int
    {
        return ($this->clock)();
    }

    /** @return array<string, mixed>|null */
    private function row(string $hash): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM viewer_sessions WHERE token_hash = :hash');
        $statement->execute(['hash' => $hash]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** The live, unreplaced row a token stands for, without following forwarding. @return array<string, mixed>|null */
    private function resolveDirect(string $token): ?array
    {
        if (!self::isWellFormedToken($token)) {
            return null;
        }
        $row = $this->row(self::hash($token));
        if ($row === null || (int) $row['expires_at'] <= $this->now() || (string) $row['replaced_by_hash'] !== '') {
            return null;
        }
        return $row;
    }

    /** Hash of the live row a token leads to after forwarding. */
    private function currentHash(string $token): ?string
    {
        if (!self::isWellFormedToken($token)) {
            return null;
        }
        $now = $this->now();
        $hash = self::hash($token);
        for ($hops = 0; $hops <= self::MAX_FORWARD_HOPS; $hops++) {
            $row = $this->row($hash);
            if ($row === null || (int) $row['expires_at'] <= $now) {
                return null;
            }
            if ((string) $row['replaced_by_hash'] === '') {
                return $hash;
            }
            if ($now - (int) $row['replaced_at'] > self::FORWARD_SECONDS) {
                return null;
            }
            $hash = (string) $row['replaced_by_hash'];
        }
        return null;
    }

    /** Records activity at most once an hour, so reading a session rarely writes. */
    private function touch(string $hash, int $lastSeenAt, int $now): void
    {
        if ($now - $lastSeenAt < self::TOUCH_INTERVAL_SECONDS) {
            return;
        }
        try {
            $this->pdo->prepare('UPDATE viewer_sessions SET last_seen_at = :now WHERE token_hash = :hash')->execute(['now' => $now, 'hash' => $hash]);
        } catch (\PDOException) {
            // Activity time is informational; a busy database must not fail the request.
        }
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS viewer_sessions (
            token_hash TEXT PRIMARY KEY,
            identity_id TEXT NOT NULL,
            lobby_identity_id TEXT NOT NULL,
            created_at INTEGER NOT NULL,
            last_seen_at INTEGER NOT NULL,
            expires_at INTEGER NOT NULL,
            replaced_by_hash TEXT NOT NULL,
            replaced_at INTEGER NOT NULL
        )');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS viewer_sessions_expires ON viewer_sessions (expires_at)');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS viewer_auth_challenges (
            challenge TEXT PRIMARY KEY,
            expires_at INTEGER NOT NULL
        )');
    }
}
