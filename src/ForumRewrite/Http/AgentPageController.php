<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\ProfilePresentationContent;
use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\View\TemplateRenderer;

/**
 * Renders /agent/: prompts a visitor pastes into their own agent to be
 * introduced to the site, post as an agent, or run their own copy. The route
 * checks TemplateRenderer::agentPageEnabled() before this runs.
 */
final class AgentPageController
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
            'agent.php',
            ['agentPrompts' => ProfilePresentationContent::agentPrompts(SiteProfileRegistry::active(), TemplateRenderer::requestOrigin())],
            'Use with your agent',
            'agent',
            [],
        );
    }
}
