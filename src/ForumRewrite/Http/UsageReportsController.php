<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Tools\ToolsPageSupport;

/**
 * Tools → Usage log (any visitor: record, review, copy, upload) and, from
 * Stage 6, Tools → Usage reports (root operators). Application routes here
 * only while the "Usage reports" flag is on.
 */
final class UsageReportsController
{
    public function __construct(private readonly RouteServices $routeServices)
    {
    }

    public function log(): void
    {
        $this->routeServices->sendHtml(
            $this->routeServices->renderPageTemplate(
                'usage_log.php',
                ['toolNavOptions' => ToolsPageSupport::navOptions('usage-log')],
                'Usage log',
                'tools',
                ['/assets/usage_log_page.js'],
            ),
            200,
            $this->routeServices->noStoreHeaders(),
        );
    }
}
