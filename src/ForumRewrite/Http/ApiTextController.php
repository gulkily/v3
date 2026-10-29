<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\ReadModel\ThreadRepository;

/**
 * Fifteenth Phase 2 slice (see docs/plans/codebase_cleanup_audit_plan_v1.md
 * and docs/plans/codebase_cleanup_audit_findings_v1.md): the plain-text
 * `/api/*` informational endpoints - `/api/`, `/api/list_index`,
 * `/api/get_thread`, `/api/get_post`, `/api/get_profile`, `/api/get_username`, and
 * `/api/get_username_claim_cta`. These are pure read-only formatters with
 * no auth/session/write-flow coupling, unlike most of the `/api` group
 * (deliberately deferred as a whole - see the plan doc), so they were
 * peeled off first rather than waiting for the full `/api` pass.
 *
 * fetchThread(), fetchThreadPosts(), fetchPost(), fetchProfileBySlug(), and
 * displayThreadTitle() stay on Application (3-12 other call sites each) and
 * are passed in as bound closures. fetchThreads() had exactly one caller
 * (this page's list index) and is called directly here via
 * ThreadRepository::fetchThreads(), the same repository BoardPageController
 * already uses, instead of keeping a single-caller wrapper on Application.
 */
final class ApiTextController
{
    /**
     * @param \Closure(string): (array<string, mixed>|null) $fetchThread
     * @param \Closure(string): array<int, array<string, mixed>> $fetchThreadPosts
     * @param \Closure(string, bool=): (array<string, mixed>|null) $fetchPost
     * @param \Closure(string): (array<string, mixed>|null) $fetchProfileBySlug
     * @param \Closure(array<string, mixed>): string $displayThreadTitle
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly \Closure $fetchThread,
        private readonly \Closure $fetchThreadPosts,
        private readonly \Closure $fetchPost,
        private readonly \Closure $fetchProfileBySlug,
        private readonly \Closure $displayThreadTitle,
    ) {
    }

    public function index(): void
    {
        $this->routeServices->sendText(
            ApiRouteCatalog::renderIndex()
            . "\nPrivate conversation lists require an approved authenticated identity; responses are no-store.\n"
            . "Success: {status: ok, conversations: [...], page_cursor: ..., next_cursor: ...}. Each row contains counterpart and latest message metadata plus encrypted_envelope; decrypt and verify locally.\n"
            . "Pages contain at most 25 counterparts ordered by latest activity (insertion order breaks timestamp ties). Pass next_cursor unchanged to continue; null means complete. page_cursor replays the current page.\n"
            . "Pagination preserves the opening snapshot. Omit cursor to refresh activity. Invalid cursors return HTTP 400 with restart: true; discard the old list and start again. Authentication failures return 401/403.\n"
            . "\nAll /api/railspike_workflow reads and actions require FORUM_RAILSPIKE_ENABLED (default false). Disabled calls return no-store HTTP 403 with code railspike_disabled; stop retries until re-enabled and reloaded. Existing workflows are retained.\n\nAll /api/private_messages endpoints require FORUM_PRIVATE_MESSAGES_ENABLED (default true). When disabled, responses are no-store HTTP 403 with {status: error, code: messaging_disabled, error: <explanation>}; no messaging data is returned or messaging writes performed. Stop messaging retries until re-enabled and reloaded; the Feature Flags page alone resumes its navigation/background work after a confirmed toggle or reset. GET /api/feature_flags/navigation returns no-store canonical navigation HTML, messaging eligibility/viewer, and dependency script URLs for the current session. Already-admitted requests may finish; preserve pending send IDs/envelopes for delivery reconciliation. Stored messages, read progress, and history-sync state are retained.\n"
            . "\nPOST /api/private_messages accepts message_id, recipient_username_token, and encrypted_envelope for an approved authenticated sender. Success is HTTP 201 with {status: ok, message: {message_id, created_at, sender_username_token, recipient_username_token}}; responses are no-store.\n"
            . "Retry an uncertain send with the SAME message ID and exact encrypted envelope under the same sending identity. An already accepted attempt returns its original metadata, even after recipient key eligibility changes. A conflicting identity, recipient, or envelope returns HTTP 400 without replacing the message. New attempts still require approved recipient keys.\n"
            . "Do not re-encrypt retries: encryption produces different ciphertext. Changed content is a new attempt with a new ID. Delivery confirmation does not establish signature verification; decrypt and verify in the browser.\n"
            . "GET /api/private_messages/conversation?username_token=<username>&cursor=<optional-opaque-cursor> requires an approved authenticated viewer and eligible counterpart; responses are no-store. Success: {status: ok, messages: [...], page_cursor: ..., next_cursor: ...}. Message fields and encrypted envelopes are unchanged.\n"
            . "Without cursor, open a snapshot of the newest 25 messages. Each page is chronological, with insertion order breaking timestamp ties. Pass next_cursor unchanged to retrieve older messages and prepend them; null means all history in this snapshot has been reached. page_cursor replays the same page.\n"
            . "Cursors are scoped to the viewer and counterpart. Arrivals, including backdated messages, do not alter the snapshot. Retry a failed request with the same cursor. HTTP 400 with restart: true means the position is unusable; explicitly omit cursor to open a fresh recent window, replacing loaded history only after that request succeeds. Viewer access failures return 401/403; an invalid or ineligible counterpart returns 400 with an explanation. Live updates are not provided.\n"
            . "\nGET /api/private_messages/unread requires an approved authenticated viewer. Success: {status: ok, viewer: <username>, unread_count: <conversation count>, conversations: {<requested username>: <boolean>, ...}, revision: <opaque coherence value>}. Accepts at most 25 counterparts[] per request; count covers the entire mailbox, not just requested rows. Batch larger row sets and apply only matching revisions. Responses are no-store and contain no envelopes or plaintext.\n"
            . "A fresh conversation response also includes read_token, scoped to the viewer/counterpart and its received-message insertion boundary; cursor requests return null for this field. It is separate from page_cursor and must not be edited.\n"
            . "POST /api/private_messages/read requires same-origin application/json with X-Requested-With: ForumPrivateMessages and {counterpart: <username>, read_token: <receipt>}. Success returns the current unread state with that counterpart. Retry an uncertain request with the SAME receipt; do not fetch a newer receipt merely to retry. Invalid receipts/eligibility return 400 with reopen: true; viewer access/request-guard failures return 401/403. All responses are no-store.\n"
            . "Acknowledge only after read attempts settle and the current window's latest-message region is presented in a visible focused conversation. This includes earlier unloaded messages and displayed failures, but never asserts verification or sends read receipts to the counterpart. Previews, hidden tabs, old-history loading, and own sends do not acknowledge. Later/backdated arrivals remain unread. State is shared across approved identities/devices for the username and advances only forward. Existing history is seen at the one-time tracking baseline; refresh on navigation, visible return, and acknowledgment, without polling.\n",
            200
        );
    }

    public function listIndex(): void
    {
        $lines = [];
        foreach (ThreadRepository::fetchThreads($this->routeServices->pdo()) as $thread) {
            $subject = ($this->displayThreadTitle)($thread);
            $lines[] = $thread['root_post_id'] . "\t" . $subject . "\t" . $thread['reply_count'];
        }

        $this->routeServices->sendText(implode("\n", $lines) . "\n", 200);
    }

    public function getThread(string $threadId): void
    {
        $thread = ($this->fetchThread)($threadId);
        if ($thread === null) {
            $this->routeServices->sendText("thread not found\n", 404);
            return;
        }

        $lines = [
            'Thread-ID: ' . $thread['root_post_id'],
            'Created-At: ' . $thread['root_post_created_at'],
            'Last-Activity-At: ' . $thread['last_activity_at'],
            'Subject: ' . ($thread['subject'] ?: ''),
            'Reply-Count: ' . $thread['reply_count'],
            'Score-Total: ' . $thread['score_total'],
            'Labels: ' . implode(' ', $thread['thread_labels']),
            '',
        ];

        foreach (($this->fetchThreadPosts)($threadId) as $post) {
            if (str_contains($post['body'], \ForumRewrite\Images\ImageReference::PREFIX)) { $lines[] = 'Image-Bytes: separate image backup required'; }
            $lines[] = '[' . $post['post_id'] . '] ' . trim(str_replace("\n", ' ', $post['body']));
        }

        $this->routeServices->sendText(implode("\n", $lines) . "\n", 200);
    }

    public function getPost(string $postId): void
    {
        $post = ($this->fetchPost)($postId);
        if ($post === null) {
            $this->routeServices->sendText("post not found\n", 404);
            return;
        }

        $imageNote = str_contains($post['body'], \ForumRewrite\Images\ImageReference::PREFIX) ? "Image-Bytes: separate image backup required\n" : '';
        $this->routeServices->sendText(
            $imageNote . "Post-ID: {$post['post_id']}\nCreated-At: {$post['created_at']}\nThread-ID: {$post['thread_id']}\nAuthor: {$post['author_label']}\n\n{$post['body']}",
            200
        );
    }

    public function getProfile(string $slug): void
    {
        $profile = ($this->fetchProfileBySlug)($slug);
        if ($profile === null) {
            $this->routeServices->sendText("profile not found\n", 404);
            return;
        }

        $approved = ((int) $profile['is_approved']) === 1 ? 'yes' : 'no';
        $imageUpload = $approved === 'yes'
            && $this->routeServices->featureFlags()->isEnabled(\ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry::IMAGE_CONTENT_ENABLED)
            && \ForumRewrite\Images\ImageUploadValidator::available() ? 'yes' : 'no';
        $approvedBy = ((int) $profile['is_approved']) === 1 ? (string) ($profile['approved_by_label'] ?? '') : '';

        $history = $this->routeServices->approvalHistory()->forProfile($profile);
        $historyText = 'Approval-Classification: ' . $history['classification'] . "\nAccepted-At: " . ($history['accepted_at'] ?? 'unknown')
            . "\nApproval-Evidence: " . ($history['source_path'] ?? 'unknown') . "\nAcceptance-Receipt: " . ($history['receipt_path'] ?? 'unknown')
            . "\nAccount-Key-History: /user/" . rawurlencode((string) $profile['username_token']) . "#account-keys\n";
        $this->routeServices->sendText(
            "Profile-Slug: {$profile['profile_slug']}\nIdentity-ID: {$profile['identity_id']}\nUsername: {$profile['username']}\nApproved: {$approved}\nImage-Upload-Allowed: {$imageUpload}\nApproved-By: {$approvedBy}\nPosts: {$profile['post_count']}\nThreads: {$profile['thread_count']}\n" . $historyText,
            200,
            ['Cache-Control: no-store']
        );
    }

    /**
     * How many approved keys use a username, so a new key can say it will join that user once approved.
     *
     * @param array<int, array<string, mixed>> $profiles profiles with this username token
     */
    public function getUsername(string $usernameToken, array $profiles): void
    {
        if (preg_match('/^[a-z0-9._-]{1,64}$/', $usernameToken) !== 1) {
            $this->routeServices->sendText("invalid username\n", 400, ['Cache-Control: no-store']);
            return;
        }

        $approvedKeys = count(array_filter($profiles, static fn (array $profile): bool => ((int) ($profile['is_approved'] ?? 0)) === 1));
        $this->routeServices->sendText(
            "Username-Token: {$usernameToken}\nApproved-Keys: {$approvedKeys}\n",
            200,
            ['Cache-Control: no-store']
        );
    }

    public function usernameClaimCta(): void
    {
        $this->routeServices->sendText("Generate a browser keypair, choose a username, and bootstrap your identity.\n", 200);
    }
}
