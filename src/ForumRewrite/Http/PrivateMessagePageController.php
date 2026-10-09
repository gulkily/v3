<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Messaging\ApprovedUserKeyResolver;
use ForumRewrite\Messaging\PrivateMessageMailboxService;
use ForumRewrite\Messaging\PrivateMessageStore;
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

    public function sent(string $method): void
    {
        $this->mailbox($method, 'sent');
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
