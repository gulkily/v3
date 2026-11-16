<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Support\GoalDirective;
use ForumRewrite\Support\PledgeAmount;
use ForumRewrite\Support\PledgeDirective;

final class PledgeCampaignTest
{
    public function testAmountsAreWholeOrTwoPlaceDecimalsAboveZero(): void
    {
        foreach ([
            '300' => 30000, '1.5' => 150, '12.50' => 1250, '0.25' => 25, '1,000' => 100000,
            '12,500,000.5' => 1250000050, ' 7 ' => 700, '007' => 700,
        ] as $text => $hundredths) {
            pledgeAssertSame($hundredths, PledgeAmount::parse((string) $text));
        }
        foreach (['', '0', '0.00', '-5', '+5', '1,5', '1,00', '1.234', '1.', '.5', '1e3', '1/2', 'a few', '300 coffees', '1 000', '1234567890123'] as $text) {
            pledgeAssertSame(null, PledgeAmount::parse($text));
        }
        pledgeAssertSame('300', PledgeAmount::format(30000));
        pledgeAssertSame('312.5', PledgeAmount::format(31250));
        pledgeAssertSame('12.05', PledgeAmount::format(1205));
        pledgeAssertSame('0.25', PledgeAmount::format(25));
    }

    public function testGoalLineIsANumberThenAUnit(): void
    {
        pledgeAssertSame(['amount' => 100000, 'written' => '1000', 'unit' => 'coffees'], GoalDirective::extract("We need coffee.\ngoal: 1000 coffees\nThanks"));
        pledgeAssertSame(['amount' => 100000, 'written' => '1,000', 'unit' => 'hours of help'], GoalDirective::extract('  GOAL:  1,000   hours of help  '));
        pledgeAssertSame(['amount' => 4000, 'written' => '40', 'unit' => ''], GoalDirective::extract('goal: 40'));
        // The first line that reads as a goal wins; quoted lines and prose are skipped.
        pledgeAssertSame('coffees', GoalDirective::extract("> goal: 5 teas\ngoal: to have fun\ngoal: 10 coffees\ngoal: 20 teas")['unit']);
        pledgeAssertSame(null, GoalDirective::extract('> goal: 1000 coffees'));
        pledgeAssertSame(null, GoalDirective::extract('our goal: 1000 coffees'));
        pledgeAssertSame(null, GoalDirective::extract('goal: lots of coffees'));
        pledgeAssertSame(null, GoalDirective::extract('goal:'));
        pledgeAssertSame(null, GoalDirective::extract('goal: 5 ' . str_repeat('x', GoalDirective::MAX_UNIT_LENGTH + 1)));
    }

    public function testPledgeLineIsReadAsWritten(): void
    {
        pledgeAssertSame('300', PledgeDirective::extract("pledge: 300\n\nI'll order these myself."));
        pledgeAssertSame('12.50 coffees', PledgeDirective::extract("Count me in.\n  Pledge:  12.50 coffees  "));
        pledgeAssertSame('a few', PledgeDirective::extract('pledge: a few'));
        pledgeAssertSame('20', PledgeDirective::extract("> pledge: 10\npledge: 20\npledge: 30"));
        pledgeAssertSame(null, PledgeDirective::extract('> pledge: 10'));
        pledgeAssertSame(null, PledgeDirective::extract('I pledge: 10'));
        pledgeAssertSame(null, PledgeDirective::extract('pledge:'));
        pledgeAssertSame('pledge: 300', PledgeDirective::line(' 300 '));
    }
}

function pledgeAssertSame(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Failed asserting identical values. Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function pledgeAssertContains(string $needle, string $haystack): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException('Failed asserting that output contains: ' . $needle);
    }
}

function pledgeAssertNotContains(string $needle, string $haystack): void
{
    if (str_contains($haystack, $needle)) {
        throw new RuntimeException('Failed asserting that output does not contain: ' . $needle);
    }
}
