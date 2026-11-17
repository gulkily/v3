<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\ProfilePresentationContent;
use ForumRewrite\ReadModel\PromptLibrary;
use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\View\TemplateRenderer;
use PDO;

/**
 * Renders /agent/, the Prompts page: the site's own prompts a visitor pastes
 * into their agent (get introduced, post as an agent, run a copy), then the
 * library of prompts members share as #prompt threads. The route checks
 * TemplateRenderer::agentPageEnabled() before this runs.
 */
final class AgentPageController
{
    /**
     * @param \Closure(string, array<string, mixed>, string, string, string[]): string $renderPageTemplate
     */
    public function __construct(
        private readonly \Closure $renderPageTemplate,
        private readonly ?PDO $pdo = null,
    ) {
    }

    public function render(mixed $sort = null, mixed $tag = null): string
    {
        $sort = $sort === 'new' ? 'new' : 'top';
        $tag = is_string($tag) ? strtolower(trim($tag)) : '';
        $library = $this->pdo === null ? ['approved' => [], 'waiting' => []] : PromptLibrary::load($this->pdo, $sort);
        /** @var array<string, int> $categories */
        $categories = [];
        foreach ($library['approved'] as $prompt) {
            foreach ($prompt['prompt_tags'] as $category) {
                $categories[$category] = ($categories[$category] ?? 0) + 1;
            }
        }
        // Most used first; ties alphabetical, so the order is stable.
        uksort($categories, static fn ($a, $b): int => [$categories[$b], (string) $a] <=> [$categories[$a], (string) $b]);
        $categories = array_map(strval(...), array_slice(array_keys($categories), 0, 12));
        $shown = $library['approved'];
        if ($tag !== '') {
            $shown = array_values(array_filter($shown, static fn (array $prompt): bool => in_array($tag, $prompt['prompt_tags'], true)));
            // A category picked from a link stays offered even past the top dozen.
            if (!in_array($tag, $categories, true) && $shown !== []) $categories[] = $tag;
        }

        return ($this->renderPageTemplate)(
            'agent.php',
            [
                'agentPrompts' => ProfilePresentationContent::agentPrompts(SiteProfileRegistry::active(), TemplateRenderer::requestOrigin()),
                'sharedPrompts' => $shown,
                'sharedPromptCount' => count($library['approved']),
                'waitingPrompts' => $library['waiting'],
                'promptCategories' => $categories,
                'promptSort' => $sort,
                'promptTag' => $tag,
                'promptShareHref' => '/compose/thread?board_tags=' . PromptLibrary::TAG,
            ],
            'Prompts',
            'agent',
            // Like on a prompt card is the thread page's Like, so the same scripts.
            $shown === [] ? [] : [
                '/assets/openpgp_loader.js',
                '/assets/browser_signing.js',
                '/assets/outbox_store.js',
                '/assets/outbox_storage.js',
                '/assets/outbox_sender.js',
                '/assets/thread_reactions.js',
            ],
        );
    }
}
