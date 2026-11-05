<?php

declare(strict_types=1);

namespace ForumRewrite\Calendar;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use ForumRewrite\Support\EventStatusDirective;

/**
 * Turns event rows and their derived states into days. Every day from an event's first
 * day to its last day lists it; declined events are left off. A day with a confirmed
 * event is confirmed, a day with only plain events is open, a day with none is empty.
 */
final class CalendarDays
{
    public const EMPTY = 'empty';
    public const OPEN = 'open';
    public const CONFIRMED = 'confirmed';

    /**
     * @param list<array<string, mixed>> $events rows from CalendarQuery
     * @param array<string, string> $states thread id => confirmed|declined
     * @return array<string, array{mark:string, entries:list<array<string, mixed>>}> keyed by YYYY-MM-DD for every day from $from to $to
     */
    public static function derive(array $events, array $states, string $from, string $to): array
    {
        $days = [];
        foreach (self::dates($from, $to) as $date) {
            $days[$date] = ['mark' => self::EMPTY, 'entries' => []];
        }

        foreach ($events as $event) {
            $state = $states[(string) $event['root_post_id']] ?? null;
            if ($state === EventStatusDirective::DECLINED) {
                continue;
            }
            $event['event_state'] = $state;
            $first = max((string) $event['event_date'], $from);
            $last = min((string) ($event['event_end_date'] ?? '') !== '' ? (string) $event['event_end_date'] : (string) $event['event_date'], $to);
            foreach (self::dates($first, $last) as $date) {
                $days[$date]['entries'][] = $event;
                if ($state === EventStatusDirective::CONFIRMED) {
                    $days[$date]['mark'] = self::CONFIRMED;
                } elseif ($days[$date]['mark'] === self::EMPTY) {
                    $days[$date]['mark'] = self::OPEN;
                }
            }
        }

        return $days;
    }

    /** @return list<string> inclusive; empty when $last is before $first */
    public static function dates(string $first, string $last): array
    {
        $utc = new DateTimeZone('UTC');
        $day = new DateTimeImmutable($first, $utc);
        $end = new DateTimeImmutable($last, $utc);
        $dates = [];
        $oneDay = new DateInterval('P1D');
        while ($day <= $end) {
            $dates[] = $day->format('Y-m-d');
            $day = $day->add($oneDay);
        }

        return $dates;
    }
}
