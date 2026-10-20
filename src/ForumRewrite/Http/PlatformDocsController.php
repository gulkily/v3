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
        $categories = array_map(fn (array $entries): array => array_map(
            fn (array $entry): array => $entry + ['href' => $this->documentHref($entry['path'])],
            $entries,
        ), PlatformDocsCatalog::topics());

        $inventory = PlatformDocsCatalog::inventory($this->projectRoot);
        $matches = PlatformDocsCatalog::search($inventory, $query);
        $page = max(1, min((int) ($query['page'] ?? 1), max(1, (int) ceil(count($matches) / 40))));
        $isResults = array_intersect_key($query, array_flip(['q', 'folder', 'category', 'group', 'page'])) !== [];
        // The landing view carries an empty results shell for the browser to fill.
        $library = $isResults ? array_slice($matches, ($page - 1) * 40, 40) : [];
        $counts = PlatformDocsCatalog::counts($inventory);
        $total = count($matches);
        return $this->routeServices->renderPageTemplate(
            'platform_docs_index.php',
            ['library' => $library, 'counts' => $counts, 'isResults' => $isResults, 'query' => $query, 'page' => $page, 'total' => $total, 'categories' => $categories, 'platformDocsBrand' => ProfilePresentationContent::platformDocs(SiteProfileRegistry::active())],
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

        $filePath = PlatformDocsCatalog::resolveSource($this->projectRoot, $sourcePath);
        if ($filePath === null) {
            return null;
        }
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $size = filesize($filePath);
        $canView = $size !== false && $size <= PlatformDocsCatalog::MAX_DOCUMENT_BYTES && $extension !== 'pdf';
        $contents = $canView ? file_get_contents($filePath) : false;
        $documentHtml = $contents === false
            ? '<p>Preview unavailable. Download the document to read it.</p>'
            : ($extension === 'md' ? PublicMarkdownRenderer::render($contents, $sourcePath, $this->projectRoot)
                : '<pre tabindex="0" role="region" aria-label="Document source"><code>' . htmlspecialchars($contents, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>');

        $documentTitle = str_starts_with($sourcePath, 'docs/') ? substr($sourcePath, 5) : $sourcePath;
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
                'documentHtml' => $documentHtml,
                'downloadHref' => '/docs/_download/' . implode('/', array_map(rawurlencode(...), explode('/', $sourcePath))),
                'platformDocsBrand' => ProfilePresentationContent::platformDocs(SiteProfileRegistry::active()),
            ],
            $documentTitle . ' - Platform Docs',
            'docs',
            ['/assets/platform_docs.js'],
        );
    }

    public function missing(): string
    {
        return $this->routeServices->renderPageTemplate(
            'platform_docs_missing.php', [], 'Document not found', 'docs', ['/assets/platform_docs.js'],
        );
    }

    private function documentHref(string $sourcePath): string
    {
        return PlatformDocsCatalog::routeForPath($sourcePath) ?? '/docs/';
    }
}
