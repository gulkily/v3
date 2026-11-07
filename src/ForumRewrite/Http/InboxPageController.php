<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Messaging\ApprovedUserKeyResolver;
use ForumRewrite\Messaging\PrivateMessageMailboxService;
use ForumRewrite\Messaging\PrivateMessageStore;

/**
 * One page for a signed-in approved member's unread things, most personal first.
 * The route is gated by FORUM_INBOX_ENABLED before this controller runs.
 */
final class InboxPageController
{
    private const MESSAGE_LIMIT = 25;

    /**
     * @param \Closure(): (array<string, mixed>|null) $authenticatedViewerProfile
     * @param (\Closure(): PrivateMessageStore)|null $privateMessageStore null when private messages are disabled
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly \Closure $authenticatedViewerProfile,
        private readonly ?\Closure $privateMessageStore,
    ) {
    }

    public function show(string $method): void
    {
        if ($method !== 'GET') {
            $this->routeServices->sendHtml($this->routeServices->renderMessagePage('Method Not Allowed', 'Method Not Allowed', 'Only GET is supported for Inbox.', 'inbox'), 405, $this->routeServices->noStoreHeaders());
            return;
        }
        $viewer = ($this->authenticatedViewerProfile)();
        if ($viewer === null || ((int) ($viewer['is_approved'] ?? 0)) !== 1) {
            $this->routeServices->sendHtml($this->routeServices->renderPageTemplate('private_message_access.php', [
                'viewerIdentityId' => (string) ($viewer['identity_id'] ?? ''),
                'featureName' => 'Inbox',
            ], 'Inbox', 'inbox', [
                '/assets/openpgp_loader.js', '/assets/browser_signing.js', '/assets/private_site_auth.js', '/assets/private_message_access.js',
            ]), $viewer === null ? 401 : 403, $this->routeServices->noStoreHeaders());
            return;
        }

        $this->routeServices->sendHtml($this->routeServices->renderPageTemplate('inbox.php', [
            'viewerProfile' => $viewer,
            'messages' => $this->messages($viewer),
        ], 'Inbox', 'inbox'), 200, $this->routeServices->noStoreHeaders());
    }

    /**
     * @param array<string, mixed> $viewer
     * @return array{enabled:bool,failed:bool,conversations:list<array{counterpart:string,unread_count:int,latest_at:string}>}
     */
    private function messages(array $viewer): array
    {
        if ($this->privateMessageStore === null) {
            return ['enabled' => false, 'failed' => false, 'conversations' => []];
        }
        try {
            $service = new PrivateMessageMailboxService(
                ($this->privateMessageStore)(),
                $this->routeServices->pdo(),
                new ApprovedUserKeyResolver($this->routeServices->repositoryRoot()),
                $this->routeServices->repositoryRoot(),
            );
            return ['enabled' => true, 'failed' => false, 'conversations' => $service->unreadConversations($viewer, self::MESSAGE_LIMIT)];
        } catch (\Throwable) {
            // The other sections stay usable when private storage is unavailable.
            return ['enabled' => true, 'failed' => true, 'conversations' => []];
        }
    }
}
