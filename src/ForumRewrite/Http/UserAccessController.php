<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\ReadModel\ProfileRepository;
use ForumRewrite\Tools\ToolsPageSupport;

final class UserAccessController
{
    /**
     * @param \Closure(): (?array) $viewer
     * @param \Closure(array, array): string $renderProfile
     */
    public function __construct(
        private readonly RouteServices $services,
        private readonly \Closure $viewer,
        private readonly \Closure $renderProfile,
    ) {
    }

    public function index(): string
    {
        $allowed = $this->services->securityReviewAllowed();
        $viewer = ($this->viewer)();
        $canResume = !$allowed && empty($_SESSION['authenticated_identity_id'])
            && $viewer !== null && (int) ($viewer['is_approved'] ?? 0) === 1
            && ($viewer['approved_by_label'] ?? '') === 'root';
        $history = $allowed ? $this->services->activityService()->fetchActivityRows('security', 'date', 'desc', limit: 5) : ['items' => [], 'has_more' => false];
        $items = [];
        foreach ($history['items'] as $row) {
            $item = $this->services->activityService()->fetchActivityDetail((int) $row['id']);
            if ($item !== null) {
                $item = $this->services->activityService()->securityReviewItem($item);
                $items[] = $item + ['link' => $this->services->activityService()->activityItemLink($item)];
            }
        }
        return $this->services->renderPageTemplate('user_access.php', [
            'users' => ProfileRepository::approvedDirectoryUsers($this->services->pdo()),
            'canSecurityReview' => $allowed,
            'canResumeHistory' => $canResume,
            'historyItems' => $items,
            'historyHasMore' => $history['has_more'],
            'toolNavOptions' => ToolsPageSupport::navOptions('user-access'),
        ], 'User Access', 'tools', $canResume ? [
            '/assets/openpgp_loader.js', '/assets/browser_signing.js', '/assets/private_site_auth.js', '/assets/access_history_auth.js',
        ] : [], !$canResume);
    }

    public function account(string $username, array $query): ?string
    {
        $username = strtolower($username);
        $profiles = ProfileRepository::byUsernameToken($this->services->pdo(), $username);
        if ($profiles === []) {
            return null;
        }
        return $this->render('user_access_account.php', [
            'usernameToken' => $username,
            'profiles' => $profiles,
            'approvalHistory' => $this->services->approvalHistory()->forUsername($username, 50, max(0, (int) ($query['key_offset'] ?? 0))),
        ]);
    }

    public function pending(string $method): void
    {
        if ($method !== 'GET') {
            $this->services->sendHtml($this->services->renderMessagePage('Method Not Allowed', 'Method Not Allowed', 'Only GET is supported.', 'tools'), 405, $this->services->noStoreHeaders());
            return;
        }
        $viewer = ($this->viewer)();
        if ($viewer === null || (int) $viewer['is_approved'] !== 1) {
            $this->services->sendHtml($this->services->renderMessagePage('Forbidden', 'Forbidden', 'Only approved users can view pending approvals. Sign in with an approved account and try again.', 'tools'), 403, $this->services->noStoreHeaders());
            return;
        }
        $this->services->sendHtml($this->services->renderPageTemplate('users_pending.php', [
            'profiles' => ProfileRepository::pendingDirectoryProfiles($this->services->pdo(), $this->services->featureFlags()->isEnabled(\ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry::USERNAME_CHANGES_ENABLED)),
            'accessManagement' => true,
            'toolNavOptions' => ToolsPageSupport::navOptions('user-access'),
        ], 'Pending approvals', 'tools', ['/assets/openpgp_loader.js', '/assets/browser_signing.js', '/assets/pending_approvals.js']), 200, $this->services->noStoreHeaders());
    }

    public function profile(string $slug, array $query): ?string
    {
        $profile = ProfileRepository::bySlug($this->services->pdo(), $slug);
        if ($profile === null) {
            return null;
        }
        return ($this->renderProfile)($profile, $query);
    }

    private function render(string $template, array $data): string
    {
        return $this->services->renderPageTemplate($template, $data + [
            'toolNavOptions' => ToolsPageSupport::navOptions('user-access'),
        ], 'User Access', 'tools');
    }
}
