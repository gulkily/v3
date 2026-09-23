<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Llm\SqliteLlmExchangeStore;
use ForumRewrite\Tools\ToolsPageSupport;

/**
 * Seventh Phase 2 slice (see docs/plans/codebase_cleanup_audit_plan_v1.md
 * and docs/plans/codebase_cleanup_audit_findings_v1.md): /tools/llm-exchanges/
 * and /tools/llm-exchanges/{id}, deferred from the "simple /tools pages"
 * slice for its extra collaborators.
 *
 * Both viewerCanInspectLlmExchanges() and llmExchangeStore() stay on
 * Application and are passed in as bound closures rather than moved: the
 * viewer check is session-bound (reads $_SESSION via
 * resolveViewerProfileFromIdentityHint()), and both are also called from
 * board/thread rendering (showing LLM exchange indicators on posts) and
 * elsewhere, outside this slice's scope. Calling through the closure
 * reuses Application's own lazy-cached store instance rather than
 * constructing a second one.
 */
final class LlmExchangesController
{
    private const FORBIDDEN_MESSAGE = 'Only approved users can view LLM exchanges, and the exchange UI must be enabled.';

    /**
     * @param \Closure(): bool $viewerCanInspect
     * @param \Closure(): (SqliteLlmExchangeStore|null) $llmExchangeStore
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly \Closure $viewerCanInspect,
        private readonly \Closure $llmExchangeStore,
    ) {
    }

    public function list(): void
    {
        if (!($this->viewerCanInspect)()) {
            $this->routeServices->sendHtml(
                $this->routeServices->renderMessagePage('LLM Exchanges', 'LLM Exchanges', self::FORBIDDEN_MESSAGE, 'tools'),
                403
            );
            return;
        }

        $this->routeServices->sendHtml(
            $this->routeServices->renderPageTemplate(
                'llm_exchanges.php',
                [
                    'exchanges' => ($this->llmExchangeStore)()?->recent() ?? [],
                    'toolNavOptions' => ToolsPageSupport::navOptions('llm-exchanges'),
                ],
                'LLM Exchanges',
                'tools'
            ),
            200
        );
    }

    public function detail(int $exchangeId): void
    {
        if (!($this->viewerCanInspect)()) {
            $this->routeServices->sendHtml(
                $this->routeServices->renderMessagePage('LLM Exchange', 'LLM Exchange', self::FORBIDDEN_MESSAGE, 'tools'),
                403
            );
            return;
        }

        $exchange = ($this->llmExchangeStore)()?->find($exchangeId);
        if ($exchange === null) {
            $this->routeServices->sendHtml(
                $this->routeServices->renderMessagePage('Not Found', 'Not Found', 'LLM exchange not found.', 'tools'),
                404
            );
            return;
        }

        $this->routeServices->sendHtml(
            $this->routeServices->renderPageTemplate(
                'llm_exchange.php',
                [
                    'exchange' => $exchange,
                    'toolNavOptions' => ToolsPageSupport::navOptions('llm-exchanges'),
                ],
                'LLM Exchange ' . $exchangeId,
                'tools'
            ),
            200
        );
    }
}
