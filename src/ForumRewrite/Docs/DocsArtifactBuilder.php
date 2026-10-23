<?php

declare(strict_types=1);

namespace ForumRewrite\Docs;

use ForumRewrite\Host\AssetFingerprint;
use ForumRewrite\Host\BrowserRuntimeAssetRenderer;
use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\View\FaviconRegistry;
use RuntimeException;

final class DocsArtifactBuilder
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly string $repositoryRoot,
        private readonly string $artifactRoot,
        private readonly string $version,
        private readonly ?\Closure $progress = null,
    ) {
    }

    public function build(): void
    {
        $renderer = new DocsPageRenderer($this->projectRoot, $this->repositoryRoot, $this->version);
        PlatformDocsCatalog::withSnapshot($this->projectRoot, function () use ($renderer): void {
            $inventory = PlatformDocsCatalog::inventory($this->projectRoot);
            $queries = PlatformDocsCatalog::browseQueries($inventory, PlatformDocsCatalog::PAGE_SIZE);
            $total = 3 + count($inventory) + count($queries);
            $done = 0;
            $render = function (string $route, array $paths) use ($renderer, &$done, $total): void {
                ($this->progress)?->__invoke('Rendering documentation (' . (++$done) . '/' . $total . '): ' . $route . '.');
                $html = $renderer->render($route);
                foreach ($paths as $path) {
                    $this->write($path, $html);
                }
            };
            $render('/docs/', ['docs.html', 'docs/index.html']);
            foreach ($inventory as $entry) {
                $render($entry['href'], [ltrim($entry['href'], '/') . '.html']);
            }
            foreach (['_missing', '_search'] as $shell) {
                $render('/docs/' . $shell . '.html', ['docs/' . $shell . '.html']);
            }
            foreach ($queries as $query) {
                $render('/docs/?' . http_build_query($query), [ltrim(PlatformDocsCatalog::browsePath($query), '/')]);
            }
            foreach ($inventory as $entry) {
                $relative = 'docs/_download/' . $entry['path'] . '.bin';
                $this->directory(dirname($this->artifactRoot . '/' . $relative));
                $source = PlatformDocsCatalog::resolveSource($this->projectRoot, $entry['path']);
                if ($source === null || !copy($source, $this->artifactRoot . '/' . $relative)
                    || hash_file('sha256', $this->artifactRoot . '/' . $relative) !== $entry['hash']) {
                    throw new RuntimeException('Documentation changed during publication; retry.');
                }
            }
            $this->write('docs/_search.json', json_encode(['entries' => $inventory], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
        });
        AssetFingerprint::copyFingerprintedAssets($this->projectRoot . '/public', $this->artifactRoot);
        $profile = SiteProfileRegistry::active();
        foreach (array_unique(['/favicon.ico', '/favicon.gif', FaviconRegistry::resolve($profile)]) as $path) {
            if (is_file($this->projectRoot . '/public' . $path)) {
                $this->directory(dirname($this->artifactRoot . $path));
                if (!copy($this->projectRoot . '/public' . $path, $this->artifactRoot . $path)) {
                    throw new RuntimeException('Cannot publish docs favicon.');
                }
            }
        }
        $this->write('service_worker.js', BrowserRuntimeAssetRenderer::serviceWorker(
            (string) file_get_contents($this->projectRoot . '/public/service_worker.js'), $profile));
        $this->write('manifest.webmanifest', BrowserRuntimeAssetRenderer::manifest($profile));
    }

    private function directory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot create docs candidate directory.');
        }
    }

    private function write(string $relative, string $contents): void
    {
        $path = $this->artifactRoot . '/' . $relative;
        $this->directory(dirname($path));
        if (file_put_contents($path, $contents) !== strlen($contents)) {
            throw new RuntimeException('Cannot write docs candidate: ' . $relative);
        }
    }
}
