<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Messaging\ApprovedUserKeyResolver;
use ForumRewrite\Messaging\PrivateMessageMailboxService;
use ForumRewrite\Messaging\PrivateMessageStore;
use ForumRewrite\ReadModel\ProfileRepository;
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
        $this->redirectMailbox($method);
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
            $this->messageError('Messages Unavailable', $exception->getMessage(), 403);
            return;
        }
        $this->routeServices->sendHtml($this->routeServices->renderPageTemplate('private_message_list.php',
            ['page' => $page, 'viewerProfile' => $viewer,
                'recipientSuggestions' => ProfileRepository::approvedDirectoryUsers($this->routeServices->pdo())], 'Messages', 'messages', [
                '/assets/openpgp_loader.js', '/assets/browser_signing.js', '/assets/private_messages.js',
                '/assets/private_message_reader.js', '/assets/message_time.js', '/assets/private_message_list.js',
            ]), 200, $this->routeServices->noStoreHeaders());
    }

    public function sent(string $method): void
    {
        $this->redirectMailbox($method);
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
            $page = $this->service()->conversationPage($viewer, $counterpartUsernameToken);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->messageError('Conversation Unavailable', $exception->getMessage(), 404);
            return;
        }
        $this->routeServices->sendHtml($this->routeServices->renderPageTemplate('private_message_conversation.php', ['counterpartUsernameToken' => strtolower($counterpartUsernameToken), 'messages' => $page['messages'], 'historyPage' => $page, 'viewerProfile' => $viewer], 'Conversation with ' . strtolower($counterpartUsernameToken), 'messages', ['/assets/openpgp_loader.js', '/assets/browser_signing.js', '/assets/private_messages.js', '/assets/private_message_compose.js', '/assets/private_message_reader.js', '/assets/message_time.js', '/assets/private_message_conversation.js', '/assets/private_message_seen.js']), 200, $this->routeServices->noStoreHeaders());
    }

    private function redirectMailbox(string $method): void
    {
        if ($method !== 'GET') {
            $this->messageError('Method Not Allowed', 'Only GET is supported for Messages.', 405);
            return;
        }
        $this->routeServices->sendRedirect('/messages', 'Opening Messages.', 302, $this->routeServices->noStoreHeaders(), 'messages');
    }

    private function messageError(string $heading, string $message, int $status): void
    {
        $this->routeServices->sendHtml($this->routeServices->renderPageTemplate('message.php', [
            'heading' => $heading, 'message' => $message, 'actionUrl' => '/messages', 'actionLabel' => 'Back to Messages',
        ], $heading, 'messages'), $status, $this->routeServices->noStoreHeaders());
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
