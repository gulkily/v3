<?php

declare(strict_types=1);

namespace ForumRewrite\Calendar;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The built-in reader for date phrases: "tomorrow", "Friday 7pm", "next Thursday at 7",
 * "Nov 14-16", "this weekend", "in 3 days". It reads relative to a given "today", so the
 * caller decides whose day it is. It never guesses: anything it does not recognise
 * comes back unread with a reason, and the LLM fallback or the poster takes over.
 *
 * Rules where English is loose:
 * - A bare weekday is the next one, today included; "this Friday" means the same.
 * - "next Friday" is the Friday of next week (weeks start on Monday).
 * - A month and day with no year is the next time that day comes round.
 * - A bare hour from 1 to 11 ("at 7") is afternoon or evening unless "am" or
 *   "morning" says otherwise; a range like "7-9pm" keeps its start.
 */
final class EventPhraseReader
{
    public const MAX_LENGTH = 120;

    private const MONTHS = [
        'jan' => 1, 'january' => 1, 'feb' => 2, 'february' => 2, 'mar' => 3, 'march' => 3,
        'apr' => 4, 'april' => 4, 'may' => 5, 'jun' => 6, 'june' => 6, 'jul' => 7, 'july' => 7,
        'aug' => 8, 'august' => 8, 'sep' => 9, 'sept' => 9, 'september' => 9, 'oct' => 10, 'october' => 10,
        'nov' => 11, 'november' => 11, 'dec' => 12, 'december' => 12,
    ];

    private const WEEKDAYS = [
        'mon' => 1, 'monday' => 1, 'tue' => 2, 'tues' => 2, 'tuesday' => 2, 'wed' => 3, 'wednesday' => 3,
        'thu' => 4, 'thur' => 4, 'thurs' => 4, 'thursday' => 4, 'fri' => 5, 'friday' => 5,
        'sat' => 6, 'saturday' => 6, 'sun' => 7, 'sunday' => 7,
    ];

    public function read(string $phrase, string $today): EventPhraseReading
    {
        $phrase = trim($phrase);
        if ($phrase === '') {
            return EventPhraseReading::unread('Type when the event happens.');
        }
        if (mb_strlen($phrase) > self::MAX_LENGTH) {
            return EventPhraseReading::unread('Keep it under ' . self::MAX_LENGTH . ' characters.');
        }
        $todayDate = DateTimeImmutable::createFromFormat('!Y-m-d', $today, new DateTimeZone('UTC'));
        if ($todayDate === false || $todayDate->format('Y-m-d') !== $today) {
            return EventPhraseReading::unread('Could not tell what today is.');
        }

        $text = $this->normalize($phrase);
        [$text, $time] = $this->takeTime($text);
        if ($time === false) {
            return EventPhraseReading::unread('Could not read a time from that.');
        }
        $text = $this->dropFillers($text);
        if ($text === '' && $time !== null) {
            return EventPhraseReading::unread('Add a day, such as "Friday" or "Nov 14".');
        }
        $span = $this->readSpan($text, $todayDate);
        if ($span === null) {
            return EventPhraseReading::unread('Could not read a date from that.');
        }

        return EventPhraseReading::read($span[0]->format('Y-m-d'), $span[1]?->format('Y-m-d'), $time, 'built-in');
    }

    /**
     * Finds one start time anywhere in the text and removes it.
     *
     * @return array{string, string|null|false} the rest of the text, and HH:MM, null for none, or false when a time is malformed
     */
    private function takeTime(string $text): array
    {
        $pm = (bool) preg_match('/\b(evening|night|tonight|afternoon)\b/u', $text);
        $am = (bool) preg_match('/\bmorning\b/u', $text);
        if (preg_match('/\b(noon|midday)\b/u', $text) === 1) {
            return [$this->without($text, '/\b(at )?(noon|midday)\b/u'), '12:00'];
        }
        if (preg_match('/\bmidnight\b/u', $text) === 1) {
            return [$this->without($text, '/\b(at )?midnight\b/u'), '00:00'];
        }
        // "7pm", "7:30 pm", "7-9pm", "7 to 9 pm": the start, with am/pm taken from the end if needed.
        $pattern = '/\b(?:at |from |around |about )?(\d{1,2})(?::(\d{2}))?\s*(am|pm)?(?:\s*(?:-|–|to|until|till)\s*\d{1,2}(?::\d{2})?)?\s*(am|pm)(?=\s|$)/u';
        if (preg_match($pattern, $text, $m) === 1) {
            $suffix = ($m[3] ?? '') !== '' ? $m[3] : $m[4];
            $time = $this->clock((int) $m[1], (int) (($m[2] ?? '') !== '' ? $m[2] : 0), str_starts_with($suffix, 'p') ? 'pm' : 'am');

            return [$this->without($text, $pattern), $time ?? false];
        }
        // "19:00", "at 7:30", "at 7": 24-hour with minutes, or a bare hour after "at".
        $pattern = '/\b(?:at |from |around |about )?(\d{1,2}):(\d{2})\b/u';
        if (preg_match($pattern, $text, $m) === 1) {
            $hour = (int) $m[1];
            $half = $hour >= 13 || $hour === 0 ? null : ($am ? 'am' : ($pm || $hour < 12 ? 'pm' : 'am'));
            $time = $half === null ? $this->clock24($hour, (int) $m[2]) : $this->clock($hour, (int) $m[2], $half);

            return [$this->without($text, $pattern), $time ?? false];
        }
        $pattern = '/\b(?:at|around|about) (\d{1,2})(?:\s*(?:-|–|to) ?\d{1,2})?(?=\s|$)/u';
        if (preg_match($pattern, $text, $m) === 1) {
            $hour = (int) $m[1];
            $time = $hour >= 13 ? $this->clock24($hour, 0) : $this->clock($hour, 0, $am ? 'am' : 'pm');

            return [$this->without($text, $pattern), $time ?? false];
        }

        return [$text, null];
    }

    private function clock(int $hour, int $minute, string $half): ?string
    {
        if ($hour < 1 || $hour > 12 || $minute > 59) {
            return null;
        }
        $hour = $hour % 12 + ($half === 'pm' ? 12 : 0);

        return sprintf('%02d:%02d', $hour, $minute);
    }

    private function clock24(int $hour, int $minute): ?string
    {
        return $hour > 23 || $minute > 59 ? null : sprintf('%02d:%02d', $hour, $minute);
    }

    private function without(string $text, string $pattern): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace($pattern, ' ', $text, 1)));
    }

    /** Words that add nothing to the day once the time is out: "starting", "in the evening". */
    private function dropFillers(string $text): string
    {
        $text = (string) preg_replace('/\b(in |this )?(morning|afternoon|evening|night)\b/u', ' ', $text);
        $text = (string) preg_replace('/\b(at|from|starting|around|about|sometime|event)\b/u', ' ', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * A day or a span of days.
     *
     * @return array{DateTimeImmutable, DateTimeImmutable|null}|null
     */
    private function readSpan(string $text, DateTimeImmutable $today): ?array
    {
        if (preg_match('/^(this |next )?weekend$/u', $text, $m) === 1) {
            $saturday = ($m[1] ?? '') === 'next ' ? $this->weekdayNextWeek($today, 6) : $this->weekendSaturday($today);
            $start = $saturday < $today ? $today : $saturday;

            return [$start, $saturday->modify('+1 day')];
        }
        $months = implode('|', array_keys(self::MONTHS));
        // "nov 14-16", "nov 14 to 16": one month, two day numbers.
        if (preg_match('/^(' . $months . ') (\d{1,2})(?:st|nd|rd|th)? ?(?:-|–|to|through|thru|until|till) ?(\d{1,2})(?:st|nd|rd|th)?(?: (\d{4}))?$/u', $text, $m) === 1) {
            $start = $this->monthDay(self::MONTHS[$m[1]], (int) $m[2], $m[4] ?? '', $today);
            $end = $start === null ? null : $this->dateFrom((int) $start->format('Y'), self::MONTHS[$m[1]], (int) $m[3]);
            if ($start !== null && $end !== null && $end < $start) {
                $end = $this->dateFrom((int) $start->format('Y'), self::MONTHS[$m[1]] + 1 > 12 ? 1 : self::MONTHS[$m[1]] + 1, (int) $m[3]);
                $end = $end !== null && self::MONTHS[$m[1]] === 12 ? $end->modify('+1 year') : $end;
            }

            return $start !== null && $end !== null ? [$start, $end] : null;
        }
        $parts = preg_split('/ ?(?:\s-\s|–|\bto\b|\bthrough\b|\bthru\b|\buntil\b|\btill\b) ?/u', $text);
        if ($parts !== false && count($parts) === 2) {
            $start = $this->readDay(trim($parts[0]), $today);
            $endText = trim($parts[1]);
            if ($start === null) {
                return null;
            }
            $end = isset(self::WEEKDAYS[$endText])
                ? $this->weekdayOnOrAfter($start, self::WEEKDAYS[$endText])
                : $this->readDay($endText, $start);

            return $end !== null && $end >= $start ? [$start, $end] : null;
        }
        $day = $this->readDay($text, $today);

        return $day === null ? null : [$day, null];
    }

    private function weekendSaturday(DateTimeImmutable $today): DateTimeImmutable
    {
        return (int) $today->format('N') === 7 ? $today->modify('-1 day') : $this->weekdayOnOrAfter($today, 6);
    }

    /** The given weekday in the week after this one (weeks start on Monday). */
    private function weekdayNextWeek(DateTimeImmutable $today, int $weekday): DateTimeImmutable
    {
        $nextMonday = $today->modify('+' . (8 - (int) $today->format('N')) . ' days');

        return $nextMonday->modify('+' . ($weekday - 1) . ' days');
    }

    private function normalize(string $phrase): string
    {
        $text = mb_strtolower($phrase);
        $text = (string) preg_replace('/\b([ap])\.m\.?/u', '$1m', $text);
        $text = (string) preg_replace('/[,.!?]+(?=\s|$)/u', ' ', $text);
        $text = (string) preg_replace('/\b(on|the)\b/u', ' ', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** One day: today, tomorrow, a weekday, "in 3 days" or a calendar date. */
    private function readDay(string $text, DateTimeImmutable $today): ?DateTimeImmutable
    {
        if ($text === 'today' || $text === 'tonight') {
            return $today;
        }
        if ($text === 'tomorrow' || $text === 'tmrw') {
            return $today->modify('+1 day');
        }
        if (preg_match('/^(this |next |coming |this coming )?([a-z]+)$/u', $text, $m) === 1 && isset(self::WEEKDAYS[$m[2]])) {
            return trim($m[1] ?? '') === 'next'
                ? $this->weekdayNextWeek($today, self::WEEKDAYS[$m[2]])
                : $this->weekdayOnOrAfter($today, self::WEEKDAYS[$m[2]]);
        }
        if (preg_match('/^in (\d{1,3}|a|one|two|three) (day|days|week|weeks)$/u', $text, $m) === 1) {
            $count = ['a' => 1, 'one' => 1, 'two' => 2, 'three' => 3][$m[1]] ?? (int) $m[1];

            return $today->modify('+' . ($count * (str_starts_with($m[2], 'week') ? 7 : 1)) . ' days');
        }

        // "Saturday Nov 14": the date decides, and the weekday has to agree with it.
        if (preg_match('/^([a-z]+) (.+)$/u', $text, $m) === 1 && isset(self::WEEKDAYS[$m[1]])) {
            $date = $this->calendarDate($m[2], $today);

            return $date !== null && (int) $date->format('N') === self::WEEKDAYS[$m[1]] ? $date : null;
        }

        return $this->calendarDate($text, $today);
    }

    private function weekdayOnOrAfter(DateTimeImmutable $day, int $weekday): DateTimeImmutable
    {
        $ahead = ($weekday - (int) $day->format('N') + 7) % 7;

        return $day->modify('+' . $ahead . ' days');
    }

    /** "2026-11-14", "11/14", "nov 14", "november 14th 2027", "14 nov". */
    private function calendarDate(string $text, DateTimeImmutable $today): ?DateTimeImmutable
    {
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $text, $m) === 1) {
            return $this->dateFrom((int) $m[1], (int) $m[2], (int) $m[3]);
        }
        if (preg_match('/^(\d{1,2})\/(\d{1,2})(?:\/(\d{2}|\d{4}))?$/', $text, $m) === 1) {
            return $this->monthDay((int) $m[1], (int) $m[2], $m[3] ?? '', $today);
        }
        $months = implode('|', array_keys(self::MONTHS));
        if (preg_match('/^(' . $months . ') (\d{1,2})(?:st|nd|rd|th)?(?: (\d{4}))?$/', $text, $m) === 1) {
            return $this->monthDay(self::MONTHS[$m[1]], (int) $m[2], $m[3] ?? '', $today);
        }
        if (preg_match('/^(\d{1,2})(?:st|nd|rd|th)?(?: of)? (' . $months . ')(?: (\d{4}))?$/', $text, $m) === 1) {
            return $this->monthDay(self::MONTHS[$m[2]], (int) $m[1], $m[3] ?? '', $today);
        }

        return null;
    }

    /** A month and day with no year means the next time that day comes round, today included. */
    private function monthDay(int $month, int $day, string $year, DateTimeImmutable $today): ?DateTimeImmutable
    {
        if ($year !== '') {
            return $this->dateFrom(strlen($year) === 2 ? 2000 + (int) $year : (int) $year, $month, $day);
        }
        $thisYear = (int) $today->format('Y');
        $date = $this->dateFrom($thisYear, $month, $day);
        if ($date !== null && $date < $today) {
            $date = $this->dateFrom($thisYear + 1, $month, $day);
        }
        if ($date === null && $month === 2 && $day === 29) {
            for ($y = $thisYear + 1; $y <= $thisYear + 4 && $date === null; $y++) {
                $date = $this->dateFrom($y, 2, 29);
            }
        }

        return $date;
    }

    private function dateFrom(int $year, int $month, int $day): ?DateTimeImmutable
    {
        if (!checkdate($month, $day, $year)) {
            return null;
        }

        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day), new DateTimeZone('UTC'));
    }
}
