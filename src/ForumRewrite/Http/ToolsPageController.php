<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
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
                'toolPages' => [
                    [
                        'label' => 'Activity',
                        'href' => '/activity/',
                        'description' => 'Recent forum activity across content, approvals, and identity events.',
                    ],
                    [
                        'label' => 'Forte',
                        'href' => '/forte',
                        'description' => 'Classic three-pane newsreader view of the whole board - folders, thread list, and preview.',
                    ],
                    [
                        'label' => 'Bookmarklets',
                        'href' => '/tools/bookmarklets/',
                        'description' => 'Bookmarklet links for clipping URLs and selections straight into Compose Thread.',
                    ],
                    [
                        'label' => 'Backup',
                        'href' => '/tools/backup/',
                        'description' => 'Portable downloads of the repository and current read-model database.',
                    ],
                    [
                        'label' => 'SQLite Viewer',
                        'href' => '/tools/sqlite/',
                        'description' => 'Inspect the published SQLite read model in your browser.',
                    ],
                    [
                        'label' => 'LLM Exchanges',
                        'href' => '/tools/llm-exchanges/',
                        'description' => 'Review private LLM prompts and responses chronologically.',
                    ],
                    [
                        'label' => 'System State',
                        'href' => '/tools/codebase/',
                        'description' => 'Current application version, repository head, and read-model health.',
                    ],
                    [
                        'label' => 'Feature Flags',
                        'href' => '/tools/feature-flags/',
                        'description' => 'Registered site feature flags, defaults, effective values, and override sources.',
                    ],
                    [
                        'label' => 'Account',
                        'href' => '/account/key/',
                        'description' => 'Browser key setup, identity linking, and technical account details.',
                    ],
                ],
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
                ],
                'toolNavOptions' => ToolsPageSupport::navOptions('bookmarklets'),
            ],
            'Bookmarklets',
            'tools',
            ['/assets/tools_bookmarklets.js'],
        );
    }

    public function sqliteViewer(): string
    {
        return $this->routeServices->renderPageTemplate(
            'sqlite_viewer.php',
            [
                'toolNavOptions' => ToolsPageSupport::navOptions('sqlite'),
            ],
            'SQLite Viewer',
            'tools',
            ['/assets/sql-wasm.js', '/assets/sqlite_viewer.js'],
        );
    }

    public function featureFlags(): string
    {
        return $this->routeServices->renderPageTemplate(
            'feature_flags.php',
            [
                'flags' => $this->featureFlags->all(),
                'toolNavOptions' => ToolsPageSupport::navOptions('feature-flags'),
            ],
            'Feature Flags',
            'tools',
            ['/assets/feature_flags.js'],
        );
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

        try {
            $result = $this->routeServices->writer()->setFeatureFlag($this->routeServices->requestData($query));
            ($this->invalidateFeatureFlagsCache)();
            $response = "status=ok\n"
                . "key={$result['key']}\n"
                . "site_value={$result['site_value']}\n"
                . "effective_value={$result['effective_value']}\n"
                . "source={$result['source']}\n"
                . "wrote_record={$result['wrote_record']}\n";
            if (isset($result['commit_sha'])) {
                $response .= "commit_sha={$result['commit_sha']}\n";
            }

            $this->routeServices->sendText($response, 200, $this->routeServices->serverTimingHeaders($result));
        } catch (RuntimeException $exception) {
            $this->routeServices->sendText("error=" . $exception->getMessage() . "\n", 400);
        }
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

        try {
            $result = $this->routeServices->writer()->setFeatureFlag($this->routeServices->requestData($query));
            ($this->invalidateFeatureFlagsCache)();
            $message = 'Feature flag updated.';
            if (isset($result['commit_sha'])) {
                $message .= ' Commit: ' . $result['commit_sha'];
            }

            $this->routeServices->sendRedirect('/tools/feature-flags/', $message, activeSection: 'tools');
        } catch (RuntimeException $exception) {
            $this->routeServices->sendHtml(
                $this->routeServices->renderMessagePage(
                    'Feature Flag Error',
                    'Feature Flag Error',
                    $exception->getMessage(),
                    'tools'
                ),
                400
            );
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
