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
            [
                'runtimeUrl' => $this->routeServices->assetPath('/assets/sql-wasm.wasm'),
                'readerRevision' => $this->routeServices->assetPath('/assets/offline_reader.js'),
            ],
            'Offline Reading',
            'board',
            [
                '/assets/sql-wasm.js',
                '/assets/openpgp_loader.js',
                '/assets/browser_signing.js',
                '/assets/outbox_store.js',
                '/assets/outbox_storage.js',
                '/assets/outbox_intent.js',
                '/assets/outbox_sender.js',
                '/assets/offline_reader.js',
            ],
        );
    }

    public function health(): string
    {
        return $this->routeServices->renderPageTemplate(
            'offline_health.php',
            ['runtimeUrl' => $this->routeServices->assetPath('/assets/sql-wasm.wasm')],
            'Offline Reading',
            'tools',
            ['/assets/sql-wasm.js', '/assets/offline_health.js'],
        );
    }
}
