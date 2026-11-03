<?php

declare(strict_types=1);

namespace ForumRewrite\Session;

/**
 * The session cookie, one per scheme.
 *
 * Browsers refuse to let http set a cookie whose name matches a Secure cookie set over
 * https, so a single name breaks http sign-in after an https visit. Each scheme therefore
 * has its own name, and https reads only its own Secure cookie, so a token that crossed
 * the network over http never signs anyone in over https.
 */
final class ViewerSessionCookie
{
    public const HTTPS_NAME = '__Host-v3_session';
    public const HTTP_NAME = 'v3_session';
    /** PHP's own session cookie, expired on sight once app-owned sessions are in use. */
    public const LEGACY_NAME = 'PHPSESSID';

    /** @param array<string, mixed> $server */
    public static function requestIsHttps(array $server): bool
    {
        return !empty($server['HTTPS']) && $server['HTTPS'] !== 'off';
    }

    public static function name(bool $https): string
    {
        return $https ? self::HTTPS_NAME : self::HTTP_NAME;
    }

    /**
     * The well-formed session token this request carries for its scheme, or ''.
     *
     * @param array<string, mixed> $cookies
     */
    public static function tokenFromRequest(array $cookies, bool $https): string
    {
        $value = $cookies[self::name($https)] ?? null;
        return is_string($value) && ViewerSessionStore::isWellFormedToken($value) ? $value : '';
    }

    /**
     * Cookie attributes for this scheme. A __Host- cookie must be Secure, have path / and
     * no Domain; the http cookie follows the same shape without Secure.
     *
     * @return array{expires:int,path:string,secure:bool,httponly:bool,samesite:string}
     */
    public static function attributes(bool $https, int $expiresAt): array
    {
        return ['expires' => $expiresAt, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax'];
    }

    public static function send(ViewerSession $session, bool $https): void
    {
        if (headers_sent()) {
            return;
        }
        setcookie(self::name($https), $session->token, self::attributes($https, $session->expiresAt));
    }

    /** Asks the browser to drop PHP's old session cookie (both secure and plain variants). */
    public static function expireLegacy(bool $https): void
    {
        if (headers_sent()) {
            return;
        }
        setcookie(self::LEGACY_NAME, '', ['expires' => 1, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    }
}
