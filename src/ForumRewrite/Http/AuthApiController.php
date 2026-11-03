<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\ReadModel\ProfileRepository;
use ForumRewrite\Security\OpenPgpSignatureVerifier;

/**
 * Twentieth Phase 2 slice (see docs/plans/codebase_cleanup_audit_plan_v1.md):
 * /api/auth_challenge, /api/authenticate_identity, /api/auth_status - the
 * challenge/signature session-auth trio. fetchProfileByIdentityId() turned
 * out to be a one-line wrapper around ProfileRepository::byIdentityId(pdo()),
 * so it's called directly here instead of via a closure; only
 * authenticatedViewerProfile() (8 other call sites) stayed a bound closure.
 */
final class AuthApiController
{
    /**
     * @param \Closure(): (array<string, mixed>|null) $authenticatedViewerProfile
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly \Closure $authenticatedViewerProfile,
        private readonly ?\Closure $recordHistoryActivity = null,
    ) {
    }

    public function authChallenge(string $method): void
    {
        if ($method !== 'GET') {
            $this->routeServices->sendText("method not allowed\n", 405);
            return;
        }

        // Challenges are single-use and need no session, so signing in never depends on a
        // cookie the browser may have refused.
        $challenge = $this->routeServices->viewerSessions()->createChallenge();
        $this->routeServices->sendText("challenge={$challenge}\n", 200, $this->routeServices->noStoreHeaders());
    }

    /**
     * @param array<string, mixed> $query
     */
    public function authenticateIdentity(string $method, array $query): void
    {
        if ($method !== 'POST') {
            $this->routeServices->sendText("method not allowed\n", 405);
            return;
        }

        $input = $this->routeServices->requestData($query);
        $challenge = trim((string) ($input['challenge'] ?? ''));
        $signature = trim((string) ($input['detached_signature'] ?? ''));
        $identityId = strtolower(trim((string) ($input['identity_id'] ?? '')));
        $sessions = $this->routeServices->viewerSessions();

        if (!$sessions->challengeIsOpen($challenge)) {
            $this->routeServices->sendText("error=Authentication challenge is missing or expired.\n", 400, $this->routeServices->noStoreHeaders());
            return;
        }

        $profile = ProfileRepository::byIdentityId($this->routeServices->pdo(), $identityId);
        if ($profile === null) {
            $this->routeServices->sendText("error=Identity not found.\n", 400, $this->routeServices->noStoreHeaders());
            return;
        }

        $fingerprint = strtoupper(trim((string) ($profile['signer_fingerprint'] ?? '')));
        $verification = (new OpenPgpSignatureVerifier())->verifyDetached(
            (string) ($profile['public_key'] ?? ''),
            $challenge,
            $signature,
            $fingerprint,
        );
        if (!$verification['ok']) {
            $this->routeServices->sendText("error=Identity signature verification failed.\n", 403, $this->routeServices->noStoreHeaders());
            return;
        }

        // Used up only by a verified signature, so a failed attempt can retry with the same challenge.
        if (!$sessions->consumeChallenge($challenge)) {
            $this->routeServices->sendText("error=Authentication challenge is missing or expired.\n", 400, $this->routeServices->noStoreHeaders());
            return;
        }
        $sessions->signIn($identityId);
        if ($this->recordHistoryActivity) ($this->recordHistoryActivity)($profile);
        $approved = ((int) ($profile['is_approved'] ?? 0)) === 1 ? '1' : '0';
        $this->routeServices->sendText("status=ok\nidentity_id={$identityId}\napproved={$approved}\n", 200, $this->routeServices->noStoreHeaders());
    }

    public function authenticationStatus(string $method): void
    {
        if ($method !== 'GET') {
            $this->routeServices->sendText("method not allowed\n", 405, $this->routeServices->noStoreHeaders());
            return;
        }

        $viewerProfile = ($this->authenticatedViewerProfile)();
        $isApproved = $viewerProfile !== null && ((int) ($viewerProfile['is_approved'] ?? 0)) === 1;
        $this->routeServices->sendText(
            $isApproved ? "status=authenticated\n" : "status=unauthenticated\n",
            $isApproved ? 200 : 401,
            $this->routeServices->noStoreHeaders(),
        );
    }
}
