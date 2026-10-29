<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\ReadModel\AuthoredContentRepository;
use ForumRewrite\ReadModel\ProfileRepository;

/**
 * Third Phase 2 slice of the Application.php decomposition (see
 * docs/plans/codebase_cleanup_audit_plan_v1.md and
 * docs/plans/codebase_cleanup_audit_findings_v1.md): the read-only side of
 * /profiles/{slug}, /user/{username}, /users/, and /users/pending/. Built on
 * ProfileRepository and AuthoredContentRepository, extracted first in this
 * same slice once /profiles and /users turned out to share those queries
 * with several still-un-extracted route groups (board, activity, lobby,
 * /forte/users).
 *
 * Application::renderProfilePage() deliberately stays where it is and is
 * passed in as a bound closure rather than moved here: it's also called
 * from the profile-approval POST handler (a write flow, out of scope for
 * this read-only slice), so moving it would mean either duplicating it or
 * making the write handler depend on this controller for an unrelated
 * reason.
 */
final class ProfilePageController
{
    /**
     * @param \Closure(): (array<string, mixed>|null) $resolveViewerProfile
     * @param \Closure(array<string, mixed>, bool, ?string, ?string, bool, array): string $renderProfilePage
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly \Closure $resolveViewerProfile,
        private readonly \Closure $renderProfilePage,
    ) {
    }

    public function profile(string $slug, bool $self, array $query): ?string
    {
        $profile = ProfileRepository::bySlug($this->routeServices->pdo(), $slug);
        if ($profile === null) {
            return null;
        }

        return ($this->renderProfilePage)($profile, $self, $this->profileNoticeFromQuery($profile, $query), null, false, $query);
    }

    public function username(string $username, array $query = []): ?string
    {
        $usernameToken = strtolower($username);
        $pdo = $this->routeServices->pdo();
        $profiles = ProfileRepository::byUsernameToken($pdo, $usernameToken);
        if ($profiles === []) {
            return null;
        }

        $approvedProfiles = array_values(array_filter(
            $profiles,
            static fn (array $profile): bool => ((int) $profile['is_approved']) === 1
        ));
        $approvedIdentityIds = array_values(array_map(
            static fn (array $profile): string => (string) $profile['identity_id'],
            $approvedProfiles
        ));
        $ownIdentityIds = array_values(array_map(
            static fn (array $profile): string => (string) $profile['identity_id'],
            $profiles
        ));
        $viewerProfile = ($this->resolveViewerProfile)();
        $canPrivateMessage = $this->routeServices->featureFlags()->isEnabled(\ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry::PRIVATE_MESSAGES_ENABLED)
            && $viewerProfile !== null
            && ((int) $viewerProfile['is_approved']) === 1
            && $approvedProfiles !== []
            && (string) ($viewerProfile['username_token'] ?? '') !== ''
            && (string) $viewerProfile['username_token'] !== $usernameToken;

        return $this->routeServices->renderPageTemplate(
            'username.php',
            [
                'usernameToken' => $usernameToken,
                'historyOffset' => max(0, (int) ($query['key_offset'] ?? 0)),
                'approvedProfiles' => $approvedProfiles,
                'approverUsernames' => self::aggregateApproverUsernames($approvedProfiles, $ownIdentityIds),
                'approvedThreadCount' => AuthoredContentRepository::countVisible($pdo, $approvedIdentityIds, true),
                'approvedPostCount' => AuthoredContentRepository::countVisible($pdo, $approvedIdentityIds, false),
                'approvedThreads' => AuthoredContentRepository::visibleThreads($pdo, $approvedIdentityIds),
                'approvedPosts' => AuthoredContentRepository::visiblePosts($pdo, $approvedIdentityIds),
                'viewerProfile' => $viewerProfile,
                'canPrivateMessage' => $canPrivateMessage,
                // The person page: Threads or Replies, "Edit in Account" on your own page, "Manage access" for operators.
                'personTab' => ($query['tab'] ?? '') === 'replies' ? 'replies' : 'threads',
                'isOwnPage' => $viewerProfile !== null && (string) ($viewerProfile['username_token'] ?? '') === $usernameToken,
                'viewerIsOperator' => $this->routeServices->securityReviewAllowed(),
            ],
            $usernameToken,
            'profiles',
        );
    }

    /**
     * @param array<int, array<string, mixed>> $approvedProfiles
     * @param array<int, string> $ownIdentityIds
     * @return array<int, array{label: string, slug: ?string}>
     */
    private static function aggregateApproverUsernames(array $approvedProfiles, array $ownIdentityIds): array
    {
        $approvers = [];
        foreach ($approvedProfiles as $profile) {
            $approverIdentityId = $profile['approved_by_identity_id'] ?? null;
            $approverLabel = (string) ($profile['approved_by_label'] ?? '');
            if ($approverLabel === '') {
                continue;
            }
            if ($approverIdentityId !== null && in_array((string) $approverIdentityId, $ownIdentityIds, true)) {
                continue;
            }
            $approverSlug = (string) ($profile['approved_by_profile_slug'] ?? '');
            $approvers[$approverLabel] = [
                'label' => $approverLabel,
                'slug' => $approverSlug !== '' ? $approverSlug : null,
            ];
        }

        return array_values($approvers);
    }

    public function directory(): string
    {
        $pdo = $this->routeServices->pdo();
        // Every viewer gets the same page, so shared copies match live ones. The
        // Members / Waiting tabs are shown before paint only for a browser
        // remembered as approved; Waiting itself stays approved-only (see pendingDirectory).
        return $this->routeServices->renderPageTemplate('users.php', [
            'users' => ProfileRepository::approvedDirectoryUsers($pdo),
            'pendingCount' => count(ProfileRepository::pendingDirectoryProfiles($pdo, $this->routeServices->featureFlags()->isEnabled(\ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry::USERNAME_CHANGES_ENABLED))),
        ], 'Users', 'profiles');
    }

    public function pendingDirectory(string $method): void
    {
        if ($method !== 'GET') {
            $this->routeServices->sendHtml($this->routeServices->renderMessagePage('Method Not Allowed', 'Method Not Allowed', 'Only GET is supported.', 'tools'), 405, $this->routeServices->noStoreHeaders());
            return;
        }
        $viewer = ($this->resolveViewerProfile)();
        if ($viewer === null || (int) $viewer['is_approved'] !== 1) {
            $this->routeServices->sendHtml($this->routeServices->renderMessagePage('Forbidden', 'Forbidden', 'Only approved users can view the pending approval directory.', 'tools'), 403, $this->routeServices->noStoreHeaders());
            return;
        }
        // Users › Waiting: the same list and Approve buttons as User Access, beside the member directory.
        $this->routeServices->sendHtml($this->routeServices->renderPageTemplate('users_pending.php', [
            'profiles' => ProfileRepository::pendingDirectoryProfiles($this->routeServices->pdo(), $this->routeServices->featureFlags()->isEnabled(\ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry::USERNAME_CHANGES_ENABLED)),
            'accessManagement' => false,
        ], 'Users', 'profiles', ['/assets/openpgp_loader.js', '/assets/browser_signing.js', '/assets/pending_approvals.js']), 200, $this->routeServices->noStoreHeaders());
    }

    /**
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $query
     */
    private function profileNoticeFromQuery(array $profile, array $query): ?string
    {
        if (($query['approval'] ?? null) !== 'success') {
            return null;
        }

        $postId = (string) ($query['post_id'] ?? '');
        $commitSha = (string) ($query['commit'] ?? '');
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $postId) || !preg_match('/^[a-f0-9]{40}$/', $commitSha)) {
            return null;
        }

        return 'Approved user ' . $this->escape((string) $profile['username']) . '. '
            . '<a href="/posts/' . $this->escape($postId) . '">Open approval post</a>.';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
