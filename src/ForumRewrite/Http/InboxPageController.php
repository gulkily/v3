<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Inbox\InboxFeed;
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
    public const POST_SECTIONS = ['replies'];

    /**
     * @param \Closure(): (array<string, mixed>|null) $authenticatedViewerProfile
     * @param (\Closure(): PrivateMessageStore)|null $privateMessageStore null when private messages are disabled
     * @param \Closure(): \ForumRewrite\Inbox\InboxMarkStore $markStore
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly \Closure $authenticatedViewerProfile,
        private readonly ?\Closure $privateMessageStore,
        private readonly \Closure $markStore,
    ) {
    }

    /** @param array<string, mixed> $query */
    public function show(string $method, array $query = []): void
    {
        if (!in_array($method, ['GET', 'POST'], true)) {
            $this->routeServices->sendHtml($this->routeServices->renderMessagePage('Method Not Allowed', 'Method Not Allowed', 'Only GET and POST are supported for Inbox.', 'inbox'), 405, $this->routeServices->noStoreHeaders());
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

        $failedCatchUp = null;
        if ($method === 'POST') {
            $failedCatchUp = $this->catchUp($viewer, $this->routeServices->requestData($query));
            if ($failedCatchUp === null) return;
        }

        $this->routeServices->sendHtml($this->routeServices->renderPageTemplate('inbox.php', [
            'viewerProfile' => $viewer,
            'messages' => $this->messages($viewer),
            'sections' => $this->sections($viewer, $failedCatchUp),
        ], 'Inbox', 'inbox'), $failedCatchUp === null ? 200 : 409, $this->routeServices->noStoreHeaders());
    }

    /**
     * Moves one section's mark past the posts the member saw, then returns to Inbox.
     * Returns the section whose update failed, or null after redirecting.
     *
     * @param array<string, mixed> $viewer
     * @param array<string, mixed> $input
     */
    private function catchUp(array $viewer, array $input): ?string
    {
        $section = in_array($input['section'] ?? null, self::POST_SECTIONS, true) ? (string) $input['section'] : 'replies';
        $ids = $input['post_ids'] ?? [];
        try {
            if (!SameOriginRequest::check() || !is_array($ids)) throw new \RuntimeException('Caught up must come from Inbox.');
            $this->feed($viewer)->catchUp($section, array_values($ids));
        } catch (\Throwable) {
            return $section;
        }
        $this->routeServices->sendRedirect('/inbox', 'Back to Inbox.', 303, $this->routeServices->noStoreHeaders(), 'inbox');
        return null;
    }

    /**
     * @param array<string, mixed> $viewer
     * @return array<string, array{items:list<array<string,mixed>>,failed:bool,catch_up_failed:bool}>
     */
    private function sections(array $viewer, ?string $failedCatchUp): array
    {
        $sections = [];
        foreach (self::POST_SECTIONS as $section) {
            try {
                $items = RecentPageController::present($this->feed($viewer)->items($section));
                $failed = false;
            } catch (\Throwable) {
                $items = [];
                $failed = true;
            }
            $sections[$section] = ['items' => $items, 'failed' => $failed, 'catch_up_failed' => $failedCatchUp === $section];
        }
        return $sections;
    }

    /** @param array<string, mixed> $viewer */
    private function feed(array $viewer): InboxFeed
    {
        $pdo = $this->routeServices->pdo();
        $keys = (new ApprovedUserKeyResolver($this->routeServices->repositoryRoot()))->keysForUsernameToken($pdo, (string) $viewer['username_token'], true);
        $identityIds = array_values(array_unique([(string) $viewer['identity_id'], ...array_column($keys, 'identity_id')]));
        return new InboxFeed($pdo, ($this->markStore)(), (string) $viewer['username_token'], $identityIds);
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
