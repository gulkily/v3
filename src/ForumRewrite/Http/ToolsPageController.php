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

    public function fastmod(?string $notice = null): string
    {
        $viewer = ($this->resolveViewerProfileFromIdentityHint)();
        $data = ['rubrics' => [], 'enabledRubrics' => [], 'settingsError' => null];
        try {
            $data = $this->routeServices->fastmodSettingsView() + ['settingsError' => null];
        } catch (\Throwable $error) {
            $data['settingsError'] = $error->getMessage();
        }
        return $this->routeServices->renderPageTemplate('fastmod.php', $data + $this->routeServices->localMetaView() + [
            'localNotice' => $notice,
            'toolNavOptions' => ToolsPageSupport::navOptions('fastmod'),
            'canManageFastmod' => $this->viewerCanManageFeatureFlags($viewer),
        ], 'Fastmod', 'tools', ['/assets/openpgp_loader.js', '/assets/browser_signing.js', '/assets/fastmod.js']);
    }

    /** POST /tools/fastmod/ with `action=retrain` queues local meta training, like Deploy's queue button. */
    public function fastmodSubmit(array $query): void
    {
        $headers = $this->routeServices->noStoreHeaders();
        if (!SameOriginRequest::check()) {
            $this->routeServices->sendHtml($this->fastmod('Same-origin request required.'), 403, $headers);
            return;
        }
        if (!$this->routeServices->securityReviewAllowed()) {
            $this->routeServices->sendHtml($this->fastmod('Retraining requires a signed-in root-approved operator.'), 403, $headers);
            return;
        }
        if ((string) ($this->routeServices->requestData($query)['action'] ?? '') !== 'retrain') {
            $this->routeServices->sendHtml($this->fastmod('Unknown action.'), 400, $headers);
            return;
        }
        try {
            $task = $this->routeServices->queueLocalMetaTrain();
            $notice = ($task['enqueued'] ?? true) ? 'Retraining queued for the task worker.' : 'Retraining is already queued.';
            $this->routeServices->sendHtml($this->fastmod($notice), 200, $headers);
        } catch (\Throwable $error) {
            $this->routeServices->sendHtml($this->fastmod('Could not queue retraining: ' . $error->getMessage()), 500, $headers);
        }
    }

    public function fastmodChangeApi(string $method, array $query, bool $finalize): void
    {
        if ($method !== 'POST') {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405, $this->routeServices->noStoreHeaders());
            return;
        }
        $viewer = ($this->resolveViewerProfileFromIdentityHint)();
        if (!$this->viewerCanManageFeatureFlags($viewer)) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'Fastmod settings require a root-approved identity.'], 403, $this->routeServices->noStoreHeaders());
            return;
        }
        try {
            $result = $this->routeServices->changeFastmodRubrics($this->routeServices->requestData($query),
                (string) ($viewer['identity_id'] ?? ''), $finalize);
            $this->routeServices->sendJson($result, 200, $this->routeServices->noStoreHeaders());
        } catch (\RuntimeException | \InvalidArgumentException $error) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => $error->getMessage()], 400, $this->routeServices->noStoreHeaders());
        }
    }

    public function highTrafficApi(string $method, array $query, bool $finalize): void
    {
        if ($method!=='POST') { $this->routeServices->sendJson(['status'=>'error','error'=>'method not allowed'],405,$this->routeServices->noStoreHeaders());return; }
        $viewer=($this->resolveViewerProfileFromIdentityHint)();
        if (!$this->viewerCanManageFeatureFlags($viewer)) { $this->routeServices->sendJson(['status'=>'error','error'=>'An approved operator identity is required.'],403,$this->routeServices->noStoreHeaders());return; }
        try {
            $result=$this->routeServices->changeHighTraffic($this->routeServices->requestData($query),(string)$viewer['identity_id'],$finalize);
            $this->routeServices->sendJson($result,200,$this->routeServices->noStoreHeaders());
        } catch (\Throwable $error) { $this->routeServices->sendJson(['status'=>'error','error'=>$error->getMessage()],400,$this->routeServices->noStoreHeaders()); }
    }

    public function index(): string
    {
        return $this->routeServices->renderPageTemplate(
            'tools.php',
            [
                'toolPages' => ToolsPageSupport::registry(),
                'toolGroups' => ToolsPageSupport::groups(),
            ],
            'Tools',
            'tools',
        );
    }

    /**
     * /tools/preferences/: the settings this browser keeps, in one place. The
     * page is the same for everyone; preferences.php reads storage before
     * paint and preferences.js saves changes.
     */
    public function preferences(): string
    {
        return $this->routeServices->renderPageTemplate(
            'preferences.php',
            [
                'preferenceThemes' => \ForumRewrite\View\ThemeRegistry::permitted(\ForumRewrite\SiteProfileRegistry::active()['permittedThemes'], $this->featureFlags),
                'threadDensityEnabled' => $this->featureFlags->isEnabled(FeatureFlagRegistry::THREAD_DENSITY_TOGGLE_ENABLED),
                'readingMarginsEnabled' => $this->featureFlags->isEnabled(FeatureFlagRegistry::READING_MARGINS_ENABLED),
                'draggableWindowsEnabled' => $this->featureFlags->isEnabled(FeatureFlagRegistry::DRAGGABLE_WINDOWS_ENABLED),
                'openBesideEnabled' => $this->featureFlags->isEnabled(FeatureFlagRegistry::OPEN_BESIDE_ENABLED),
                'usageLogEnabled' => $this->featureFlags->isEnabled(FeatureFlagRegistry::USAGE_REPORTS_ENABLED),
                'toolNavOptions' => ToolsPageSupport::navOptions('preferences'),
            ],
            'Preferences',
            'tools',
            ['/assets/preferences.js'],
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
                        'description' => 'Current page URL.',
                        'bookmarklet_kind' => 'url',
                    ],
                    [
                        'label' => 'Clip',
                        'mode' => 'same-window',
                        'description' => 'Selected text plus source title and URL.',
                        'bookmarklet_kind' => 'clip',
                    ],
                    [
                        'label' => 'Rip',
                        'mode' => 'same-window',
                        'description' => 'Selected text only.',
                        'bookmarklet_kind' => 'selection',
                    ],
                    [
                        'label' => 'Clip',
                        'mode' => 'new-window',
                        'description' => 'Selected text plus source title and URL.',
                        'bookmarklet_kind' => 'clip',
                    ],
                    [
                        'label' => 'Rip',
                        'mode' => 'new-window',
                        'description' => 'Selected text only.',
                        'bookmarklet_kind' => 'selection',
                    ],
                    [
                        'label' => 'Tweet',
                        'mode' => 'same-window',
                        'description' => 'Tweet text, author, and link from x.com.',
                        'bookmarklet_kind' => 'tweet',
                    ],
                    [
                        'label' => 'Tweet',
                        'mode' => 'new-window',
                        'description' => 'Tweet text, author, and link from x.com.',
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

    /** Read-only: which thread tags unlock which post tags, and every post marked claimed, resolved or closed. */
    public function tagUnlocks(): string
    {
        $enabled = $this->featureFlags->isEnabled(FeatureFlagRegistry::TAG_UNLOCKS_ENABLED);

        return $this->routeServices->renderPageTemplate(
            'tag_unlocks.php',
            [
                'tagsets' => \ForumRewrite\Support\Tagsets::forApplication()->all(),
                'markedPosts' => $enabled ? \ForumRewrite\Tools\MarkedPostsQuery::byState($this->routeServices->pdo()) : [],
                'tagUnlocksOn' => $enabled,
                'toolNavOptions' => ToolsPageSupport::navOptions('tag-unlocks'),
            ],
            'Tag Unlocks',
            'tools',
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

    public function featureFlagNavigation(string $method): void
    {
        if ($method !== 'GET') {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405, $this->routeServices->noStoreHeaders());
            return;
        }
        $this->routeServices->sendJson(
            ['status' => 'ok'] + $this->routeServices->navigationState('tools'),
            200,
            $this->routeServices->noStoreHeaders(),
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
                'attributionCutoff' => $this->routeServices->attributionCutoff(),
            ],
            'Feature Flags',
            'tools',
            ['/assets/openpgp_loader.js', '/assets/browser_signing.js', '/assets/feature_flags.js'],
        );
    }

    public function operations(?string $tourNotice = null): string
    {
        $canManage = $this->viewerCanManageFeatureFlags(($this->resolveViewerProfileFromIdentityHint)());
        $status = $this->routeServices->highTrafficStatus();
        try {
            $tour = \ForumRewrite\Tour\TourSnapshots::forProject($this->routeServices->projectRoot())->status();
        } catch (\Throwable) {
            $tour = [];
        }

        return $this->routeServices->renderPageTemplate(
            'operations.php',
            [
                'toolNavOptions' => ToolsPageSupport::navOptions('operations'),
                'canManageOperations' => $canManage,
                'highTrafficEnabled' => ($status['effective_mode'] ?? '') === 'high-traffic',
                'highTrafficReason' => \ForumRewrite\Host\HighTrafficAutomation::statusReason($status),
                'highTrafficTurnOn' => ($status['turn_on_automatically'] ?? false) === true,
                'highTrafficTurnOff' => ($status['turn_off_automatically'] ?? false) === true,
                'highTrafficTurnOffUnavailable' => $status['turn_off_unavailable'] ?? null,
                'highTrafficError' => $canManage ? ($status['error'] ?? null) : null,
                'tourStatus' => $tour,
                'tourNotice' => $tourNotice,
            ],
            'Operations',
            'tools',
            $canManage ? ['/assets/openpgp_loader.js', '/assets/browser_signing.js', '/assets/high_traffic_control.js'] : [],
        );
    }

    /**
     * POST /tools/operations/ with `action` = refresh-tour: start a tour refresh in the
     * background and show the page again. Same checks as Deploy now.
     *
     * @param array<string, mixed> $query
     */
    public function operationsSubmit(array $query): void
    {
        $headers = ['Cache-Control: no-store'];
        if (!\ForumRewrite\Http\SameOriginRequest::check()) {
            $this->routeServices->sendHtml($this->operations('Same-origin request required.'), 403, $headers);
            return;
        }
        if (!$this->routeServices->securityReviewAllowed()) {
            $this->routeServices->sendHtml($this->operations('Refreshing requires a signed-in root-approved operator.'), 403, $headers);
            return;
        }
        if ((string) ($this->routeServices->requestData($query)['action'] ?? '') !== 'refresh-tour') {
            $this->routeServices->sendHtml($this->operations('Unknown action.'), 400, $headers);
            return;
        }
        $snapshots = \ForumRewrite\Tour\TourSnapshots::forProject($this->routeServices->projectRoot());
        $notice = ($snapshots->status()['State'] ?? '') === 'running'
            ? 'A refresh is already running.'
            : ((new \ForumRewrite\Tour\TourRefreshLauncher($this->routeServices->projectRoot()))->start() ? 'Refresh started.' : 'Could not start a refresh. Run ./v3 tour refresh.');
        $this->routeServices->sendHtml($this->operations($notice), 200, $headers);
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
     * Make an approved key an operator. Prepare and finalize both re-check
     * authority in the writer; finalize also verifies the operator's signature.
     *
     * @param array<string, mixed> $query
     */
    public function operatorGrantApi(string $method, array $query, bool $finalize): void
    {
        if ($method !== 'POST') {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405, $this->routeServices->noStoreHeaders());
            return;
        }
        $viewerProfile = ($this->resolveViewerProfileFromIdentityHint)();
        if (!$this->routeServices->profileIsActiveOperator($viewerProfile)) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'Making a key an operator requires a root-approved identity.'], 403, $this->routeServices->noStoreHeaders());
            return;
        }
        try {
            $operatorIdentityId = (string) ($viewerProfile['identity_id'] ?? '');
            $input = $this->routeServices->requestData($query);
            $result = $finalize
                ? $this->routeServices->writer()->finalizePreparedOperatorGrant($input, $operatorIdentityId)
                : $this->routeServices->writer()->prepareOperatorGrant($input, $operatorIdentityId);
            $this->routeServices->sendJson($result, 200, $this->routeServices->noStoreHeaders());
        } catch (RuntimeException $exception) {
            $this->routeServices->sendJson($this->routeServices->errorPayload($exception), 400, $this->routeServices->noStoreHeaders());
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    public function attributionCutoffApi(string $method, array $query, bool $finalize): void
    {
        if ($method !== 'POST') {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405, $this->routeServices->noStoreHeaders());
            return;
        }
        $viewerProfile = ($this->resolveViewerProfileFromIdentityHint)();
        if (!$this->viewerCanManageFeatureFlags($viewerProfile)) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'Moving the attribution cutoff requires a root-approved identity.'], 403, $this->routeServices->noStoreHeaders());
            return;
        }
        try {
            $operatorIdentityId = (string) ($viewerProfile['identity_id'] ?? '');
            $input = $this->routeServices->requestData($query);
            $result = $finalize
                ? $this->routeServices->writer()->finalizePreparedAttributionCutoff($input, $operatorIdentityId)
                : $this->routeServices->writer()->prepareAttributionCutoff($input, $operatorIdentityId);
            if ($finalize) {
                ($this->invalidateFeatureFlagsCache)();
            }
            $this->routeServices->sendJson($result, 200, $this->routeServices->noStoreHeaders());
        } catch (RuntimeException $exception) {
            $this->routeServices->sendJson($this->routeServices->errorPayload($exception), 400, $this->routeServices->noStoreHeaders());
        }
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
            $this->routeServices->sendJson($this->routeServices->errorPayload($exception), 400, $this->routeServices->noStoreHeaders());
        }
    }

    /**
     * @param array<string, mixed>|null $viewerProfile
     */
    private function viewerCanManageFeatureFlags(?array $viewerProfile): bool
    {
        return $this->routeServices->profileIsActiveOperator($viewerProfile);
    }
}
