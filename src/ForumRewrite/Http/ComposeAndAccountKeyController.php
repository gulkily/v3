<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Security\OpenPgpKeyInspector;
use RuntimeException;

/**
 * Fourteenth Phase 2 slice (see docs/plans/codebase_cleanup_audit_plan_v1.md
 * and docs/plans/codebase_cleanup_audit_findings_v1.md): the "write flows"
 * slice - /compose/thread, /compose/reply, and /account/key, GET and POST
 * together. Earlier passes checked the GET side of these three routes
 * alongside /lobby//invites/ and left them on Application because they were
 * one-line wrappers around a render*Page() method shared with their own POST
 * handler - extracting only the GET half wasn't worth it. Pulling GET and
 * POST out together, once the POST side's own shared dependencies
 * (writer(), requestData(), the timing helpers) had a home on RouteServices,
 * closes that gap in one slice instead of two.
 *
 * fetchPost() and resolveViewerProfileFromIdentityHint() stay on Application
 * (12 and 16 call sites respectively at extraction time, spanning well
 * beyond this route group) and are passed in as bound closures.
 *
 * linkIdentityApi() (added later, alongside the /api/apply_thread_tag and
 * /api/set_feature_flag slices - see
 * docs/plans/codebase_cleanup_audit_plan_v1.md) is /api/link_identity, the
 * plain-text API twin of submitAccountKey()'s writer()->linkIdentity()
 * call - same write operation, different response shape, so it landed on
 * this controller rather than a new one. Needed no new closures at all.
 */
final class ComposeAndAccountKeyController
{
    /**
     * @param \Closure(string, bool=): (array<string, mixed>|null) $fetchPost
     * @param \Closure(): (array<string, mixed>|null) $resolveViewerProfileFromIdentityHint
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly \Closure $fetchPost,
        private readonly \Closure $resolveViewerProfileFromIdentityHint,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     */
    public function composeThread(array $query): string
    {
        return $this->renderComposeThreadPage(
            (string) ($query['board_tags'] ?? 'general'),
            (string) ($query['subject'] ?? ''),
            (string) ($query['body'] ?? '')
        );
    }

    /**
     * @param array<string, mixed> $query
     */
    public function composeReply(array $query): string
    {
        return $this->renderComposeReplyPage(
            (string) ($query['thread_id'] ?? ''),
            (string) ($query['parent_id'] ?? '')
        );
    }

    public function accountKey(): string
    {
        return $this->renderAccountKeyPage();
    }

    /**
     * @param array<string, mixed> $query
     */
    public function submitComposeThread(array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        $phaseStartedAt = hrtime(true);
        $input = $this->routeServices->requestData($query);
        $timings['request_data'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);
        try {
            $result = $this->routeServices->writer()->createThread($input);
            $result = $this->routeServices->mergeResultTimings($result, $timings, $totalStartedAt);
            $this->queueComposeDraftClear($this->composeDraftStorageKey('thread'));
            $returnTo = $this->resolveComposeThreadReturnTo((string) ($input['return_to'] ?? ''), (string) $result['thread_id']);
            $location = $returnTo
                . (str_contains($returnTo, '?') ? '&' : '?') . 'created_post_id=' . rawurlencode($result['post_id'])
                . '&__v=' . rawurlencode($result['commit_sha']);
            $this->routeServices->sendRedirect(
                $location,
                'Created thread ' . $result['thread_id'] . '. Commit ' . $result['commit_sha'] . '.',
                303,
                $this->routeServices->serverTimingHeaders($result)
            );
        } catch (RuntimeException $exception) {
            $this->routeServices->sendHtml(
                $this->renderComposeThreadPage(
                    (string) ($input['board_tags'] ?? 'general'),
                    (string) ($input['subject'] ?? ''),
                    (string) ($input['body'] ?? ''),
                    null,
                    $exception->getMessage()
                ),
                400,
                $this->routeServices->serverTimingHeaders(['timings' => $this->routeServices->timingsWithTotal($timings, $totalStartedAt)])
            );
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    public function submitComposeReply(array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        $phaseStartedAt = hrtime(true);
        $input = $this->routeServices->requestData($query);
        $timings['request_data'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);
        $threadId = (string) ($input['thread_id'] ?? '');
        $parentId = (string) ($input['parent_id'] ?? '');

        try {
            $result = $this->routeServices->writer()->createReply($input);
            $result = $this->routeServices->mergeResultTimings($result, $timings, $totalStartedAt);
            $this->queueComposeDraftClear($this->composeDraftStorageKey('reply', $threadId, $parentId));
            $returnTo = $this->resolveComposeReplyReturnTo((string) ($input['return_to'] ?? ''), $result['thread_id']);
            $location = $returnTo
                . (str_contains($returnTo, '?') ? '&' : '?') . 'created_post_id=' . rawurlencode($result['post_id'])
                . '&__v=' . rawurlencode($result['commit_sha'])
                . '#post-' . rawurlencode($result['post_id']);
            $this->routeServices->sendRedirect(
                $location,
                'Created reply ' . $result['post_id'] . '. Commit ' . $result['commit_sha'] . '.',
                303,
                $this->routeServices->serverTimingHeaders($result)
            );
        } catch (RuntimeException $exception) {
            $this->routeServices->sendHtml(
                $this->renderComposeReplyPage(
                    $threadId,
                    $parentId,
                    null,
                    $exception->getMessage(),
                    (string) ($input['board_tags'] ?? 'general'),
                    (string) ($input['body'] ?? '')
                ),
                400,
                $this->routeServices->serverTimingHeaders(['timings' => $this->routeServices->timingsWithTotal($timings, $totalStartedAt)])
            );
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    public function submitAccountKey(array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        $phaseStartedAt = hrtime(true);
        $input = $this->routeServices->requestData($query);
        $timings['request_data'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);
        try {
            $result = $this->routeServices->writer()->linkIdentity($input);
            $result = $this->routeServices->mergeResultTimings($result, $timings, $totalStartedAt);
            $location = '/profiles/' . $result['profile_slug'];
            $this->routeServices->sendRedirect(
                $location,
                'Linked identity ' . $result['identity_id'] . ' as ' . $result['username'] . '. Commit ' . $result['commit_sha'] . '.',
                303,
                $this->routeServices->serverTimingHeaders($result)
            );
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'Identity already exists for this fingerprint.') {
                try {
                    $key = (new OpenPgpKeyInspector())->inspect((string) ($input['public_key'] ?? ''));
                    $this->routeServices->sendRedirect(
                        '/profiles/openpgp-' . strtolower($key['fingerprint']),
                        'This identity is already linked. Showing its existing profile.',
                    );
                    return;
                } catch (RuntimeException) {
                    // Keep the normal form error when the submitted key cannot be inspected.
                }
            }
            $this->routeServices->sendHtml(
                $this->renderAccountKeyPage(null, $exception->getMessage()),
                400,
                $this->routeServices->serverTimingHeaders(['timings' => $this->routeServices->timingsWithTotal($timings, $totalStartedAt)])
            );
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    public function linkIdentityApi(string $method, array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        if ($method !== 'POST') {
            $this->routeServices->sendText("method not allowed\n", 405);
            return;
        }

        $phaseStartedAt = hrtime(true);
        $input = $this->routeServices->requestData($query);
        $timings['request_data'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);
        try {
            $result = $this->routeServices->writer()->linkIdentity($input);
            $result = $this->routeServices->mergeResultTimings($result, $timings, $totalStartedAt);
            $this->routeServices->sendText(
                "status=ok\nidentity_id={$result['identity_id']}\nprofile_slug={$result['profile_slug']}\nusername={$result['username']}\nbootstrap_post_id={$result['bootstrap_post_id']}\nbootstrap_thread_id={$result['bootstrap_thread_id']}\ncommit_sha={$result['commit_sha']}\n",
                200,
                $this->routeServices->serverTimingHeaders($result)
            );
        } catch (RuntimeException $exception) {
            $this->routeServices->sendText(
                "error=" . $exception->getMessage() . "\n",
                400,
                $this->routeServices->serverTimingHeaders(['timings' => $this->routeServices->timingsWithTotal($timings, $totalStartedAt)])
            );
        }
    }

    private function renderComposeThreadPage(
        string $boardTags = 'general',
        string $subject = '',
        string $body = '',
        ?string $notice = null,
        ?string $error = null
    ): string {
        return $this->routeServices->renderPageTemplate('compose_thread.php', [
            'boardTags' => $boardTags !== '' ? $boardTags : 'general',
            'subject' => $subject,
            'body' => $body,
            'notice' => $notice,
            'error' => $error,
        ], 'Compose Thread', 'compose', $this->identityScripts());
    }

    private function renderComposeReplyPage(
        string $threadId,
        string $parentId,
        ?string $notice = null,
        ?string $error = null,
        string $boardTags = 'general',
        string $body = ''
    ): string {
        $parentPost = $parentId !== '' ? ($this->fetchPost)($parentId) : null;
        if (is_array($parentPost) && $threadId !== '' && (string) ($parentPost['thread_id'] ?? '') !== $threadId) {
            $parentPost = null;
        }

        return $this->routeServices->renderPageTemplate('compose_reply.php', [
            'threadId' => $threadId,
            'parentId' => $parentId,
            'parentPost' => $parentPost,
            'notice' => $notice,
            'error' => $error,
            'boardTags' => $boardTags !== '' ? $boardTags : 'general',
            'body' => $body,
        ], 'Compose Reply', 'compose', $this->identityScripts());
    }

    private function renderAccountKeyPage(?string $notice = null, ?string $error = null): string
    {
        $viewerProfile = ($this->resolveViewerProfileFromIdentityHint)();

        return $this->routeServices->renderPageTemplate('account_key.php', [
            'identityHint' => $_COOKIE['identity_hint'] ?? '',
            'viewerProfile' => $viewerProfile,
            'notice' => $notice,
            'error' => $error,
        ], 'Account Key', 'account', $this->identityScripts(['/assets/private_site_auth.js']));
    }

    private function resolveComposeReplyReturnTo(string $requestedReturnTo, string $threadId): string
    {
        if (preg_match('#^/forte(?:\?(.*))?$#', $requestedReturnTo, $matches) === 1) {
            return $this->buildForteBoardReturnTo($matches[1] ?? '');
        }

        return '/threads/' . $threadId;
    }

    /**
     * Sibling to resolveComposeReplyReturnTo() for thread creation: there's
     * no existing thread to whitelist a single-thread return path against
     * (the thread doesn't exist until after this call), so this only
     * recognizes the `/forte` board shape and always selects the
     * newly-created thread there, overriding anything the client sent.
     */
    private function resolveComposeThreadReturnTo(string $requestedReturnTo, string $newThreadId): string
    {
        if (preg_match('#^/forte(?:\?(.*))?$#', $requestedReturnTo, $matches) === 1) {
            return $this->buildForteBoardReturnTo($matches[1] ?? '', $newThreadId);
        }

        return '/threads/' . $newThreadId;
    }

    /**
     * Rebuilds a `/forte` return URL from only a fixed, character-restricted
     * allowlist of query params (`tag`, `selected`), discarding anything else
     * so the client-supplied query string is never passed through verbatim.
     * $overrideSelected, when given, wins over any `selected` present in
     * $requestedQueryString (used by thread creation, where the client can't
     * know the new thread's ID up front).
     */
    private function buildForteBoardReturnTo(string $requestedQueryString, ?string $overrideSelected = null): string
    {
        parse_str($requestedQueryString, $params);
        $allowed = [];

        $tag = (string) ($params['tag'] ?? '');
        if ($tag !== '' && preg_match('/^[a-z0-9-]+$/', $tag) === 1) {
            $allowed['tag'] = $tag;
        }

        $selected = $overrideSelected ?? (string) ($params['selected'] ?? '');
        if ($selected !== '' && preg_match('/^[A-Za-z0-9._:-]+$/', $selected) === 1) {
            $allowed['selected'] = $selected;
        }

        $queryString = http_build_query($allowed);

        return '/forte' . ($queryString !== '' ? '?' . $queryString : '');
    }

    private function composeDraftStorageKey(string $kind, string $threadId = '', string $parentId = ''): string
    {
        if ($kind === 'reply') {
            return 'forum_compose_draft:reply:' . $threadId . ':' . $parentId;
        }

        return 'forum_compose_draft:' . $kind;
    }

    private function queueComposeDraftClear(string $storageKey): void
    {
        setcookie('forum_clear_compose_draft', $storageKey, [
            'expires' => time() + 300,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * @param string[] $extra
     * @return string[]
     */
    private function identityScripts(array $extra = []): array
    {
        return array_merge([
            '/assets/openpgp_loader.js',
            '/assets/browser_signing.js',
        ], $extra);
    }
}
