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

    public function index(): string
    {
        $categories = [];
        foreach (PlatformDocsCatalog::entries() as $entry) {
            $entry['href'] = $this->documentHref($entry['path']);
            $categories[$entry['category']][] = $entry;
        }

        return $this->routeServices->renderPageTemplate(
            'platform_docs_index.php',
            ['categories' => $categories, 'platformDocsBrand' => ProfilePresentationContent::platformDocs(SiteProfileRegistry::active())],
            'Platform Docs',
            'docs',
        );
    }

    public function document(string $encodedPath): ?string
    {
        $sourcePath = PlatformDocsCatalog::normalizePath('docs/' . $encodedPath);
        if ($sourcePath === null) {
            return null;
        }

        $filePath = PlatformDocsCatalog::resolvePath($this->projectRoot, $sourcePath);
        $markdown = $filePath === null ? false : file_get_contents($filePath);
        if ($markdown === false) {
            return null;
        }

        return $this->routeServices->renderPageTemplate(
            'platform_docs_document.php',
            [
                'sourcePath' => $sourcePath,
                'documentHtml' => PublicMarkdownRenderer::render($markdown),
                'platformDocsBrand' => ProfilePresentationContent::platformDocs(SiteProfileRegistry::active()),
            ],
            'Platform Docs',
            'docs',
        );
    }

    private function documentHref(string $sourcePath): string
    {
        return PlatformDocsCatalog::routeForPath($sourcePath) ?? '/docs/';
    }
}
