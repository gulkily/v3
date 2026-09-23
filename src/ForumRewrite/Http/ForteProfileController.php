<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\ReadModel\AuthoredContentRepository;
use ForumRewrite\ReadModel\ProfileRepository;

/**
 * Ninth Phase 2 slice (see docs/plans/codebase_cleanup_audit_plan_v1.md and
 * docs/plans/codebase_cleanup_audit_findings_v1.md): /forte/profiles/{slug}
 * and /forte/user/{username} - the Forte-flavored (chromeless standalone
 * layout) mirrors of ProfilePageController::profile()/username(), built on
 * the same already-extracted ProfileRepository/AuthoredContentRepository.
 * The rest of /forte (the board, activity, and user-directory views) is
 * meaningfully more complex - reply-tree building, selection/sort
 * resolution - and deliberately left for a later slice.
 */
final class ForteProfileController
{
    public function __construct(
        private readonly RouteServices $routeServices,
    ) {
    }

    public function profile(string $slug): ?string
    {
        $profile = ProfileRepository::bySlug($this->routeServices->pdo(), $slug);
        if ($profile === null) {
            return null;
        }

        $pageTitleLabel = trim((string) ($profile['username'] ?? ''));
        if ($pageTitleLabel === '') {
            $pageTitleLabel = trim((string) ($profile['fallback_label'] ?? ''));
        }
        if ($pageTitleLabel === '') {
            $pageTitleLabel = (string) $profile['profile_slug'];
        }

        return $this->routeServices->renderStandalonePage(
            'forte_profile.php',
            [
                'profile' => $profile,
            ],
            $pageTitleLabel . ' - Forte Profile',
            'paned-reader-body',
            [],
            ['/assets/forte.css'],
        );
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

        return $this->routeServices->renderStandalonePage(
            'forte_username.php',
            [
                'usernameToken' => $usernameToken,
                'approvedProfiles' => $approvedProfiles,
                'unapprovedProfiles' => $unapprovedProfiles,
                'approvedThreadCount' => AuthoredContentRepository::countVisible($pdo, $approvedIdentityIds, true),
                'approvedPostCount' => AuthoredContentRepository::countVisible($pdo, $approvedIdentityIds, false),
                'approvedThreads' => AuthoredContentRepository::visibleThreads($pdo, $approvedIdentityIds),
                'approvedPosts' => AuthoredContentRepository::visiblePosts($pdo, $approvedIdentityIds),
            ],
            'User ' . $usernameToken . ' - Forte',
            'paned-reader-body',
            [],
            ['/assets/forte.css'],
        );
    }
}
