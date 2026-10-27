<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\PresentationSlotRegistry;
use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\View\TemplateRenderer;

final class AppNavigationPlacementTest
{
    private const VIEWERS = [
        'guest' => null,
        'unapproved' => ['is_approved' => 0, 'profile_slug' => 'mouse'],
        'approved' => ['is_approved' => 1, '_authenticated_identity' => true, 'username_token' => 'alice', 'profile_slug' => 'alice'],
    ];

    public function testZenmemesSelectsTheAppShellAndOtherSitesKeepTheirs(): void
    {
        $profiles = SiteProfileRegistry::all();
        assertSame('app', PresentationSlotRegistry::resolve($profiles['zenmemes'], 'navigation'));
        assertSame('forum', PresentationSlotRegistry::resolve($profiles['chouse'], 'navigation'));
        assertSame('qdb', PresentationSlotRegistry::resolve($profiles['qdb'], 'navigation'));
        assertSame('forum', PresentationSlotRegistry::resolve($profiles['mitrapclub'], 'navigation'));
        PresentationSlotRegistry::validate($profiles['zenmemes']['presentationSlots'], 'zenmemes');
    }

    public function testEveryViewerKeepsEveryDestinationWithAPlacement(): void
    {
        $this->withSite('zenmemes', function (): void {
            foreach ([false, true] as $messages) {
                $renderer = $this->renderer([FeatureFlagRegistry::PRIVATE_MESSAGES_ENABLED => $messages]);
                foreach (self::VIEWERS as $name => $viewer) {
                    $items = $renderer->navigationItems($viewer);
                    $placements = array_column($items, 'placement', 'href');
                    assertSame(count($items), count($placements), $name);
                    assertSame('main', $placements['/'], $name);
                    assertSame('main', $placements['/users/'], $name);
                    foreach (['/about/', '/tools/', '/account/key/'] as $href) {
                        assertSame('menu', $placements[$href], $name . ' ' . $href);
                    }
                    $approved = $name === 'approved';
                    assertSame($approved && $messages ? 'main' : null, $placements['/messages'] ?? null, $name);
                    assertSame($approved ? 'menu' : null, $placements['/invites/'] ?? null, $name);
                }
            }
        });
    }

    public function testMembersOnlyViewersGetLobbyInTheMainBarAndTheRestInTheMenu(): void
    {
        $this->withSite('zenmemes', function (): void {
            $renderer = $this->renderer([FeatureFlagRegistry::APPROVED_MEMBERS_ONLY => true]);
            $placements = array_column($renderer->navigationItems(self::VIEWERS['unapproved']), 'placement', 'href');
            assertSame(['/lobby/' => 'main', '/profiles/mouse' => 'menu', '/account/key/' => 'menu'], $placements);
        });
    }

    public function testOtherSitesGetNoPlacementsAndUnchangedHeaders(): void
    {
        foreach (['chouse', 'qdb', 'mitrapclub'] as $site) {
            $this->withSite($site, function () use ($site): void {
                foreach ($this->renderer()->navigationItems(self::VIEWERS['approved']) as $item) {
                    assertSame(false, array_key_exists('placement', $item), $site);
                }
            });
        }
    }

    public function testZenmemesHeaderIsUnchangedUntilTheShellRendersPlacements(): void
    {
        $profiles = SiteProfileRegistry::all();
        $forum = $profiles;
        $forum['zenmemes']['presentationSlots']['navigation'] = 'forum';
        $headers = [];
        foreach (['app' => $profiles, 'forum' => $forum] as $slot => $set) {
            SiteProfileRegistry::setProfilesForTesting($set);
            try {
                $this->withSite('zenmemes', function () use ($slot, &$headers): void {
                    $html = $this->renderer()->renderLayout('Board', '', 'board', viewerProfile: self::VIEWERS['approved']);
                    preg_match('/<header class="site-header">.*?<\/header>/s', $html, $matches);
                    $headers[$slot] = $matches[0];
                });
            } finally {
                SiteProfileRegistry::setProfilesForTesting(null);
            }
        }
        assertSame($headers['forum'], $headers['app']);
    }

    /** @param array<string, bool> $flags */
    private function renderer(array $flags = []): TemplateRenderer
    {
        return new TemplateRenderer(dirname(__DIR__) . '/templates', featureFlags: new FeatureFlagEvaluator(siteValues: $flags));
    }

    private function withSite(string $site, callable $test): void
    {
        $previous = getenv('FORUM_SITE_ID');
        putenv('FORUM_SITE_ID=' . $site);
        try {
            $test();
        } finally {
            putenv($previous === false ? 'FORUM_SITE_ID' : 'FORUM_SITE_ID=' . $previous);
        }
    }
}
