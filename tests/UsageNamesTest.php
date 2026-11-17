<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Tools\ToolsPageSupport;
use ForumRewrite\UsageReports\UsageNames;
use ForumRewrite\UsageReports\UsagePageKind;

final class UsageNamesTest
{
    public function testNamesPagesControlsAndToolsFromTheRegistry(): void
    {
        assertSame('Thread', UsageNames::page('/threads/*'));
        assertSame('Board', UsageNames::page('/'));
        assertSame('Feature Flags', UsageNames::page('/tools/feature-flags'));
        assertSame('Usage Log', UsageNames::page('/tools/usage-log'));
        assertSame('Recent', UsageNames::control('tab:recent'));
        assertSame('You menu', UsageNames::control('tab:account'));
        assertSame('Usage Log', UsageNames::control('tool:usage'));
        assertSame('Feature Flags', UsageNames::control(self::toneFor('/tools/feature-flags/')));
        assertSame('Usage log copy', UsageNames::control('action:usage-log-copy'));
        assertSame('Where do we keep the keys?', UsageNames::control('Where do we keep the keys?'));
    }

    public function testUnknownKindsAndHooksFallBackToTheirRawForm(): void
    {
        assertSame('/somewhere/*', UsageNames::page('/somewhere/*'));
        assertSame('Brand new', UsageNames::control('tab:brand-new'));
        assertSame('Mystery tool', UsageNames::control('tool:mystery-tool'));
    }

    /** Every route the router serves has a readable name or is raw on purpose. */
    public function testEveryRouteKindIsNamedOrRawByDesign(): void
    {
        $paths = [
            '/', '/about/', '/account/key/', '/activity/', '/agent/', '/approvals/root/ABC/', '/calendar/', '/calendar/2026-11-14/',
            '/calendar/rapclub/2026-11-14/', '/compose/thread', '/compose/reply', '/gallery/', '/guestbook/', '/hints/', '/inbox/',
            '/invites/', '/latest/3', '/lobby/', '/messages/', '/messages/conversation/T1/', '/offline/', '/posts/P1/',
            '/profiles/ilyag/', '/profiles/ilyag/approve/', '/random/', '/recent/', '/records/R1/', '/releases/', '/search/',
            '/tags/', '/tags/news/', '/themes/', '/threads/', '/threads/T1/', '/threads/T1/forte', '/top/2', '/unread/',
            '/user/ilyag/', '/users/', '/watch/', '/docs/reference/agent_api.md', '/forte/threads/T1/replies',
            '/source/blob/abc/src/A.php', '/tools/', '/tools/feature-flags/changes/C1/', '/tools/llm-exchanges/42/',
            '/tools/user-access/accounts/ilyag/', '/tools/user-access/profiles/openpgp-AB/',
        ];
        foreach (ToolsPageSupport::registry() as $tool) {
            $paths[] = $tool['href'];
        }
        foreach ($paths as $path) {
            $kind = UsagePageKind::fromPath($path);
            assertTrue(UsageNames::covers($kind), $path . ' (' . $kind . ') has no readable name');
        }
    }

    public function testBrowserTableMatchesTheServer(): void
    {
        $table = UsageNames::forBrowser();
        assertSame('Thread', $table['pages']['/threads/*']);
        assertSame('Feature Flags', $table['pages']['/tools/feature-flags']);
        assertSame('You menu', $table['tabs']['account']);
        assertSame('Usage Log', $table['tools']['usage']);
        foreach (UsagePageKind::WORDS as $word) {
            assertSame(1, preg_match('/^[a-z-]+$/', $word));
        }
    }

    private static function toneFor(string $href): string
    {
        foreach (ToolsPageSupport::registry() as $tool) {
            if ($tool['href'] === $href) {
                return 'tool:' . $tool['tone'];
            }
        }

        return 'tool:none';
    }
}
