<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\Tools\ToolsPageSupport;
use RuntimeException;

/**
 * Sixth Phase 2 slice of the Application.php decomposition (see
 * docs/plans/codebase_cleanup_audit_plan_v1.md and
 * docs/plans/codebase_cleanup_audit_findings_v1.md): the simple, static
 * /tools/* pages - the index, bookmarklets, the SQLite viewer shell, and
 * the feature-flags listing. /tools/codebase (collectCodebaseState(), a
 * half-dozen collaborators including ExecutionLock and
 * ReadModelStaleMarker) and /tools/llm-exchanges/* (needs the LLM exchange
 * store plus session-bound viewer approval checks) are meaningfully more
 * complex and deliberately left on Application for a future slice.
 *
 * submitFeatureFlagApi()/submitFeatureFlagSubmit() (added later, alongside
 * the /api/apply_thread_tag slice - see
 * docs/plans/codebase_cleanup_audit_plan_v1.md) are the POST twins of
 * featureFlags(): /api/set_feature_flag and the /tools/feature-flags/ form
 * submit, which both landed here rather than a separate controller since
 * they're the same page's write side. viewerCanManageFeatureFlags() had
 * exactly these two callers and moved here wholesale.
 * resolveViewerProfileFromIdentityHint() and invalidateFeatureFlagsCache()
 * (Application's own memoized-cache reset - preserved exactly as-is, not a
 * behavior change) stay on Application and are passed in as bound closures.
 */
final class ToolsPageController
{
    /**
     * @param \Closure(): (array<string, mixed>|null) $resolveViewerProfileFromIdentityHint
     * @param \Closure(): void $invalidateFeatureFlagsCache
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly FeatureFlagEvaluator $featureFlags,
        private readonly \Closure $resolveViewerProfileFromIdentityHint,
        private readonly \Closure $invalidateFeatureFlagsCache,
    ) {
    }

    public function index(): string
    {
        return $this->routeServices->renderPageTemplate(
            'tools.php',
            [
                'toolPages' => array_map(
                    static fn (array $tool): array => [
                        'label' => $tool['label'],
                        'href' => $tool['href'],
                        'description' => $tool['description'],
                    ],
                    ToolsPageSupport::registry(),
                ),
                'toolNavOptions' => ToolsPageSupport::navOptions(null),
            ],
            'Tools',
            'tools',
        );
    }

    public function bookmarklets(): string
    {
        return $this->routeServices->renderPageTemplate(
            'bookmarklets.php',
            [
                'bookmarklets' => [
                    [
                        'label' => '+URL',
                        'mode' => 'same-window',
                        'description' => 'Open Compose Thread in this tab with the current page URL in the body.',
                        'bookmarklet_kind' => 'url',
                    ],
                    [
                        'label' => 'Clip',
                        'mode' => 'same-window',
                        'description' => 'Open Compose Thread in this tab with selected text plus source title and URL.',
                        'bookmarklet_kind' => 'clip',
                    ],
                    [
                        'label' => 'Rip',
                        'mode' => 'same-window',
                        'description' => 'Open Compose Thread in this tab with only the selected text.',
                        'bookmarklet_kind' => 'selection',
                    ],
                    [
                        'label' => 'Clip',
                        'mode' => 'new-window',
                        'description' => 'Open Compose Thread in a new window with selected text plus source title and URL.',
                        'bookmarklet_kind' => 'clip',
                    ],
                    [
                        'label' => 'Rip',
                        'mode' => 'new-window',
                        'description' => 'Open Compose Thread in a new window with only the selected text.',
                        'bookmarklet_kind' => 'selection',
                    ],
                    [
                        'label' => 'Tweet',
                        'mode' => 'same-window',
                        'description' => 'On x.com, open Compose Thread in this tab with the tweet text, author, and link filled in.',
                        'bookmarklet_kind' => 'tweet',
                    ],
                    [
                        'label' => 'Tweet',
                        'mode' => 'new-window',
                        'description' => 'On x.com, open Compose Thread in a new window with the tweet text, author, and link filled in.',
                        'bookmarklet_kind' => 'tweet',
                    ],
                ],
                'toolNavOptions' => ToolsPageSupport::navOptions('bookmarklets'),
            ],
            'Bookmarklets',
            'tools',
            ['/assets/tools_bookmarklets.js'],
        );
    }

    public function outbox(): string
    {
        return $this->routeServices->renderPageTemplate(
            'outbox.php',
            ['toolNavOptions' => ToolsPageSupport::navOptions('outbox')],
            'Outbox',
            'tools',
            [
                '/assets/openpgp_loader.js',
                '/assets/browser_signing.js',
                '/assets/outbox_store.js',
                '/assets/outbox_storage.js',
                '/assets/outbox_sender.js',
                '/assets/outbox.js',
            ],
        );
    }

    public function sqliteViewer(): string
    {
        return $this->routeServices->renderPageTemplate(
            'sqlite_viewer.php',
            [
                'toolNavOptions' => ToolsPageSupport::navOptions('sqlite'),
                'runtimeUrl' => $this->routeServices->assetPath('/assets/sql-wasm.wasm'),
            ],
            'SQLite Viewer',
            'tools',
            ['/assets/sql-wasm.js', '/assets/sqlite_viewer.js'],
        );
    }

    public function featureFlags(): string
    {
        $viewerProfile = ($this->resolveViewerProfileFromIdentityHint)();

        return $this->routeServices->renderPageTemplate(
            'feature_flags.php',
            [
                'flags' => $this->featureFlags->all(),
                'registry' => new FeatureFlagRegistry(),
                'toolNavOptions' => ToolsPageSupport::navOptions('feature-flags'),
                'canManageFeatureFlags' => $this->viewerCanManageFeatureFlags($viewerProfile),
            ],
            'Feature Flags',
            'tools',
            ['/assets/openpgp_loader.js', '/assets/browser_signing.js', '/assets/feature_flags.js'],
        );
    }

    /**
     * @param array<string, mixed> $query
     */
    public function prepareFeatureFlagChangeApi(string $method, array $query): void
    {
        $this->handlePreparedFeatureFlagChange($method, $query, false);
    }

    /**
     * @param array<string, mixed> $query
     */
    public function finalizeFeatureFlagChangeApi(string $method, array $query): void
    {
        $this->handlePreparedFeatureFlagChange($method, $query, true);
    }

    /**
     * @param array<string, mixed> $query
     */
    public function submitFeatureFlagApi(string $method, array $query): void
    {
        if ($method !== 'POST') {
            $this->routeServices->sendText("method not allowed\n", 405);
            return;
        }

        $viewerProfile = ($this->resolveViewerProfileFromIdentityHint)();
        if (!$this->viewerCanManageFeatureFlags($viewerProfile)) {
            $this->routeServices->sendText("error=Feature flag changes require a root-approved identity.\n", 403);
            return;
        }

        $this->routeServices->sendText("error=Feature flag changes require a browser signature.\n", 400);
    }

    /**
     * @param array<string, mixed> $query
     */
    public function submitFeatureFlagSubmit(array $query): void
    {
        $viewerProfile = ($this->resolveViewerProfileFromIdentityHint)();
        if (!$this->viewerCanManageFeatureFlags($viewerProfile)) {
            $this->routeServices->sendHtml(
                $this->routeServices->renderMessagePage(
                    'Forbidden',
                    'Forbidden',
                    'Feature flag changes require a root-approved identity.',
                    'tools'
                ),
                403
            );
            return;
        }

        $this->routeServices->sendHtml(
            $this->routeServices->renderMessagePage(
                'Feature Flag Signature Required',
                'Feature Flag Signature Required',
                'Feature flag changes require a browser signature. Enable JavaScript and use a root-approved browser identity.',
                'tools'
            ),
            400
        );
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handlePreparedFeatureFlagChange(string $method, array $query, bool $finalize): void
    {
        if ($method !== 'POST') {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405, $this->routeServices->noStoreHeaders());
            return;
        }
        $viewerProfile = ($this->resolveViewerProfileFromIdentityHint)();
        if (!$this->viewerCanManageFeatureFlags($viewerProfile)) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'Feature flag changes require a root-approved identity.'], 403, $this->routeServices->noStoreHeaders());
            return;
        }
        try {
            $operatorIdentityId = (string) ($viewerProfile['identity_id'] ?? '');
            $input = $this->routeServices->requestData($query);
            $result = $finalize
                ? $this->routeServices->writer()->finalizePreparedFeatureFlagChange($input, $operatorIdentityId)
                : $this->routeServices->writer()->prepareFeatureFlagChange($input, $operatorIdentityId);
            if ($finalize && ($result['wrote_record'] ?? '') === 'yes') {
                ($this->invalidateFeatureFlagsCache)();
            }
            $this->routeServices->sendJson($result, 200, $this->routeServices->noStoreHeaders());
        } catch (RuntimeException $exception) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => $exception->getMessage()], 400, $this->routeServices->noStoreHeaders());
        }
    }

    /**
     * @param array<string, mixed>|null $viewerProfile
     */
    private function viewerCanManageFeatureFlags(?array $viewerProfile): bool
    {
        return $viewerProfile !== null
            && ((int) ($viewerProfile['is_approved'] ?? 0)) === 1
            && (string) ($viewerProfile['approved_by_label'] ?? '') === 'root';
    }
}
