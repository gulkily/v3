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
        $this->assertReads('Sat Nov 14 at 8pm', '2026-11-14', null, '20:00');
        $this->assertReads('Saturday, November 14', '2026-11-14');
    }

    public function testDatesWithoutAYearMeanTheNextOne(): void
    {
        $this->assertReads('Jan 3', '2027-01-03');
        $this->assertReads('Nov 9', '2027-11-09');
        $this->assertReads('Feb 29', '2028-02-29');
    }

    public function testThisAndNextWeekdays(): void
    {
        $this->assertReads('this Friday', '2026-11-13');
        $this->assertReads('next Friday', '2026-11-20');
        $this->assertReads('next Monday', '2026-11-16');
        $this->assertReads('next Tuesday', '2026-11-17');
        $this->assertReads('next Sunday', '2026-11-22', null, null, '2026-11-15');
    }

    public function testRelativeDays(): void
    {
        $this->assertReads('in 3 days', '2026-11-13');
        $this->assertReads('in a week', '2026-11-17');
        $this->assertReads('in two weeks', '2026-11-24');
    }

    public function testTimes(): void
    {
        $this->assertReads('Friday 7pm', '2026-11-13', null, '19:00');
        $this->assertReads('next Thursday at 7', '2026-11-19', null, '19:00');
        $this->assertReads('tomorrow at 9 in the morning', '2026-11-11', null, '09:00');
        $this->assertReads('tomorrow morning at 9:30', '2026-11-11', null, '09:30');
        $this->assertReads('Nov 14 at 19:00', '2026-11-14', null, '19:00');
        $this->assertReads('Nov 14, 7:30 p.m.', '2026-11-14', null, '19:30');
        $this->assertReads('Saturday 7-9pm', '2026-11-14', null, '19:00');
        $this->assertReads('12pm Friday', '2026-11-13', null, '12:00');
        $this->assertReads('12am Friday', '2026-11-13', null, '00:00');
        $this->assertReads('Friday at noon', '2026-11-13', null, '12:00');
        $this->assertReads('tonight at 8', '2026-11-10', null, '20:00');
        $this->assertReads('Friday evening', '2026-11-13');
    }

    public function testSpans(): void
    {
        $this->assertReads('Nov 14-16', '2026-11-14', '2026-11-16');
        $this->assertReads('November 14 to 16', '2026-11-14', '2026-11-16');
        $this->assertReads('Nov 28 - Dec 2', '2026-11-28', '2026-12-02');
        $this->assertReads('Dec 30 - Jan 2', '2026-12-30', '2027-01-02');
        $this->assertReads('Dec 30-2', '2026-12-30', '2027-01-02');
        $this->assertReads('Friday to Sunday', '2026-11-13', '2026-11-15');
        $this->assertReads('Friday through Monday 6pm', '2026-11-13', '2026-11-16', '18:00');
        $this->assertReads('this weekend', '2026-11-14', '2026-11-15');
        $this->assertReads('next weekend', '2026-11-21', '2026-11-22');
        $this->assertReads('this weekend', '2026-11-15', null, null, '2026-11-15');
    }

    public function testMonthAndYearEdges(): void
    {
        $this->assertReads('tomorrow', '2027-01-01', null, null, '2026-12-31');
        $this->assertReads('next Monday', '2027-01-04', null, null, '2026-12-31');
        $this->assertReads('Friday', '2026-03-06', null, null, '2026-02-28');
    }

    public function testUnreadablePhrasesSayWhy(): void
    {
        $this->assertUnread('', 'Type when the event happens.');
        $this->assertUnread('sometime soon', 'Could not read a date from that.');
        $this->assertUnread('Feb 30', 'Could not read a date from that.');
        $this->assertUnread(str_repeat('x', 121), 'Keep it under 120 characters.');
        $this->assertUnread('at 7pm', 'Add a day, such as "Friday" or "Nov 14".');
        $this->assertUnread('Friday at 25:00', 'Could not read a time from that.');
        $this->assertUnread('Friday to Wednesday last week', 'Could not read a date from that.');
        $this->assertUnread('next week', 'Could not read a date from that.');
        $this->assertUnread('Friday Nov 14', 'Could not read a date from that.');
        $this->assertUnread('the week of the 20th', 'Could not read a date from that.');
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
