<?php

declare(strict_types=1);

namespace ForumRewrite\Host;

use ForumRewrite\Application;
use ForumRewrite\SiteConfig;
use RuntimeException;
use Throwable;

final class FrontController
{
    private readonly string $publicRoot;

    public function __construct(
        private readonly string $projectRoot,
        private readonly string $repositoryRoot,
        private readonly string $databasePath,
        private readonly string $staticHtmlRoot,
        ?string $publicRoot = null,
    ) {
        $this->publicRoot = $publicRoot ?? ($projectRoot . '/public');
    }

    /**
     * @param array<string, string> $cookies
     */
    public function handle(string $method, string $requestUri, array $cookies = []): void
    {
        $configurationError = $this->configurationError();
        if ($configurationError !== null) {
            $this->sendHtml($this->renderConfigurationError($configurationError), 503);
            return;
        }

        $staticArtifact = $this->resolveStaticArtifactPath($method, $requestUri, $cookies);
        if ($staticArtifact !== null && is_file($staticArtifact)) {
            $contents = file_get_contents($staticArtifact);
            if ($contents === false) {
                throw new RuntimeException('Unable to read static HTML artifact: ' . $staticArtifact);
            }

            $this->sendHtml($contents, 200);
            return;
        }

        try {
            $application = new Application(
                $this->projectRoot,
                $this->repositoryRoot,
                $this->databasePath,
            );
            $application->handle($method, $requestUri);
            $this->buildStaticArtifactOnEligibleMiss($method, $requestUri, $cookies, $staticArtifact);
        } catch (Throwable $throwable) {
            $this->sendHtml($this->renderConfigurationError($throwable->getMessage()), 503);
        }
    }

    private function configurationError(): ?string
    {
        if (!is_dir($this->repositoryRoot)) {
            return 'Repository root does not exist: ' . $this->repositoryRoot;
        }

        if (!is_dir($this->repositoryRoot . '/records')) {
            return 'Repository root is missing records/: ' . $this->repositoryRoot;
        }

        $databaseDirectory = dirname($this->databasePath);
        if ($databaseDirectory !== '' && !is_dir($databaseDirectory) && !@mkdir($databaseDirectory, 0777, true) && !is_dir($databaseDirectory)) {
            return 'Database directory is not writable: ' . $databaseDirectory;
        }

        if (!is_dir($this->staticHtmlRoot) && !@mkdir($this->staticHtmlRoot, 0777, true) && !is_dir($this->staticHtmlRoot)) {
            return 'Static HTML directory is not writable: ' . $this->staticHtmlRoot;
        }

        return null;
    }

    /**
     * @param array<string, string> $cookies
     */
    private function resolveStaticArtifactPath(string $method, string $requestUri, array $cookies): ?string
    {
        if ($method !== 'GET' || $cookies !== []) {
            return null;
        }

        $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $query = (string) (parse_url($requestUri, PHP_URL_QUERY) ?? '');
        if ($query !== '') {
            return null;
        }

        if ($path === '/' || $path === '') {
            return $this->firstExistingPath([
                $this->publicRoot . '/index.html',
                $this->staticHtmlRoot . '/index.html',
            ]);
        }

        if ($path === '/instance/' || $path === '/instance' || $path === '/backup/' || $path === '/backup' || $path === '/tools/backup/' || $path === '/tools/backup') {
            return $this->firstExistingPath([
                $this->publicRoot . '/instance.html',
                $this->staticHtmlRoot . '/instance/index.html',
            ]);
        }

        if ($path === '/tools/' || $path === '/tools') {
            return $this->firstExistingPath([
                $this->publicRoot . '/tools.html',
                $this->publicRoot . '/tools/index.html',
                $this->staticHtmlRoot . '/tools/index.html',
            ]);
        }

        if ($path === '/tools/bookmarklets/' || $path === '/tools/bookmarklets') {
            return $this->firstExistingPath([
                $this->publicRoot . '/tools/bookmarklets.html',
                $this->staticHtmlRoot . '/tools/bookmarklets/index.html',
            ]);
        }

        if ($path === '/activity/' || $path === '/activity') {
            return $this->firstExistingPath([
                $this->publicRoot . '/activity.html',
                $this->staticHtmlRoot . '/activity/index.html',
            ]);
        }

        if ($path === '/users/pending/' || $path === '/users/pending') {
            return null;
        }

        if ($path === '/users/' || $path === '/users') {
            return $this->firstExistingPath([
                $this->publicRoot . '/users.html',
                $this->staticHtmlRoot . '/users/index.html',
            ]);
        }

        if ($path === '/tags/' || $path === '/tags') {
            return $this->firstExistingPath([
                $this->publicRoot . '/tags.html',
                $this->publicRoot . '/tags/index.html',
                $this->staticHtmlRoot . '/tags/index.html',
            ]);
        }

        if (preg_match('#^/(threads|posts|profiles)/([^/]+)/?$#', $path, $matches) === 1) {
            return $this->firstExistingPath([
                $this->publicRoot . '/' . $matches[1] . '/' . $matches[2] . '.html',
                $this->staticHtmlRoot . '/' . $matches[1] . '/' . $matches[2] . '/index.html',
            ]);
        }

        if (preg_match('#^/tags/([a-z0-9]+(?:-[a-z0-9]+)*)/?$#', $path, $matches) === 1) {
            return $this->firstExistingPath([
                $this->publicRoot . '/tags/' . $matches[1] . '.html',
                $this->staticHtmlRoot . '/tags/' . $matches[1] . '/index.html',
            ]);
        }

        return null;
    }

    /**
     * @param list<string> $paths
     */
    private function firstExistingPath(array $paths): ?string
    {
        foreach ($paths as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $cookies
     */
    private function buildStaticArtifactOnEligibleMiss(
        string $method,
        string $requestUri,
        array $cookies,
        ?string $staticArtifact,
    ): void {
        if ($staticArtifact !== null) {
            return;
        }

        if ($method !== 'GET' || $cookies !== []) {
            return;
        }

        $query = (string) (parse_url($requestUri, PHP_URL_QUERY) ?? '');
        if ($query !== '') {
            return;
        }

        $builder = new StaticArtifactBuilder(
            $this->projectRoot,
            $this->repositoryRoot,
            $this->databasePath,
            $this->publicRoot,
        );

        try {
            $builder->buildSingleRoute($requestUri);
        } catch (Throwable) {
            // Best-effort generation should not affect the current response.
        }
    }

    private function renderConfigurationError(string $details): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Configuration Error</title><link rel="stylesheet" href="/assets/site.css"></head><body>'
            . '<div class="shell"><header class="site-header"><p class="eyebrow">'
            . htmlspecialchars(SiteConfig::SITE_NAME, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</p></header>'
            . '<main class="main"><section class="stack"><h1>Configuration Error</h1>'
            . '<article class="card"><p>The PHP host configuration is incomplete or invalid.</p><p>'
            . htmlspecialchars($details, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</p></article></section></main></div></body></html>';
    }

    private function sendHtml(string $html, int $statusCode): void
    {
        http_response_code($statusCode);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }
}
