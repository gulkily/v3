<?php

declare(strict_types=1);

use ForumRewrite\Calendar\EventPhraseFill;
use ForumRewrite\Calendar\EventPhraseReader;

require __DIR__ . '/../autoload.php';

/**
 * The built-in date-phrase reader. Today is fixed in every case so the expectations
 * never drift: 2026-11-10 is a Tuesday.
 */
final class EventPhraseReaderTest
{
    private const TODAY = '2026-11-10';

    public function testDayWords(): void
    {
        $this->assertReads('today', '2026-11-10');
        $this->assertReads('Tonight', '2026-11-10');
        $this->assertReads('tomorrow', '2026-11-11');
    }

    public function testWeekdaysCountTodayAndLookAhead(): void
    {
        $this->assertReads('Tuesday', '2026-11-10');
        $this->assertReads('wed', '2026-11-11');
        $this->assertReads('on Friday', '2026-11-13');
        $this->assertReads('Monday', '2026-11-16');
    }

    public function testCalendarDates(): void
    {
        $this->assertReads('Nov 14', '2026-11-14');
        $this->assertReads('November 14th', '2026-11-14');
        $this->assertReads('14 Nov', '2026-11-14');
        $this->assertReads('the 14th of November', '2026-11-14');
        $this->assertReads('2027-01-05', '2027-01-05');
        $this->assertReads('11/14', '2026-11-14');
        $this->assertReads('1/5/27', '2027-01-05');
        $this->assertReads('Nov 14 2027', '2027-11-14');
    }

    public function testDatesWithoutAYearMeanTheNextOne(): void
    {
        $this->assertReads('Jan 3', '2027-01-03');
        $this->assertReads('Nov 9', '2027-11-09');
        $this->assertReads('Feb 29', '2028-02-29');
    }

    public function testUnreadablePhrasesSayWhy(): void
    {
        $this->assertUnread('', 'Type when the event happens.');
        $this->assertUnread('sometime soon', 'Could not read a date from that.');
        $this->assertUnread('Feb 30', 'Could not read a date from that.');
        $this->assertUnread(str_repeat('x', 121), 'Keep it under 120 characters.');
    }

    public function testFillLeavesAFixedDateAlone(): void
    {
        $fill = new EventPhraseFill();
        $fixed = $fill->fill('Friday', self::TODAY, ['event_date' => '2026-11-20'], true);
        eventPhraseAssertSame('2026-11-20', $fixed['fields']['event_date']);
        eventPhraseAssertSame('The date stays 2026-11-20 on this page.', $fixed['status']);

        $free = $fill->fill('Friday', self::TODAY, ['event_date' => '2026-11-20'], false);
        eventPhraseAssertSame('2026-11-13', $free['fields']['event_date']);
        eventPhraseAssertSame('', $free['status']);

        $unread = $fill->fill('whenever', self::TODAY, ['event_date' => '2026-11-20', 'event_time' => '18:00'], false);
        eventPhraseAssertSame(false, $unread['filled']);
        eventPhraseAssertSame(['event_date' => '2026-11-20', 'event_end_date' => '', 'event_time' => '18:00'], $unread['fields']);
    }

    private function assertReads(string $phrase, string $date, ?string $endDate = null, ?string $time = null, string $today = self::TODAY): void
    {
        $reading = (new EventPhraseReader())->read($phrase, $today);
        eventPhraseAssertSame(
            ['date' => $date, 'end' => $endDate, 'time' => $time, 'reason' => null],
            ['date' => $reading->date, 'end' => $reading->endDate, 'time' => $reading->time, 'reason' => $reading->reason],
            $phrase
        );
    }

    private function assertUnread(string $phrase, string $reason): void
    {
        $reading = (new EventPhraseReader())->read($phrase, self::TODAY);
        eventPhraseAssertSame(['date' => null, 'reason' => $reason], ['date' => $reading->date, 'reason' => $reading->reason], $phrase);
    }
}

function eventPhraseAssertSame(mixed $expected, mixed $actual, string $context = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(($context !== '' ? '"' . $context . '": ' : '') . 'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}
