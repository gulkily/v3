<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\UsageReports\UsagePageKind;

final class UsagePageKindTest
{
    public function testKeepsRouteWordsAndDropsSlashesAndQueries(): void
    {
        assertSame('/', UsagePageKind::fromPath('/'));
        assertSame('/threads', UsagePageKind::fromPath('/threads/'));
        assertSame('/tools/feature-flags', UsagePageKind::fromPath('/tools/feature-flags/?group=ACCESS'));
        assertSame('/messages/conversation/*', UsagePageKind::fromPath('https://zenmemes.com/messages/conversation/bob/#end'));
    }

    /** Every parameterized route in Application.php, filled with values that must not survive. */
    public function testRedactsEveryParameterizedRoute(): void
    {
        $cases = [
            '/threads/T9f3a1c/' => '/threads/*',
            '/threads/T9f3a1c/forte' => '/threads/*/forte',
            '/posts/P77aa01/' => '/posts/*',
            '/records/R5521/' => '/records/*',
            '/profiles/openpgp-ABCDEF0123/' => '/profiles/*',
            '/profiles/ilyag/approve/' => '/profiles/*/approve',
            '/user/ilyag/' => '/user/*',
            '/tags/specialoffer/' => '/tags/*',
            '/calendar/2026-11-14/' => '/calendar/*',
            '/calendar/rapclub/2026-11-14/' => '/calendar/*/*',
            '/latest/3' => '/latest/*',
            '/top/2' => '/top/*',
            '/approvals/root/ABCDEF/' => '/approvals/root/*',
            '/tools/feature-flags/changes/C123/' => '/tools/feature-flags/changes/*',
            '/tools/llm-exchanges/42/' => '/tools/llm-exchanges/*',
            '/tools/user-access/accounts/ilyag/' => '/tools/user-access/accounts/*',
            '/tools/user-access/profiles/openpgp-ABCD/' => '/tools/user-access/profiles/*',
            '/forte/threads/T1/replies' => '/forte/threads/*/replies',
            '/forte/profiles/ilyag/' => '/forte/profiles/*',
            '/forte/user/ilyag/' => '/forte/user/*',
            '/source/blob/abc123/src/Thing.php' => '/source/blob/*/*/*',
            '/source/commits/abc123' => '/source/commits/*',
            '/source/current/records/key-retirements/' => '/source/current/records/*',
            '/docs/reference/agent_api.md' => '/docs/*/*',
            '/messages/conversation/T42/' => '/messages/conversation/*',
        ];
        foreach ($cases as $path => $expected) {
            assertSame($expected, UsagePageKind::fromPath($path), $path);
        }
    }

    public function testWordsHaveNoIdentifiersOrDuplicates(): void
    {
        assertSame(count(UsagePageKind::WORDS), count(array_unique(UsagePageKind::WORDS)));
        foreach (UsagePageKind::WORDS as $word) {
            assertSame(1, preg_match('/^[a-z]+(?:-[a-z]+)*$/', $word), $word);
        }
    }
}
