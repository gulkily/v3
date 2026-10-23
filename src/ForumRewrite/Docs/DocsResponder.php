<?php

declare(strict_types=1);

namespace ForumRewrite\Docs;

use ForumRewrite\Host\AssetFingerprint;
use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\View\FaviconRegistry;

/** Only public documentation and its immutable public assets; never forum output. */
final class DocsResponder
{
    public function __construct(private readonly string $projectRoot, private readonly string $staticHtmlRoot)
    {
    }

    public function serve(string $method, string $requestUri): bool
    {
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $docs = $path === '/docs' || str_starts_with($path, '/docs/');
        $asset = preg_match('#^/assets/[A-Za-z0-9][A-Za-z0-9._-]*\.[a-f0-9]{12}\.[A-Za-z0-9]+$#D', $path) === 1;
        if (!$docs && !$asset && $path !== '/api/docs-version') {
            return false;
        }
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            if ($path === '/api/docs-version') {
                http_response_code(405);
                header('Allow: GET, HEAD');
                return true;
            }
            return false;
        }
        try {
            $release = (new DocsReleaseLocator($this->staticHtmlRoot))->active();
        } catch (\Throwable) {
            $release = null;
        }
        if ($path === '/api/docs-version') {
            $text = ($release['version'] ?? 'unpublished') . "\n";
            http_response_code(200);
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Content-Length: ' . strlen($text));
            if ($method !== 'HEAD') {
                echo $text;
            }
            return true;
        }
        if ($release === null) {
            // Existing PHP readers still have their public favicon before first publication.
            if (str_starts_with($path, '/docs/_assets/')) {
                foreach (array_unique(['/favicon.ico', '/favicon.gif', FaviconRegistry::resolve(SiteProfileRegistry::active())]) as $favicon) {
                    if ($path === '/docs/_assets/' . basename($favicon)) {
                        $file = DocsReleaseLocator::file($this->projectRoot . '/public', ltrim($favicon, '/'));
                        if ($file !== null) {
                            return $this->send($file, $method);
                        }
                    }
                }
            }
            return false;
        }
        if ($asset) {
            $file = DocsReleaseLocator::file($release['root'], ltrim($path, '/'));
            preg_match('/\.([a-f0-9]{12})\.[A-Za-z0-9]+$/D', $path, $fingerprint);
            if ($file === null || substr((string) hash_file('sha256', $file), 0, 12) !== ($fingerprint[1] ?? null)) {
                return false;
            }
            return $this->send($file, $method);
        }
        if ($path === '/docs' || $path === '/docs/') {
            parse_str((string) parse_url($requestUri, PHP_URL_QUERY), $query);
            $relative = array_intersect_key($query, array_flip(['q', 'folder', 'category', 'group', 'page'])) === []
                ? 'docs/index.html' : ltrim(PlatformDocsCatalog::browsePath($query) ?? '/docs/_search.html', '/');
            $file = DocsReleaseLocator::file($release['root'], $relative)
                ?? DocsReleaseLocator::file($release['root'], 'docs/_search.html');
        } elseif ($path === '/docs/_search.json') {
            $file = DocsReleaseLocator::file($release['root'], 'docs/_search.json');
        } elseif (str_starts_with($path, '/docs/_assets/')) {
            $name = substr($path, strlen('/docs/_assets/'));
            $file = preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/D', $name)
                ? DocsReleaseLocator::file($release['root'], 'docs/_assets/' . $name) : null;
            if ($file === null && $release['legacy'] && preg_match('/^favicon[A-Za-z0-9._-]*\.(?:ico|gif)$/D', $name)) {
                $file = DocsReleaseLocator::file($release['root'], 'assets/' . $name)
                    ?? DocsReleaseLocator::file($release['root'], $name);
            }
        } elseif (str_starts_with($path, '/docs/_download/')) {
            $source = PlatformDocsCatalog::normalizePath(substr($path, strlen('/docs/_download/')));
            $file = $source === null ? null : DocsReleaseLocator::file($release['root'], 'docs/_download/' . $source . '.bin');
            if ($file !== null) {
                header('Content-Disposition: attachment; filename="' . str_replace('"', '', basename($source)) . '"');
            }
        } else {
            $source = PlatformDocsCatalog::sourceForRoute(substr($path, strlen('/docs/')));
            $file = $source === null ? null : DocsReleaseLocator::file($release['root'], ltrim(PlatformDocsCatalog::routeForPath($source), '/') . '.html');
        }
        if ($file !== null) {
            return $this->send($file, $method, 200, $release);
        }
        $missing = DocsReleaseLocator::file($release['root'], 'docs/_missing.html');
        return $missing !== null && $this->send($missing, $method, 404, $release);
    }

    private function send(string $file, string $method, int $status = 200, ?array $release = null): bool
    {
        $type = match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
            'html' => 'text/html; charset=utf-8', 'json' => 'application/json; charset=utf-8',
            'css' => 'text/css; charset=utf-8', 'js' => 'application/javascript; charset=utf-8',
            'ico' => 'image/x-icon', 'gif' => 'image/gif', 'png' => 'image/png',
            'svg' => 'image/svg+xml', 'woff2' => 'font/woff2', default => 'application/octet-stream',
        };
        $html = null;
        if (($release['legacy'] ?? false) && str_ends_with($file, '.html')) {
            $html = $this->legacyHtml((string) file_get_contents($file), $release['version']);
        }
        http_response_code($status);
        header('Content-Type: ' . $type);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-cache, must-revalidate');
        header('Content-Length: ' . ($html === null ? filesize($file) : strlen($html)));
        if ($method !== 'HEAD') {
            if ($html !== null) echo $html;
            else readfile($file);
        }
        return true;
    }

    /** Adapt one requested legacy page; never rewrite or scan the stored library. */
    private function legacyHtml(string $html, string $version): string
    {
        if (str_contains($html, '<meta name="app-version" content="')) {
            $html = preg_replace('~<meta name="app-version" content="[^"]*">~', '<meta name="app-version" content="' . $version . '">', $html);
            $html = preg_replace('~<meta name="app-version-storage-key" content="[^"]*">~', '', $html);
            $html = preg_replace('~<meta name="app-version-endpoint" content="[^"]*">~',
                '<meta name="app-version-endpoint" content="/api/docs-version"><meta name="app-version-storage-key" content="forum_pending_docs_version">', $html);
            $checker = AssetFingerprint::fingerprintedPath($this->projectRoot . '/public', '/assets/version_check.js');
            $html = preg_replace('~/assets/version_check(?:\.[a-f0-9]{12})?\.js~', $checker, $html);
        }
        return preg_replace('~href="/(?:assets/)?(favicon[^"/]*\.(?:ico|gif))"~', 'href="/docs/_assets/$1"', $html);
    }
}
