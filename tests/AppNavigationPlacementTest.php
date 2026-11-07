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
                    assertSame('main', $placements['/recent/'], $name);
                    assertSame('main', $placements['/users/'], $name);
                    // Desktop bar holds every destination that is not about the viewer.
                    foreach (['/search', '/about/', '/tools/'] as $href) {
                        assertSame('main', $placements[$href], $name . ' ' . $href);
                    }
                    assertSame('menu', $placements['/account/key/'], $name);
                    // Phones keep Board and Calendar in the bar; everything else opens from More.
                    $phones = array_column($items, 'phone', 'href');
                    assertSame('bar', $phones['/'], $name);
                    foreach (['/recent/', '/users/', '/search', '/about/', '/tools/', '/account/key/'] as $href) {
                        assertSame('more', $phones[$href], $name . ' ' . $href);
                    }
                    // Member-only links are rendered for every viewer and shown before paint by the client.
                    assertSame($messages ? 'main' : null, $placements['/messages'] ?? null, $name);
                    assertSame('menu', $placements['/invites/'] ?? null, $name);
                    foreach ($items as $item) {
                        assertSame(in_array($item['href'], ['/messages', '/invites/'], true), $item['member_only'] ?? false, $name . ' ' . $item['href']);
                    }
                }
            }
        });
    }

    public function testInboxTakesTheMessagesPlaceOnTheBarOnlyWhileItsFlagIsOn(): void
    {
        $this->withSite('zenmemes', function (): void {
            $off = $this->renderer([FeatureFlagRegistry::PRIVATE_MESSAGES_ENABLED => true]);
            $placements = array_column($off->navigationItems(self::VIEWERS['approved']), 'placement', 'href');
            assertSame(null, $placements['/inbox'] ?? null);
            assertSame('main', $placements['/messages']);

            $on = $this->renderer([FeatureFlagRegistry::PRIVATE_MESSAGES_ENABLED => true, FeatureFlagRegistry::INBOX_ENABLED => true]);
            $items = $on->navigationItems(self::VIEWERS['approved']);
            $placements = array_column($items, 'placement', 'href');
            assertSame('main', $placements['/inbox']);
            assertSame('menu', $placements['/messages']);
            $hrefs = array_column(array_filter($items, static fn (array $item): bool => $item['placement'] === 'main'), 'href');
            assertTrue(array_search('/inbox', $hrefs, true) < array_search('/search', $hrefs, true), 'Inbox sits where Messages was.');
            assertSame('more', array_column($items, 'phone', 'href')['/inbox']);
            assertSame(true, array_column($items, 'member_only', 'href')['/inbox']);
            assertTrue(in_array('inbox', array_column(array_filter($items, static fn (array $item): bool => $item['placement'] === 'main' && $item['phone'] === 'more'), 'section'), true), 'Inbox can take a phone slot.');

            $withoutMessages = $this->renderer([FeatureFlagRegistry::PRIVATE_MESSAGES_ENABLED => false, FeatureFlagRegistry::INBOX_ENABLED => true]);
            $placements = array_column($withoutMessages->navigationItems(self::VIEWERS['approved']), 'placement', 'href');
            assertSame('main', $placements['/inbox']);
            assertSame(null, $placements['/messages'] ?? null);

            $state = $on->navigationState(self::VIEWERS['approved'], 'inbox');
            assertStringContains('href="/inbox"', $state['html']);
        });
    }

    public function testMembersOnlyViewersGetLobbyInTheMainBarAndTheRestInTheMenu(): void
    {
        $this->withSite('zenmemes', function (): void {
            $renderer = $this->renderer([FeatureFlagRegistry::APPROVED_MEMBERS_ONLY => true]);
            $placements = array_column($renderer->navigationItems(self::VIEWERS['unapproved']), 'placement', 'href');
            assertSame(['/lobby/' => 'main', '/profiles/mouse' => 'menu', '/account/key/' => 'menu'], $placements);
            assertSame('bar', array_column($renderer->navigationItems(self::VIEWERS['unapproved']), 'phone', 'href')['/lobby/']);
        });
    }

    public function testOtherSitesGetNoPlacementsAndUnchangedHeaders(): void
    {
        foreach (['chouse', 'qdb', 'mitrapclub'] as $site) {
            $this->withSite($site, function () use ($site): void {
                $items = $this->renderer()->navigationItems(self::VIEWERS['approved']);
                // Board search is linked from the app navigation only; QDB keeps its own Search.
                assertSame($site === 'qdb' ? 1 : 0, count(array_filter($items, static fn (array $item): bool => $item['href'] === '/search')), $site);
                foreach ($items as $item) {
                    assertSame(false, array_key_exists('placement', $item), $site);
                }
            });
        }
    }

    public function testAppHeaderSplitsDestinationsBetweenTheBarAndTheAccountMenu(): void
    {
        $this->withSite('zenmemes', function (): void {
            $html = $this->renderer([FeatureFlagRegistry::PRIVATE_MESSAGES_ENABLED => true])
                ->renderLayout('Board', '', 'board', viewerProfile: self::VIEWERS['approved']);
            preg_match('/<nav class="nav">.*?<\/nav>/s', $html, $bar);
            preg_match('/<details class="account-menu".*?<\/details>/s', $html, $menu);
            foreach (['/', '/recent/', '/users/', '/messages', '/search', '/about/', '/tools/'] as $href) {
                assertStringContains('href="' . $href . '"', $bar[0]);
            }
            // Bar order follows the Step 2 layout: Users before Messages before Search.
            assertSame(true, strpos($bar[0], 'href="/users/"') < strpos($bar[0], 'href="/messages"'));
            assertSame(true, strpos($bar[0], 'href="/messages"') < strpos($bar[0], 'href="/search"'));
            foreach (['/account/key/', '/invites/'] as $href) {
                assertSame(false, str_contains($bar[0], 'href="' . $href . '"'), $href);
                assertStringContains('href="' . $href . '"', $menu[0]);
            }
            assertStringContains('data-tab="board"', $bar[0]);
            assertStringContains('data-tab="board" data-phone="bar"', $bar[0]);
            assertStringContains('data-tab="recent" data-phone="more"', $bar[0]);
            // The same panel lists the phone-only More destinations once more, for every viewer.
            assertStringContains('class="account-menu__links account-menu__more"', $menu[0]);
            foreach (['/recent/', '/users/', '/messages', '/search', '/about/', '/tools/'] as $href) {
                assertStringContains('href="' . $href . '"', $menu[0]);
            }
            assertStringContains('account-menu__label--more">More<', $menu[0]);
            // The More tab and the panel's Messages link mirror the unread count on phones.
            assertStringContains('aria-label="Menu" data-private-message-unread-mirror', $menu[0]);
            // The head script publishes the free slot count and the sections that may fill one, the same for every viewer.
            assertStringContains('window.__forumNavSlots = { count: navSlotCount, sections: navSlotSections };', $html);
            assertStringContains('var navSlotSections = ["recent","profiles","messages","search","about","tools"];', $html);
            assertStringContains('/assets/nav_slots.', $html);
            assertSame(false, str_contains($menu[0], 'nav-pin'));
            // Board is in the bar, so the More tab keeps its own name.
            assertSame(false, str_contains($menu[0], 'data-current'));
            assertSame(false, str_contains($menu[0], 'account-menu__label--current'));
            assertStringContains('href="/messages" data-member-only data-private-message-unread-mirror', $menu[0]);
            assertStringContains('data-invite-navigation', $menu[0]);
            assertStringContains('data-role="theme-menu"', $menu[0]);
            assertStringContains('class="new-post-button" href="/compose/thread"', $html);
            assertStringContains('<div class="shell-sky" aria-hidden="true"></div>', $html);
            assertStringContains('/assets/account_menu.', $html);
            assertStringContains('/assets/tab_bar_scroll.', $html);
        });
    }

    public function testMoreTabTakesTheNameOfAPageListedInThePanel(): void
    {
        $this->withSite('zenmemes', function (): void {
            $renderer = $this->renderer([FeatureFlagRegistry::PRIVATE_MESSAGES_ENABLED => true]);
            foreach ([['about', 'About'], ['profiles', 'Users'], ['messages', 'Messages'], ['account', 'Account']] as [$section, $label]) {
                $html = $renderer->renderLayout($label, '', $section, viewerProfile: self::VIEWERS['approved']);
                assertStringContains('data-tab="account" data-current="' . $section . '">', $html);
                assertStringContains('account-menu__label--more">More</span><span class="account-menu__label account-menu__label--current">' . $label . '</span></summary>', $html);
            }
        });
    }

    public function testSlotCountLeavesFiveTabsAtMostAndTwoSlotsAtMost(): void
    {
        $this->withSite('zenmemes', function (): void {
            // Board, New post, More -> two slots; with Calendar -> one; Lobby alone -> two (capped).
            $renderer = $this->renderer();
            assertSame(2, TemplateRenderer::navSlotCount($renderer->navigationItems(self::VIEWERS['approved'])));
            $events = $this->renderer([FeatureFlagRegistry::EVENT_SUPPORT_ENABLED => true]);
            assertSame(1, TemplateRenderer::navSlotCount($events->navigationItems(self::VIEWERS['approved'])));
            $lobby = $this->renderer([FeatureFlagRegistry::APPROVED_MEMBERS_ONLY => true]);
            assertSame(2, TemplateRenderer::navSlotCount($lobby->navigationItems(self::VIEWERS['unapproved'])));
        });
        $this->withSite('chouse', function (): void {
            assertSame(0, TemplateRenderer::navSlotCount($this->renderer()->navigationItems(self::VIEWERS['approved'])));
            assertSame(false, str_contains($this->renderer()->renderLayout('Board', '', 'board', viewerProfile: self::VIEWERS['approved']), '__forumNavSlots'));
        });
    }

    public function testMembersOnlyViewersGetNoNewPostButton(): void
    {
        $this->withSite('zenmemes', function (): void {
            $html = $this->markup($this->renderer([FeatureFlagRegistry::APPROVED_MEMBERS_ONLY => true])
                ->renderLayout('Lobby', '', 'lobby', viewerProfile: self::VIEWERS['unapproved']));
            assertSame(false, str_contains($html, 'new-post-button'));
            assertStringContains('href="/profiles/mouse"', $html);
        });
    }

    public function testForumSlotKeepsTodaysHeaderForZenmemes(): void
    {
        $profiles = SiteProfileRegistry::all();
        $profiles['zenmemes']['presentationSlots']['navigation'] = 'forum';
        SiteProfileRegistry::setProfilesForTesting($profiles);
        try {
            $this->withSite('zenmemes', function (): void {
                $html = $this->markup($this->renderer()->renderLayout('Board', '', 'board', viewerProfile: self::VIEWERS['approved']));
                assertStringContains('<header class="site-header">', $html);
                foreach (['account-menu', 'new-post-button', 'data-tab=', 'shell-sky', '/assets/account_menu.', '/assets/tab_bar_scroll.'] as $marker) {
                    assertSame(false, str_contains($html, $marker), $marker);
                }
            });
        } finally {
            SiteProfileRegistry::setProfilesForTesting(null);
        }
    }

    /** The page without its inline critical CSS, which names every shell class. */
    private function markup(string $html): string
    {
        return (string) preg_replace('/<style data-role="critical-css">.*?<\/style>/s', '', $html);
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
