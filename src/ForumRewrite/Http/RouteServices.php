<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\ReadModel\ReadModelConnection;
use ForumRewrite\View\TemplateRenderer;
use PDO;
use RuntimeException;

/**
 * Bundles the framework-level dependencies nearly every route handler
 * needs - DB access, response senders, page rendering - so route-group
 * controllers extracted out of Application.php (see
 * docs/plans/codebase_cleanup_audit_plan_v1.md, Phase 2) can depend on this
 * instead of on Application itself. Built once per request by
 * Application::routeServices().
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
    ) {
    }

    public function pdo(): PDO
    {
        return (new ReadModelConnection($this->databasePath))->open();
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
