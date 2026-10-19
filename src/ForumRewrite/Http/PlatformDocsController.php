<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Docs\PlatformDocsCatalog;
use ForumRewrite\Docs\PublicMarkdownRenderer;
use ForumRewrite\ProfilePresentationContent;
use ForumRewrite\SiteProfileRegistry;

final class PlatformDocsController
{
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly string $projectRoot,
    ) {
    }

    public function index(array $query = []): string
    {
        $categories = [];
        foreach (PlatformDocsCatalog::entries() as $entry) {
            $entry['href'] = $this->documentHref($entry['path']);
            $categories[$entry['category']][] = $entry;
        }

        $inventory = PlatformDocsCatalog::inventory($this->projectRoot);
        $matches = PlatformDocsCatalog::search($inventory, $query);
        $page = max(1, min((int) ($query['page'] ?? 1), max(1, (int) ceil(count($matches) / 40))));
        $library = array_slice($matches, ($page - 1) * 40, 40);
        $folders = array_values(array_unique(array_column($inventory, 'folder')));
        sort($folders);
        $total = count($matches);
        return $this->routeServices->renderPageTemplate(
            'platform_docs_index.php',
            ['library' => $library, 'folders' => $folders, 'inventory' => $inventory, 'query' => $query, 'page' => $page, 'total' => $total, 'categories' => $categories, 'platformDocsBrand' => ProfilePresentationContent::platformDocs(SiteProfileRegistry::active())],
            'Platform Docs',
            'docs',
            ['/assets/platform_docs.js'],
        );
    }

    public function document(string $encodedPath, array $query = []): ?string
    {
        $sourcePath = PlatformDocsCatalog::sourceForRoute($encodedPath);
        if ($sourcePath === null) {
            return null;
        }

        $filePath = PlatformDocsCatalog::resolvePath($this->projectRoot, $sourcePath);
        $markdown = $filePath === null ? false : file_get_contents($filePath);
        if ($markdown === false) {
            if (PlatformDocsCatalog::resolveSource($this->projectRoot, $sourcePath) === null) {
                return null;
            }
            $markdown = "# Document unavailable\n\nThis document exceeds the viewing limit.";
        }

        $documentTitle = substr($sourcePath, strlen('docs/'));
        foreach (PlatformDocsCatalog::entries() as $entry) {
            if ($entry['path'] === $sourcePath) {
                $documentTitle = $entry['title'];
                break;
            }
        }

        return $this->routeServices->renderPageTemplate(
            'platform_docs_document.php',
            [
                'sourcePath' => $sourcePath,
                'returnQuery' => array_filter(array_intersect_key($query, array_flip(['q', 'folder', 'category', 'group', 'page'])), 'is_scalar'),
                'documentHtml' => PublicMarkdownRenderer::render($markdown, $sourcePath, $this->projectRoot),
                'platformDocsBrand' => ProfilePresentationContent::platformDocs(SiteProfileRegistry::active()),
            ],
            $documentTitle . ' - Platform Docs',
            'docs',
            ['/assets/platform_docs.js'],
        );
    }

    private function documentHref(string $sourcePath): string
    {
        return PlatformDocsCatalog::routeForPath($sourcePath) ?? '/docs/';
    }
}
