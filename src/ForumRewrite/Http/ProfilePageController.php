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
     * @param \Closure(array<string, mixed>, bool, ?string, ?string): string $renderProfilePage
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

        return ($this->renderProfilePage)($profile, $self, $this->profileNoticeFromQuery($profile, $query));
    }

    public function username(string $username): ?string
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
        $unapprovedProfiles = array_values(array_filter(
            $profiles,
            static fn (array $profile): bool => ((int) $profile['is_approved']) !== 1
        ));
        $approvedIdentityIds = array_values(array_map(
            static fn (array $profile): string => (string) $profile['identity_id'],
            $approvedProfiles
        ));

        return $this->routeServices->renderPageTemplate(
            'username.php',
            [
                'usernameToken' => $usernameToken,
                'approvedProfiles' => $approvedProfiles,
                'unapprovedProfiles' => $unapprovedProfiles,
                'approvedThreadCount' => AuthoredContentRepository::countVisible($pdo, $approvedIdentityIds, true),
                'approvedPostCount' => AuthoredContentRepository::countVisible($pdo, $approvedIdentityIds, false),
                'approvedThreads' => AuthoredContentRepository::visibleThreads($pdo, $approvedIdentityIds),
                'approvedPosts' => AuthoredContentRepository::visiblePosts($pdo, $approvedIdentityIds),
            ],
            'User ' . $usernameToken,
            'profiles',
        );
    }

    public function directory(): string
    {
        $viewerProfile = ($this->resolveViewerProfile)();

        return $this->routeServices->renderPageTemplate(
            'users.php',
            [
                'users' => ProfileRepository::approvedDirectoryUsers($this->routeServices->pdo()),
                'showPendingLink' => $viewerProfile !== null
                    && ((int) $viewerProfile['is_approved']) === 1
                    && ProfileRepository::hasPendingDirectoryProfiles($this->routeServices->pdo()),
            ],
            'Users',
            'profiles',
        );
    }

    public function pendingDirectory(string $method): void
    {
        if ($method !== 'GET') {
            $this->routeServices->sendHtml(
                $this->routeServices->renderMessagePage(
                    'Method Not Allowed',
                    'Method Not Allowed',
                    'Only GET is supported for the pending user directory.',
                    'none'
                ),
                405
            );
            return;
        }

        $viewerProfile = ($this->resolveViewerProfile)();
        if ($viewerProfile === null || ((int) $viewerProfile['is_approved']) !== 1) {
            $this->routeServices->sendHtml(
                $this->routeServices->renderMessagePage(
                    'Forbidden',
                    'Forbidden',
                    'Only approved users can view the pending approval directory.',
                    'profiles'
                ),
                403
            );
            return;
        }

        $this->routeServices->sendHtml($this->renderPendingDirectory(), 200);
    }

    private function renderPendingDirectory(): string
    {
        return $this->routeServices->renderPageTemplate(
            'users_pending.php',
            [
                'profiles' => ProfileRepository::pendingDirectoryProfiles($this->routeServices->pdo()),
            ],
            'Users Awaiting Approval',
            'profiles',
            $this->identityScripts(['/assets/pending_approvals.js']),
        );
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
            . '<a href="/posts/' . $this->escape($postId) . '">Open approval post</a>. '
            . 'Commit ' . $this->escape($commitSha);
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
