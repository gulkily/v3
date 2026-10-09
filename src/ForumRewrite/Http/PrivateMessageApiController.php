<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Messaging\ApprovedUserKeyResolver;
use ForumRewrite\Messaging\PrivateMessageMailboxService;
use ForumRewrite\Messaging\PrivateMessageStore;
use InvalidArgumentException;
use RuntimeException;

final class PrivateMessageApiController
{
    /**
     * @param \Closure(): (array<string, mixed>|null) $authenticatedViewerProfile
     * @param \Closure(): PrivateMessageStore $privateMessageStore
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly \Closure $authenticatedViewerProfile,
        private readonly \Closure $privateMessageStore,
    ) {
    }

    /** @param array<string, mixed> $query */
    public function send(string $method, array $query): void
    {
        if ($method !== 'POST') {
            $this->methodNotAllowed();
            return;
        }

        $viewer = $this->viewer();
        if ($viewer === null) {
            return;
        }

        try {
            $message = $this->service()->send($viewer, $this->routeServices->requestData($query));
            $this->routeServices->sendJson(['status' => 'ok', 'message' => $message], 201, $this->routeServices->noStoreHeaders());
        } catch (InvalidArgumentException $exception) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => $exception->getMessage()], 400, $this->routeServices->noStoreHeaders());
        } catch (RuntimeException $exception) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => $exception->getMessage()], 403, $this->routeServices->noStoreHeaders());
        }
    }

    /** @param array<string, mixed> $query */
    public function inbox(string $method, array $query): void
    {
        $this->listMailbox($method, $query, 'inbox');
    }

    /** @param array<string, mixed> $query */
    public function sent(string $method, array $query): void
    {
        $this->listMailbox($method, $query, 'sent');
    }

    /** @param array<string, mixed> $query */
    private function listMailbox(string $method, array $query, string $kind): void
    {
        if ($method !== 'GET') {
            $this->methodNotAllowed();
            return;
        }

        $viewer = $this->viewer();
        if ($viewer === null) {
            return;
        }

        try {
            $messages = $kind === 'inbox'
                ? $this->service()->inbox($viewer)
                : $this->service()->sent($viewer);
            $this->routeServices->sendJson(['status' => 'ok', 'messages' => $messages], 200, $this->routeServices->noStoreHeaders());
        } catch (RuntimeException $exception) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => $exception->getMessage()], 403, $this->routeServices->noStoreHeaders());
        }
    }

    /** @return array<string, mixed>|null */
    private function viewer(): ?array
    {
        $viewer = ($this->authenticatedViewerProfile)();
        if ($viewer !== null) {
            return $viewer;
        }

        $this->routeServices->sendJson(
            ['status' => 'error', 'error' => 'Authentication is required.'],
            401,
            $this->routeServices->noStoreHeaders(),
        );
        return null;
    }

    private function service(): PrivateMessageMailboxService
    {
        return new PrivateMessageMailboxService(
            ($this->privateMessageStore)(),
            $this->routeServices->pdo(),
            new ApprovedUserKeyResolver(),
        );
    }

    private function methodNotAllowed(): void
    {
        $this->routeServices->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405, $this->routeServices->noStoreHeaders());
    }
}
