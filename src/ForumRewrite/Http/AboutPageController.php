<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\SiteConfig;

/**
 * Renders the static /about page. First slice of moving route-group
 * rendering out of Application.php - see
 * docs/plans/codebase_cleanup_audit_plan_v1.md (Phase 2).
 *
 * Takes Application::renderPageTemplate() as a bound closure (via first-class
 * callable syntax at the call site) rather than depending on Application
 * itself, so this class stays free of the surrounding god-object's surface
 * area. Later slices with more shared-state dependencies may need a
 * different seam.
 */
final class AboutPageController
{
    /**
     * @param \Closure(string, array<string, mixed>, string, string, string[]): string $renderPageTemplate
     */
    public function __construct(private readonly \Closure $renderPageTemplate)
    {
    }

    public function render(): string
    {
        return ($this->renderPageTemplate)(
            'about.php',
            ['siteName' => SiteConfig::siteName()],
            'About',
            'about',
            [],
        );
    }
}
