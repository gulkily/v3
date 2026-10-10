<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Messaging\ApprovedUserKeyResolver;
use ForumRewrite\Messaging\PrivateMessageMailboxService;
use ForumRewrite\Messaging\PrivateMessageStore;
use ForumRewrite\Messaging\InvalidHistoryCursor;
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
    public function conversations(string $method, array $query): void
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
            if (isset($query['cursor']) && !is_string($query['cursor'])) {
                throw new InvalidArgumentException('This message list has expired. Reload Messages to start again.');
            }
            $page = $this->service()->conversations($viewer, $query['cursor'] ?? null);
            $this->routeServices->sendJson(['status' => 'ok'] + $page, 200, $this->routeServices->noStoreHeaders());
        } catch (InvalidArgumentException $exception) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => $exception->getMessage(), 'restart' => true], 400, $this->routeServices->noStoreHeaders());
        } catch (RuntimeException $exception) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => $exception->getMessage()], 403, $this->routeServices->noStoreHeaders());
        }
    }

    /** @param array<string, mixed> $query */
    public function conversation(string $method, array $query): void
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
            if (isset($query['cursor']) && !is_string($query['cursor'])) throw new InvalidHistoryCursor();
            if (isset($query['username_token']) && !is_string($query['username_token'])) throw new InvalidArgumentException('Conversation counterpart is invalid.');
            $page = $this->service()->conversationPage($viewer, (string) ($query['username_token'] ?? ''), $query['cursor'] ?? null);
            $this->routeServices->sendJson(['status' => 'ok'] + $page, 200, $this->routeServices->noStoreHeaders());
        } catch (InvalidHistoryCursor $exception) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => $exception->getMessage(), 'restart' => true], 400, $this->routeServices->noStoreHeaders());
        } catch (InvalidArgumentException $exception) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => $exception->getMessage()], 400, $this->routeServices->noStoreHeaders());
        } catch (RuntimeException $exception) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => $exception->getMessage()], 403, $this->routeServices->noStoreHeaders());
        }
    }

    /** @param array<string, mixed> $query */
    public function recipientKeys(string $method, array $query): void
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
            $keys = $this->service()->recipientKeys($viewer, (string) ($query['username_token'] ?? ''));
            $this->routeServices->sendJson(['status' => 'ok', 'keys' => $keys], 200, $this->routeServices->noStoreHeaders());
        } catch (InvalidArgumentException $exception) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => $exception->getMessage()], 400, $this->routeServices->noStoreHeaders());
        } catch (RuntimeException $exception) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => $exception->getMessage()], 403, $this->routeServices->noStoreHeaders());
        }
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

    public function unread(string $method, array $query): void
    {
        if ($method !== 'GET') { $this->methodNotAllowed(); return; }
        $viewer = $this->viewer();
        if ($viewer === null) return;
        try {
            $counterparts = $query['counterparts'] ?? [];
            if (!is_array($counterparts)) throw new InvalidArgumentException('Conversation states must be a list.');
            $state = $this->service()->unreadState($viewer, $counterparts);
            $this->routeServices->sendJson(['status' => 'ok'] + $state, 200, $this->routeServices->noStoreHeaders());
        } catch (InvalidArgumentException $error) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => $error->getMessage()], 400, $this->routeServices->noStoreHeaders());
        } catch (RuntimeException $error) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => $error->getMessage()], 403, $this->routeServices->noStoreHeaders());
        }
    }

    public function read(string $method, array $query): void
    {
        if ($method !== 'POST') { $this->methodNotAllowed(); return; }
        $viewer = $this->viewer();
        if ($viewer === null) return;
        try {
            // A non-simple request plus a private signed receipt prevents cross-site form writes.
            if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'ForumPrivateMessages'
                || !str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')
                || in_array($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '', ['cross-site', 'same-site'], true)) {
                throw new RuntimeException('A same-origin JSON request is required.');
            }
            $input = $this->routeServices->requestData($query);
            if (!is_string($input['counterpart'] ?? null) || !is_string($input['read_token'] ?? null)) {
                throw new InvalidArgumentException('A counterpart and read token are required.');
            }
            $state = $this->service()->acknowledge($viewer, $input['counterpart'], $input['read_token']);
            $this->routeServices->sendJson(['status' => 'ok'] + $state, 200, $this->routeServices->noStoreHeaders());
        } catch (InvalidArgumentException $error) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => $error->getMessage(), 'reopen' => true], 400, $this->routeServices->noStoreHeaders());
        } catch (RuntimeException $error) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => $error->getMessage()], 403, $this->routeServices->noStoreHeaders());
        }
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
