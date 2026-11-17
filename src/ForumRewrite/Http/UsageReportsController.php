<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Tools\ToolsPageSupport;
use ForumRewrite\UsageReports\UsageJourneys;
use ForumRewrite\UsageReports\UsageLogFormat;
use ForumRewrite\UsageReports\UsageNames;
use ForumRewrite\UsageReports\UsageReportResult;
use ForumRewrite\UsageReports\UsageReportStore;

/**
 * Tools → Usage log (any visitor: record, review, copy, upload) and Tools →
 * Usage reports (root operators: click counts, journeys, shortcut candidates).
 * Application routes here only while the "Usage reports" flag is on. See
 * docs/concepts/usage_reports.md.
 */
final class UsageReportsController
{
    private const FORBIDDEN_MESSAGE = 'Usage reports require a server-authenticated root-approved identity.';
    private const CLICK_ROWS = 50;
    private const JOURNEY_ROWS = 50;

    /**
     * @param \Closure(): UsageReportStore $store
     * @param \Closure(): ?string $viewerUsername the signed-in viewer's username, or null
     * @param \Closure(): bool $viewerIsRootOperator
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly ?\Closure $store = null,
        private readonly ?\Closure $viewerUsername = null,
        private readonly ?\Closure $viewerIsRootOperator = null,
    ) {
    }

    /**
     * Tools → Usage reports, root operators only. `?uploader=<username>` or
     * `?uploader=anonymous` narrows to one uploader; absent means everyone.
     *
     * @param array<string, mixed> $query
     */
    public function summary(array $query): void
    {
        if ($this->viewerIsRootOperator === null || !($this->viewerIsRootOperator)()) {
            $this->routeServices->sendHtml(
                $this->routeServices->renderMessagePage('Usage Reports', 'Usage Reports', self::FORBIDDEN_MESSAGE, 'tools'),
                403,
                $this->routeServices->noStoreHeaders(),
            );
            return;
        }

        $store = ($this->store)();
        $uploaders = $store->uploaders();
        $uploader = trim((string) ($query['uploader'] ?? ''));
        $known = array_map(static fn (array $row): string => $row['username'] ?? 'anonymous', $uploaders);
        if (!in_array($uploader, $known, true)) {
            $uploader = '';
        }
        $rows = $store->reports($uploader === '' ? null : ($uploader === 'anonymous' ? '' : $uploader));
        $reports = array_map(static fn (array $row): array => UsageLogFormat::entries($row['body']), $rows);
        $journeyCards = array_map(self::journeyCard(...), array_slice(UsageJourneys::groups(UsageJourneys::fromReports($reports)), 0, self::JOURNEY_ROWS));
        $clicks = array_slice(UsageJourneys::clickCounts($reports), 0, self::CLICK_ROWS);
        $mostClicks = max([1, ...array_column($clicks, 'clicks')]);
        $uploaderLinks = [['label' => 'Everyone', 'href' => '/tools/usage-reports/', 'current' => $uploader === '', 'count' => null]];
        foreach ($uploaders as $row) {
            $value = $row['username'] ?? 'anonymous';
            $uploaderLinks[] = [
                'label' => $row['username'] ?? 'Anonymous',
                'href' => '/tools/usage-reports/?uploader=' . rawurlencode($value),
                'current' => $value === $uploader,
                'count' => $row['reports'],
            ];
        }

        $this->routeServices->sendHtml(
            $this->routeServices->renderPageTemplate(
                'usage_reports.php',
                [
                    'hasReports' => $uploaders !== [],
                    'uploaderLinks' => $uploaderLinks,
                    'reportCount' => count($rows),
                    'entryCount' => array_sum(array_map('count', $reports)),
                    'shortcutCount' => count(array_filter($journeyCards, static fn (array $card): bool => $card['shortcut'])),
                    'journeyCards' => $journeyCards,
                    'clickRows' => array_map(static fn (array $row): array => [
                        'name' => UsageNames::control($row['name']),
                        'raw' => $row['page'] . ' · ' . $row['region'] . ' · ' . $row['name'],
                        'where' => self::regionPhrase($row['region']) . UsageNames::page($row['page']),
                        'clicks' => $row['clicks'],
                        'percent' => (int) round(100 * $row['clicks'] / $mostClicks),
                    ], $clicks),
                    'stopSeconds' => UsageJourneys::STOP_SECONDS,
                    'toolNavOptions' => ToolsPageSupport::navOptions('usage-reports'),
                ],
                'Usage Reports',
                'tools',
            ),
            200,
            $this->routeServices->noStoreHeaders(),
        );
    }

    /**
     * One journey group in words: "Thread → Save on Feature Flags", its usual
     * clicks as named steps, and each name's raw form for hover.
     *
     * @param array{start:string, end:string, times:int, fewest:int, usual:list<string>, usual_clicks:int, shortcut:bool} $group
     * @return array{from:string, fromRaw:string, to:string, toRaw:string, steps:list<array{name:string, raw:string}>, clicks:int, fewest:int, times:int, shortcut:bool}
     */
    private static function journeyCard(array $group): array
    {
        return [
            'from' => self::placeName($group['start']),
            'fromRaw' => $group['start'],
            'to' => self::placeName($group['end']),
            'toRaw' => $group['end'],
            'steps' => array_map(static fn (string $step): array => [
                'name' => str_starts_with($step, 'submit ') ? UsageNames::control(substr($step, 7)) : UsageNames::control($step),
                'raw' => $step,
            ], $group['usual']),
            'clicks' => $group['usual_clicks'],
            'fewest' => $group['fewest'],
            'times' => $group['times'],
            'shortcut' => $group['shortcut'],
        ];
    }

    /** A journey end: a page kind, or "page › control" for a menu or a submit. */
    private static function placeName(string $place): string
    {
        if ($place === 'another site') {
            return 'Another site';
        }
        $parts = explode(' › ', $place, 2);
        if (count($parts) === 2) {
            return UsageNames::control($parts[1]) . ' on ' . UsageNames::page($parts[0]);
        }

        return UsageNames::page($place);
    }

    private static function regionPhrase(string $region): string
    {
        return match ($region) {
            'navbar' => 'navbar, on ',
            'menu' => 'menu, on ',
            'tools-bar' => 'Tools bar, on ',
            'list' => 'list, on ',
            'post' => 'post, on ',
            'composer' => 'composer, on ',
            'footer' => 'footer, on ',
            default => 'on ',
        };
    }

    public function log(): void
    {
        $this->routeServices->sendHtml(
            $this->routeServices->renderPageTemplate(
                'usage_log.php',
                [
                    'toolNavOptions' => ToolsPageSupport::navOptions('usage-log'),
                    'usageNamesJson' => json_encode(UsageNames::forBrowser(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'uploaderName' => $this->viewerUsername !== null ? ($this->viewerUsername)() : null,
                ],
                'Usage log',
                'tools',
                ['/assets/usage_log_page.js'],
            ),
            200,
            $this->routeServices->noStoreHeaders(),
        );
    }

    /**
     * POST the reviewed log as JSON `{"log": "<text>"}` (what the Usage log page
     * sends, because some hosts refuse raw line breaks in a POST body) or as the
     * raw text. Refusals keep the visitor's log:
     * 405 wrong method, 403 cross-site, 413 too large, 400 not a usage log,
     * 429 with Retry-After, 503 store full.
     */
    public function upload(string $method, string $body, string $remoteAddress, string $userAgent, int $now): void
    {
        if ($method !== 'POST') {
            $this->refuse(405, 'method_not_allowed', 'Upload with POST.', ['Allow: POST']);
            return;
        }
        if (!SameOriginRequest::check()) {
            $this->refuse(403, 'cross_site', 'Uploads must come from this site\'s Usage log page.');
            return;
        }
        $body = self::logText($body);
        if (strlen($body) > UsageReportStore::MAX_REPORT_BYTES) {
            $this->refuse(413, 'too_large', 'This log is over 100 KB. Clear it and record a shorter session.');
            return;
        }
        if (!UsageLogFormat::isValid($body)) {
            $this->refuse(400, 'invalid_log', 'This is not a usage log from this site.');
            return;
        }

        $username = ($this->viewerUsername)();
        $result = ($this->store)()->add($body, $username, UsageReportStore::clientHash($remoteAddress, $userAgent), $now);
        match ($result->status) {
            UsageReportResult::STORED => $this->routeServices->sendJson(
                ['status' => 'stored', 'id' => $result->id, 'username' => $username],
                201,
                $this->routeServices->noStoreHeaders(),
            ),
            UsageReportResult::TOO_LARGE => $this->refuse(413, 'too_large', 'This log is over 100 KB. Clear it and record a shorter session.'),
            UsageReportResult::RATE_LIMITED => $this->refuse(
                429,
                'rate_limited',
                'Too many uploads from here in the last hour. Try again in ' . (int) ceil($result->retryAfterSeconds / 60) . ' minutes.',
                ['Retry-After: ' . $result->retryAfterSeconds],
                ['retry_after' => $result->retryAfterSeconds],
            ),
            default => $this->refuse(503, 'store_full', 'The site is not taking more usage reports right now. Your log is still here.'),
        };
    }

    /** The log from a JSON `{"log": ...}` body, or the body itself when it is not that. */
    private static function logText(string $body): string
    {
        if (!str_starts_with(ltrim($body), '{')) {
            return $body;
        }
        $decoded = json_decode($body, true);

        return is_array($decoded) && is_string($decoded['log'] ?? null) ? $decoded['log'] : $body;
    }

    /** @param list<string> $headers @param array<string, mixed> $extra */
    private function refuse(int $status, string $code, string $message, array $headers = [], array $extra = []): void
    {
        $this->routeServices->sendJson(
            ['status' => 'error', 'code' => $code, 'error' => $message] + $extra,
            $status,
            array_merge($this->routeServices->noStoreHeaders(), $headers),
        );
    }
}
