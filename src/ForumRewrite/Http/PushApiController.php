<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use Closure;
use ForumRewrite\Push\PushEndpointPolicy;
use ForumRewrite\Push\PushSender;
use ForumRewrite\Push\PushSendResult;
use ForumRewrite\Push\PushSubscription;
use ForumRewrite\Push\PushSubscriptionStore;
use ForumRewrite\Push\PushVapidKeys;
use ForumRewrite\Push\WebPushCrypto;

/**
 * Preferences → Notifications: the routes a signed-in member's browser uses to turn
 * notifications on and off for one device and to send itself a test.
 *
 *   GET  /api/push/key          the site's push key (made on first use)
 *   POST /api/push/subscribe    {endpoint, keys: {p256dh, auth}}
 *   POST /api/push/unsubscribe  {endpoint}
 *   POST /api/push/test         {endpoint}
 *
 * POSTs need JSON with X-Requested-With: ForumPush, which no other site's form can send.
 * The flag check (FORUM_PUSH_ENABLED) is the router's.
 */
final class PushApiController
{
    public const TEST_PAYLOAD = ['title' => 'ZenMemes', 'body' => 'Test notification', 'url' => '/tools/preferences/', 'tag' => 'push-test'];

    /**
     * @param Closure(): (array<string, mixed>|null) $viewerProfile
     * @param Closure(): PushSubscriptionStore $openStore
     * @param Closure(): PushSender $sender
     * @param Closure(): int|null $clock
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly Closure $viewerProfile,
        private readonly Closure $openStore,
        private readonly PushVapidKeys $keys,
        private readonly Closure $sender,
        private readonly PushEndpointPolicy $policy = new PushEndpointPolicy(),
        private readonly ?Closure $clock = null,
    ) {
    }

    public function key(string $method): void
    {
        if ($method !== 'GET') {
            $this->error(405, 'Only GET is supported.', ['Allow: GET']);
            return;
        }
        if ($this->viewer() === null) {
            $this->error(401, 'Sign in to turn on notifications.');
            return;
        }
        $this->ok(['public_key' => WebPushCrypto::base64UrlEncode($this->keys->publicKeyRaw())]);
    }

    /** @param array<string, mixed> $query */
    public function subscribe(string $method, array $query = []): void
    {
        $input = $this->guardedInput($method, $query);
        if ($input === null) {
            return;
        }
        [$viewer, $data] = $input;
        $endpoint = is_string($data['endpoint'] ?? null) ? $data['endpoint'] : '';
        $keys = is_array($data['keys'] ?? null) ? $data['keys'] : [];
        try {
            $subscription = PushSubscription::fromBrowser($endpoint, (string) ($keys['p256dh'] ?? ''), (string) ($keys['auth'] ?? ''));
        } catch (\InvalidArgumentException) {
            $this->error(400, 'Send endpoint and keys (p256dh, auth) from the browser\'s subscription.');
            return;
        }
        if (!$this->policy->allows($endpoint)) {
            $this->error(422, 'This browser\'s push service is not one this site sends to.');
            return;
        }
        $sessionKey = $this->routeServices->viewerSessions()->sessionKey();
        if ($sessionKey === '') {
            $this->error(401, 'Sign in to turn on notifications.');
            return;
        }
        try {
            ($this->openStore)()->save($subscription, $viewer['username'], $viewer['identity_id'], $sessionKey, $this->now());
        } catch (\Throwable) {
            $this->error(503, 'Notifications could not be turned on; try again.');
            return;
        }
        $this->ok(['public_key' => WebPushCrypto::base64UrlEncode($this->keys->publicKeyRaw())]);
    }

    /** @param array<string, mixed> $query */
    public function unsubscribe(string $method, array $query = []): void
    {
        $input = $this->guardedInput($method, $query);
        if ($input === null) {
            return;
        }
        [$viewer, $data] = $input;
        $endpoint = is_string($data['endpoint'] ?? null) ? $data['endpoint'] : '';
        try {
            $store = ($this->openStore)();
            $row = $endpoint !== '' ? $store->find($endpoint) : null;
            if ($row !== null && $row['username'] === $viewer['username']) {
                $store->delete($endpoint);
            }
        } catch (\Throwable) {
            $this->error(503, 'Notifications could not be turned off; try again.');
            return;
        }
        $this->ok([]);
    }

    /** @param array<string, mixed> $query */
    public function test(string $method, array $query = []): void
    {
        $input = $this->guardedInput($method, $query);
        if ($input === null) {
            return;
        }
        [$viewer, $data] = $input;
        $endpoint = is_string($data['endpoint'] ?? null) ? $data['endpoint'] : '';
        try {
            $store = ($this->openStore)();
            $row = $endpoint !== '' ? $store->find($endpoint) : null;
            if ($row === null || $row['username'] !== $viewer['username']) {
                $this->ok(['outcome' => PushSendResult::GONE, 'reason' => 'not turned on']);
                return;
            }
            // The device belongs to the session that subscribed it; once that session is signed
            // out or replaced, nothing is sent and the row goes.
            if ($this->routeServices->viewerSessions()->signedInIdentityForKey($row['session_key']) !== $row['identity_id']) {
                $store->delete($endpoint);
                $this->ok(['outcome' => PushSendResult::GONE, 'reason' => 'signed out']);
                return;
            }
            $result = ($this->sender)()->send($row['subscription'], self::TEST_PAYLOAD, 'high');
            if ($result->outcome === PushSendResult::GONE) {
                $store->delete($endpoint);
            } elseif ($result->outcome === PushSendResult::SENT) {
                $store->markSent($endpoint, $this->now());
            }
        } catch (\Throwable) {
            $this->error(503, 'The test could not be sent; try again.');
            return;
        }
        $this->ok(['outcome' => $result->outcome, 'http_status' => $result->status, 'reason' => $result->reason]);
    }

    /** @return array{username:string,identity_id:string}|null */
    private function viewer(): ?array
    {
        $profile = ($this->viewerProfile)();
        if (!is_array($profile)) {
            return null;
        }
        $identityId = strtolower((string) ($profile['identity_id'] ?? ''));
        if ($identityId === '') {
            return null;
        }
        $username = strtolower(trim((string) ($profile['username'] ?? '')));

        return ['username' => $username !== '' ? $username : $identityId, 'identity_id' => $identityId];
    }

    /**
     * @param array<string, mixed> $query
     * @return array{0:array{username:string,identity_id:string},1:array<string,mixed>}|null
     */
    private function guardedInput(string $method, array $query): ?array
    {
        if ($method !== 'POST') {
            $this->error(405, 'Only POST is supported.', ['Allow: POST']);
            return null;
        }
        $viewer = $this->viewer();
        if ($viewer === null) {
            $this->error(401, 'Sign in to use notifications.');
            return null;
        }
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'ForumPush'
            || !str_starts_with(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')
            || in_array($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '', ['cross-site', 'same-site'], true)) {
            $this->error(403, 'Send JSON with the header X-Requested-With: ForumPush.');
            return null;
        }

        return [$viewer, $this->routeServices->requestData($query)];
    }

    private function now(): int
    {
        return $this->clock !== null ? ($this->clock)() : time();
    }

    /** @param array<string, mixed> $payload */
    private function ok(array $payload): void
    {
        $this->routeServices->sendJson(['status' => 'ok'] + $payload, 200, $this->routeServices->noStoreHeaders());
    }

    /** @param list<string> $headers */
    private function error(int $status, string $message, array $headers = []): void
    {
        $this->routeServices->sendJson(['status' => 'error', 'error' => $message], $status, [...$headers, ...$this->routeServices->noStoreHeaders()]);
    }
}
