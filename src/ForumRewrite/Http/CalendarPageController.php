<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use DateTimeImmutable;
use DateTimeZone;
use ForumRewrite\Calendar\CalendarDays;
use ForumRewrite\Calendar\CalendarQuery;
use ForumRewrite\Calendar\EventStatus;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;

/**
 * /calendar and /calendar/<tag>: month grids over event threads. Two months show at a
 * time, starting with ?month=YYYY-MM or the current UTC month. The markup is the same
 * for every viewer; access follows the instance's own rule.
 */
final class CalendarPageController
{
    public const MONTHS_SHOWN = 2;
    public const ENTRIES_PER_CELL = 3;

    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly string $repositoryRoot,
    ) {
    }

    public function grid(?string $tag, mixed $month, ?string $today = null): string
    {
        $tag = $tag === null ? null : strtolower($tag);
        $base = self::basePath($tag);
        if (!$this->routeServices->featureFlags()->isEnabled(FeatureFlagRegistry::EVENT_SUPPORT_ENABLED)) {
            return $this->render(['calendarEnabled' => false, 'calendarTag' => $tag, 'calendarBase' => $base], $tag);
        }

        $utc = new DateTimeZone('UTC');
        $first = self::monthStart(is_string($month) ? $month : null)
            ?? new DateTimeImmutable(substr($today ?? gmdate('Y-m-d'), 0, 7) . '-01', $utc);
        $last = $first->modify('+' . self::MONTHS_SHOWN . ' months')->modify('-1 day');
        $from = $first->format('Y-m-d');
        $to = $last->format('Y-m-d');

        $pdo = $this->routeServices->pdo();
        $events = CalendarQuery::eventsBetween($pdo, $from, $to, $tag);
        $states = EventStatus::forThreads($pdo, $this->repositoryRoot, array_map('strval', array_column($events, 'root_post_id')));
        $days = CalendarDays::derive($events, $states, $from, $to);

        $months = [];
        for ($index = 0; $index < self::MONTHS_SHOWN; $index++) {
            $months[] = self::monthGrid($first->modify("+{$index} months"), $days);
        }

        return $this->render([
            'calendarEnabled' => true,
            'calendarTag' => $tag,
            'calendarBase' => $base,
            'calendarMonths' => $months,
            'calendarPrevious' => $base . '?month=' . $first->modify('-1 month')->format('Y-m'),
            'calendarNext' => $base . '?month=' . $first->modify('+1 month')->format('Y-m'),
            'calendarEntriesPerCell' => self::ENTRIES_PER_CELL,
        ], $tag);
    }

    /** /calendar/<date> and /calendar/<tag>/<date>; null for a date that does not exist. */
    public function day(?string $tag, string $date): ?string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $match) !== 1 || !checkdate((int) $match[2], (int) $match[3], (int) $match[1])) {
            return null;
        }
        $tag = $tag === null ? null : strtolower($tag);
        $base = self::basePath($tag);
        $data = ['calendarTag' => $tag, 'calendarBase' => $base, 'calendarDate' => $date];
        if (!$this->routeServices->featureFlags()->isEnabled(FeatureFlagRegistry::EVENT_SUPPORT_ENABLED)) {
            return $this->renderDay($data + ['calendarEnabled' => false], $tag, $date);
        }

        $pdo = $this->routeServices->pdo();
        $events = CalendarQuery::eventsBetween($pdo, $date, $date, $tag);
        $states = EventStatus::forThreads($pdo, $this->repositoryRoot, array_map('strval', array_column($events, 'root_post_id')));
        $day = CalendarDays::derive($events, $states, $date, $date)[$date];

        return $this->renderDay($data + [
            'calendarEnabled' => true,
            'calendarMonthLink' => $base . '?month=' . substr($date, 0, 7),
            'calendarDay' => $day,
            // The shared composer, as /compose/thread renders it, with the day fixed and the tag preset.
            'boardTags' => $tag ?? 'general',
            'subject' => '',
            'body' => '',
            'notice' => null,
            'error' => null,
            'eventDate' => $date,
            'eventEndDate' => '',
            'eventTime' => '',
            'eventLocation' => '',
            'eventLink' => '',
        ], $tag, $date);
    }

    public static function basePath(?string $tag): string
    {
        return $tag === null ? '/calendar' : '/calendar/' . $tag;
    }

    private static function monthStart(?string $month): ?DateTimeImmutable
    {
        if ($month === null || preg_match('/^(\d{4})-(\d{2})$/', $month, $match) !== 1) {
            return null;
        }
        $number = (int) $match[2];
        if ($number < 1 || $number > 12 || (int) $match[1] < 1) {
            return null;
        }

        return new DateTimeImmutable($month . '-01', new DateTimeZone('UTC'));
    }

    /**
     * Weeks start on Sunday; days outside the month are null.
     *
     * @param array<string, array{mark:string, entries:list<array<string, mixed>>}> $days
     * @return array{label:string, weeks:list<list<array{date:string, day:int, mark:string, entries:list<array<string, mixed>>}|null>>}
     */
    private static function monthGrid(DateTimeImmutable $first, array $days): array
    {
        $cells = array_fill(0, (int) $first->format('w'), null);
        $count = (int) $first->format('t');
        for ($day = 1; $day <= $count; $day++) {
            $date = $first->format('Y-m-') . sprintf('%02d', $day);
            $cells[] = ['date' => $date, 'day' => $day] + ($days[$date] ?? ['mark' => CalendarDays::EMPTY, 'entries' => []]);
        }
        while (count($cells) % 7 !== 0) {
            $cells[] = null;
        }

        return ['label' => $first->format('F Y'), 'weeks' => array_chunk($cells, 7)];
    }

    /** @param array<string, mixed> $data */
    private function renderDay(array $data, ?string $tag, string $date): string
    {
        return $this->routeServices->renderPageTemplate(
            'calendar_day.php',
            $data,
            $date . ($tag === null ? '' : ' #' . $tag) . ' - Calendar',
            'calendar',
            ['/assets/openpgp_loader.js', '/assets/browser_signing.js', '/assets/outbox_store.js', '/assets/outbox_storage.js', '/assets/outbox_compose.js'],
        );
    }

    /** @param array<string, mixed> $data */
    private function render(array $data, ?string $tag): string
    {
        return $this->routeServices->renderPageTemplate('calendar.php', $data, $tag === null ? 'Calendar' : '#' . $tag . ' - Calendar', 'calendar');
    }
}
