<?php

declare(strict_types=1);
namespace ForumRewrite\Http;

use ForumRewrite\View\PledgeCampaign;

/**
 * The support page: the site's support campaign thread drawn as a wall of squares, with its
 * opening post as the pitch. The numbers come from PledgeCampaign::fromPosts() on the same
 * posts the thread page loads, so the page and the thread always agree. The page is rendered
 * live, never baked, so a pledge or an approval shows on the next view.
 */
final class SupportPageController
{
    /** @param callable(string): list<array<string, mixed>> $fetchThreadPosts */
    public function __construct(
        private readonly RouteServices $routeServices,
        private $fetchThreadPosts,
    ) {}

    public function page(string $threadId): string
    {
        $posts = ($this->fetchThreadPosts)($threadId);
        $root = null;
        foreach ($posts as $post) {
            if ((string) $post['post_id'] === $threadId && ($post['retracted_at'] ?? null) === null) {
                $root = $post;
                break;
            }
        }
        $campaign = $root === null ? null : PledgeCampaign::fromPosts(
            $posts,
            $threadId,
            fn (string $identityId): bool => \ForumRewrite\Security\ApprovalPolicy::isActiveOperator($this->routeServices->repositoryRoot(), $identityId),
        );

        $profile = \ForumRewrite\SiteProfileRegistry::active();
        $title = 'Support ' . (string) ($profile['appName'] ?? $profile['displayName']);

        return $this->routeServices->renderPageTemplate('support.php', [
            'supportTitle' => $title,
            'supportThreadId' => $threadId,
            'supportRoot' => $root,
            'pledgeCampaign' => $campaign,
            'posts' => $posts,
        ], $title, 'support', [
            '/assets/openpgp_loader.js',
            '/assets/browser_signing.js',
            '/assets/outbox_store.js',
            '/assets/outbox_storage.js',
            '/assets/outbox_compose.js',
            '/assets/outbox_sender.js',
            '/assets/private_site_auth.js',
            ...($campaign !== null ? ['/assets/pledge_field.js'] : []),
        ]);
    }
}
