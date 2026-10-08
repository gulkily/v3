<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Tools\ToolsPageSupport;
use Throwable;

final class VisitorStatisticsController
{
    private const FORBIDDEN_MESSAGE = 'Visitor statistics require a server-authenticated root-approved identity.';
    /** @var array<string, array{hours:int,label:string}> */
    private const PERIOD_OPTIONS = [
        '24h' => ['hours' => 24, 'label' => 'Last 24 hours'],
        '7d' => ['hours' => 24 * 7, 'label' => 'Last 7 days'],
        '30d' => ['hours' => 24 * 30, 'label' => 'Last 30 days'],
        '90d' => ['hours' => 24 * 90, 'label' => 'Last 90 days'],
    ];

    /**
     * @param \Closure(): bool $viewerCanInspect
     * @param \Closure(): array{status:string,windows:array<int, array{visits:int,clients:int,authenticated_users:int}>} $summary
     * @param \Closure(int): array<string, mixed> $dashboardSummary
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly \Closure $viewerCanInspect,
        private readonly \Closure $summary,
        private readonly \Closure $dashboardSummary,
    ) {
    }

    /** @param array<string, mixed> $query */
    public function render(array $query = []): void
    {
        if (!($this->viewerCanInspect)()) {
            $this->routeServices->sendHtml(
                $this->routeServices->renderMessagePage('Visitor Statistics', 'Visitor Statistics', self::FORBIDDEN_MESSAGE, 'tools'),
                403,
            );
            return;
        }

        $periodKey = $this->periodKey($query);
        try {
            $summary = ($this->summary)();
            $dashboard = ($this->dashboardSummary)(self::PERIOD_OPTIONS[$periodKey]['hours']);
        } catch (Throwable) {
            $summary = ['status' => 'unavailable', 'windows' => []];
            $dashboard = ['status' => 'unavailable'];
        }

        $this->routeServices->sendHtml(
            $this->routeServices->renderPageTemplate(
                'visitor_statistics.php',
                [
                    'summary' => $summary,
                    'dashboard' => $dashboard,
                    'periodKey' => $periodKey,
                    'periodOptions' => self::PERIOD_OPTIONS,
                    'toolNavOptions' => ToolsPageSupport::navOptions('visitor-statistics'),
                ],
                'Visitor Statistics',
                'tools',
            ),
            200,
        );
    }

    /** @param array<string, mixed> $query */
    private function periodKey(array $query): string
    {
        $period = $query['period'] ?? null;

        return is_string($period) && array_key_exists($period, self::PERIOD_OPTIONS)
            ? $period
            : '30d';
    }
}
