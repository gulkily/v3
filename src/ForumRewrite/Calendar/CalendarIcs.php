<?php

declare(strict_types=1);

namespace ForumRewrite\Calendar;

use ForumRewrite\Support\EventStatusDirective;
use ForumRewrite\Support\PostText;
use ForumRewrite\Support\ThreadTitle;

/**
 * Writes calendar events as an iCalendar (RFC 5545) document, for the calendar
 * feeds and the one-event file. Each event's UID is its thread ID, so a subscriber's
 * copy updates in place when the thread changes.
 */
final class CalendarIcs
{
    private const LINE_OCTETS = 75;
    private const TIMED_EVENT_MINUTES = 60;

    /**
     * @param list<array<string, mixed>> $events rows shaped like CalendarQuery::eventsBetween
     * @param array<string, ?string> $states thread ID => EventStatus state (null while open)
     */
    public static function document(
        array $events,
        array $states,
        string $siteUrl,
        string $name,
        ?\DateTimeImmutable $now = null,
        ?\DateTimeZone $timeZone = null,
    ): string {
        $siteUrl = rtrim($siteUrl, '/');
        $host = parse_url($siteUrl, PHP_URL_HOST);
        $host = is_string($host) && $host !== '' ? $host : 'localhost';
        $stamp = ($now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//v3//Calendar feed//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . self::text($name),
            'X-PUBLISHED-TTL:PT1H',
        ];
        foreach ($events as $event) {
            $threadId = (string) ($event['root_post_id'] ?? '');
            if ($threadId === '' || !isset($event['event_date'])) {
                continue;
            }
            array_push($lines, ...self::event($event, $states[$threadId] ?? null, $siteUrl, $host, $stamp, $timeZone));
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines)) . "\r\n";
    }

    /**
     * @param array<string, mixed> $event
     * @return list<string>
     */
    private static function event(array $event, ?string $state, string $siteUrl, string $host, string $stamp, ?\DateTimeZone $timeZone): array
    {
        $threadId = (string) $event['root_post_id'];
        $threadUrl = $siteUrl . '/threads/' . rawurlencode($threadId);
        $first = self::date((string) $event['event_date']);
        $last = self::date((string) ($event['event_end_date'] ?? '')) ?? $first;
        if ($first === null) {
            return [];
        }
        if ($last < $first) {
            $last = $first;
        }

        $lines = ['BEGIN:VEVENT', 'UID:' . self::text($threadId . '@' . $host), 'DTSTAMP:' . $stamp];
        $time = self::time((string) ($event['event_time'] ?? ''));
        $endTime = self::time((string) ($event['event_end_time'] ?? ''));
        if ($time === null) {
            $lines[] = 'DTSTART;VALUE=DATE:' . $first->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $last->modify('+1 day')->format('Ymd');
        } else {
            [$start, $end] = self::timedDates($first, $last, $time, $endTime, $timeZone);
            $lines[] = 'DTSTART:' . $start;
            $lines[] = 'DTEND:' . $end;
        }

        $lines[] = 'SUMMARY:' . self::text(self::title($event));
        $location = trim((string) ($event['event_location'] ?? ''));
        if ($location !== '') {
            $lines[] = 'LOCATION:' . self::text($location);
        }
        $lines[] = 'STATUS:' . ($state === EventStatusDirective::CONFIRMED ? 'CONFIRMED' : 'TENTATIVE');
        $lines[] = 'URL:' . self::text($threadUrl);

        $description = [];
        $when = trim((string) ($event['event_when'] ?? ''));
        if ($when !== '') {
            $description[] = $when;
        }
        $link = trim((string) ($event['event_link'] ?? ''));
        if ($link !== '') {
            $description[] = $link;
        }
        $description[] = $threadUrl;
        $lines[] = 'DESCRIPTION:' . self::text(implode("\n", $description));
        $lines[] = 'END:VEVENT';

        return $lines;
    }

    /**
     * Google Calendar's "new event" form for one event, filled in.
     *
     * @param array<string, mixed> $event
     */
    public static function googleEventUrl(array $event, ?\DateTimeZone $timeZone = null): ?string
    {
        $first = self::date((string) ($event['event_date'] ?? ''));
        if ($first === null) {
            return null;
        }
        $last = self::date((string) ($event['event_end_date'] ?? '')) ?? $first;
        if ($last < $first) {
            $last = $first;
        }
        $time = self::time((string) ($event['event_time'] ?? ''));
        $endTime = self::time((string) ($event['event_end_time'] ?? ''));
        if ($time === null) {
            $dates = $first->format('Ymd') . '/' . $last->modify('+1 day')->format('Ymd');
        } else {
            $dates = implode('/', self::timedDates($first, $last, $time, $endTime, $timeZone));
        }
        $details = array_values(array_filter([
            trim((string) ($event['event_when'] ?? '')),
            trim((string) ($event['event_link'] ?? '')),
        ], static fn (string $line): bool => $line !== ''));
        $query = ['action' => 'TEMPLATE', 'text' => self::title($event), 'dates' => $dates];
        if ($details !== []) {
            $query['details'] = implode("\n", $details);
        }
        $location = trim((string) ($event['event_location'] ?? ''));
        if ($location !== '') {
            $query['location'] = $location;
        }

        return 'https://calendar.google.com/calendar/render?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * The event's name: the thread's display title, the same one the calendar grid
     * shows, so a thread with no subject is named from its text.
     *
     * @param array<string, mixed> $event
     */
    public static function title(array $event): string
    {
        return ThreadTitle::displayTitle(
            (string) ($event['subject'] ?? ''),
            PostText::plainPreview((string) ($event['body_preview'] ?? ''), $event['body_format'] ?? null),
            (string) ($event['root_post_id'] ?? ''),
            80,
        );
    }

    /**
     * @param array{int, int} $time
     * @param array{int, int}|null $endTime
     * @return array{string, string}
     */
    private static function timedDates(
        \DateTimeImmutable $first,
        \DateTimeImmutable $last,
        array $time,
        ?array $endTime,
        ?\DateTimeZone $timeZone,
    ): array {
        [$hour, $minute] = $time;
        if ($timeZone !== null) {
            // Reinterpret the stored date in the event zone before setting the local clock time.
            $first = new \DateTimeImmutable($first->format('Y-m-d'), $timeZone);
            $last = new \DateTimeImmutable($last->format('Y-m-d'), $timeZone);
        }
        $utc = new \DateTimeZone('UTC');
        $start = $first->setTime($hour, $minute)->setTimezone($utc);
        if ($endTime === null) {
            $end = $last->setTime($hour, $minute)->setTimezone($utc)
                ->modify('+' . self::TIMED_EVENT_MINUTES . ' minutes');
        } else {
            if ($first == $last && $endTime <= $time) {
                $last = $last->modify('+1 day');
            }
            $end = $last->setTime(...$endTime)->setTimezone($utc);
        }
        $format = $timeZone === null ? 'Ymd\THis' : 'Ymd\THis\Z';

        return [$start->format($format), $end->format($format)];
    }

    private static function date(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }

    /** @return array{int, int}|null */
    private static function time(string $value): ?array
    {
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value, $match) !== 1) {
            return null;
        }

        return [(int) $match[1], (int) $match[2]];
    }

    /** Escapes a TEXT value (RFC 5545 section 3.3.11). */
    private static function text(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        return strtr($value, ['\\' => '\\\\', ';' => '\\;', ',' => '\\,', "\n" => '\\n']);
    }

    /** Folds a content line at 75 octets without splitting a UTF-8 character. */
    private static function fold(string $line): string
    {
        if (strlen($line) <= self::LINE_OCTETS) {
            return $line;
        }
        $parts = [];
        $current = '';
        $limit = self::LINE_OCTETS;
        foreach (mb_str_split($line, 1, 'UTF-8') as $character) {
            if (strlen($current) + strlen($character) > $limit) {
                $parts[] = $current;
                $current = '';
                $limit = self::LINE_OCTETS - 1;
            }
            $current .= $character;
        }
        $parts[] = $current;

        return implode("\r\n ", $parts);
    }
}
