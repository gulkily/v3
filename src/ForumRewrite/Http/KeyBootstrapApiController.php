<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\ReadModel\ProfileRepository;
use ForumRewrite\Security\KeyRetirement;
use ForumRewrite\Session\DeviceFingerprint;
use ForumRewrite\Session\ViewerSession;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use RuntimeException;

/**
 * Session key bootstrap (docs/specs/key_bootstrap_record_v1.md):
 *
 * - GET  /api/key_bootstrap_status    may this browser's session vouch for a new key, and under which name;
 * - POST /api/prepare_key_bootstrap   the record the new key signs, or "pending" when the browser does not match;
 * - POST /api/finalize_key_bootstrap  accepts the signed record; one per session.
 *
 * The session is the proof. Every call re-checks the flag, the scheme, the session and
 * the browser's coarse description; the writer then checks accounts and policy.
 */
final class KeyBootstrapApiController
{
    public function __construct(private readonly RouteServices $routeServices)
    {
    }

    public function status(string $method): void
    {
        if ($method !== 'GET') {
            $this->send(['status' => 'error', 'error' => 'method not allowed'], 405);
            return;
        }
        $check = $this->sessionCheck(false);
        if ($check['status'] !== 'eligible') {
            $this->send($check, 200);
            return;
        }
        $profile = $check['profile'];
        $this->send(['status' => 'eligible', 'from_identity_id' => (string) $profile['identity_id'],
            'username' => (string) $profile['username'], 'device_match' => $check['device']], 200);
    }

    /** @param array<string, mixed> $query */
    public function prepare(string $method, array $query): void
    {
        if (!$this->acceptsPost($method)) {
            return;
        }
        $check = $this->sessionCheck(true);
        if ($check['status'] !== 'eligible') {
            $this->send($check, $check['status'] === 'error' ? 400 : 200);
            return;
        }
        $input = $this->routeServices->requestData($query);
        if (!$check['device']) {
            // A clear mismatch is the manual step: the new key stays pending for another key or an operator.
            $this->send(['status' => 'pending', 'reason' => 'device'], 200);
            return;
        }
        try {
            $result = $this->routeServices->writer()->prepareKeyBootstrap([
                'from_identity_id' => (string) $check['profile']['identity_id'],
                'identity_id' => (string) ($input['identity_id'] ?? ''),
            ]);
            $this->send($result, 200);
        } catch (RuntimeException $error) {
            $this->send($this->routeServices->errorPayload($error), 400);
        }
    }

    /** @param array<string, mixed> $query */
    public function finalize(string $method, array $query): void
    {
        if (!$this->acceptsPost($method)) {
            return;
        }
        $input = $this->routeServices->requestData($query);
        $check = $this->sessionCheck(true, $this->isAcceptedRetry($input));
        if ($check['status'] !== 'eligible') {
            $this->send($check, $check['status'] === 'error' ? 400 : 200);
            return;
        }
        if (!$check['device']) {
            $this->send(['status' => 'pending', 'reason' => 'device'], 200);
            return;
        }
        $sessions = $this->routeServices->viewerSessions();
        $retry = $check['retry'];
        // Claimed before the commit, so two requests on one session cannot both mint a key.
        if (!$retry && !$sessions->markKeyBootstrapped()) {
            $this->send(['status' => 'refused', 'reason' => 'used'], 200);
            return;
        }
        try {
            $this->send($this->routeServices->writer()->finalizeKeyBootstrap($input, $retry), 200);
        } catch (RuntimeException $error) {
            if (!$retry) $sessions->clearKeyBootstrapped();
            $this->send($this->routeServices->errorPayload($error), 400);
        }
    }

    /**
     * @return array{status:string,reason?:string,error?:string,profile?:array<string,mixed>,device?:bool,retry?:bool}
     */
    private function sessionCheck(bool $writing, bool $retry = false): array
    {
        $flags = $this->routeServices->featureFlags();
        if (!$flags->isEnabled(FeatureFlagRegistry::SESSION_KEY_BOOTSTRAP_ENABLED)) {
            return ['status' => 'refused', 'reason' => 'off'];
        }
        $sessions = $this->routeServices->viewerSessions();
        if (!$sessions->isHttps() && !$flags->isEnabled(FeatureFlagRegistry::SESSION_KEY_BOOTSTRAP_HTTP_ENABLED)) {
            return ['status' => 'refused', 'reason' => 'http'];
        }
        $session = $sessions->current();
        if (!$session instanceof ViewerSession || !$session->isSignedIn()) {
            return ['status' => 'refused', 'reason' => 'signed_out'];
        }
        if ($session->keyBootstrappedAt !== 0 && !$retry) {
            return ['status' => 'refused', 'reason' => 'used'];
        }
        $profile = ProfileRepository::byIdentityId($this->routeServices->pdo(), $session->identityId);
        if ($profile === null || (int) ($profile['is_approved'] ?? 0) !== 1
            || KeyRetirement::read($this->routeServices->repositoryRoot(), $session->identityId) !== null) {
            return ['status' => 'refused', 'reason' => 'not_member'];
        }
        $device = DeviceFingerprint::matches($session->deviceFingerprint,
            DeviceFingerprint::fromUserAgent((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')));
        return ['status' => 'eligible', 'profile' => $profile, 'device' => $device, 'retry' => $retry];
    }

    /** A repeat of a finalize whose record is already in the repository; the writer returns the accepted result. */
    private function isAcceptedRetry(array $input): bool
    {
        $path = (string) ($input['record_path'] ?? '');
        return preg_match('#^records/posts/[A-Za-z0-9/_.-]+\.txt$#D', $path) === 1 && !str_contains($path, '..')
            && is_file($this->routeServices->repositoryRoot() . '/' . $path);
    }

    private function acceptsPost(string $method): bool
    {
        if ($method !== 'POST') {
            $this->send(['status' => 'error', 'error' => 'method not allowed'], 405);
            return false;
        }
        $https = $this->routeServices->viewerSessions()->isHttps();
        $expected = ($https ? 'https://' : 'http://') . (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ((string) ($_SERVER['HTTP_ORIGIN'] ?? '') !== $expected) {
            $this->send(['status' => 'error', 'error' => 'Key bootstrap must come from this site.'], 403);
            return false;
        }
        return true;
    }

    /** @param array<string, mixed> $payload */
    private function send(array $payload, int $status): void
    {
        unset($payload['profile'], $payload['device'], $payload['retry']);
        $this->routeServices->sendJson($payload, $status, $this->routeServices->noStoreHeaders());
    }
}
