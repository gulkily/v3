<?php

declare(strict_types=1);

namespace ForumRewrite\Calendar;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The built-in reader for date phrases ("tomorrow", "Friday", "Nov 14"). It reads
 * relative to a given "today", so the caller decides whose day it is. It never
 * guesses: anything it does not recognise comes back unread with a reason.
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
        $date = $this->readDay($text, $todayDate);
        if ($date === null) {
            return EventPhraseReading::unread('Could not read a date from that.');
        }

        return EventPhraseReading::read($date->format('Y-m-d'), null, null, 'built-in');
    }

    private function normalize(string $phrase): string
    {
        $text = mb_strtolower($phrase);
        $text = (string) preg_replace('/[,.!?]+(?=\s|$)/u', ' ', $text);
        $text = (string) preg_replace('/\b(on|the)\b/u', ' ', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** One day: today, tomorrow, a weekday or a calendar date. */
    private function readDay(string $text, DateTimeImmutable $today): ?DateTimeImmutable
    {
        if ($text === 'today' || $text === 'tonight') {
            return $today;
        }
        if ($text === 'tomorrow' || $text === 'tmrw') {
            return $today->modify('+1 day');
        }
        if (isset(self::WEEKDAYS[$text])) {
            return $this->weekdayOnOrAfter($today, self::WEEKDAYS[$text]);
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
