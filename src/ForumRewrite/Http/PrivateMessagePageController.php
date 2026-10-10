<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Messaging\ApprovedUserKeyResolver;
use ForumRewrite\Messaging\PrivateMessageMailboxService;
use ForumRewrite\Messaging\PrivateMessageStore;
use InvalidArgumentException;
use RuntimeException;

final class PrivateMessagePageController
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

    public function inbox(string $method): void
    {
        $this->mailbox($method, 'inbox');
    }

    public function conversations(string $method): void
    {
        if ($method !== 'GET') {
            $this->routeServices->sendHtml($this->routeServices->renderMessagePage('Method Not Allowed', 'Method Not Allowed', 'Only GET is supported for Messages.', 'messages'), 405, $this->routeServices->noStoreHeaders());
            return;
        }
        $viewer = ($this->authenticatedViewerProfile)();
        if ($viewer === null) {
            $this->routeServices->sendHtml($this->routeServices->renderMessagePage('Authentication Required', 'Authentication Required', 'Authenticate an approved browser identity to view private messages.', 'messages'), 401, $this->routeServices->noStoreHeaders());
            return;
        }
        try {
            $page = $this->service()->conversations($viewer);
        } catch (RuntimeException $exception) {
            $this->routeServices->sendHtml($this->routeServices->renderMessagePage('Messages Unavailable', 'Messages Unavailable', $exception->getMessage(), 'messages'), 403, $this->routeServices->noStoreHeaders());
            return;
        }
        $this->routeServices->sendHtml($this->routeServices->renderPageTemplate('private_message_list.php',
            ['page' => $page, 'viewerProfile' => $viewer], 'Messages', 'messages'), 200, $this->routeServices->noStoreHeaders());
    }

    public function sent(string $method): void
    {
        $this->mailbox($method, 'sent');
    }

    public function conversation(string $method, string $counterpartUsernameToken): void
    {
        if ($method !== 'GET') {
            $this->routeServices->sendHtml($this->routeServices->renderMessagePage('Method Not Allowed', 'Method Not Allowed', 'Only GET is supported for private-message conversations.', 'messages'), 405, $this->routeServices->noStoreHeaders());
            return;
        }
        $viewer = ($this->authenticatedViewerProfile)();
        if ($viewer === null) {
            $this->routeServices->sendHtml($this->routeServices->renderMessagePage('Authentication Required', 'Authentication Required', 'Authenticate an approved browser identity to view private messages.', 'messages'), 401, $this->routeServices->noStoreHeaders());
            return;
        }
        try {
            $messages = $this->service()->conversation($viewer, $counterpartUsernameToken);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->routeServices->sendHtml($this->routeServices->renderMessagePage('Conversation Unavailable', 'Conversation Unavailable', $exception->getMessage(), 'messages'), 404, $this->routeServices->noStoreHeaders());
            return;
        }
        $this->routeServices->sendHtml($this->routeServices->renderPageTemplate('private_message_conversation.php', ['counterpartUsernameToken' => strtolower($counterpartUsernameToken), 'messages' => $messages, 'viewerProfile' => $viewer], 'Conversation with ' . strtolower($counterpartUsernameToken), 'messages', ['/assets/openpgp_loader.js', '/assets/browser_signing.js', '/assets/private_messages.js', '/assets/private_message_compose.js', '/assets/private_message_reader.js']), 200, $this->routeServices->noStoreHeaders());
    }

    private function mailbox(string $method, string $kind): void
    {
        if ($method !== 'GET') {
            $this->routeServices->sendHtml(
                $this->routeServices->renderMessagePage(
                    'Method Not Allowed',
                    'Method Not Allowed',
                    'Only GET is supported for private-message mailboxes.',
                    'messages',
                ),
                405,
                $this->routeServices->noStoreHeaders(),
            );
            return;
        }

        $viewer = ($this->authenticatedViewerProfile)();
        if ($viewer === null) {
            $this->routeServices->sendHtml(
                $this->routeServices->renderMessagePage(
                    'Authentication Required',
                    'Authentication Required',
                    'Authenticate an approved browser identity to view private messages.',
                    'messages',
                ),
                401,
                $this->routeServices->noStoreHeaders(),
            );
            return;
        }

        try {
            $messages = $kind === 'inbox'
                ? $this->service()->inbox($viewer)
                : $this->service()->sent($viewer);
        } catch (RuntimeException $exception) {
            $this->routeServices->sendHtml(
                $this->routeServices->renderMessagePage('Forbidden', 'Forbidden', $exception->getMessage(), 'messages'),
                403,
                $this->routeServices->noStoreHeaders(),
            );
            return;
        }

        $this->routeServices->sendHtml(
            $this->routeServices->renderPageTemplate(
                'private_messages.php',
                [
                    'mailbox' => $kind,
                    'messages' => $messages,
                    'viewerProfile' => $viewer,
                ],
                $kind === 'inbox' ? 'Inbox' : 'Sent Messages',
                'messages',
                [
                    '/assets/openpgp_loader.js',
                    '/assets/browser_signing.js',
                    '/assets/private_messages.js',
                    '/assets/private_message_reader.js',
                ],
            ),
            200,
            $this->routeServices->noStoreHeaders(),
        );
    }

    private function service(): PrivateMessageMailboxService
    {
        return new PrivateMessageMailboxService(
            ($this->privateMessageStore)(),
            $this->routeServices->pdo(),
            new ApprovedUserKeyResolver(),
        );
    }
}
