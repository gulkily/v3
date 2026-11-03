<?php

declare(strict_types=1);

/**
 * The one way tests put a viewer in a sign-in state. Tests describe who is signed in;
 * this helper decides how the session is stored, so the storage can change without
 * touching every test.
 *
 * Stage 2 of app_sessions: this still drives PHP sessions exactly as the tests did
 * before (a real session file and cookie when no output has been sent yet, otherwise the
 * in-memory session the application reads). Stage 4 switches it to real app-owned
 * sessions and cookies.
 */
final class TestViewerSession
{
    /** Signs the next requests in as this identity. */
    public static function signIn(string $identityId): void
    {
        self::begin(['authenticated_identity_id' => $identityId]);
    }

    /** A browser that signed out of this identity: the Lobby still knows it, nothing is signed in. */
    public static function lobbyOnly(string $identityId): void
    {
        self::begin(['lobby_identity_id' => $identityId]);
    }

    /** No session and no session cookie. */
    public static function signedOut(): void
    {
        self::reset();
        $_SESSION = [];
    }

    /** Opens a sign-in challenge the next /api/authenticate_identity request can answer. */
    public static function challenge(): string
    {
        $challenge = bin2hex(random_bytes(32));
        $data = $_SESSION ?? [];
        $data['forum_auth_challenges'] = [bin2hex(random_bytes(32)) => time() + 300, $challenge => time() + 300];
        self::begin($data);
        return $challenge;
    }

    /** The identity the application currently treats as signed in, '' when none. */
    public static function identityId(): string
    {
        return (string) (($_SESSION ?? [])['authenticated_identity_id'] ?? '');
    }

    /** Prepares for another request from the same browser: the session cookie is sent again. */
    public static function nextRequest(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $sessionId = $_COOKIE[session_name()] ?? null;
        if (is_string($sessionId) && $sessionId !== '') {
            // The CLI session module does not read $_COOKIE; mirror the web SAPI.
            session_id($sessionId);
        }
    }

    /** Ends whatever session a test set up and forgets its cookie. */
    public static function reset(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_id('');
        unset($_COOKIE[session_name()]);
        unset($_SESSION);
    }

    /** @param array<string, mixed> $data */
    private static function begin(array $data): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        if (headers_sent()) {
            // Output has started (only when a test echoes directly; the runner writes to
            // STDOUT, which does not send headers), so no session can start: the
            // application reads this in-memory state directly.
            session_id('');
            unset($_COOKIE[session_name()]);
            $_SESSION = $data;
            return;
        }
        // A real session file and the cookie a browser would send. PHP generates the ID:
        // once the application has started a session in this process, strict mode is on
        // and a self-chosen ID would be refused and silently replaced.
        session_id('');
        session_start();
        $sessionId = session_id();
        $_SESSION = $data;
        session_write_close();
        $_COOKIE[session_name()] = $sessionId;
        // The CLI session module does not read $_COOKIE; mirror the web SAPI.
        session_id($sessionId);
    }
}
