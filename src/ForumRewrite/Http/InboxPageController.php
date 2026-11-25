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
    public const POST_SECTIONS = ['replies', 'new'];

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
            $this->routeServices->sendHtml($this->routeServices->renderMessagePage('Method Not Allowed', 'Method Not Allowed', 'Only GET and POST are supported for Unread.', 'inbox'), 405, $this->routeServices->noStoreHeaders());
            return;
        }
        $viewer = ($this->authenticatedViewerProfile)();
        if ($viewer === null || ((int) ($viewer['is_approved'] ?? 0)) !== 1) {
            $this->routeServices->sendHtml($this->routeServices->renderPageTemplate('private_message_access.php', [
                'viewerIdentityId' => (string) ($viewer['identity_id'] ?? ''),
                'featureName' => 'Unread',
            ], 'Unread', 'inbox', [
                '/assets/openpgp_loader.js', '/assets/browser_signing.js', '/assets/private_site_auth.js', '/assets/private_message_access.js',
            ]), $viewer === null ? 401 : 403, $this->routeServices->noStoreHeaders());
            return;
        }

        $failedCatchUp = null;
        if ($method === 'POST') {
            $failedCatchUp = $this->catchUp($viewer, $this->routeServices->requestData($query));
            if ($failedCatchUp === null) return;
        }

        $waiting = $this->privateMessageStore === null ? [] : \ForumRewrite\Security\PrivateKeyAccess::waitingCardKeys($this->routeServices->pdo(), $this->routeServices->repositoryRoot(), $viewer);
        $this->routeServices->sendHtml($this->routeServices->renderPageTemplate('inbox.php', [
            'viewerProfile' => $viewer,
            'stagedCount' => $this->stagedCount($viewer),
            'messages' => $this->messages($viewer),
            'sections' => $this->sections($viewer, $failedCatchUp),
            'waitingKeys' => $waiting,
            'accountName' => (string) ($viewer['username'] ?? $viewer['username_token'] ?? ''),
        ], 'Unread', 'inbox', array_merge(['/assets/inbox_catch_up.js'], $waiting === [] ? []
            : ['/assets/openpgp_loader.js', '/assets/browser_signing.js', '/assets/private_access_confirm.js'])),
            $failedCatchUp === null ? 200 : 409, $this->routeServices->noStoreHeaders());
    }

    /**
     * The Inbox link's count: unread conversations plus unread replies. New posts are left
     * out, because the board would almost never show zero. Reading the count moves no mark.
     */
    public function unread(string $method): void
    {
        if ($method !== 'GET') {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'Only GET is supported.'], 405, $this->routeServices->noStoreHeaders());
            return;
        }
        $viewer = ($this->authenticatedViewerProfile)();
        if ($viewer === null || ((int) ($viewer['is_approved'] ?? 0)) !== 1) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'Unread is for signed-in approved members.'], $viewer === null ? 401 : 403, $this->routeServices->noStoreHeaders());
            return;
        }
        try {
            $replies = $this->feed($viewer)->unreadCount('replies');
        } catch (\Throwable) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'Unread status unavailable.'], 503, $this->routeServices->noStoreHeaders());
            return;
        }
        $messages = $this->messages($viewer);
        $conversations = count($messages['conversations']);
        $this->routeServices->sendJson([
            'status' => 'ok',
            'unread_count' => $replies + $conversations,
            'replies' => $replies,
            'conversations' => $conversations,
            'messages_available' => !$messages['failed'],
        ], 200, $this->routeServices->noStoreHeaders());
    }

    /**
     * POST /api/inbox/catch_up: the Caught up action for clients other than the Unread page,
     * such as an agent after its check-in. Takes JSON {section, post_ids} and returns the
     * section's remaining unread count. IDs that are not currently unread are ignored.
     *
     * @param array<string, mixed> $query
     */
    public function apiCatchUp(string $method, array $query = []): void
    {
        $headers = $this->routeServices->noStoreHeaders();
        if ($method !== 'POST') {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'Only POST is supported.'], 405, ['Allow: POST', ...$headers]);
            return;
        }
        $viewer = ($this->authenticatedViewerProfile)();
        if ($viewer === null || ((int) ($viewer['is_approved'] ?? 0)) !== 1) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'Unread is for signed-in approved members.'], $viewer === null ? 401 : 403, $headers);
            return;
        }
        // Same guard as /api/private_messages/read: a non-simple request that no other site's form can send.
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'ForumInbox'
            || !str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')
            || in_array($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '', ['cross-site', 'same-site'], true)) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'Send JSON with the header X-Requested-With: ForumInbox.'], 403, $headers);
            return;
        }
        $input = $this->routeServices->requestData($query);
        $section = $input['section'] ?? 'replies';
        $ids = $input['post_ids'] ?? null;
        if (!in_array($section, self::POST_SECTIONS, true) || !is_array($ids) || array_filter($ids, 'is_string') !== $ids) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'Send section (replies or new) and post_ids, a list of post IDs.'], 400, $headers);
            return;
        }
        try {
            $feed = $this->feed($viewer);
            $feed->catchUp($section, array_values($ids));
            $unread = $feed->unreadCount($section);
        } catch (\Throwable) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'Nothing was marked read; try again.'], 503, $headers);
            return;
        }
        $this->routeServices->sendJson(['status' => 'ok', 'section' => $section, 'unread' => $unread], 200, $headers);
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
            if (!SameOriginRequest::check() || !is_array($ids)) throw new \RuntimeException('Caught up must come from Unread.');
            $this->feed($viewer)->catchUp($section, array_values($ids));
        } catch (\Throwable) {
            return $section;
        }
        $this->routeServices->sendRedirect('/unread', 'Back to Unread.', 303, $this->routeServices->noStoreHeaders(), 'inbox');
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

    /**
     * What Unread holds for an approved viewer, for other readers such as the agent check-in.
     * Reading it moves no mark. A failing part reports failed instead of throwing.
     *
     * @param array<string, mixed> $viewer an approved viewer profile
     * @return array{replies:list<array<string,mixed>>,reply_count:int,replies_failed:bool,conversations:list<array{counterpart:string,unread_count:int,latest_at:string}>,messages_enabled:bool,messages_failed:bool}
     */
    public function unreadFor(array $viewer, int $limit): array
    {
        try {
            $feed = $this->feed($viewer);
            $replies = array_slice($feed->items('replies'), 0, max(0, $limit));
            $count = $feed->unreadCount('replies');
            $failed = false;
        } catch (\Throwable) {
            [$replies, $count, $failed] = [[], 0, true];
        }
        $messages = $this->messages($viewer);
        return [
            'replies' => $replies,
            'reply_count' => $count,
            'replies_failed' => $failed,
            'conversations' => $messages['conversations'],
            'messages_enabled' => $messages['enabled'],
            'messages_failed' => $messages['failed'],
        ];
    }

    /** @param array<string, mixed> $viewer */
    private function stagedCount(array $viewer): int
    {
        try {
            return count((new StagedPageController($this->routeServices, $this->authenticatedViewerProfile))->visibleItems($viewer));
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @param array<string, mixed> $viewer */
    private function feed(array $viewer): InboxFeed
    {
        $pdo = $this->routeServices->pdo();
        $keys = (new ApprovedUserKeyResolver($this->routeServices->repositoryRoot()))->keysForUsernameToken($pdo, (string) $viewer['username_token'], true);
        $identityIds = array_values(array_unique([(string) $viewer['identity_id'], ...array_column($keys, 'identity_id')]));
        return new InboxFeed($pdo, ($this->markStore)(), (string) $viewer['username_token'], $identityIds, \ForumRewrite\SiteProfileRegistry::backlogClaimsThread());
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
        } catch (\ForumRewrite\Security\PublicOnlyKeyException) {
            return ['enabled' => true, 'failed' => false, 'public_only' => true, 'conversations' => []];
        } catch (\Throwable) {
            // The other sections stay usable when private storage is unavailable.
            return ['enabled' => true, 'failed' => true, 'conversations' => []];
        }
    }
}
