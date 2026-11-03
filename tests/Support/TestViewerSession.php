<?php

declare(strict_types=1);

use ForumRewrite\Session\ViewerSessionCookie;
use ForumRewrite\Session\ViewerSessionDatabaseConfig;
use ForumRewrite\Session\ViewerSessionStore;

/**
 * The one way tests put a viewer in a sign-in state. Tests describe who is signed in;
 * this helper creates a real app-owned session and puts its cookie in the request, so
 * the application finds the viewer exactly as it would for a browser: through the
 * cookie and the session store. Nothing is pre-filled in memory.
 *
 * The store lives in a temporary file named by VIEWER_SESSION_DATABASE_PATH, which
 * the application reads through PrivateConfig and child processes inherit.
 */
final class TestViewerSession
{
    /** Signs the next requests in as this identity. */
    public static function signIn(string $identityId): void
    {
        $session = self::store()->signIn($identityId);
        self::setCookie($session->token);
    }

    /** A browser that signed out of this identity: the Lobby still knows it, nothing is signed in. */
    public static function lobbyOnly(string $identityId): void
    {
        $store = self::store();
        $session = $store->signIn($identityId);
        $store->signOut($session->token);
        self::setCookie($session->token);
    }

    /** No session cookie. */
    public static function signedOut(): void
    {
        self::reset();
    }

    /** Opens a sign-in challenge the next /api/authenticate_identity request can answer. */
    public static function challenge(): string
    {
        return self::store()->createChallenge();
    }

    /** The identity the request's cookie currently signs in, '' when none. */
    public static function identityId(): string
    {
        $token = ViewerSessionCookie::tokenFromRequest($_COOKIE, self::https());
        return $token === '' ? '' : (self::store()->resolve($token)?->identityId ?? '');
    }

    /** The session cookie's name and value, as a browser would hold them. @return array{0: string, 1: string} */
    public static function cookie(): array
    {
        $name = ViewerSessionCookie::name(self::https());
        return [$name, (string) ($_COOKIE[$name] ?? '')];
    }

    /**
     * Uses the session store a test server rooted at this directory would use
     * (its default <root>/state/private/viewer_sessions.sqlite3).
     */
    public static function useStateDirectory(string $directory): void
    {
        putenv('VIEWER_SESSION_DATABASE_PATH=' . ViewerSessionDatabaseConfig::path($directory));
    }

    /** Another request from the same browser: the cookie is already in $_COOKIE. */
    public static function nextRequest(): void
    {
    }

    /** Forgets the session cookie. */
    public static function reset(): void
    {
        unset($_COOKIE[ViewerSessionCookie::HTTPS_NAME], $_COOKIE[ViewerSessionCookie::HTTP_NAME]);
    }

    private static function setCookie(string $token): void
    {
        self::reset();
        $_COOKIE[ViewerSessionCookie::name(self::https())] = $token;
    }

    private static function https(): bool
    {
        return ViewerSessionCookie::requestIsHttps($_SERVER);
    }

    /** Points the application at a temporary session store unless one is already chosen. */
    public static function prepare(): void
    {
        if (getenv('VIEWER_SESSION_DATABASE_PATH') === false) {
            $path = sys_get_temp_dir() . '/test-viewer-sessions-' . getmypid() . '-' . bin2hex(random_bytes(4)) . '.sqlite3';
            putenv('VIEWER_SESSION_DATABASE_PATH=' . $path);
            register_shutdown_function(static function () use ($path): void {
                foreach ([$path, $path . '-wal', $path . '-shm'] as $file) {
                    @unlink($file);
                }
            });
        }
    }

    private static function store(): ViewerSessionStore
    {
        self::prepare();
        $path = (string) getenv('VIEWER_SESSION_DATABASE_PATH');
        return new ViewerSessionStore(ViewerSessionDatabaseConfig::open('', ['VIEWER_SESSION_DATABASE_PATH' => $path]));
    }
}

TestViewerSession::prepare();
