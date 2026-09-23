<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Tools\ToolsPageSupport;

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
 */
final class ToolsPageController
{
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly FeatureFlagEvaluator $featureFlags,
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
}
