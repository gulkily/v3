<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Messaging\ApprovedUserKeyResolver;
use ForumRewrite\Security\ApprovalPolicy;

/**
 * Signed posts the site kept but has not published. A signed-in author sees their own;
 * an operator sees everyone's. Everyone else gets the same empty page. Private and
 * per viewer like Unread, so it is never cached or built into static or offline pages.
 */
final class StagedPageController
{
    /** @param \Closure(): (array<string, mixed>|null) $authenticatedViewerProfile */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly \Closure $authenticatedViewerProfile,
    ) {
    }

    /** @param array<string, mixed> $query */
    public function show(string $method, array $query = []): void
    {
        if (!in_array($method, ['GET', 'POST'], true)) {
            $this->routeServices->sendHtml($this->routeServices->renderMessagePage('Method Not Allowed', 'Method Not Allowed', 'Only GET and POST are supported for Staged.', 'staged'), 405, $this->routeServices->noStoreHeaders());
            return;
        }
        $viewer = ($this->authenticatedViewerProfile)();
        $discardFailed = false;
        if ($method === 'POST') {
            if ($viewer !== null && $this->discard($viewer, (string) ($this->routeServices->requestData($query)['post_id'] ?? ''))) {
                $this->routeServices->sendRedirect('/staged/', 'Back to Staged.', 303, $this->routeServices->noStoreHeaders(), 'staged');
                return;
            }
            $discardFailed = true;
        }

        $operator = $viewer !== null && ApprovalPolicy::isOperator($this->routeServices->repositoryRoot(), (string) $viewer['identity_id']);
        $data = [
            'signedIn' => $viewer !== null,
            'operator' => $operator,
            'items' => $viewer === null ? [] : $this->visibleItems($viewer, $operator),
            'discardFailed' => $discardFailed,
        ];
        if ($viewer !== null) $data['viewerProfile'] = $viewer;
        $this->routeServices->sendHtml($this->routeServices->renderPageTemplate('staged.php', $data, 'Staged', 'staged', $viewer === null ? [
            '/assets/openpgp_loader.js', '/assets/browser_signing.js', '/assets/private_site_auth.js', '/assets/staged_access.js',
        ] : []), $discardFailed ? 409 : 200, $this->routeServices->noStoreHeaders());
    }

    /**
     * Staged posts this viewer may see: their own (any of their username's approved keys),
     * or every one for an operator. Posts that have since been published are left out.
     *
     * @param array<string, mixed>|null $viewer
     * @return list<array<string, mixed>>
     */
    public function visibleItems(?array $viewer, ?bool $operator = null): array
    {
        if ($viewer === null) return [];
        $operator ??= ApprovalPolicy::isOperator($this->routeServices->repositoryRoot(), (string) $viewer['identity_id']);
        $items = $this->routeServices->writer()->stagedSubmissions()->listFor($operator ? null : $this->viewerIdentities($viewer));
        $root = $this->routeServices->repositoryRoot();
        $items = array_values(array_filter($items, static function (array $item) use ($root): bool {
            foreach (CanonicalPathResolver::postCandidates((string) $item['post_id']) as $path) {
                if (is_file($root . '/' . $path)) return false;
            }
            return true;
        }));
        return $this->withNames($items);
    }

    /**
     * Adds the author's name and the thread's subject where the read model knows them.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function withNames(array $items): array
    {
        try {
            $pdo = $this->routeServices->pdo();
            $author = $pdo->prepare('SELECT username FROM profiles WHERE identity_id = ? AND username IS NOT NULL AND username <> \'\'');
            $thread = $pdo->prepare('SELECT subject FROM threads WHERE root_post_id = ? AND subject IS NOT NULL AND subject <> \'\'');
            foreach ($items as $index => $item) {
                $author->execute([$item['author_identity_id']]);
                $name = $author->fetchColumn();
                if (is_string($name)) $items[$index]['author_label'] = $name;
                if ($item['thread_id'] !== null) {
                    $thread->execute([$item['thread_id']]);
                    $subject = $thread->fetchColumn();
                    if (is_string($subject)) $items[$index]['thread_subject'] = $subject;
                }
            }
        } catch (\Throwable) {
            // Without the read model, IDs stand in for names.
        }
        return $items;
    }

    /** @param array<string, mixed> $viewer */
    private function discard(array $viewer, string $postId): bool
    {
        if (!SameOriginRequest::check()) return false;
        foreach ($this->visibleItems($viewer) as $item) {
            if ($item['post_id'] === $postId) {
                try {
                    $this->routeServices->writer()->stagedSubmissions()->discard($postId);
                    return true;
                } catch (\RuntimeException) {
                    return false;
                }
            }
        }
        // Already gone (published, discarded elsewhere): nothing left to do.
        return $this->routeServices->writer()->stagedSubmissions()->find($postId) === null;
    }

    /**
     * @param array<string, mixed> $viewer
     * @return list<string>
     */
    private function viewerIdentities(array $viewer): array
    {
        $identities = [(string) $viewer['identity_id']];
        try {
            $keys = (new ApprovedUserKeyResolver($this->routeServices->repositoryRoot()))->keysForUsernameToken($this->routeServices->pdo(), (string) ($viewer['username_token'] ?? ''), true);
            $identities = [...$identities, ...array_column($keys, 'identity_id')];
        } catch (\Throwable) {
            // Without the read model, show what the signed-in key itself staged.
        }
        return array_values(array_unique($identities));
    }
}
