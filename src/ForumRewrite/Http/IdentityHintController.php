<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

/**
 * Nineteenth Phase 2 slice (see docs/plans/codebase_cleanup_audit_plan_v1.md
 * and docs/plans/codebase_cleanup_audit_findings_v1.md): /api/set_identity_hint
 * and /api/clear_identity - pure cookie/session bookkeeping for the
 * `identity_hint` cookie, no PDO, no viewer-profile lookup, no writer(). The
 * cheapest slice yet: only RouteServices::sendText()/noStoreHeaders() are
 * needed, so noStoreHeaders() (16 other call sites on Application) joined
 * RouteServices the same way the write-flow slice's timing helpers did,
 * rather than becoming a closure.
 */
final class IdentityHintController
{
    public function __construct(
        private readonly RouteServices $routeServices,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     */
    public function setIdentityHint(string $method, array $query): void
    {
        if (!in_array($method, ['GET', 'POST'], true)) {
            $this->routeServices->sendText("method not allowed\n", 405);
            return;
        }

        $hint = strtolower(trim((string) ($query['identity_hint'] ?? $query['value'] ?? '')));
        if ($hint === '') {
            $hint = 'guest';
        }

        setcookie('identity_hint', $hint, [
            'expires' => time() + 86400 * 30,
            'path' => '/',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);

        $_COOKIE['identity_hint'] = $hint;
        $this->routeServices->sendText("identity_hint={$hint}\n", 200);
    }

    public function clearIdentity(string $method): void
    {
        if ($method !== 'POST') {
            $this->routeServices->sendText("method not allowed\n", 405, $this->routeServices->noStoreHeaders());
            return;
        }

        $authenticatedIdentityId = strtolower(trim((string) ($_SESSION['authenticated_identity_id'] ?? '')));
        if ($authenticatedIdentityId !== '') {
            $_SESSION['lobby_identity_id'] = $authenticatedIdentityId;
        }

        unset(
            $_SESSION['authenticated_identity_id'],
            $_SESSION['forum_auth_challenges'],
        );
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        setcookie('identity_hint', 'guest', [
            'expires' => time() + 86400 * 30,
            'path' => '/',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['identity_hint'] = 'guest';

        $this->routeServices->sendText("status=ok\nidentity_hint=guest\n", 200, $this->routeServices->noStoreHeaders());
    }
}
