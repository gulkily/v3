<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

/**
 * Twelfth Phase 2 slice (see docs/plans/codebase_cleanup_audit_plan_v1.md
 * and docs/plans/codebase_cleanup_audit_findings_v1.md): /lobby/ and
 * /invites/ - small, single-caller, but with real logic of their own
 * (unlike /compose/thread, /compose/reply, and /account/key's GET routes,
 * which turned out to already be one-line wrappers around renderer methods
 * shared with their POST submit handlers - not worth a second layer of
 * indirection for that reason, so left on Application).
 *
 * lobbyViewerProfile() and authenticatedViewerProfile() stay on Application
 * (session-bound, used throughout the auth-gating logic) and are passed in
 * as bound closures.
 */
final class LobbyController
{
    /**
     * @param \Closure(): (array<string, mixed>|null) $lobbyViewerProfile
     * @param \Closure(): (array<string, mixed>|null) $authenticatedViewerProfile
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly \Closure $lobbyViewerProfile,
        private readonly \Closure $authenticatedViewerProfile,
    ) {
    }

    public function lobby(): string
    {
        return $this->routeServices->renderPageTemplate(
            'lobby.php',
            [
                'viewerProfile' => ($this->lobbyViewerProfile)(),
            ],
            'Lobby',
            'lobby',
            $this->identityScripts(['/assets/private_site_auth.js', '/assets/invite_redemption.js'])
        );
    }

    /**
     * @param array<string, mixed> $query
     */
    public function invitation(array $query): string
    {
        $viewer = ($this->authenticatedViewerProfile)();
        if ($viewer === null || ((int) ($viewer['is_approved'] ?? 0)) !== 1) {
            return $this->routeServices->renderMessagePage('Invitation required', 'Invitation required', 'Only authenticated approved members can generate invitations.', 'account');
        }

        return $this->routeServices->renderPageTemplate(
            'invites.php',
            ['destination' => trim((string) ($query['destination'] ?? ''))],
            'Generate invite',
            'invite',
            $this->identityScripts(['/assets/invite_issuance.js']),
        );
    }

    /**
     * @param string[] $extra
     * @return string[]
     */
    private function identityScripts(array $extra = []): array
    {
        return array_merge([
            '/assets/openpgp_loader.js',
            '/assets/browser_signing.js',
        ], $extra);
    }
}
