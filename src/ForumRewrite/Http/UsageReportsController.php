<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Tools\ToolsPageSupport;
use ForumRewrite\UsageReports\UsageJourneys;
use ForumRewrite\UsageReports\UsageLogFormat;
use ForumRewrite\UsageReports\UsageReportResult;
use ForumRewrite\UsageReports\UsageReportStore;

/**
 * Tools → Usage log (any visitor: record, review, copy, upload) and, from
 * Stage 6, Tools → Usage reports (root operators). Application routes here
 * only while the "Usage reports" flag is on.
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

        $this->routeServices->sendHtml(
            $this->routeServices->renderPageTemplate(
                'usage_reports.php',
                [
                    'uploaders' => $uploaders,
                    'uploader' => $uploader,
                    'reportCount' => count($rows),
                    'entryCount' => array_sum(array_map('count', $reports)),
                    'journeyGroups' => array_slice(UsageJourneys::groups(UsageJourneys::fromReports($reports)), 0, self::JOURNEY_ROWS),
                    'clickCounts' => array_slice(UsageJourneys::clickCounts($reports), 0, self::CLICK_ROWS),
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

    public function log(): void
    {
        $this->routeServices->sendHtml(
            $this->routeServices->renderPageTemplate(
                'usage_log.php',
                ['toolNavOptions' => ToolsPageSupport::navOptions('usage-log')],
                'Usage log',
                'tools',
                ['/assets/usage_log_page.js'],
            ),
            200,
            $this->routeServices->noStoreHeaders(),
        );
    }

    /**
     * POST the reviewed log text as the body. Refusals keep the visitor's log:
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
