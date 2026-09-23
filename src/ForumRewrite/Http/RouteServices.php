<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Host\HtmlResponseCache;
use ForumRewrite\ReadModel\ReadModelConnection;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\View\TemplateRenderer;
use ForumRewrite\Write\LocalWriteService;
use PDO;
use RuntimeException;

/**
 * Bundles the framework-level dependencies nearly every route handler
 * needs - DB access, response senders, page rendering, and (for write
 * flows) request-body parsing, the write service, and timing/instrumentation
 * helpers - so route-group controllers extracted out of Application.php
 * (see docs/plans/codebase_cleanup_audit_plan_v1.md, Phase 2) can depend on
 * this instead of on Application itself. Built once per request by
 * Application::routeServices().
 *
 * The write-side members (writer(), requestData(), the timing helpers) were
 * added for the write-flow slice: grep confirmed each is shared across
 * 14-39 call sites spanning the whole write-API surface, not just compose -
 * the same "heavily shared framework layer" shape as the read-side members
 * already here, so they were folded in here rather than given a new home.
 *
 * The default viewer profile is resolved lazily via a bound closure
 * (Application::authenticatedViewerProfile(...)) rather than eagerly in the
 * constructor, so construction stays safe even before session bootstrap
 * runs (e.g. if a future caller builds this outside handle()) and CLI
 * scripts that only need pdo() never pay for a profile lookup they don't
 * use. It's still memoized after first use, since the answer can't change
 * mid-request.
 */
final class RouteServices
{
    private bool $viewerProfileResolved = false;
    private ?array $resolvedViewerProfile = null;

    /**
     * @param \Closure(): (array<string, mixed>|null) $viewerProfileResolver
     */
    public function __construct(
        private readonly string $databasePath,
        private readonly TemplateRenderer $renderer,
        private readonly string $routeSource,
        private readonly bool $approvedMembersOnlyEnabled,
        private readonly \Closure $viewerProfileResolver,
        private readonly string $repositoryRoot,
        private readonly string $projectRoot,
        private readonly ?string $artifactRoot,
        private readonly ?string $staticHtmlRoot,
        private readonly FeatureFlagEvaluator $featureFlags,
    ) {
    }

    public function pdo(): PDO
    {
        return (new ReadModelConnection($this->databasePath))->open();
    }

    public function writer(): LocalWriteService
    {
        return new LocalWriteService(
            $this->repositoryRoot,
            $this->databasePath,
            $this->artifactRoot ?? ($this->projectRoot . '/public'),
            new CanonicalRecordRepository($this->repositoryRoot),
            featureFlags: $this->featureFlags,
            additionalArtifactRoots: $this->additionalArtifactRoots(),
        );
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function requestData(array $query): array
    {
        $data = $query;

        foreach ($_POST as $key => $value) {
            $data[$key] = $value;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $rawBody = (string) file_get_contents('php://input');

        return $this->mergeRequestBodyData($data, $contentType, $rawBody);
    }

    public function elapsedMilliseconds(int $startedAt): float
    {
        return round((hrtime(true) - $startedAt) / 1000000, 1);
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, float|int> $timings
     * @return array<string, mixed>
     */
    public function mergeResultTimings(array $result, array $timings, int $totalStartedAt): array
    {
        $existing = isset($result['timings']) && is_array($result['timings'])
            ? $result['timings']
            : [];

        if (isset($existing['total']) && (is_int($existing['total']) || is_float($existing['total']))) {
            $existing['write_total'] = $existing['total'];
            unset($existing['total']);
        }

        $result['timings'] = array_merge($timings, $existing);
        $result['timings']['total'] = $this->elapsedMilliseconds($totalStartedAt);

        return $result;
    }

    /**
     * @param array<string, float|int> $timings
     * @return array<string, float|int>
     */
    public function timingsWithTotal(array $timings, int $totalStartedAt): array
    {
        $timings['total'] = $this->elapsedMilliseconds($totalStartedAt);

        return $timings;
    }

    /**
     * @param array<string, mixed> $result
     * @return list<string>
     */
    public function serverTimingHeaders(array $result): array
    {
        if (!isset($result['timings']) || !is_array($result['timings'])) {
            return [];
        }

        $metrics = [];
        foreach ($result['timings'] as $name => $duration) {
            if (!is_string($name) || !preg_match('/^[a-z_][a-z0-9_]*$/', $name)) {
                continue;
            }

            if (!is_int($duration) && !is_float($duration)) {
                continue;
            }

            $metrics[] = sprintf('%s;dur=%.1f', $name, (float) $duration);
        }

        if ($metrics === []) {
            return [];
        }

        return ['Server-Timing: ' . implode(', ', $metrics)];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function mergeRequestBodyData(array $data, string $contentType, string $rawBody): array
    {
        $normalizedContentType = strtolower(trim(explode(';', $contentType, 2)[0]));
        if ($rawBody === '') {
            return $data;
        }

        if ($normalizedContentType === 'application/json') {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                foreach ($decoded as $key => $value) {
                    if (is_string($key)) {
                        $data[$key] = $value;
                    }
                }
            }

            return $data;
        }

        if ($normalizedContentType === 'application/x-www-form-urlencoded') {
            $decoded = [];
            parse_str($rawBody, $decoded);
            foreach ($decoded as $key => $value) {
                if (is_string($key)) {
                    $data[$key] = $value;
                }
            }
        }

        return $data;
    }

    /**
     * @return list<string>
     */
    private function additionalArtifactRoots(): array
    {
        $roots = [];
        if ($this->staticHtmlRoot !== null && $this->staticHtmlRoot !== ($this->artifactRoot ?? ($this->projectRoot . '/public'))) {
            $roots[] = $this->staticHtmlRoot;
        }

        return $roots;
    }

    /**
     * @param array<string, mixed> $pageData
     * @param string[] $scriptPaths
     */
    public function renderPageTemplate(
        string $pageTemplate,
        array $pageData,
        string $title,
        string $activeSection,
        array $scriptPaths = [],
    ): string {
        if (!array_key_exists('viewerProfile', $pageData)) {
            $pageData['viewerProfile'] = $this->defaultViewerProfile();
        }
        $publicAuthenticationResume = !$this->approvedMembersOnlyEnabled
            && $pageData['viewerProfile'] === null;

        return $this->renderer->renderPageTemplate(
            $pageTemplate,
            $pageData,
            $title,
            $activeSection,
            $scriptPaths,
            $this->routeSource,
            $publicAuthenticationResume,
        );
    }

    /**
     * @param array<string, mixed> $pageData
     * @param string[] $scriptPaths
     * @param string[] $additionalCssPaths
     */
    public function renderStandalonePage(
        string $pageTemplate,
        array $pageData,
        string $title,
        string $bodyClass = '',
        array $scriptPaths = [],
        array $additionalCssPaths = [],
    ): string {
        return $this->renderer->renderStandalonePage($pageTemplate, $pageData, $title, $bodyClass, $scriptPaths, $additionalCssPaths);
    }

    public function renderMessagePage(string $title, string $heading, string $message, string $activeSection): string
    {
        return $this->renderPageTemplate(
            'message.php',
            [
                'heading' => $heading,
                'message' => $message,
            ],
            $title,
            $activeSection,
        );
    }

    public function notFound(): void
    {
        $this->sendHtml(
            $this->renderMessagePage(
                'Not Found',
                'Not Found',
                'The requested route does not exist in the local test slice.',
                'none'
            ),
            404
        );
    }

    /** @param string[] $headers */
    public function sendHtml(string $html, int $statusCode, array $headers = []): void
    {
        $etag = HtmlResponseCache::etag($html);
        $headers = array_merge([
            'Cache-Control: private, no-cache, must-revalidate, max-age=0',
            'Vary: Cookie',
            'ETag: ' . $etag,
        ], $headers);

        if ($statusCode === 200 && HtmlResponseCache::requestMatches($etag)) {
            http_response_code(304);
            foreach ($headers as $headerValue) {
                header($headerValue);
            }
            return;
        }

        http_response_code($statusCode);
        header('Content-Type: text/html; charset=utf-8');
        foreach ($headers as $headerValue) {
            header($headerValue);
        }
        echo $html;
    }

    /** @param string[] $headers */
    public function sendText(string $text, int $statusCode, array $headers = []): void
    {
        http_response_code($statusCode);
        header('Content-Type: text/plain; charset=utf-8');
        foreach ($headers as $headerValue) {
            header($headerValue);
        }
        echo $text;
    }

    public function sendXml(string $xml, int $statusCode): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/rss+xml; charset=utf-8');
        echo $xml;
    }

    /** @param string[] $headers */
    public function sendRedirect(
        string $location,
        string $message,
        int $statusCode = 303,
        array $headers = [],
        string $activeSection = 'compose',
    ): void {
        http_response_code($statusCode);
        header('Location: ' . $location);
        header('Content-Type: text/html; charset=utf-8');
        foreach ($headers as $headerValue) {
            header($headerValue);
        }

        echo $this->renderPageTemplate(
            'redirect.php',
            [
                'location' => $location,
                'message' => $message,
            ],
            'Redirecting',
            $activeSection
        );
    }

    public function sendDownload(string $path, string $contentType, string $filename, bool $deleteAfterSend = false): void
    {
        $size = filesize($path);
        if ($size === false) {
            if ($deleteAfterSend) {
                @unlink($path);
            }
            throw new RuntimeException('Unable to determine download size.');
        }

        http_response_code(200);
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        header('Content-Length: ' . (string) $size);
        readfile($path);

        if ($deleteAfterSend) {
            @unlink($path);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function defaultViewerProfile(): ?array
    {
        if (!$this->viewerProfileResolved) {
            $this->resolvedViewerProfile = ($this->viewerProfileResolver)();
            $this->viewerProfileResolved = true;
        }

        return $this->resolvedViewerProfile;
    }
}
