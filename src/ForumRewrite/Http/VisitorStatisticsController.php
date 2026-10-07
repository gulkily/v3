<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Tools\ToolsPageSupport;
use Throwable;

final class VisitorStatisticsController
{
    private const FORBIDDEN_MESSAGE = 'Visitor statistics require a server-authenticated root-approved identity.';

    /**
     * @param \Closure(): bool $viewerCanInspect
     * @param \Closure(): array{status:string,windows:array<int, array{visits:int,clients:int,authenticated_users:int}>} $summary
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly \Closure $viewerCanInspect,
        private readonly \Closure $summary,
    ) {
    }

    public function render(): void
    {
        if (!($this->viewerCanInspect)()) {
            $this->routeServices->sendHtml(
                $this->routeServices->renderMessagePage('Visitor Statistics', 'Visitor Statistics', self::FORBIDDEN_MESSAGE, 'tools'),
                403,
            );
            return;
        }

        try {
            $summary = ($this->summary)();
        } catch (Throwable) {
            $summary = ['status' => 'unavailable', 'windows' => []];
        }

        $this->routeServices->sendHtml(
            $this->routeServices->renderPageTemplate(
                'visitor_statistics.php',
                [
                    'summary' => $summary,
                    'toolNavOptions' => ToolsPageSupport::navOptions('visitor-statistics'),
                ],
                'Visitor Statistics',
                'tools',
            ),
            200,
        );
    }
}
