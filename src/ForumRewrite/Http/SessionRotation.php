<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

/**
 * Signing in moves the session to a new ID. Requests already on their way still carry the old ID,
 * so the old session forwards to the new one for a short grace period instead of disappearing:
 * deleting it gave those requests an empty session whose cookie replaced the signed-in one.
 * See docs/plans/session_rotation_race_decision.md.
 */
final class SessionRotation
{
    public const GRACE_SECONDS = 30;
    private const REPLACED_AT = '_session_replaced_at';
    private const REPLACED_BY = '_session_replaced_by';
    private const MAX_HOPS = 3;

    /** Moves the active session's data to a new ID; the old ID keeps only a forwarding marker. */
    public static function rotate(?int $now = null): void
    {
        $data = $_SESSION;
        $newId = session_create_id();
        if ($newId === false) {
            session_regenerate_id(true);
            return;
        }
        $_SESSION = [self::REPLACED_AT => $now ?? time(), self::REPLACED_BY => $newId];
        session_write_close();
        // The new ID is ours, so strict mode must not refuse it as unknown. The setting can't change
        // while the session is open and only applies when one starts, so it stays off for the rest
        // of this request; later requests start with it on again.
        ini_set('session.use_strict_mode', '0');
        session_id($newId);
        session_start();
        $_SESSION = $data;
    }

    /**
     * Call right after session_start(). A replaced session forwards to its replacement during the
     * grace period; after it, the old ID starts over as a new empty session. Returns true when the
     * request was moved to a different session ID.
     */
    public static function follow(?int $now = null): bool
    {
        $now ??= time();
        $forwarded = false;
        for ($hops = 0; $hops < self::MAX_HOPS && isset($_SESSION[self::REPLACED_AT]); $hops++) {
            $replacedBy = (string) ($_SESSION[self::REPLACED_BY] ?? '');
            if ($replacedBy === '' || $now - (int) $_SESSION[self::REPLACED_AT] > self::GRACE_SECONDS) {
                $_SESSION = [];
                session_regenerate_id(true);
                return false;
            }
            session_write_close();
            session_id($replacedBy);
            session_start();
            $forwarded = true;
        }
        if (isset($_SESSION[self::REPLACED_AT])) {
            // A chain longer than any real burst of sign-ins: start over rather than follow further.
            $_SESSION = [];
            session_regenerate_id(true);
            return false;
        }
        return $forwarded;
    }
}
