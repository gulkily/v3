<?php

declare(strict_types=1);

namespace ForumRewrite\Docs;

use ForumRewrite\Http\PlatformDocsController;
use ForumRewrite\Http\RouteServices;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\View\TemplateRenderer;
use RuntimeException;

/** The existing docs reader, rendered anonymously without Application startup or a database. */
final class DocsPageRenderer
{
    private readonly PlatformDocsController $controller;

    public function __construct(string $projectRoot, string $repositoryRoot, string $version)
    {
        if (($_COOKIE ?? []) !== [] || ($_SESSION ?? []) !== []) {
            throw new RuntimeException('Docs publication requires an anonymous process.');
        }
        $flags = FeatureFlagEvaluator::forApplication($repositoryRoot, $projectRoot);
        $renderer = new TemplateRenderer($projectRoot . '/templates', $version, $flags,
            versionEndpoint: '/api/docs-version', versionStorageKey: 'forum_pending_docs_version');
        $services = new RouteServices('', $renderer, 'static-html',
            $flags->isEnabled(FeatureFlagRegistry::APPROVED_MEMBERS_ONLY),
            static fn () => null, $repositoryRoot, $projectRoot, null, null, $flags,
            static fn () => null, readModelAccessAllowed: false);
        $this->controller = new PlatformDocsController($services, $projectRoot);
    }

    public function render(string $route): string
    {
        $path = parse_url($route, PHP_URL_PATH);
        parse_str((string) parse_url($route, PHP_URL_QUERY), $query);
        $html = match ($path) {
            '/docs', '/docs/' => $this->controller->index($query),
            '/docs/_search.html' => $this->controller->index([], true),
            '/docs/_missing.html' => $this->controller->missing(),
            default => is_string($path) && str_starts_with($path, '/docs/')
                ? $this->controller->document(substr($path, strlen('/docs/')), $query) : null,
        };
        if ($html === null) {
            throw new RuntimeException('Documentation source changed or route is unavailable: ' . $route);
        }
        return $html;
    }
}
