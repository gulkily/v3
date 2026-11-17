<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\UsageReports\UsageJourneys;
use ForumRewrite\UsageReports\UsageLogFormat;

final class UsageJourneysTest
{
    /** Read a thread, open the account menu, go to the profile, then to Keys: three clicks, then a stop. */
    private const THREE_CLICKS = "usage-log v1\n"
        . "view\t0\t/threads/*\n"
        . "click\t40\t/threads/*\tmenu\ttab:account\t-\n"
        . "click\t2\t/threads/*\tmenu\tProfile\t/profiles/*\n"
        . "view\t1\t/profiles/*\n"
        . "click\t3\t/profiles/*\tmain\tKeys\t/tools/user-access/profiles/*\n"
        . "view\t1\t/tools/user-access/profiles/*\n"
        . "click\t60\t/tools/user-access/profiles/*\ttools-bar\ttool:flags\t/tools/feature-flags\n"
        . "view\t1\t/tools/feature-flags\n";

    public function testSplitsJourneysAtStopsAndFollowsDestinations(): void
    {
        $journeys = UsageJourneys::fromEntries(UsageLogFormat::entries(self::THREE_CLICKS));

        assertSame([
            ['start' => '/threads/*', 'end' => '/tools/user-access/profiles/*', 'clicks' => 3, 'path' => ['tab:account', 'Profile', 'Keys']],
            ['start' => '/tools/user-access/profiles/*', 'end' => '/tools/feature-flags', 'clicks' => 1, 'path' => ['tool:flags']],
        ], $journeys);
    }

    public function testAMultiClickJourneyNeverDoneInOneClickIsAShortcutCandidate(): void
    {
        $groups = UsageJourneys::groups(UsageJourneys::fromReports([
            UsageLogFormat::entries(self::THREE_CLICKS),
            UsageLogFormat::entries(self::THREE_CLICKS),
        ]));

        assertSame(1, count($groups), 'one-click journeys alone are not listed');
        assertSame('/threads/*', $groups[0]['start']);
        assertSame('/tools/user-access/profiles/*', $groups[0]['end']);
        assertSame(2, $groups[0]['times']);
        assertSame(3, $groups[0]['fewest']);
        assertSame(['tab:account', 'Profile', 'Keys'], $groups[0]['usual']);
        assertTrue($groups[0]['shortcut']);
    }

    public function testThePairIsNotACandidateOnceItWasReachedInOneClick(): void
    {
        $direct = "usage-log v1\nview\t0\t/threads/*\nclick\t40\t/threads/*\tpost\tKeys link\t/tools/user-access/profiles/*\nview\t1\t/tools/user-access/profiles/*\n";
        $groups = UsageJourneys::groups(UsageJourneys::fromReports([
            UsageLogFormat::entries(self::THREE_CLICKS),
            UsageLogFormat::entries($direct),
        ]));

        assertSame(1, count($groups));
        assertSame(1, $groups[0]['fewest']);
        assertSame(['tab:account', 'Profile', 'Keys'], $groups[0]['usual'], 'the usual path shown is still the long one');
        assertFalse($groups[0]['shortcut']);
    }

    public function testMenusAndSubmitsEndJourneysAndLoopsAreSkipped(): void
    {
        $log = "usage-log v1\n"
            . "view\t0\t/\n"
            . "click\t30\t/\tnavbar\ttab:tools\t/tools\n"
            . "view\t1\t/tools\n"
            . "click\t2\t/tools\tmenu\ttab:account\t-\n"
            . "click\t25\t/tools\tmenu\ttab:account\t-\n"
            . "click\t1\t/tools\tnavbar\ttab:board\t/\n"
            . "view\t1\t/\n"
            . "click\t2\t/\tmain\tNew thread\t/compose/thread\n"
            . "view\t1\t/compose/thread\n"
            . "submit\t9\t/compose/thread\tcomposer\tPost\n";
        $journeys = UsageJourneys::fromEntries(UsageLogFormat::entries($log));

        assertSame('/tools › tab:account', $journeys[0]['end'], 'a stop on an open menu ends there');
        assertSame(2, $journeys[0]['clicks']);
        assertSame('/compose/thread › Post', $journeys[1]['end'], 'a submit ends a journey');
        assertSame(['tab:account', 'tab:board', 'New thread', 'submit Post'], $journeys[1]['path']);
        assertSame(2, count(UsageJourneys::groups($journeys)));
    }

    public function testCountsClicksPerPageRegionAndControl(): void
    {
        $counts = UsageJourneys::clickCounts([UsageLogFormat::entries(self::THREE_CLICKS), UsageLogFormat::entries(self::THREE_CLICKS)]);

        assertSame(4, count($counts));
        assertSame(['page' => '/threads/*', 'region' => 'menu', 'name' => 'tab:account', 'clicks' => 2], $counts[0]);
    }
}
