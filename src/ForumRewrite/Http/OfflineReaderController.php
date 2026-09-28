<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

final class OfflineReaderController
{
    public function __construct(
        private readonly RouteServices $routeServices,
    ) {
    }

    public function reader(): string
    {
        return $this->routeServices->renderPageTemplate(
            'offline_reader.php',
            [],
            'Offline Reading',
            'board',
            ['/assets/sql-wasm.js', '/assets/offline_reader.js'],
        );
    }

    public function health(): string
    {
        return $this->routeServices->renderPageTemplate(
            'offline_health.php',
            [],
            'Offline Reading',
            'tools',
        );
    }
}
