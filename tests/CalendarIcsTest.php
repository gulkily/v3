<?php

declare(strict_types=1);

use ForumRewrite\Calendar\CalendarIcs;
use ForumRewrite\Support\EventStatusDirective;

require __DIR__ . '/../autoload.php';

/** The iCal writer behind the calendar feeds and the one-event file. */
final class CalendarIcsTest
{
    public function testOneDayEventIsAllDayWithAnExclusiveEnd(): void
    {
        $ics = $this->write([$this->event()]);
        calendarIcsAssertContains("DTSTART;VALUE=DATE:20261120\r\nDTEND;VALUE=DATE:20261121\r\n", $ics);
        calendarIcsAssertContains("UID:t1@zenmemes.com\r\n", $ics);
        calendarIcsAssertContains("DTSTAMP:20261117T220000Z\r\n", $ics);
        calendarIcsAssertContains("URL:https://zenmemes.com/threads/t1\r\n", $ics);
    }

    public function testMultiDayEventEndsTheDayAfterItsLastDay(): void
    {
        $ics = $this->write([$this->event(['event_end_date' => '2026-11-29', 'event_date' => '2026-11-27'])]);
        calendarIcsAssertContains("DTSTART;VALUE=DATE:20261127\r\nDTEND;VALUE=DATE:20261130\r\n", $ics);
    }

    public function testTimedEventIsFloatingAndLastsAnHour(): void
    {
        $ics = $this->write([$this->event(['event_time' => '19:00'])]);
        calendarIcsAssertContains("DTSTART:20261120T190000\r\nDTEND:20261120T200000\r\n", $ics);
        calendarIcsAssertNotContains('TZID', $ics);
        calendarIcsAssertNotContains('T190000Z', $ics);
    }

    public function testTimedMultiDayEventEndsOnItsLastDay(): void
    {
        $ics = $this->write([$this->event(['event_time' => '23:30', 'event_end_date' => '2026-11-21'])]);
        calendarIcsAssertContains("DTSTART:20261120T233000\r\nDTEND:20261122T003000\r\n", $ics);
    }

    public function testStatusFollowsTheEventState(): void
    {
        $open = $this->write([$this->event()]);
        calendarIcsAssertContains("STATUS:TENTATIVE\r\n", $open);
        $confirmed = $this->write([$this->event()], ['t1' => EventStatusDirective::CONFIRMED]);
        calendarIcsAssertContains("STATUS:CONFIRMED\r\n", $confirmed);
    }

    public function testTextIsEscapedAndDescriptionCarriesPhraseLinkAndThread(): void
    {
        $ics = $this->write([$this->event([
            'subject' => 'Couch; chairs, and a \\ slash',
            'event_location' => "392 Harvard St, Cambridge",
            'event_when' => 'next Friday at 7',
            'event_link' => 'https://example.com/rsvp',
        ])]);
        calendarIcsAssertContains("SUMMARY:Couch\\; chairs\\, and a \\\\ slash\r\n", $ics);
        calendarIcsAssertContains("LOCATION:392 Harvard St\\, Cambridge\r\n", $ics);
        calendarIcsAssertContains('DESCRIPTION:next Friday at 7\\nhttps://example.com/rsvp\\nhttps://zenmemes.com/threads/t1', $this->unfold($ics));
    }

    public function testLongLinesFoldAt75OctetsWithoutSplittingCharacters(): void
    {
        $subject = str_repeat('Пикник у реки ', 12);
        $ics = $this->write([$this->event(['subject' => $subject])]);
        foreach (explode("\r\n", $ics) as $line) {
            if (strlen($line) > 75) {
                throw new RuntimeException('Line longer than 75 octets: ' . $line);
            }
            calendarIcsAssertSame(true, mb_check_encoding($line, 'UTF-8'));
        }
        calendarIcsAssertContains('SUMMARY:' . trim($subject), $this->unfold($ics));
    }

    public function testDocumentWrapsEventsAndSkipsRowsWithoutADate(): void
    {
        $ics = $this->write([$this->event(), $this->event(['root_post_id' => 't2', 'event_date' => 'not-a-date'])], [], 'ZenMemes');
        calendarIcsAssertSame(0, strpos($ics, "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n"));
        calendarIcsAssertContains("X-WR-CALNAME:ZenMemes\r\n", $ics);
        calendarIcsAssertSame(1, substr_count($ics, 'BEGIN:VEVENT'));
        calendarIcsAssertSame(true, str_ends_with($ics, "END:VCALENDAR\r\n"));
    }

    /**
     * @param list<array<string, mixed>> $events
     * @param array<string, ?string> $states
     */
    private function write(array $events, array $states = [], string $name = 'Calendar'): string
    {
        return CalendarIcs::document($events, $states, 'https://zenmemes.com/', $name, new DateTimeImmutable('2026-11-17T22:00:00Z'));
    }

    /** @param array<string, mixed> $overrides */
    private function event(array $overrides = []): array
    {
        return $overrides + [
            'root_post_id' => 't1',
            'subject' => 'Couch night',
            'event_date' => '2026-11-20',
            'event_end_date' => null,
            'event_time' => null,
            'event_location' => null,
            'event_link' => null,
            'event_when' => null,
        ];
    }

    private function unfold(string $ics): string
    {
        return str_replace("\r\n ", '', $ics);
    }
}

function calendarIcsAssertSame(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function calendarIcsAssertContains(string $needle, string $haystack): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException('Expected to find ' . var_export($needle, true) . ' in ' . substr($haystack, 0, 800));
    }
}

function calendarIcsAssertNotContains(string $needle, string $haystack): void
{
    if (str_contains($haystack, $needle)) {
        throw new RuntimeException('Did not expect ' . var_export($needle, true));
    }
}
