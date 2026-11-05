<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/TestViewerSession.php';

use ForumRewrite\Application;
use ForumRewrite\Calendar\CalendarDays;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Write\LocalWriteService;

final class CalendarPageTest
{
    private const OPERATOR = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';

    public function testDayStatesAcrossSpansMonthEdgesAndStates(): void
    {
        $event = static fn (string $id, string $first, ?string $last = null): array => ['root_post_id' => $id, 'event_date' => $first, 'event_end_date' => $last];
        $events = [
            $event('single', '2026-11-03'),
            $event('span', '2026-11-05', '2026-11-07'),
            $event('edge', '2026-10-30', '2026-11-02'),
            $event('late', '2026-11-29', '2026-12-03'),
            $event('confirmed', '2026-11-06'),
            $event('declined', '2026-11-10'),
            $event('declined-over-plain', '2026-11-05'),
        ];
        $states = ['confirmed' => 'confirmed', 'declined' => 'declined', 'declined-over-plain' => 'declined'];

        $days = CalendarDays::derive($events, $states, '2026-11-01', '2026-11-30');

        calendarAssertSame(30, count($days));
        $summary = [];
        foreach ($days as $date => $day) {
            if ($day['mark'] !== CalendarDays::EMPTY) {
                $summary[$date] = $day['mark'] . ':' . implode(',', array_column($day['entries'], 'root_post_id'));
            }
        }
        calendarAssertSame([
            '2026-11-01' => 'open:edge',
            '2026-11-02' => 'open:edge',
            '2026-11-03' => 'open:single',
            '2026-11-05' => 'open:span',
            '2026-11-06' => 'confirmed:span,confirmed',
            '2026-11-07' => 'open:span',
            '2026-11-29' => 'open:late',
            '2026-11-30' => 'open:late',
        ], $summary);
        calendarAssertSame('confirmed', $days['2026-11-06']['entries'][1]['event_state']);
        calendarAssertSame(['2026-02-28', '2026-03-01'], CalendarDays::dates('2026-02-28', '2026-03-01'));
        calendarAssertSame([], CalendarDays::dates('2026-03-02', '2026-03-01'));
    }

    public function testGridShowsEventsByDayWithTagCalendarsAndNavigation(): void
    {
        [$root, $service, $application] = $this->environment();
        $span = $this->event($service, 'couches', 'Spare room', 'Couch for three nights', '2026-11-29', '2026-12-02');
        $labelled = $this->event($service, 'general', 'Futon', "Futon available #couches", '2026-11-20');
        $cypher = $this->event($service, 'general', 'Cypher night', 'Doors at seven', '2026-11-14');
        $service->createReply(['thread_id' => $cypher, 'parent_id' => $cypher, 'body' => 'event: confirmed', 'author_identity_id' => self::OPERATOR]);
        $declined = $this->event($service, 'couches', 'Declined couch', 'Not this time', '2026-11-21');
        $service->createReply(['thread_id' => $declined, 'parent_id' => $declined, 'body' => 'event: declined', 'author_identity_id' => self::OPERATOR]);
        for ($n = 1; $n <= 5; $n++) {
            $this->event($service, 'general', 'Busy ' . $n, 'Same day', '2026-11-25');
        }

        $all = $this->render($application, '/calendar', ['month' => '2026-11']);
        calendarAssertSame([
            '2026-11-14' => 'confirmed', '2026-11-20' => 'open', '2026-11-25' => 'open',
            '2026-11-29' => 'open', '2026-11-30' => 'open', '2026-12-01' => 'open', '2026-12-02' => 'open',
        ], $this->marks($all));
        calendarAssertContains('<caption>November 2026</caption>', $all);
        calendarAssertContains('<caption>December 2026</caption>', $all);
        calendarAssertContains('href="/calendar?month=2026-10" rel="prev"', $all);
        calendarAssertContains('href="/calendar?month=2026-12" rel="next"', $all);
        calendarAssertContains('href="/threads/' . $span . '">Spare room</a>', $all);
        calendarAssertContains('href="/calendar/2026-11-14"', $all);
        calendarAssertContains('aria-label="confirmed">✓</span> Cypher night', $all);
        calendarAssertNotContains('Declined couch', $all);
        calendarAssertSame(3, substr_count($this->cell($all, '2026-11-25'), '<li class="calendar-entry'));
        calendarAssertContains('href="/calendar/2026-11-25">+2 more</a>', $this->cell($all, '2026-11-25'));
        calendarAssertNotContains('All events', $all);

        $couches = $this->render($application, '/calendar/couches', ['month' => '2026-11']);
        calendarAssertSame([
            '2026-11-20' => 'open', '2026-11-29' => 'open', '2026-11-30' => 'open', '2026-12-01' => 'open', '2026-12-02' => 'open',
        ], $this->marks($couches));
        calendarAssertContains('href="/threads/' . $labelled . '">Futon</a>', $couches);
        calendarAssertContains('href="/calendar/couches/2026-11-20"', $couches);
        calendarAssertContains('href="/calendar/couches?month=2026-10" rel="prev"', $couches);
        calendarAssertContains('<a href="/calendar">All events</a>', $couches);
        calendarAssertSame($couches, $this->render($application, '/calendar/Couches/', ['month' => '2026-11']));

        $current = $this->render($application, '/calendar', ['month' => 'not-a-month']);
        calendarAssertContains('<caption>' . gmdate('F Y') . '</caption>', $current);
        calendarAssertSame([], $this->marks($this->render($application, '/calendar/nothing-tagged', ['month' => '2026-11'])));
    }

    public function testMarkupIsTheSameForEveryViewer(): void
    {
        [, $service, $application] = $this->environment();
        $this->event($service, 'couches', 'Spare room', 'Couch', '2026-11-29');
        try {
            TestViewerSession::signedOut();
            $guest = $this->section($this->render($application, '/calendar', ['month' => '2026-11']));
            TestViewerSession::signIn(self::OPERATOR);
            $operator = $this->section($this->render($application, '/calendar', ['month' => '2026-11']));
        } finally {
            TestViewerSession::reset();
        }
        calendarAssertContains('Spare room', $guest);
        calendarAssertSame($guest, $operator);
    }

    public function testEventSupportOffShowsOnlyTheFlagLink(): void
    {
        [, , $application] = $this->environment(false);
        $html = $this->render($application, '/calendar');
        calendarAssertContains('href="/tools/feature-flags/#flag-FORUM_EVENT_SUPPORT_ENABLED"', $html);
        calendarAssertNotContains('calendar-grid', $html);
        calendarAssertNotContains('rel="next"', $html);
    }

    public function testMembersOnlySiteKeepsTheCalendarFromUnapprovedVisitors(): void
    {
        [, $service, $application] = $this->environment();
        $this->event($service, 'couches', 'Private couch', 'Couch', '2026-11-29');
        try {
            putenv('FORUM_APPROVED_MEMBERS_ONLY=true');
            TestViewerSession::signedOut();
            foreach (['/calendar', '/calendar/couches'] as $path) {
                $html = $this->render($application, $path, ['month' => '2026-11']);
                calendarAssertSame(true, http_response_code() !== 200);
                calendarAssertNotContains('Private couch', $html);
            }
            TestViewerSession::signIn(self::OPERATOR);
            calendarAssertContains('Private couch', $this->render($application, '/calendar/couches', ['month' => '2026-11']));
        } finally {
            putenv('FORUM_APPROVED_MEMBERS_ONLY');
            TestViewerSession::reset();
        }
    }

    /** @return array{string, LocalWriteService, Application} */
    private function environment(bool $events = true): array
    {
        $root = sys_get_temp_dir() . '/forum-rewrite-calendar-repo-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $root);
        file_put_contents($root . '/records/instance/feature-flags.txt', "Schema: site-feature-flags-v1\n\nFORUM_EVENT_SUPPORT_ENABLED: " . ($events ? 'true' : 'false') . "\n");
        foreach (['git init', 'git config user.name "Forum Rewrite"', 'git config user.email "forum-rewrite@example.invalid"', 'git add .', 'git -c commit.gpgsign=false commit -m init'] as $command) {
            exec('cd ' . escapeshellarg($root) . ' && ' . $command . ' 2>&1', $output, $status);
            if ($status !== 0) {
                throw new RuntimeException('Command failed: ' . $command);
            }
        }
        $database = $root . '.sqlite3';
        $artifacts = $root . '-public';
        mkdir($artifacts, 0777, true);
        $application = new Application(dirname(__DIR__), $root, $database, $artifacts);
        $this->render($application, '/');
        $service = new LocalWriteService($root, $database, $artifacts, new CanonicalRecordRepository($root), featureFlags: FeatureFlagEvaluator::forRepository($root));

        return [$root, $service, $application];
    }

    private function event(LocalWriteService $service, string $tags, string $subject, string $body, string $first, ?string $last = null): string
    {
        return (string) $service->createThread([
            'board_tags' => $tags, 'subject' => $subject, 'body' => $body,
            'event_date' => $first, 'event_end_date' => $last ?? '',
        ])['thread_id'];
    }

    /** @param array<string, string> $query */
    private function render(Application $application, string $path, array $query = []): string
    {
        $_GET = $query;
        http_response_code(200);
        ob_start();
        try {
            $application->handle('GET', $path);
        } finally {
            $html = (string) ob_get_clean();
            $_GET = [];
        }

        return $html;
    }

    /** @return array<string, string> */
    private function marks(string $html): array
    {
        preg_match_all('/data-calendar-day="([^"]+)" data-calendar-mark="(open|confirmed)"/', $html, $matches);

        return array_combine($matches[1], $matches[2]);
    }

    private function cell(string $html, string $date): string
    {
        $start = strpos($html, 'data-calendar-day="' . $date . '"');
        calendarAssertSame(true, $start !== false);

        return substr($html, $start, strpos($html, '</td>', $start) - $start);
    }

    private function section(string $html): string
    {
        $start = strpos($html, '<section class="stack calendar">');
        calendarAssertSame(true, $start !== false);

        return substr($html, $start, strpos($html, '</section>', $start) - $start);
    }

    private function copyDirectory(string $source, string $destination): void
    {
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($items as $item) {
            $target = $destination . '/' . $items->getSubPathName();
            $item->isDir() ? (is_dir($target) || mkdir($target, 0777, true)) : copy($item->getPathname(), $target);
        }
    }
}

function calendarAssertSame(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Failed asserting identical values. Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function calendarAssertContains(string $needle, string $haystack): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException('Failed asserting that output contains: ' . $needle);
    }
}

function calendarAssertNotContains(string $needle, string $haystack): void
{
    if (str_contains($haystack, $needle)) {
        throw new RuntimeException('Failed asserting that output does not contain: ' . $needle);
    }
}
