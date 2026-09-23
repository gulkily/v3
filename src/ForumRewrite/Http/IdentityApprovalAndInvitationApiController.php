<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\ReadModel\ProfileRepository;
use RuntimeException;

/**
 * Twenty-second Phase 2 slice (see docs/plans/codebase_cleanup_audit_plan_v1.md):
 * the remaining approval/invitation write API group - /api/approve_user,
 * /api/prepare_approval, /api/create_prepared_approval,
 * /api/prepare_invitation, /api/create_prepared_invitation,
 * /api/prepare_invitation_redemption. Same shape as the prior write/prepare
 * slice: everything needed was already on RouteServices (writer(),
 * requestData(), sendJson()/sendText(), noStoreHeaders(), the timing
 * helpers). prepareUserApprovalBySlug() had exactly one caller
 * (handlePrepareUserApproval), so it moved wholesale as a private method
 * rather than becoming a closure; it needed fetchProfileBySlug() (4 other
 * call sites) and resolveViewerProfileFromIdentityHint() (14 other call
 * sites) as closures. handlePrepareInvitationRedemption's
 * fetchProfileByIdentityId() call is, as established in the AuthApiController
 * slice, just ProfileRepository::byIdentityId(pdo()) - called directly here
 * too. handlePrepareInvitation's authenticatedViewerProfile() (8 other call
 * sites) stayed a bound closure.
 */
final class IdentityApprovalAndInvitationApiController
{
    /**
     * @param \Closure(): (array<string, mixed>|null) $authenticatedViewerProfile
     * @param \Closure(string): (array<string, mixed>|null) $fetchProfileBySlug
     * @param \Closure(): (array<string, mixed>|null) $resolveViewerProfileFromIdentityHint
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly \Closure $authenticatedViewerProfile,
        private readonly \Closure $fetchProfileBySlug,
        private readonly \Closure $resolveViewerProfileFromIdentityHint,
    ) {
    }

    public function approveUser(string $method): void
    {
        if ($method !== 'POST') {
            $this->routeServices->sendText("method not allowed\n", 405);
            return;
        }

        $this->routeServices->sendText("error=Approval requires a browser signature. Refresh this page and try again.\n", 400);
    }

    /**
     * @param array<string, mixed> $query
     */
    public function prepareApproval(string $method, array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        if ($method !== 'POST') {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405);
            return;
        }

        $phaseStartedAt = hrtime(true);
        $profileSlug = trim((string) ($this->routeServices->requestData($query)['profile_slug'] ?? ''));
        $timings['request_data'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);
        if ($profileSlug === '') {
            $this->routeServices->sendJson(
                ['status' => 'error', 'error' => 'Missing profile_slug.'],
                400,
                $this->routeServices->serverTimingHeaders(['timings' => $this->routeServices->timingsWithTotal($timings, $totalStartedAt)])
            );
            return;
        }

        try {
            $result = $this->prepareUserApprovalBySlug($profileSlug, $timings);
            $result = $this->routeServices->mergeResultTimings($result, $timings, $totalStartedAt);
            unset($result['timings']);
            $this->routeServices->sendJson($result, 200, $this->routeServices->serverTimingHeaders($result));
        } catch (RuntimeException $exception) {
            $this->routeServices->sendJson(
                ['status' => 'error', 'error' => $exception->getMessage()],
                400,
                $this->routeServices->serverTimingHeaders(['timings' => $this->routeServices->timingsWithTotal($timings, $totalStartedAt)])
            );
        }
    }

    /**
     * @param array<string, float|int> $timings
     * @return array<string, mixed>
     */
    private function prepareUserApprovalBySlug(string $slug, array &$timings = []): array
    {
        $phaseStartedAt = hrtime(true);
        $profile = ($this->fetchProfileBySlug)($slug);
        $timings['target_profile'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);
        if ($profile === null) {
            throw new RuntimeException('Profile not found.');
        }

        $phaseStartedAt = hrtime(true);
        $viewerProfile = ($this->resolveViewerProfileFromIdentityHint)();
        $timings['viewer_profile'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);
        if ($viewerProfile === null || ((int) $viewerProfile['is_approved']) !== 1) {
            throw new RuntimeException('Only approved users can approve other users.');
        }

        if ((string) $viewerProfile['identity_id'] === (string) $profile['identity_id']) {
            throw new RuntimeException('Self-approval is not allowed.');
        }

        if ((int) $profile['is_approved'] === 1) {
            throw new RuntimeException('User is already approved.');
        }

        return $this->routeServices->writer()->prepareApproval([
            'approver_identity_id' => (string) $viewerProfile['identity_id'],
            'target_identity_id' => (string) $profile['identity_id'],
            'target_profile_slug' => (string) $profile['profile_slug'],
            'thread_id' => (string) $profile['bootstrap_thread_id'],
            'parent_id' => (string) $profile['bootstrap_post_id'],
        ]);
    }

    /**
     * @param array<string, mixed> $query
     */
    public function createPreparedApproval(string $method, array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        if ($method !== 'POST') {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405);
            return;
        }

        $phaseStartedAt = hrtime(true);
        $input = $this->routeServices->requestData($query);
        $timings['request_data'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);
        try {
            $result = $this->routeServices->writer()->finalizePreparedApproval($input);
            $result = $this->routeServices->mergeResultTimings($result, $timings, $totalStartedAt);
            unset($result['timings']);
            $this->routeServices->sendJson($result, 200, $this->routeServices->serverTimingHeaders($result));
        } catch (RuntimeException $exception) {
            $this->routeServices->sendJson(
                ['status' => 'error', 'error' => $exception->getMessage()],
                400,
                $this->routeServices->serverTimingHeaders(['timings' => $this->routeServices->timingsWithTotal($timings, $totalStartedAt)])
            );
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    public function prepareInvitation(string $method, array $query): void
    {
        if ($method !== 'POST') {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405);
            return;
        }
        try {
            $viewer = ($this->authenticatedViewerProfile)();
            if ($viewer === null || ((int) ($viewer['is_approved'] ?? 0)) !== 1) {
                throw new RuntimeException('Only authenticated approved users can issue invitations.');
            }
            $input = $this->routeServices->requestData($query);
            $result = $this->routeServices->writer()->prepareInvitation([
                'issuer_identity_id' => (string) $viewer['identity_id'],
                'thread_id' => (string) $viewer['bootstrap_thread_id'],
                'parent_id' => (string) $viewer['bootstrap_post_id'],
                'verification_hash' => (string) ($input['verification_hash'] ?? ''),
                'expires_at' => (string) ($input['expires_at'] ?? ''),
                'destination' => (string) ($input['destination'] ?? ''),
                'action' => (string) ($input['action'] ?? 'issue'),
                'invitation_id' => (string) ($input['invitation_id'] ?? ''),
            ]);
            $this->routeServices->sendJson($result, 200, $this->routeServices->noStoreHeaders());
        } catch (RuntimeException $exception) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => $exception->getMessage()], 400, $this->routeServices->noStoreHeaders());
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    public function createPreparedInvitation(string $method, array $query): void
    {
        if ($method !== 'POST') {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405);
            return;
        }
        try {
            $input = $this->routeServices->requestData($query);
            $this->routeServices->sendJson($this->routeServices->writer()->finalizePreparedInvitation($input), 200, $this->routeServices->noStoreHeaders());
        } catch (RuntimeException $exception) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => $exception->getMessage()], 400, $this->routeServices->noStoreHeaders());
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    public function prepareInvitationRedemption(string $method, array $query): void
    {
        if ($method !== 'POST') {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405);
            return;
        }
        try {
            $input = $this->routeServices->requestData($query);
            $identityId = strtolower(trim((string) ($input['identity_id'] ?? '')));
            $profile = ProfileRepository::byIdentityId($this->routeServices->pdo(), $identityId);
            if ($profile === null) {
                throw new RuntimeException('Identity not found.');
            }
            $this->routeServices->sendJson($this->routeServices->writer()->prepareInvitationRedemption([
                'recipient_identity_id' => $identityId,
                'thread_id' => (string) $profile['bootstrap_thread_id'],
                'parent_id' => (string) $profile['bootstrap_post_id'],
                'invite_token' => (string) ($input['invite_token'] ?? ''),
            ]), 200, $this->routeServices->noStoreHeaders());
        } catch (RuntimeException $exception) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => $exception->getMessage()], 400, $this->routeServices->noStoreHeaders());
        }
    }
}
