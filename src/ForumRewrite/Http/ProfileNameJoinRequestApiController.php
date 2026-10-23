<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use RuntimeException;

/**
 * Join requests for renames into a held username: the signed-in key reads or
 * cancels its own request, and a holder or operator lists or declines the
 * requests it may answer. Accepting is a signed grant (WritePostAndIdentityApiController).
 */
final class ProfileNameJoinRequestApiController
{
    /**
     * @param \Closure(): (array<string, mixed>|null) $authenticatedViewerProfile
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly \Closure $authenticatedViewerProfile,
    ) {
    }

    public function ownRequest(string $method): void
    {
        $this->respond($method, 'GET', fn (string $identityId): array => [
            'status' => 'ok',
            'request' => $this->routeServices->writer()->profileNameJoinRequest($identityId),
        ]);
    }

    public function cancelOwnRequest(string $method): void
    {
        $this->respond($method, 'POST', fn (string $identityId): array => $this->routeServices->writer()->cancelProfileNameJoinRequest($identityId));
    }

    public function answerableRequests(string $method): void
    {
        $this->respond($method, 'GET', fn (string $identityId): array => [
            'status' => 'ok',
            'requests' => $this->routeServices->writer()->profileNameJoinRequestsFor($identityId),
        ]);
    }

    /** @param array<string, mixed> $query */
    public function declineRequest(string $method, array $query): void
    {
        $input = $this->routeServices->requestData($query);
        $this->respond($method, 'POST', fn (string $identityId): array => $this->routeServices->writer()
            ->declineProfileNameJoinRequest($identityId, (string) ($input['grantee_identity_id'] ?? '')));
    }

    /** @param callable(string): array<string, mixed> $handler */
    private function respond(string $method, string $allowed, callable $handler): void
    {
        if ($method !== $allowed) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405, $this->routeServices->noStoreHeaders());
            return;
        }
        $viewer = ($this->authenticatedViewerProfile)();
        if ($viewer === null) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'Authentication is required.'], 401, $this->routeServices->noStoreHeaders());
            return;
        }
        try {
            $this->routeServices->sendJson($handler((string) $viewer['identity_id']), 200, $this->routeServices->noStoreHeaders());
        } catch (RuntimeException $exception) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => $exception->getMessage()], 400, $this->routeServices->noStoreHeaders());
        }
    }
}
