<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\View\ThemeCatalog;

/** Renders /themes, the catalog of this site's themes. */
final class ThemeCatalogPageController
{
    /**
     * @param \Closure(string, array<string, mixed>, string, string, string[]): string $renderPageTemplate
     */
    public function __construct(
        private readonly \Closure $renderPageTemplate,
        private readonly string $projectRoot,
    ) {
    }

    public function render(): string
    {
        return ($this->renderPageTemplate)(
            'themes.php',
            ['themes' => ThemeCatalog::entries($this->projectRoot)],
            'Themes',
            'themes',
            ['/assets/theme_catalog.js'],
        );
    }
}
