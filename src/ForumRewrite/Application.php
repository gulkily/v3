<?php

declare(strict_types=1);

namespace ForumRewrite;

use ForumRewrite\Activity\SqliteActivityCommitManifestCache;
use ForumRewrite\Analysis\PostAnalysisService;
use ForumRewrite\Analysis\PostAnalyzerFactory;
use ForumRewrite\Analysis\RelatedContentSearchService;
use ForumRewrite\Analysis\SqlitePostAnalysisStore;
use ForumRewrite\Analysis\SqliteUnicodeRiskStore;
use ForumRewrite\Analysis\UnicodeRiskInspector;
use ForumRewrite\Agent\AgentReplyFulfillmentService;
use ForumRewrite\Agent\DedalusAgentReplyGenerator;
use ForumRewrite\Agent\SqliteAgentReplyGenerationStore;
use ForumRewrite\Agent\AgentIdentityService;
use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Codex\CodexHandoffDraftService;
use ForumRewrite\Codex\CodexHandoffStore;
use ForumRewrite\Http\AboutPageController;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelCapabilityInspector;
use ForumRewrite\ReadModel\ReadModelConnection;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\ReadModel\ReadModelStaleMarker;
use ForumRewrite\Support\ExecutionLock;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\Support\PrivateConfig;
use ForumRewrite\Support\ResumeTarget;
use ForumRewrite\Support\ThreadTitle;
use ForumRewrite\View\TemplateRenderer;
use ForumRewrite\Write\LocalWriteService;
use ForumRewrite\Write\IdentityBootstrapTimingException;
use ForumRewrite\Llm\LlmExchangeDatabaseConfig;
use ForumRewrite\Llm\LlmExchangeRecorder;
use ForumRewrite\Llm\SqliteLlmExchangeStore;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;
use ForumRewrite\TaskQueue\TaskQueueDatabaseConfig;
use ForumRewrite\Security\OpenPgpKeyInspector;
use ForumRewrite\Security\OpenPgpSignatureVerifier;
use PDO;
use RuntimeException;
use PDOStatement;

final class Application
{
    private const PERSISTENT_VIEWER_SESSION_COOKIE_LIFETIME = 34560000;
    private const HIDDEN_BOOTSTRAP_TAG = 'identity';
    private const ANALYSIS_SCHEMA_VERSION = 5;
    private const ACTIVITY_ITEM_LIMIT = 100;
    private const BACKUP_PREVIEW_LIMIT = 5;
    private const THREAD_CONTEXT_COMMENT_BODY_LIMIT = 3000;
    private const THREAD_CONTEXT_TOTAL_BODY_LIMIT = 18000;
    private const CODEX_HANDOFF_DEVELOPMENT_TAGS = ['feature', 'bug', 'task', 'dev', 'development', 'codex', 'implementation', 'fdp'];
    private ?string $appVersion = null;
    private ?FeatureFlagEvaluator $featureFlags = null;
    private ?LlmExchangeRecorder $llmExchangeRecorder = null;
    private bool $llmExchangeRecorderInitialized = false;
    private ?SqliteLlmExchangeStore $llmExchangeStore = null;
    private bool $llmExchangeStoreInitialized = false;
    /** @var array<string, list<array{status:string,path:string,previous_path:string}>|null> */
    private array $sourceCommitFileManifestCache = [];
    /** @var array<string, list<array{status:string,path:string,previous_path:string,role:string,href:string,signature_signer_identity:string,signature_public_key_path:string,signature_public_key_href:string,signature_key_status:string}>|null> */
    private array $activityCommitManifestCache = [];
    private ?SqliteActivityCommitManifestCache $activityCommitManifestCacheStore = null;
    private bool $activityCommitManifestCacheStoreInitialized = false;
    private ?bool $commitsCapabilityAvailable = null;
    private ?SqliteTaskQueueStore $taskQueueStore = null;
    private bool $taskQueueStoreInitialized = false;

    public function __construct(
        private readonly string $projectRoot,
        private readonly string $repositoryRoot,
        private readonly string $databasePath,
        private readonly ?string $artifactRoot = null,
        private readonly ?string $staticHtmlRoot = null,
        private readonly string $routeSource = 'php-fallback',
    ) {
    }

    /**
     * @param array<string, mixed> $requestRow
     * @return array<string, mixed>
     */
    public function fulfillAgentReplyRequest(array $requestRow): array
    {
        return $this->agentReplyFulfillmentService()->fulfillRequest($requestRow);
    }

    public function handle(string $method, string $requestUri): void
    {
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $query = [];
        parse_str((string) parse_url($requestUri, PHP_URL_QUERY), $query);
        if ($this->approvedMembersOnlyEnabled()
            || in_array($path, ['/api/auth_challenge', '/api/authenticate_identity', '/api/auth_status', '/api/clear_identity'], true)
        ) {
            $this->startViewerSession();
        } elseif ($this->shouldResumeViewerSession($method, $path, $query)) {
            $this->resumeViewerSession();
        }

        if ($path === '/api/version') {
            $this->sendText($this->appVersion() . "\n", 200, [
                'Cache-Control: no-store, no-cache, must-revalidate, max-age=0',
                'Pragma: no-cache',
                'Expires: 0',
            ]);
            return;
        }

        $this->ensureReadModel();

        if ($this->approvedMembersOnlyEnabled() && $this->membersOnlyLobbyRedirect($method, $path, $query)) {
            $this->sendRedirect('/lobby/', 'Entering lobby.', statusCode: 303, activeSection: 'account');
            return;
        }

        if ($this->approvedMembersOnlyEnabled() && !$this->membersOnlyRequestAllowed($method, $path)) {
            if (!$this->isApplicationRoute($path)) {
                $this->notFound();
                return;
            }

            if ($this->shouldRenderAuthenticationResume($method, $path, $query)) {
                $this->sendHtml(
                    $this->renderAuthenticationResumePage(ResumeTarget::fromRequestUri($requestUri)),
                    401,
                );
                return;
            }

            $this->sendHtml(
                $this->renderLobbyAccessRequiredPage(),
                403
            );
            return;
        }

        if ($path === '/api/set_identity_hint') {
            $this->handleSetIdentityHint($method, $query);
            return;
        }

        if ($path === '/api/clear_identity') {
            $this->handleClearIdentity($method);
            return;
        }

        if ($path === '/api/auth_challenge') {
            $this->handleAuthChallenge($method);
            return;
        }

        if ($path === '/api/authenticate_identity') {
            $this->handleAuthenticateIdentity($method, $query);
            return;
        }

        if ($path === '/api/auth_status') {
            $this->handleAuthenticationStatus($method);
            return;
        }

        if ($path === '/api/create_thread') {
            $this->handleCreateThread($method, $query);
            return;
        }

        if ($path === '/api/prepare_thread') {
            $this->handlePrepareThread($method, $query);
            return;
        }

        if ($path === '/api/prepare_identity') {
            $this->handlePrepareIdentity($method, $query);
            return;
        }

        if ($path === '/api/create_reply') {
            $this->handleCreateReply($method, $query);
            return;
        }

        if ($path === '/api/prepare_reply') {
            $this->handlePrepareReply($method, $query);
            return;
        }

        if ($path === '/api/create_prepared_post') {
            $this->handleCreatePreparedPost($method, $query);
            return;
        }

        if ($path === '/api/create_identity') {
            $this->handleCreateIdentity($method, $query);
            return;
        }

        if ($path === '/api/analyze_post') {
            $this->handleAnalyzePost($method, $query);
            return;
        }

        if ($path === '/api/generate_agent_reply') {
            $this->handleGenerateAgentReply($method, $query);
            return;
        }

        if ($path === '/api/codex_handoff') {
            $this->handleCodexHandoff($method, $query);
            return;
        }

        if ($path === '/api/codex_handoff_approval') {
            $this->handleCodexHandoffApproval($method, $query);
            return;
        }

        if ($path === '/api/apply_thread_tag') {
            $this->handleApplyThreadTag($method, $query);
            return;
        }

        if ($path === '/api/apply_post_tag') {
            $this->handleApplyPostTag($method, $query);
            return;
        }

        if ($path === '/api/set_feature_flag') {
            $this->handleSetFeatureFlagApi($method, $query);
            return;
        }

        if ($path === '/api/link_identity') {
            $this->handleLinkIdentity($method, $query);
            return;
        }

        if ($path === '/api/approve_user') {
            $this->handleApproveUserApi($method, $query);
            return;
        }

        if ($path === '/api/prepare_approval') {
            $this->handlePrepareUserApproval($method, $query);
            return;
        }

        if ($path === '/api/create_prepared_approval') {
            $this->handleCreatePreparedApproval($method, $query);
            return;
        }

        if ($path === '/api/prepare_invitation') {
            $this->handlePrepareInvitation($method, $query);
            return;
        }

        if ($path === '/api/create_prepared_invitation') {
            $this->handleCreatePreparedInvitation($method, $query);
            return;
        }

        if ($path === '/api/prepare_invitation_redemption') {
            $this->handlePrepareInvitationRedemption($method, $query);
            return;
        }

        if ($path === '/invites/' || $path === '/invites') {
            $this->sendHtml($this->renderInvitationPage($query), 200);
            return;
        }

        if ($path === '/compose/thread' && $method === 'POST') {
            $this->handleComposeThreadSubmit($query);
            return;
        }

        if ($path === '/compose/reply' && $method === 'POST') {
            $this->handleComposeReplySubmit($query);
            return;
        }

        if (($path === '/account/key/' || $path === '/account/key') && $method === 'POST') {
            $this->handleAccountKeySubmit($query);
            return;
        }

        if ($method === 'POST' && preg_match('#^/profiles/([^/]+)/approve/?$#', $path, $matches) === 1) {
            $this->handleApproveUserSubmit($matches[1], $query);
            return;
        }

        if (($path === '/tools/feature-flags/' || $path === '/tools/feature-flags') && $method === 'POST') {
            $this->handleSetFeatureFlagSubmit($query);
            return;
        }

        if ($method !== 'GET') {
            $this->sendHtml(
                $this->renderMessagePage(
                    'Method Not Allowed',
                    'Method Not Allowed',
                    'Only GET is supported in the local test slice, except for the identity-hint cookie route.',
                    'none'
                ),
                405
            );
            return;
        }

        if ($path === '/' || $path === '' || $path === '/threads/' || $path === '/threads') {
            if (($query['format'] ?? null) === 'rss') {
                $this->sendXml($this->renderBoardRss(), 200);
                return;
            }

            $this->sendHtml(
                $this->renderBoard(
                    (string) ($query['view'] ?? 'liked'),
                    (string) ($query['sort'] ?? 'newest'),
                ),
                200
            );
            return;
        }

        if ($path === '/about/' || $path === '/about') {
            $this->sendHtml($this->renderAbout(), 200);
            return;
        }

        if ($path === '/lobby/' || $path === '/lobby') {
            $this->sendHtml($this->renderLobby(), 200);
            return;
        }

        if ($path === '/instance/' || $path === '/instance' || $path === '/backup/' || $path === '/backup' || $path === '/tools/backup/' || $path === '/tools/backup') {
            $this->sendHtml($this->renderBackup(), 200);
            return;
        }

        if ($path === '/tools/sqlite/' || $path === '/tools/sqlite') {
            $this->sendHtml($this->renderSqliteViewer(), 200);
            return;
        }

        if ($path === '/tools/llm-exchanges/' || $path === '/tools/llm-exchanges') {
            $this->handleLlmExchangeList();
            return;
        }

        if (preg_match('#^/tools/llm-exchanges/(\d+)/?$#', $path, $matches) === 1) {
            $this->handleLlmExchangeDetail((int) $matches[1]);
            return;
        }

        if ($path === '/downloads/repository.tar.gz') {
            $this->handleRepositoryDownload($method, 'tar.gz');
            return;
        }

        if ($path === '/downloads/repository.zip') {
            $this->handleRepositoryDownload($method, 'zip');
            return;
        }

        if ($path === '/downloads/read_model.sqlite3') {
            $this->handleReadModelDatabaseDownload($method);
            return;
        }

        if ($path === '/downloads/sqlite_query_catalog.sql') {
            $this->handleSqliteQueryCatalogDownload($method);
            return;
        }

        if ($path === '/activity/' || $path === '/activity') {
            if (($query['format'] ?? null) === 'rss') {
                $this->sendXml($this->renderActivityRss((string) ($query['view'] ?? 'all')), 200);
                return;
            }

            $this->sendHtml($this->renderActivity((string) ($query['view'] ?? 'all')), 200);
            return;
        }

        if (preg_match('#^/source/current/(.+)$#', $path, $matches) === 1) {
            $this->handleCurrentSourceFile($matches[1]);
            return;
        }

        if (preg_match('#^/source/blob/([^/]+)/(.+)$#', $path, $matches) === 1) {
            $this->handleSourceBlob($matches[1], $matches[2]);
            return;
        }

        if (preg_match('#^/source/commits/([^/]+)$#', $path, $matches) === 1) {
            $this->handleSourceCommit($matches[1]);
            return;
        }

        if ($path === '/users/pending/' || $path === '/users/pending') {
            $this->handlePendingUserDirectory($method);
            return;
        }

        if ($path === '/users/' || $path === '/users') {
            $this->sendHtml($this->renderUserDirectory(), 200);
            return;
        }

        if ($path === '/tags/' || $path === '/tags') {
            $this->sendHtml($this->renderTagsIndex(), 200);
            return;
        }

        if ($path === '/tools/' || $path === '/tools') {
            $this->sendHtml($this->renderTools(), 200);
            return;
        }

        if ($path === '/tools/bookmarklets/' || $path === '/tools/bookmarklets') {
            $this->sendHtml($this->renderBookmarklets(), 200);
            return;
        }

        if ($path === '/tools/codebase/' || $path === '/tools/codebase') {
            $this->sendHtml($this->renderCodebaseState(), 200);
            return;
        }

        if ($path === '/tools/feature-flags/' || $path === '/tools/feature-flags') {
            $this->sendHtml($this->renderFeatureFlags(), 200);
            return;
        }

        if ($path === '/compose/thread') {
            $this->sendHtml(
                $this->renderComposeThread(
                    (string) ($query['board_tags'] ?? 'general'),
                    (string) ($query['subject'] ?? ''),
                    (string) ($query['body'] ?? '')
                ),
                200
            );
            return;
        }

        if ($path === '/compose/reply') {
            $this->sendHtml(
                $this->renderComposeReply((string) ($query['thread_id'] ?? ''), (string) ($query['parent_id'] ?? '')),
                200
            );
            return;
        }

        if ($path === '/account/key/' || $path === '/account/key') {
            $this->sendHtml($this->renderAccountKey(), 200);
            return;
        }

        if ($path === '/api/' || $path === '/api') {
            $this->sendText($this->renderApiIndex(), 200);
            return;
        }

        if ($path === '/api/list_index') {
            $this->sendText($this->renderApiListIndex(), 200);
            return;
        }

        if ($path === '/api/get_thread') {
            $thread = $this->renderApiGetThread((string) ($query['thread_id'] ?? ''));
            if ($thread === null) {
                $this->sendText("thread not found\n", 404);
                return;
            }

            $this->sendText($thread, 200);
            return;
        }

        if ($path === '/api/get_post') {
            $post = $this->renderApiGetPost((string) ($query['post_id'] ?? ''));
            if ($post === null) {
                $this->sendText("post not found\n", 404);
                return;
            }

            $this->sendText($post, 200);
            return;
        }

        if ($path === '/api/get_profile') {
            $profile = $this->renderApiGetProfile((string) ($query['profile_slug'] ?? ''));
            if ($profile === null) {
                $this->sendText("profile not found\n", 404);
                return;
            }

            $this->sendText($profile, 200);
            return;
        }

        if ($path === '/api/forte_activity_page') {
            $this->handleForteActivityPage($query);
            return;
        }

        if ($path === '/api/forte_commit_detail') {
            $this->handleForteCommitDetail($query);
            return;
        }

        if ($path === '/api/get_forte_content_summary') {
            $this->handleForteContentSummary($query);
            return;
        }

        if ($path === '/api/forte_user_detail') {
            $this->handleForteUserDetail($query);
            return;
        }

        if ($path === '/api/get_username_claim_cta') {
            $this->sendText("Generate a browser keypair, choose a username, and bootstrap your identity.\n", 200);
            return;
        }

        if ($path === '/api/read_model_status') {
            $this->sendText($this->renderReadModelStatus(), 200);
            return;
        }

        if (preg_match('#^/threads/([^/]+)/?$#', $path, $matches) === 1) {
            if (($query['format'] ?? null) === 'rss') {
                $xml = $this->renderThreadRss($matches[1]);
                if ($xml === null) {
                    $this->notFound();
                    return;
                }

                $this->sendXml($xml, 200);
                return;
            }

            $html = $this->renderThread($matches[1], (string) ($query['created_post_id'] ?? ''));
            if ($html === null) {
                $this->notFound();
                return;
            }

            $this->sendHtml($html, 200);
            return;
        }

        if (preg_match('#^/forte/?$#', $path) === 1) {
            $this->sendHtml($this->renderForteBoard(
                (string) ($query['tag'] ?? ''),
                (string) ($query['sort'] ?? ''),
                (string) ($query['dir'] ?? ''),
                (string) ($query['selected'] ?? ''),
                (string) ($query['created_post_id'] ?? ''),
            ), 200);
            return;
        }

        if ($path === '/forte/users/' || $path === '/forte/users') {
            $this->sendHtml($this->renderForteUserDirectory(
                (string) ($query['view'] ?? ''),
                (string) ($query['selected'] ?? ''),
                (string) ($query['sort'] ?? ''),
                (string) ($query['dir'] ?? ''),
            ), 200);
            return;
        }

        if ($path === '/forte/activity/' || $path === '/forte/activity') {
            $this->sendHtml($this->renderForteActivity(
                (string) ($query['view'] ?? ''),
                (string) ($query['selected'] ?? ''),
                (string) ($query['sort'] ?? ''),
                (string) ($query['dir'] ?? ''),
            ), 200);
            return;
        }

        if (preg_match('#^/forte/profiles/([^/]+)/?$#', $path, $matches) === 1) {
            $html = $this->renderForteProfile($matches[1]);
            if ($html === null) {
                $this->notFound();
                return;
            }

            $this->sendHtml($html, 200);
            return;
        }

        if (preg_match('#^/forte/user/([^/]+)/?$#', $path, $matches) === 1) {
            $html = $this->renderForteUsername($matches[1]);
            if ($html === null) {
                $this->notFound();
                return;
            }

            $this->sendHtml($html, 200);
            return;
        }

        if (preg_match('#^/tags/([a-z0-9]+(?:-[a-z0-9]+)*)/?$#', $path, $matches) === 1) {
            $html = $this->renderTagPage($matches[1]);
            if ($html === null) {
                $this->notFound();
                return;
            }

            $this->sendHtml($html, 200);
            return;
        }

        if (preg_match('#^/posts/([^/]+)/?$#', $path, $matches) === 1) {
            $html = $this->renderPost($matches[1]);
            if ($html === null) {
                $this->notFound();
                return;
            }

            $this->sendHtml($html, 200);
            return;
        }

        if (preg_match('#^/profiles/([^/]+)/?$#', $path, $matches) === 1) {
            $html = $this->renderProfile($matches[1], isset($query['self']), $query);
            if ($html === null) {
                $this->notFound();
                return;
            }

            $this->sendHtml($html, 200);
            return;
        }

        if (preg_match('#^/user/([^/]+)/?$#', $path, $matches) === 1) {
            $html = $this->renderUsername($matches[1]);
            if ($html === null) {
                $this->notFound();
                return;
            }

            $this->sendHtml($html, 200);
            return;
        }

        if ($path === '/llms.txt') {
            $this->sendText($this->renderLlmsTxt(), 200);
            return;
        }

        $this->notFound();
    }

    private function ensureReadModel(): void
    {
        $rebuildReason = 'missing_database';
        if ($this->staleMarker()->exists()) {
            $rebuildReason = 'stale_marker';
        }

        if (is_file($this->databasePath) && $rebuildReason !== 'stale_marker') {
            $pdo = null;
            try {
                $pdo = $this->pdo();
                $metadata = $this->readMetadata($pdo);
                $pdo = null;

                $currentRepositoryHead = ReadModelMetadata::repositoryHead($this->repositoryRoot);
                if (($metadata['repository_root'] ?? null) !== $this->repositoryRoot) {
                    $rebuildReason = 'repository_root_mismatch';
                } elseif (($metadata['schema_version'] ?? null) !== ReadModelMetadata::SCHEMA_VERSION) {
                    $rebuildReason = 'schema_version_mismatch';
                } elseif (($metadata['repository_head'] ?? null) !== $currentRepositoryHead) {
                    $rebuildReason = 'repository_head_mismatch';
                } else {
                    return;
                }
            } catch (\Throwable) {
                $pdo = null;
                $rebuildReason = 'metadata_unreadable';
            }
        }

        $this->executionLock()->withExclusiveLock(function () use ($rebuildReason): void {
            if (is_file($this->databasePath)) {
                try {
                    $metadata = $this->readMetadata($this->pdo());
                    $currentRepositoryHead = ReadModelMetadata::repositoryHead($this->repositoryRoot);
                    if (!$this->staleMarker()->exists()
                        && ($metadata['repository_root'] ?? null) === $this->repositoryRoot
                        && ($metadata['schema_version'] ?? null) === ReadModelMetadata::SCHEMA_VERSION
                        && ($metadata['repository_head'] ?? null) === $currentRepositoryHead) {
                        return;
                    }
                } catch (\Throwable) {
                    // Rebuild while holding the lock if metadata is still unreadable.
                }
            }

            $builder = new ReadModelBuilder(
                $this->repositoryRoot,
                $this->databasePath,
                new CanonicalRecordRepository($this->repositoryRoot),
                $rebuildReason,
            );
            $builder->rebuild();
            $this->staleMarker()->clear();
        });
    }

    private function renderReadModelStatus(): string
    {
        $metadata = [];
        if (is_file($this->databasePath)) {
            try {
                $metadata = $this->readMetadata($this->pdo());
            } catch (\Throwable) {
                $metadata = [];
            }
        }

        $currentRepositoryHead = ReadModelMetadata::repositoryHead($this->repositoryRoot);
        $staleMarker = $this->staleMarker()->read();
        $commitsAvailable = $this->commitsCapabilityAvailable();
        $status = (($metadata['repository_root'] ?? null) === $this->repositoryRoot)
            && (($metadata['schema_version'] ?? null) === ReadModelMetadata::SCHEMA_VERSION)
            && (($metadata['repository_head'] ?? null) === $currentRepositoryHead)
            && $staleMarker === null
            && $commitsAvailable
            ? 'ready'
            : 'stale';
        $taskQueue = $this->taskQueueStatus();

        return "status={$status}\n"
            . 'schema_version=' . ($metadata['schema_version'] ?? 'missing') . "\n"
            . 'repository_root=' . ($metadata['repository_root'] ?? 'missing') . "\n"
            . 'repository_head=' . ($metadata['repository_head'] ?? 'missing') . "\n"
            . 'current_repository_head=' . $currentRepositoryHead . "\n"
            . 'rebuilt_at=' . ($metadata['rebuilt_at'] ?? 'missing') . "\n"
            . 'lock_status=' . ($this->executionLock()->isLocked() ? 'locked' : 'unlocked') . "\n"
            . 'stale_marker=' . ($staleMarker === null ? 'absent' : 'present') . "\n"
            . 'stale_reason=' . ($staleMarker['reason'] ?? 'none') . "\n"
            . 'stale_commit_sha=' . ($staleMarker['commit_sha'] ?? 'none') . "\n"
            . 'rebuild_reason=' . ($metadata['rebuild_reason'] ?? 'missing') . "\n"
            . 'commits_capability=' . ($commitsAvailable ? 'available' : 'unavailable') . "\n"
            . 'rebuild_required=' . ($status === 'ready' ? 'no' : 'yes') . "\n"
            . 'task_queue_status=' . $taskQueue['status'] . "\n"
            . 'task_queue_queued=' . $taskQueue['queued'] . "\n"
            . 'task_queue_running=' . $taskQueue['running'] . "\n"
            . 'task_queue_failed=' . $taskQueue['failed'] . "\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function collectCodebaseState(): array
    {
        $metadata = [];
        $metadataReadable = false;
        $rowCounts = [];
        $databaseExists = is_file($this->databasePath);

        if ($databaseExists) {
            try {
                $pdo = $this->pdo();
                $metadata = $this->readMetadata($pdo);
                $metadataReadable = true;
                $rowCounts = $this->readModelRowCounts($pdo);
            } catch (\Throwable) {
                $metadata = [];
                $rowCounts = [];
            }
        }

        $currentRepositoryHead = ReadModelMetadata::repositoryHead($this->repositoryRoot);
        $staleMarker = $this->staleMarker()->read();
        $readModelReady = $metadataReadable
            && (($metadata['repository_root'] ?? null) === $this->repositoryRoot)
            && (($metadata['schema_version'] ?? null) === ReadModelMetadata::SCHEMA_VERSION)
            && (($metadata['repository_head'] ?? null) === $currentRepositoryHead)
            && $staleMarker === null;
        $lockStatus = $this->executionLock()->isLocked() ? 'locked' : 'unlocked';

        $overallStatus = $readModelReady ? 'ready' : 'stale';
        if ($lockStatus === 'locked') {
            $overallStatus = 'locked';
        } elseif (!$databaseExists || !$metadataReadable) {
            $overallStatus = 'configuration issue';
        }

        return [
            'overall_status' => $overallStatus,
            'app_version' => $this->appVersion(),
            'repository' => [
                'root_label' => basename($this->repositoryRoot),
                'git_exists' => is_dir($this->repositoryRoot . '/.git') ? 'yes' : 'no',
                'records_exists' => is_dir($this->repositoryRoot . '/records') ? 'yes' : 'no',
                'head' => $currentRepositoryHead,
                'short_head' => $this->repositoryShortCommit(),
                'latest_commit' => $this->latestRepositoryCommit(),
            ],
            'read_model' => [
                'database_label' => basename($this->databasePath),
                'database_exists' => $databaseExists ? 'yes' : 'no',
                'metadata_status' => $metadataReadable ? 'readable' : 'unreadable',
                'schema_version' => $metadata['schema_version'] ?? 'missing',
                'expected_schema_version' => ReadModelMetadata::SCHEMA_VERSION,
                'repository_root' => $metadata['repository_root'] ?? 'missing',
                'repository_head' => $metadata['repository_head'] ?? 'missing',
                'current_repository_head' => $currentRepositoryHead,
                'rebuilt_at' => $metadata['rebuilt_at'] ?? 'missing',
                'rebuild_reason' => $metadata['rebuild_reason'] ?? 'missing',
                'lock_status' => $lockStatus,
                'stale_marker' => $staleMarker === null ? 'absent' : 'present',
                'stale_reason' => $staleMarker['reason'] ?? 'none',
                'stale_commit_sha' => $staleMarker['commit_sha'] ?? 'none',
                'row_counts' => $rowCounts,
            ],
            'downloads' => [
                ['href' => '/downloads/repository.tar.gz', 'label' => 'Content repository (.tar.gz)'],
                ['href' => '/downloads/repository.zip', 'label' => 'Content repository (.zip)'],
                ['href' => '/downloads/read_model.sqlite3', 'label' => 'SQLite index database'],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function readModelRowCounts(PDO $pdo): array
    {
        $counts = [];
        foreach ([
            'posts' => 'Posts',
            'threads' => 'Threads',
            'profiles' => 'Profiles',
            'username_routes' => 'Username routes',
            'activity' => 'Activity rows',
            'post_analyses' => 'Post analyses',
            'post_unicode_risks' => 'Unicode risk rows',
            'post_generated_responses' => 'Generated responses',
        ] as $table => $label) {
            if (!$this->readModelTableExists($pdo, $table)) {
                $counts[$label] = 'missing';
                continue;
            }

            $counts[$label] = (string) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        }

        if ($this->readModelTableExists($pdo, 'profiles')) {
            $counts['Approved profiles'] = (string) $pdo->query('SELECT COUNT(*) FROM profiles WHERE is_approved = 1')->fetchColumn();
        }

        return $counts;
    }

    private function readModelTableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :name");
        $stmt->execute(['name' => $table]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @return array{short:string,date:string,subject:string}|null
     */
    private function latestRepositoryCommit(): ?array
    {
        if (!is_dir($this->repositoryRoot . '/.git')) {
            return null;
        }

        $command = sprintf('git -C %s log -1 --format=%%h%%x09%%cI%%x09%%s 2>&1', escapeshellarg($this->repositoryRoot));
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        if ($exitCode !== 0 || $output === []) {
            return null;
        }

        $parts = explode("\t", trim(implode("\n", $output)), 3);
        if (count($parts) !== 3) {
            return null;
        }

        return [
            'short' => $parts[0],
            'date' => $parts[1],
            'subject' => $parts[2],
        ];
    }

    private function renderBoard(string $view, string $sort): string
    {
        $view = $this->normalizeBoardView($view);
        $sort = $this->normalizeBoardSort($sort);
        $viewOptions = $this->boardViewOptions($view, $sort);
        $sortOptions = $this->boardSortOptions($view, $sort);

        return $this->renderPageTemplate(
            'board.php',
            [
                'threads' => $this->fetchBoardThreads($view, $sort),
                'view' => $view,
                'sort' => $sort,
                'viewOptions' => $viewOptions,
                'sortOptions' => $sortOptions,
                'viewLabel' => $this->activeBoardOptionLabel($viewOptions, $view),
                'sortLabel' => $this->activeBoardOptionLabel($sortOptions, $sort),
            ],
            'Board',
            'board',
            [
                '/assets/inline_reply_form.js',
                '/assets/lazy_compose_signing.js',
            ],
        );
    }

    private function renderTagsIndex(): string
    {
        $view = $this->normalizeBoardView('all');
        $sort = $this->normalizeBoardSort('newest');
        $viewOptions = $this->boardViewOptions($view, $sort);
        $sortOptions = $this->boardSortOptions($view, $sort);
        $threads = $this->fetchThreads();

        return $this->renderPageTemplate(
            'tags.php',
            [
                'tagGroups' => $this->limitTagGroupThreads($this->groupThreadsByTag($threads), 5),
                'viewOptions' => $viewOptions,
                'sortOptions' => $sortOptions,
            ],
            'Tags',
            'board',
        );
    }

    private function renderTagPage(string $tag): ?string
    {
        $threads = $this->fetchThreads();
        $group = $this->findTagGroup($this->groupThreadsByTag($threads), $tag);
        if ($group === null) {
            return null;
        }
        $title = '#' . $tag . ' - Tag';

        return $this->renderPageTemplate(
            'tag.php',
            [
                'group' => $group,
            ],
            $title,
            'board',
        );
    }

    private function renderForteBoard(string $requestedTag = '', string $requestedSortColumn = '', string $requestedSortDir = '', string $requestedSelected = '', string $requestedCreatedPostId = ''): string
    {
        $threads = $this->fetchThreads();
        $tagGroups = $this->groupThreadsByTag($threads);
        $selection = $this->resolveForteBoardSelection($threads, $tagGroups, $requestedTag, $requestedSelected);
        $selectedTag = $selection['tag'];
        $selectedThreadId = $selection['selectedThreadId'];
        $sort = $this->resolveForteBoardSort($requestedSortColumn, $requestedSortDir);
        $threads = $this->applyForteBoardSort($threads, $sort['column'], $sort['dir']);

        // A thread excluded from the board's own listing (identity/
        // bootstrap/approval-only) still resolves when linked to directly -
        // fetched on its own here, kept out of $threads/$tagGroups/counts
        // entirely, and folded only into $contentThreads below so just the
        // content pane (never the row list or folder tree) can render it.
        $extraThread = null;
        if ($selectedThreadId === '' && $requestedSelected !== '') {
            $extraThread = $this->fetchThreadById($requestedSelected);
            if ($extraThread !== null) {
                $selectedThreadId = $requestedSelected;
            }
        }
        $contentThreads = $extraThread !== null ? array_merge($threads, [$extraThread]) : $threads;

        $replyPostsByThreadId = $this->fetchAllThreadReplyPosts();
        $replyTreesByThreadId = [];
        $allPostIds = [];
        $highlightedPostId = '';
        foreach ($contentThreads as $thread) {
            $threadId = (string) $thread['root_post_id'];
            $replyTreesByThreadId[$threadId] = $this->buildReplyTree($replyPostsByThreadId[$threadId] ?? []);
            $allPostIds[] = $threadId;
            foreach ($replyPostsByThreadId[$threadId] ?? [] as $replyPost) {
                $postId = (string) $replyPost['post_id'];
                $allPostIds[] = $postId;
                if ($threadId === $selectedThreadId && $postId === $requestedCreatedPostId) {
                    $highlightedPostId = $postId;
                }
            }
        }

        $viewerProfile = $this->resolveViewerProfileFromIdentityHint();
        $viewerIdentityId = $viewerProfile !== null ? (string) $viewerProfile['identity_id'] : '';
        $viewerLikedThreadIds = $viewerProfile !== null
            ? $this->viewerThreadTagsForThreads(array_column($contentThreads, 'root_post_id'), 'like', $viewerIdentityId)
            : [];
        $viewerFlaggedPostIds = $viewerProfile !== null
            ? $this->viewerPostTagsForPosts($allPostIds, 'flag', $viewerIdentityId)
            : [];

        return $this->renderer()->renderStandalonePage(
            'forte_board.php',
            [
                'threads' => $threads,
                'contentThreads' => $contentThreads,
                'tagGroups' => $tagGroups,
                'selectedTag' => $selectedTag,
                'selectedThreadId' => $selectedThreadId,
                'sortColumn' => $sort['column'],
                'sortDir' => $sort['dir'],
                'replyTreesByThreadId' => $replyTreesByThreadId,
                'viewerLikedThreadIds' => $viewerLikedThreadIds,
                'viewerFlaggedPostIds' => $viewerFlaggedPostIds,
                'highlightedPostId' => $highlightedPostId,
            ],
            'Forte',
            'paned-reader-body',
            ['/assets/paned_board_reader.js', '/assets/lazy_compose_signing.js', '/assets/thread_reactions.js'],
            ['/assets/forte.css'],
        );
    }

    private function renderForteProfile(string $slug): ?string
    {
        $profile = $this->fetchProfileBySlug($slug);
        if ($profile === null) {
            return null;
        }

        $pageTitleLabel = trim((string) ($profile['username'] ?? ''));
        if ($pageTitleLabel === '') {
            $pageTitleLabel = trim((string) ($profile['fallback_label'] ?? ''));
        }
        if ($pageTitleLabel === '') {
            $pageTitleLabel = (string) $profile['profile_slug'];
        }

        return $this->renderer()->renderStandalonePage(
            'forte_profile.php',
            [
                'profile' => $profile,
            ],
            $pageTitleLabel . ' - Forte Profile',
            'paned-reader-body',
            [],
            ['/assets/forte.css'],
        );
    }

    private function renderForteUsername(string $username): ?string
    {
        $usernameToken = strtolower($username);
        $profiles = $this->fetchProfilesByUsernameToken($usernameToken);
        if ($profiles === []) {
            return null;
        }

        $approvedProfiles = array_values(array_filter(
            $profiles,
            static fn (array $profile): bool => ((int) $profile['is_approved']) === 1
        ));
        $unapprovedProfiles = array_values(array_filter(
            $profiles,
            static fn (array $profile): bool => ((int) $profile['is_approved']) !== 1
        ));
        $approvedIdentityIds = array_values(array_map(
            static fn (array $profile): string => (string) $profile['identity_id'],
            $approvedProfiles
        ));

        return $this->renderer()->renderStandalonePage(
            'forte_username.php',
            [
                'usernameToken' => $usernameToken,
                'approvedProfiles' => $approvedProfiles,
                'unapprovedProfiles' => $unapprovedProfiles,
                'approvedThreadCount' => $this->countVisibleAuthoredRows($approvedIdentityIds, true),
                'approvedPostCount' => $this->countVisibleAuthoredRows($approvedIdentityIds, false),
                'approvedThreads' => $this->fetchVisibleAuthoredThreads($approvedIdentityIds),
                'approvedPosts' => $this->fetchVisibleAuthoredPosts($approvedIdentityIds),
            ],
            'User ' . $usernameToken . ' - Forte',
            'paned-reader-body',
            [],
            ['/assets/forte.css'],
        );
    }

    private function renderForteUserDirectory(
        string $requestedView = '',
        string $requestedSelected = '',
        string $requestedSortColumn = '',
        string $requestedSortDir = '',
    ): string {
        $users = $this->fetchApprovedUserDirectoryUsers();
        $activityBoundsByToken = $this->fetchUserDirectoryActivityBoundsByToken();
        foreach ($users as &$user) {
            $bounds = $activityBoundsByToken[$user['username_token']] ?? ['earliest' => '', 'latest' => ''];
            $user['active_at'] = $bounds['latest'];
            $user['joined_at'] = $bounds['earliest'];
        }
        unset($user);

        $flagsByToken = $this->buildUserDirectoryCategoryFlags($users);
        $pendingUsers = $this->fetchNeverApprovedPendingUserDirectoryUsers();
        // Pending users have no per-token activity-bounds query (a separate,
        // secondary population - see fetchNeverApprovedPendingUserDirectoryUsers()'s
        // own doc comment) - the Active/Joined columns render blank for
        // these rows rather than adding a second bounds query for them.
        foreach ($pendingUsers as &$pendingUser) {
            $pendingUser['active_at'] = '';
            $pendingUser['joined_at'] = '';
        }
        unset($pendingUser);

        $categoryCounts = $this->buildUserDirectoryCategoryCounts(count($users), $flagsByToken, count($pendingUsers));

        $sort = $this->resolveUserDirectorySort($requestedSortColumn, $requestedSortDir);
        $users = $this->applyUserDirectorySort($users, $sort['column'], $sort['dir']);
        $pendingUsers = $this->applyUserDirectorySort($pendingUsers, $sort['column'], $sort['dir']);

        return $this->renderer()->renderStandalonePage(
            'forte_users.php',
            [
                'users' => $users,
                'flagsByToken' => $flagsByToken,
                'pendingUsers' => $pendingUsers,
                'categoryCounts' => $categoryCounts,
                'selectedCategory' => $this->normalizeUserDirectoryCategory($requestedView),
                'selectedUserToken' => strtolower(trim($requestedSelected)),
                'sortColumn' => $sort['column'],
                'sortDir' => $sort['dir'],
            ],
            'Users - Forte',
            'paned-reader-body',
            ['/assets/paned_users_reader.js'],
            ['/assets/forte.css'],
        );
    }

    /**
     * Renders the Forte Activity three-pane view: a left pane of the same 5
     * category filters classic's `/activity/` offers, a list pane of items
     * for the selected filter, and a detail pane with the selected item's
     * full technical metadata.
     *
     * Calls `fetchActivity($view)` once per view (5 calls total, each
     * already its own cheap indexed/limited query - the same call classic's
     * own `renderActivity()` makes for whichever single view it's showing)
     * rather than deriving view membership from one superset fetch: a
     * view's own `LIMIT` window can reach further back in time than the
     * unfiltered "all" view's window once enough non-matching rows crowd
     * out its most recent items, so only a real per-view fetch reproduces
     * classic's exact per-view item set and counts.
     */
    private function renderForteActivity(
        string $requestedView = '',
        string $requestedSelected = '',
        string $requestedSort = '',
        string $requestedDirection = '',
    ): string {
        $viewLabels = [
            'all' => 'All Activity',
            'content' => 'Visible Content',
            'identity' => 'Identity',
            'bootstrap' => 'Bootstraps',
            'approval' => 'Approvals',
        ];

        ['column' => $sortColumn, 'direction' => $sortDirection] = $this->resolveActivitySort($requestedSort, $requestedDirection);

        $itemsById = [];
        $viewItemIds = [];
        $viewPagination = [];
        foreach (array_keys($viewLabels) as $viewKey) {
            $viewItemIds[$viewKey] = [];
            $viewResult = $this->fetchActivity($viewKey, $sortColumn, $sortDirection);
            foreach ($viewResult['items'] as $item) {
                $itemId = (string) $item['id'];
                $viewItemIds[$viewKey][] = $itemId;
                if (!isset($itemsById[$itemId])) {
                    $item['forte_link'] = $this->activityItemBoardLink($item);
                    $itemsById[$itemId] = $item;
                }
            }

            // The cursor is derived from the last item actually returned for
            // this view, so an empty page never exposes a "Load more"
            // control with nothing to page from.
            $lastItem = $viewResult['items'][count($viewResult['items']) - 1] ?? null;
            $viewPagination[$viewKey] = [
                'has_more' => $viewResult['has_more'] && $lastItem !== null,
                'next_cursor' => $lastItem !== null ? [
                    'sort_value' => $this->activitySortValueFromItem($lastItem, $sortColumn),
                    'id' => (int) $lastItem['id'],
                ] : null,
            ];
        }

        foreach ($itemsById as $itemId => $item) {
            // $itemId comes back as an int here (PHP casts numeric string
            // array keys), so it must be re-stringified before a strict
            // in_array() against $viewItemIds' string ids.
            $itemIdString = (string) $itemId;
            foreach (array_keys($viewLabels) as $viewKey) {
                $itemsById[$itemId]['view_' . $viewKey] = in_array($itemIdString, $viewItemIds[$viewKey], true);
            }
        }

        // Matches each fetchActivity() call's own DB-level order (same sort
        // column, same id tiebreaker), so the merged cross-view pool's
        // display order agrees with any single view's own fetch order.
        $items = array_values($itemsById);
        usort($items, function (array $a, array $b) use ($sortColumn, $sortDirection): int {
            $aValue = $this->activitySortValueFromItem($a, $sortColumn);
            $bValue = $this->activitySortValueFromItem($b, $sortColumn);
            $result = $sortDirection === 'desc' ? strcmp($bValue, $aValue) : strcmp($aValue, $bValue);
            if ($result !== 0) {
                return $result;
            }

            return $sortDirection === 'desc' ? ($b['id'] <=> $a['id']) : ($a['id'] <=> $b['id']);
        });

        $viewCounts = [];
        foreach ($viewLabels as $viewKey => $viewLabel) {
            $viewCounts[] = [
                'key' => $viewKey,
                'label' => $viewLabel,
                // Full total for the view (independent of pagination) - the
                // left-pane folder count. Separate from how many of those
                // are actually loaded/visible right now (below), which is
                // what the status bar tracks.
                'count' => $this->countActivityViewTotal($viewKey),
                'loadedCount' => count($viewItemIds[$viewKey]),
            ];
        }

        $commitsAvailable = $this->commitsCapabilityAvailable();
        if (!$commitsAvailable) {
            $this->enqueueReadModelRecovery();
        }

        // Commits are a parallel, structurally different row set - not
        // activity actions, so they're kept out of $itemsById/$items
        // rather than forced into that shape. Only `date` is sortable for
        // commits (resolveCommitSort() falls back to it for any other
        // requested column), so a Kind/Label header click while browsing
        // Commits has no effect on commit order - a deliberate Step 3 scope
        // decision, not a bug.
        $commitItems = [];
        if ($commitsAvailable) {
            ['column' => $commitSortColumn, 'direction' => $commitSortDirection] = $this->resolveCommitSort($requestedSort, $requestedDirection);
            $commitResult = $this->fetchCommits($commitSortColumn, $commitSortDirection);
            $commitItems = $commitResult['items'];
            $lastCommit = $commitItems[count($commitItems) - 1] ?? null;
            $viewPagination['commits'] = [
                'has_more' => $commitResult['has_more'] && $lastCommit !== null,
                'next_cursor' => $lastCommit !== null ? [
                    'sort_value' => $this->commitSortValueFromItem($lastCommit, $commitSortColumn),
                    'id' => (int) $lastCommit['id'],
                ] : null,
            ];
            $viewCounts[] = [
                'key' => 'commits',
                'label' => 'Commits',
                'count' => $this->countCommitsTotal(),
                'loadedCount' => count($commitItems),
            ];
        }

        $selectedView = $this->normalizeActivityView($requestedView);
        if (!$commitsAvailable && $selectedView === 'commits') {
            $selectedView = 'all';
        }
        // Commit selection is handled entirely client-side (Stage 4: fetched
        // on demand when a commit row is clicked, not pre-selected here) -
        // $viewItemIds has no 'commits' entry to look up against.
        $selectedItemId = $selectedView === 'commits'
            ? ''
            : (in_array($requestedSelected, $viewItemIds[$selectedView], true)
                ? $requestedSelected
                : (string) ($viewItemIds[$selectedView][0] ?? ''));
        $sortHeaderLinks = $this->activitySortHeaderLinks($selectedView, $sortColumn, $sortDirection);

        return $this->renderer()->renderStandalonePage(
            'forte_activity.php',
            [
                'items' => $items,
                'commitItems' => $commitItems,
                'viewCounts' => $viewCounts,
                'selectedView' => $selectedView,
                'selectedItemId' => $selectedItemId,
                'viewPagination' => $viewPagination,
                'sortHeaderLinks' => $sortHeaderLinks,
                'recoveryNotice' => $commitsAvailable ? '' : 'Commit history is temporarily unavailable while site data updates.',
            ],
            'Activity - Forte',
            'paned-reader-body',
            ['/assets/paned_activity_reader.js'],
            ['/assets/forte.css'],
        );
    }

    /**
     * Serves one additional page of activity rows for a single view, reusing
     * the canonical row partial (`paned_activity_item_row.php`) so appended
     * rows are byte-identical to the ones the initial page render produces.
     * Unlike `renderForteActivity()`, this only checks membership in the
     * requested view - an item's other `view_*` flags are left false, since
     * paging one view is not supposed to fetch the other 4 views' data too.
     *
     * Registered below `handle()`'s blanket non-GET rejection, so (like its
     * `/api/get_thread`, `/api/get_post`, and `/api/get_profile` siblings)
     * it never runs for a non-GET request and needs no method check here.
     *
     * @param array<string, mixed> $query
     */
    private function handleForteActivityPage(array $query): void
    {
        $view = $this->normalizeActivityView((string) ($query['view'] ?? ''));

        $rawCursor = trim((string) ($query['cursor'] ?? ''));
        $cursor = null;
        if ($rawCursor !== '') {
            try {
                $decodedCursor = json_decode($rawCursor, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $decodedCursor = null;
            }

            if (
                !is_array($decodedCursor)
                || !isset($decodedCursor['sort_value'], $decodedCursor['id'])
                || !is_string($decodedCursor['sort_value'])
                || !is_int($decodedCursor['id'])
            ) {
                $this->sendJson(['status' => 'error', 'error' => 'invalid cursor'], 400);
                return;
            }

            $cursor = [
                'sort_value' => $decodedCursor['sort_value'],
                'id' => $decodedCursor['id'],
            ];
        }

        if ($view === 'commits') {
            if (!$this->commitsCapabilityAvailable()) {
                $this->enqueueReadModelRecovery();
                $this->sendReadModelCapabilityUnavailable();
                return;
            }

            // Commits are a parallel row set with their own fetch/sort
            // (Stage 2) and their own row partial (Stage 3) - and, unlike
            // activity items, no eagerly-rendered detail article: Stage 4
            // fetches a commit's full manifest on demand when it's
            // selected, not for every loaded row up front.
            ['column' => $commitSortColumn, 'direction' => $commitSortDirection] = $this->resolveCommitSort(
                (string) ($query['sort'] ?? ''),
                (string) ($query['dir'] ?? ''),
            );
            $commitResult = $this->fetchCommits($commitSortColumn, $commitSortDirection, $cursor);

            $html = '';
            foreach ($commitResult['items'] as $item) {
                $html .= $this->renderer()->renderFragment('partials/paned_activity_commit_row.php', [
                    'item' => $item,
                    'isSelected' => false,
                    'isTabStop' => false,
                    'visible' => true,
                ]);
            }

            $lastCommit = $commitResult['items'][count($commitResult['items']) - 1] ?? null;
            $hasMore = $commitResult['has_more'] && $lastCommit !== null;
            $nextCursor = $lastCommit !== null ? [
                'sort_value' => $this->commitSortValueFromItem($lastCommit, $commitSortColumn),
                'id' => (int) $lastCommit['id'],
            ] : null;

            $this->sendJson([
                'status' => 'ok',
                'html' => $html,
                'detail_html' => '',
                'has_more' => $hasMore,
                'next_cursor' => $nextCursor,
            ], 200);
            return;
        }

        ['column' => $sortColumn, 'direction' => $sortDirection] = $this->resolveActivitySort(
            (string) ($query['sort'] ?? ''),
            (string) ($query['dir'] ?? ''),
        );

        $result = $this->fetchActivity($view, $sortColumn, $sortDirection, $cursor);

        $html = '';
        $detailHtml = '';
        foreach ($result['items'] as $item) {
            $item['forte_link'] = $this->activityItemBoardLink($item);
            foreach (['all', 'content', 'identity', 'bootstrap', 'approval'] as $flagView) {
                $item['view_' . $flagView] = ($flagView === $view);
            }

            $html .= $this->renderer()->renderFragment('partials/paned_activity_item_row.php', [
                'item' => $item,
                'isSelected' => false,
                'isTabStop' => false,
                'visible' => true,
            ]);

            // Every appended row needs a matching detail-pane article, or
            // selecting it leaves the detail pane blank (no article matches
            // its id, so every existing article - and the placeholder - end
            // up hidden). Reuses the same partial the initial page render
            // uses, so this is never selected by default.
            $detailHtml .= $this->renderer()->renderFragment('partials/paned_activity_detail_article.php', [
                'item' => $item,
                'isSelected' => false,
            ]);
        }

        $lastItem = $result['items'][count($result['items']) - 1] ?? null;
        $hasMore = $result['has_more'] && $lastItem !== null;
        $nextCursor = $lastItem !== null ? [
            'sort_value' => $this->activitySortValueFromItem($lastItem, $sortColumn),
            'id' => (int) $lastItem['id'],
        ] : null;

        $this->sendJson([
            'status' => 'ok',
            'html' => $html,
            'detail_html' => $detailHtml,
            'has_more' => $hasMore,
            'next_cursor' => $nextCursor,
        ], 200);
    }

    /**
     * A commit's full file manifest is fetched on demand, not pre-rendered
     * for every loaded row the way activity items' detail articles are -
     * some commits touch thousands of files, and only one is ever viewed at
     * a time, so eagerly rendering all of them (as Stage 3's row list does)
     * would recreate the exact page-bloat problem the shared-manifest-block
     * mechanism was built to work around.
     *
     * @param array<string, mixed> $query
     */
    private function handleForteCommitDetail(array $query): void
    {
        if (!$this->commitsCapabilityAvailable()) {
            $this->enqueueReadModelRecovery();
            $this->sendReadModelCapabilityUnavailable();
            return;
        }

        $sha = (string) ($query['sha'] ?? '');
        if (preg_match('/^[0-9a-f]{40}$/', $sha) !== 1) {
            $this->sendJson(['status' => 'error', 'error' => 'invalid sha'], 400);
            return;
        }

        $files = $this->activityCommitManifest($sha);
        if ($files === null) {
            $this->sendJson(['status' => 'error', 'error' => 'commit not found'], 404);
            return;
        }

        $html = $this->renderer()->renderFragment('partials/activity_commit_manifest.php', [
            'files' => $files,
            'commit_sha' => $sha,
            'commit_href' => $this->sourceCommitHref($sha) ?? '',
        ]);

        $this->sendJson(['status' => 'ok', 'html' => $html], 200);
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleForteContentSummary(array $query): void
    {
        $summary = $this->forteContentSummary((string) ($query['post_id'] ?? ''));
        if ($summary === null) {
            $this->sendJson(['status' => 'error', 'error' => 'not found'], 404);
            return;
        }

        $this->sendJson(array_merge(['status' => 'ok'], $summary), 200);
    }

    /**
     * Serves the Users pane's detail-pane fragment for one username_token,
     * reusing the same aggregation `renderForteUsername()` already performs
     * (approved profiles, visible thread/post counts and rows) rather than
     * a new query, and returning it the same way `handleForteCommitDetail()`
     * returns its manifest fragment: `{status, html}` for client-side
     * injection into the pane.
     *
     * @param array<string, mixed> $query
     */
    private function handleForteUserDetail(array $query): void
    {
        $usernameToken = strtolower(trim((string) ($query['username_token'] ?? '')));
        $profiles = $usernameToken === '' ? [] : $this->fetchProfilesByUsernameToken($usernameToken);
        $approvedProfiles = array_values(array_filter(
            $profiles,
            static fn (array $profile): bool => ((int) $profile['is_approved']) === 1
        ));
        if ($approvedProfiles === []) {
            $this->handleForteUserDetailPending($usernameToken, $profiles);
            return;
        }

        $approvedIdentityIds = array_values(array_map(
            static fn (array $profile): string => (string) $profile['identity_id'],
            $approvedProfiles
        ));

        $approvedThreads = $this->fetchVisibleAuthoredThreads($approvedIdentityIds);
        $approvedPosts = $this->fetchVisibleAuthoredPosts($approvedIdentityIds);
        $activityBounds = $this->userDirectoryActivityBounds($approvedThreads, $approvedPosts);

        $html = $this->renderer()->renderFragment('partials/paned_user_detail_pane.php', [
            'usernameToken' => $usernameToken,
            'approvedThreadCount' => count($approvedThreads),
            'approvedPostCount' => count($approvedPosts),
            'approvedThreads' => $approvedThreads,
            'approvedPosts' => $approvedPosts,
            'activeAt' => $activityBounds['latest'],
            'memberSince' => $activityBounds['earliest'],
        ]);

        $this->sendJson(['status' => 'ok', 'html' => $html], 200);
    }

    /**
     * Earliest/latest timestamps across a user's visible threads/posts, for
     * the detail pane's "Member since"/"Active" header line - approximated
     * from their visible authored content (no join-date column exists) so
     * it costs nothing beyond the thread/post lists the pane already
     * fetches. ISO 8601 UTC timestamps sort correctly as plain strings, no
     * DateTime parsing needed.
     *
     * @param array<int, array<string, mixed>> $threads
     * @param array<int, array<string, mixed>> $posts
     * @return array{earliest: string, latest: string}
     */
    private function userDirectoryActivityBounds(array $threads, array $posts): array
    {
        $timestamps = [];
        foreach ($threads as $thread) {
            $timestamps[] = (string) ($thread['root_post_created_at'] ?? '');
        }
        foreach ($posts as $post) {
            $timestamps[] = (string) ($post['created_at'] ?? '');
        }
        $timestamps = array_values(array_filter($timestamps, static fn (string $timestamp): bool => $timestamp !== ''));

        if ($timestamps === []) {
            return ['earliest' => '', 'latest' => ''];
        }

        return ['earliest' => min($timestamps), 'latest' => max($timestamps)];
    }

    /**
     * The Users pane detail-pane fallback for a token with no approved
     * profile - either genuinely unknown, or a "never approved" pending
     * user (see `fetchNeverApprovedPendingUserDirectoryUsers()`). Reuses
     * `$profiles` already fetched by `handleForteUserDetail()` rather than
     * a second query - a caller that already confirmed `$approvedProfiles`
     * is empty need not re-derive that from scratch.
     *
     * @param array<int, array<string, mixed>> $profiles every profile (any approval status) for this token
     */
    private function handleForteUserDetailPending(string $usernameToken, array $profiles): void
    {
        if ($profiles === []) {
            $this->sendJson(['status' => 'error', 'error' => 'user not found'], 404);
            return;
        }

        $pendingThreadCount = array_sum(array_map(static fn (array $p): int => (int) $p['thread_count'], $profiles));
        $pendingPostCount = array_sum(array_map(static fn (array $p): int => (int) $p['post_count'], $profiles));

        $html = $this->renderer()->renderFragment('partials/paned_user_pending_detail_pane.php', [
            'usernameToken' => $usernameToken,
            'pendingProfileCount' => count($profiles),
            'pendingThreadCount' => $pendingThreadCount,
            'pendingPostCount' => $pendingPostCount,
        ]);

        $this->sendJson(['status' => 'ok', 'html' => $html], 200);
    }

    /**
     * A board-visibility-independent summary of one post, for the content-
     * summary preview dialog: resolved straight from `posts` via the same
     * `fetchPost()` every other post lookup uses, unlike the Forte board's
     * own thread listing (`fetchThreads()`), which excludes identity/
     * bootstrap/approval-only threads entirely. `is_hidden` (moderation)
     * still applies - this is for previewing real, un-moderated content
     * that just isn't board-listed, not bypassing moderation.
     *
     * @return array{post_id:string,thread_id:string,is_reply:bool,title:string,author_label:string,created_at:string,body_preview:string,reply_count:int}|null
     */
    private function forteContentSummary(string $postId): ?array
    {
        $post = $this->fetchPost($postId);
        if ($post === null) {
            return null;
        }

        // posts.thread_id is self-referential for a root post (equals its
        // own post_id), never null - parent_id is the real root/reply
        // discriminator (null only for a root).
        $isReply = trim((string) ($post['parent_id'] ?? '')) !== '';
        $threadId = (string) $post['thread_id'];

        $stmt = $this->pdo()->prepare('SELECT reply_count FROM threads WHERE root_post_id = :root_post_id');
        $stmt->execute(['root_post_id' => $threadId]);
        $replyCount = $stmt->fetchColumn();

        return [
            'post_id' => (string) $post['post_id'],
            'thread_id' => $threadId,
            'is_reply' => $isReply,
            'title' => ThreadTitle::displayTitle(
                (string) ($post['subject'] ?? ''),
                (string) ($post['body'] ?? ''),
                (string) $post['post_id'],
            ),
            'author_label' => (string) ($post['author_label'] ?? ''),
            'created_at' => (string) ($post['created_at'] ?? ''),
            'body_preview' => (string) ($post['body'] ?? ''),
            'reply_count' => $replyCount !== false ? (int) $replyCount : 0,
        ];
    }

    /**
     * Maps one activity item to its Forte-native destination link, mirroring
     * `activity.php`'s classic kind-based destinations but pointing
     * post/thread kinds at the existing `forte_post_permalink` URL shape
     * instead of classic's own `/threads/`/`/posts/` pages, so clicking
     * through from the Activity view lands in the Forte board itself.
     *
     * @param array<string, mixed> $item
     * @return array{href: string, label: string}
     */
    private function activityItemBoardLink(array $item): array
    {
        if ($item['kind'] === 'site_feature_flag') {
            return ['href' => '/tools/feature-flags/', 'label' => 'site feature flags'];
        }

        $threadId = (string) ($item['thread_id'] ?? '');
        $postId = $item['kind'] === 'thread_label_add' ? $threadId : (string) ($item['post_id'] ?? '');
        if ($threadId === '' || $postId === '') {
            return ['href' => '', 'label' => ''];
        }

        // Every resolvable item links into Forte itself, regardless of
        // board visibility - identity/bootstrap/approval-only threads are
        // excluded from the board's own listing but still resolve when
        // linked directly (fetchThreadById(), kept out of the list/tag
        // groups/counts), so there's no more need for classic's own
        // /posts//threads/ destination as a fallback here.
        return [
            'href' => '/forte?selected=' . $threadId . '&created_post_id=' . $postId . '#post-' . $postId,
            'label' => $postId,
        ];
    }

    /**
     * Resolves a requested ?tag= value against real tag names, falling back
     * to '' (All Threads) when missing or unrecognized.
     *
     * @param array<int, array{tag: string, count: int, threads: array}> $tagGroups
     */
    private function resolveForteBoardTag(string $requestedTag, array $tagGroups): string
    {
        if ($requestedTag === '') {
            return '';
        }

        foreach ($tagGroups as $group) {
            if ($group['tag'] === $requestedTag) {
                return $requestedTag;
            }
        }

        return '';
    }

    /**
     * Resolves a requested ?tag=/?selected= pair for server-side rendering,
     * so a reader following a link (permalink, reply redirect, or a plain
     * bookmark) sees the right thread/tag in the very first response
     * instead of a client-side JS correction after the fact.
     *
     * `selected` wins on conflict: if the requested thread doesn't carry the
     * requested tag, the tag drops to '' (All Threads) rather than losing
     * the selection - this can only happen via a hand-edited URL or a
     * thread's tags changing after a link was shared, never from normal
     * clicking (a click can only ever target an already-visible,
     * correctly-tagged row).
     *
     * @param array<int, array<string, mixed>> $threads
     * @param array<int, array{tag: string, count: int, threads: array}> $tagGroups
     * @return array{tag: string, selectedThreadId: string}
     */
    private function resolveForteBoardSelection(array $threads, array $tagGroups, string $requestedTag, string $requestedSelected): array
    {
        $resolvedTag = $this->resolveForteBoardTag($requestedTag, $tagGroups);

        $selectedThreadId = '';
        foreach ($threads as $thread) {
            if ((string) $thread['root_post_id'] === $requestedSelected) {
                $selectedThreadId = $requestedSelected;
                break;
            }
        }

        if ($selectedThreadId === '') {
            return ['tag' => $resolvedTag, 'selectedThreadId' => ''];
        }

        if ($resolvedTag !== '') {
            $group = $this->findTagGroup($tagGroups, $resolvedTag);
            $threadIdsInGroup = $group !== null ? array_column($group['threads'], 'root_post_id') : [];
            if (!in_array($selectedThreadId, $threadIdsInGroup, true)) {
                $resolvedTag = '';
            }
        }

        return ['tag' => $resolvedTag, 'selectedThreadId' => $selectedThreadId];
    }

    /**
     * Resolves requested ?sort=/?dir= values against the four sortable
     * columns, falling back to '' (today's default newest-first order,
     * unrelated to any single column) when the column is missing or
     * unrecognized. An unrecognized direction falls back to a per-column
     * default: ascending for text columns, descending for date/replies.
     *
     * @return array{column: string, dir: string}
     */
    private function resolveForteBoardSort(string $requestedColumn, string $requestedDir): array
    {
        $validColumns = ['subject', 'from', 'date', 'replies'];
        if (!in_array($requestedColumn, $validColumns, true)) {
            return ['column' => '', 'dir' => ''];
        }

        $defaultDir = in_array($requestedColumn, ['date', 'replies'], true) ? 'desc' : 'asc';
        $dir = in_array($requestedDir, ['asc', 'desc'], true) ? $requestedDir : $defaultDir;

        return ['column' => $requestedColumn, 'dir' => $dir];
    }

    /**
     * @param array<int, array<string, mixed>> $threads
     * @return array<int, array<string, mixed>>
     */
    private function applyForteBoardSort(array $threads, string $column, string $dir): array
    {
        if ($column === '') {
            return $threads;
        }

        $sorted = $threads;
        usort($sorted, function (array $left, array $right) use ($column): int {
            return $this->forteBoardSortValue($left, $column) <=> $this->forteBoardSortValue($right, $column);
        });

        return $dir === 'desc' ? array_reverse($sorted) : $sorted;
    }

    private function forteBoardSortValue(array $thread, string $column): string|int
    {
        return match ($column) {
            'subject' => mb_strtolower(ThreadTitle::displayTitle(
                (string) ($thread['subject'] ?? ''),
                (string) ($thread['body_preview'] ?? ''),
                (string) $thread['root_post_id'],
            )),
            'from' => mb_strtolower(trim((string) ($thread['author_label'] ?? '')) ?: 'guest'),
            'date' => (string) ($thread['root_post_created_at'] ?? ''),
            'replies' => (int) ($thread['reply_count'] ?? 0),
            default => '',
        };
    }

    /**
     * Nests a flat, sequence_number-ordered post list into a reply tree using
     * each post's parent_id. A post whose parent_id is missing or not present
     * in the fetched set (e.g. hidden/deleted) is treated as a root.
     *
     * @param array<int, array<string, mixed>> $posts
     * @return array<int, array{post: array<string, mixed>, children: array}>
     */
    private function buildReplyTree(array $posts): array
    {
        $nodesByPostId = [];
        foreach ($posts as $post) {
            $nodesByPostId[(string) $post['post_id']] = [
                'post' => $post,
                'children' => [],
            ];
        }

        $roots = [];
        foreach ($nodesByPostId as $postId => &$node) {
            $parentId = $node['post']['parent_id'] !== null ? (string) $node['post']['parent_id'] : null;
            if ($parentId !== null && $parentId !== $postId && isset($nodesByPostId[$parentId])) {
                $nodesByPostId[$parentId]['children'][] = &$node;
            } else {
                $roots[] = &$node;
            }
        }
        unset($node);

        return $roots;
    }

    private function renderThread(string $threadId, string $createdPostId = ''): ?string
    {
        $threadRow = $this->fetchThread($threadId);
        if ($threadRow === null) {
            return null;
        }

        $title = $this->displayThreadTitle($threadRow);
        $viewerProfile = $this->resolveViewerProfileFromIdentityHint();
        $viewerHasLiked = $viewerProfile !== null
            && $this->viewerHasThreadTag($threadId, 'like', (string) $viewerProfile['identity_id']);
        $posts = $this->fetchThreadPosts($threadId);
        $viewerPostFlags = $viewerProfile !== null
            ? $this->viewerPostTagsForPosts(array_column($posts, 'post_id'), 'flag', (string) $viewerProfile['identity_id'])
            : [];
        $viewerPostLikes = $viewerProfile !== null
            ? $this->viewerPostTagsForPosts(array_column($posts, 'post_id'), 'like', (string) $viewerProfile['identity_id'])
            : [];
        $viewerCanSeePostAnalysis = $viewerProfile !== null && ((int) ($viewerProfile['is_approved'] ?? 0)) === 1;
        $viewerCanUseCodexHandoff = $this->viewerCanUseCodexHandoff($viewerProfile);
        $createdPostId = $this->createdPostIdForThread($threadId, $createdPostId);
        $postAnalysesForWork = $this->fetchPostAnalysesForPosts($posts);
        $agentRepliesByPostId = $this->fetchAgentReplyGenerationsForPosts($posts);
        $llmExchangesByPostId = $this->viewerCanInspectLlmExchanges() ? $this->fetchLlmExchangesForPosts($posts) : [];
        $codexHandoffsByPostId = $viewerCanUseCodexHandoff ? $this->fetchCodexHandoffsForPosts($posts) : [];
        $codexHandoffEligiblePostIds = $viewerCanUseCodexHandoff ? $this->codexHandoffEligiblePostIds($posts, $threadRow) : [];

        return $this->renderPageTemplate(
            'thread.php',
            [
                'thread' => $threadRow,
                'posts' => $posts,
                'title' => $title,
                'viewerProfile' => $viewerProfile,
                'viewerHasLiked' => $viewerHasLiked,
                'viewerPostFlags' => $viewerPostFlags,
                'viewerPostLikes' => $viewerPostLikes,
                'createdPostId' => $createdPostId,
                'viewerCanSeePostAnalysis' => $viewerCanSeePostAnalysis,
                'viewerCanUseCodexHandoff' => $viewerCanUseCodexHandoff,
                'postAnalysesByPostId' => $viewerCanSeePostAnalysis ? $postAnalysesForWork : [],
                'agentRepliesByPostId' => $agentRepliesByPostId,
                'llmExchangesByPostId' => $llmExchangesByPostId,
                'codexHandoffsByPostId' => $codexHandoffsByPostId,
                'codexHandoffEligiblePostIds' => $codexHandoffEligiblePostIds,
                'agentReplyWorkByPostId' => $this->agentReplyWorkByPostId(
                    $posts,
                    $createdPostId,
                    $postAnalysesForWork,
                    $agentRepliesByPostId
                ),
            ],
            $title,
            'board',
            $this->identityScripts([
                '/assets/inline_reply_form.js',
                '/assets/thread_reactions.js',
                '/assets/post_analysis.js',
            ]),
        );
    }

    /**
     * @param array<string, mixed> $thread
     */
    private function displayThreadTitle(array $thread): string
    {
        return ThreadTitle::displayTitle(
            (string) ($thread['subject'] ?? ''),
            (string) ($thread['body_preview'] ?? $thread['body'] ?? ''),
            (string) ($thread['root_post_id'] ?? $thread['thread_id'] ?? $thread['post_id'] ?? '')
        );
    }

    private function renderPost(string $postId): ?string
    {
        $post = $this->fetchPost($postId, true);
        if ($post === null) {
            return null;
        }

        if (((int) ($post['is_hidden'] ?? 0)) === 1) {
            return $this->renderMessagePage('Post Hidden', 'Post Hidden', 'This post has been hidden.', 'board');
        }

        $viewerProfile = $this->resolveViewerProfileFromIdentityHint();
        $viewerPostFlags = $viewerProfile !== null
            ? $this->viewerPostTagsForPosts([$post['post_id']], 'flag', (string) $viewerProfile['identity_id'])
            : [];
        $viewerPostLikes = $viewerProfile !== null
            ? $this->viewerPostTagsForPosts([$post['post_id']], 'like', (string) $viewerProfile['identity_id'])
            : [];
        $viewerCanSeePostAnalysis = $viewerProfile !== null && ((int) ($viewerProfile['is_approved'] ?? 0)) === 1;
        $viewerCanUseCodexHandoff = $this->viewerCanUseCodexHandoff($viewerProfile);
        $posts = [$post];
        $postAnalysesForWork = $this->fetchPostAnalysesForPosts($posts);
        $agentRepliesByPostId = $this->fetchAgentReplyGenerationsForPosts($posts);
        $llmExchangesByPostId = $this->viewerCanInspectLlmExchanges() ? $this->fetchLlmExchangesForPosts($posts) : [];
        $codexHandoffsByPostId = $viewerCanUseCodexHandoff ? $this->fetchCodexHandoffsForPosts($posts) : [];
        $threadRow = $this->fetchThread((string) $post['thread_id']);
        $codexHandoffEligiblePostIds = $viewerCanUseCodexHandoff ? $this->codexHandoffEligiblePostIds($posts, $threadRow) : [];

        return $this->renderPageTemplate(
            'post.php',
            [
                'post' => $post,
                'viewerPostFlags' => $viewerPostFlags,
                'viewerPostLikes' => $viewerPostLikes,
                'viewerCanSeePostAnalysis' => $viewerCanSeePostAnalysis,
                'viewerCanUseCodexHandoff' => $viewerCanUseCodexHandoff,
                'postAnalysesByPostId' => $viewerCanSeePostAnalysis ? $postAnalysesForWork : [],
                'agentRepliesByPostId' => $agentRepliesByPostId,
                'llmExchangesByPostId' => $llmExchangesByPostId,
                'codexHandoffsByPostId' => $codexHandoffsByPostId,
                'codexHandoffEligiblePostIds' => $codexHandoffEligiblePostIds,
                'agentReplyWorkByPostId' => $this->agentReplyWorkByPostId(
                    $posts,
                    '',
                    $postAnalysesForWork,
                    $agentRepliesByPostId
                ),
            ],
            'Post ' . $post['post_id'],
            'board',
            $this->identityScripts([
                '/assets/thread_reactions.js',
                '/assets/post_analysis.js',
            ]),
        );
    }

    /**
     * @param array<string, mixed> $query
     */
    private function renderProfile(string $slug, bool $self = false, array $query = []): ?string
    {
        $profile = $this->fetchProfileBySlug($slug);
        if ($profile === null) {
            return null;
        }

        return $this->renderProfilePage($profile, $self, $this->profileNoticeFromQuery($profile, $query));
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function renderProfilePage(array $profile, bool $self = false, ?string $notice = null, ?string $error = null): string
    {
        $viewerProfile = $this->resolveViewerProfileFromIdentityHint();
        $isOwnProfile = $viewerProfile !== null
            && ((string) $viewerProfile['identity_id']) === ((string) $profile['identity_id']);
        $canApprove = $viewerProfile !== null
            && ((int) $viewerProfile['is_approved']) === 1
            && ((int) $profile['is_approved']) !== 1
            && ((string) $viewerProfile['identity_id']) !== ((string) $profile['identity_id']);
        $pageTitleLabel = trim((string) ($profile['username'] ?? ''));
        if ($pageTitleLabel === '') {
            $pageTitleLabel = trim((string) ($profile['fallback_label'] ?? ''));
        }
        if ($pageTitleLabel === '') {
            $pageTitleLabel = (string) $profile['profile_slug'];
        }

        return $this->renderPageTemplate(
            'profile.php',
            [
                'profile' => $profile,
                'self' => $self,
                'identityHint' => $_COOKIE['identity_hint'] ?? '',
                'notice' => $notice,
                'error' => $error,
                'viewerProfile' => $viewerProfile,
                'isOwnProfile' => $isOwnProfile,
                'canApprove' => $canApprove,
            ],
            $pageTitleLabel . ' - Profile',
            'profiles',
            $this->identityScripts(['/assets/pending_approvals.js']),
        );
    }

    /**
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $query
     */
    private function profileNoticeFromQuery(array $profile, array $query): ?string
    {
        if (($query['approval'] ?? null) !== 'success') {
            return null;
        }

        $postId = (string) ($query['post_id'] ?? '');
        $commitSha = (string) ($query['commit'] ?? '');
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $postId) || !preg_match('/^[a-f0-9]{40}$/', $commitSha)) {
            return null;
        }

        return 'Approved user ' . $this->escape((string) $profile['username']) . '. '
            . '<a href="/posts/' . $this->escape($postId) . '">Open approval post</a>. '
            . 'Commit ' . $this->escape($commitSha);
    }

    private function renderUsername(string $username): ?string
    {
        $usernameToken = strtolower($username);
        $profiles = $this->fetchProfilesByUsernameToken($usernameToken);
        if ($profiles === []) {
            return null;
        }

        $approvedProfiles = array_values(array_filter(
            $profiles,
            static fn (array $profile): bool => ((int) $profile['is_approved']) === 1
        ));
        $unapprovedProfiles = array_values(array_filter(
            $profiles,
            static fn (array $profile): bool => ((int) $profile['is_approved']) !== 1
        ));
        $approvedIdentityIds = array_values(array_map(
            static fn (array $profile): string => (string) $profile['identity_id'],
            $approvedProfiles
        ));

        return $this->renderPageTemplate(
            'username.php',
            [
                'usernameToken' => $usernameToken,
                'approvedProfiles' => $approvedProfiles,
                'unapprovedProfiles' => $unapprovedProfiles,
                'approvedThreadCount' => $this->countVisibleAuthoredRows($approvedIdentityIds, true),
                'approvedPostCount' => $this->countVisibleAuthoredRows($approvedIdentityIds, false),
                'approvedThreads' => $this->fetchVisibleAuthoredThreads($approvedIdentityIds),
                'approvedPosts' => $this->fetchVisibleAuthoredPosts($approvedIdentityIds),
            ],
            'User ' . $usernameToken,
            'profiles',
        );
    }

    private function renderBackup(): string
    {
        $backupSnapshot = $this->fetchBackupSnapshot();

        return $this->renderPageTemplate(
            'instance.php',
            [
                'siteName' => SiteConfig::siteName(),
                'admins' => $this->fetchSeedApprovedUsers(),
                'toolNavOptions' => $this->toolNavOptions('backup'),
                'backupSnapshot' => $backupSnapshot,
                'downloads' => [
                    [
                        'href' => '/downloads/repository.tar.gz',
                        'label' => 'Content repository (.tar.gz)',
                        'description' => 'Tarball of the full repository, including .git history.',
                    ],
                    [
                        'href' => '/downloads/repository.zip',
                        'label' => 'Content repository (.zip)',
                        'description' => 'ZIP archive of the full repository, including .git history.',
                    ],
                    [
                        'href' => '/downloads/read_model.sqlite3',
                        'label' => 'SQLite index database',
                        'description' => 'Current read-model database for local indexing and queries.',
                    ],
                ],
            ],
            'Backup',
            'tools',
        );
    }

    /**
     * @return array{generated_at:string,repository_head:string,items:array<int,array<string,mixed>>}
     */
    private function fetchBackupSnapshot(): array
    {
        $metadata = [];
        $pdo = $this->pdo();
        if ($this->readModelTableExists($pdo, 'metadata')) {
            $rows = $pdo->query('SELECT key, value FROM metadata')->fetchAll();
            foreach ($rows as $row) {
                $metadata[(string) $row['key']] = (string) $row['value'];
            }
        }

        return [
            'generated_at' => $metadata['rebuilt_at'] ?? '',
            'repository_head' => $metadata['repository_head'] ?? ReadModelMetadata::repositoryHead($this->repositoryRoot),
            'items' => array_slice($this->fetchActivity('content', 'date', 'desc')['items'], 0, self::BACKUP_PREVIEW_LIMIT),
        ];
    }

    private function renderAbout(): string
    {
        return (new AboutPageController($this->renderPageTemplate(...)))->render();
    }

    private function renderActivity(string $view): string
    {
        $view = $this->normalizeActivityView($view);

        return $this->renderPageTemplate(
            'activity.php',
            [
                'view' => $view,
                'viewOptions' => [
                    [
                        'label' => 'All Activity',
                        'href' => '/activity/?view=all',
                        'is_active' => $view === 'all',
                    ],
                    [
                        'label' => 'Visible Content',
                        'href' => '/activity/?view=content',
                        'is_active' => $view === 'content',
                    ],
                    [
                        'label' => 'Identity',
                        'href' => '/activity/?view=identity',
                        'is_active' => $view === 'identity',
                    ],
                    [
                        'label' => 'Bootstraps',
                        'href' => '/activity/?view=bootstrap',
                        'is_active' => $view === 'bootstrap',
                    ],
                    [
                        'label' => 'Approvals',
                        'href' => '/activity/?view=approval',
                        'is_active' => $view === 'approval',
                    ],
                    [
                        'label' => 'RSS',
                        'href' => '/activity/?view=' . rawurlencode($view) . '&format=rss',
                        'is_active' => false,
                    ],
                ],
                'items' => $this->fetchActivity($view, 'date', 'desc')['items'],
            ],
            'Activity',
            'activity',
        );
    }

    private function handleCurrentSourceFile(string $encodedRelativePath): void
    {
        $relativePath = $this->normalizeSourceRoutePath($encodedRelativePath);
        if ($relativePath === null || !$this->isValidCanonicalSourcePath($relativePath)) {
            $this->sendText("Invalid source path\n", 400);
            return;
        }

        $contents = $this->readCurrentSourceFile($relativePath);
        if ($contents === null) {
            $this->sendText("Source not found\n", 404);
            return;
        }

        $this->sendText($contents, 200);
    }

    private function handleSourceBlob(string $commitSha, string $encodedRelativePath): void
    {
        if (!$this->isValidSourceCommitSha($commitSha)) {
            $this->sendText("Invalid source commit\n", 400);
            return;
        }

        $relativePath = $this->normalizeSourceRoutePath($encodedRelativePath);
        if ($relativePath === null || !$this->isValidCanonicalSourcePath($relativePath)) {
            $this->sendText("Invalid source path\n", 400);
            return;
        }

        $contents = $this->readSourceBlob($commitSha, $relativePath);
        if ($contents === null) {
            $this->sendText("Source not found\n", 404);
            return;
        }

        $this->sendText($contents, 200);
    }

    private function handleSourceCommit(string $commitSha): void
    {
        if (!$this->isValidSourceCommitSha($commitSha)) {
            $this->sendText("Invalid source commit\n", 400);
            return;
        }

        $details = $this->sourceCommitDetails($commitSha);
        if ($details === null) {
            $this->sendText("Commit not found\n", 404);
            return;
        }

        $this->sendText($details, 200);
    }

    private function renderComposeThread(
        string $boardTags = 'general',
        string $subject = '',
        string $body = '',
    ): string
    {
        return $this->renderComposeThreadPage($boardTags, $subject, $body);
    }

    private function renderUserDirectory(): string
    {
        $viewerProfile = $this->resolveViewerProfileFromIdentityHint();

        return $this->renderPageTemplate(
            'users.php',
            [
                'users' => $this->fetchApprovedUserDirectoryUsers(),
                'showPendingLink' => $viewerProfile !== null
                    && ((int) $viewerProfile['is_approved']) === 1
                    && $this->hasPendingUserDirectoryProfiles(),
            ],
            'Users',
            'profiles',
        );
    }

    private function renderPendingUserDirectory(): string
    {
        return $this->renderPageTemplate(
            'users_pending.php',
            [
                'profiles' => $this->fetchPendingUserDirectoryProfiles(),
            ],
            'Users Awaiting Approval',
            'profiles',
            $this->identityScripts(['/assets/pending_approvals.js']),
        );
    }

    private function renderTools(): string
    {
        return $this->renderPageTemplate(
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
                'toolNavOptions' => $this->toolNavOptions(null),
            ],
            'Tools',
            'tools',
        );
    }

    private function renderCodebaseState(): string
    {
        return $this->renderPageTemplate(
            'codebase_state.php',
            [
                'state' => $this->collectCodebaseState(),
                'toolNavOptions' => $this->toolNavOptions('codebase'),
                'siteName' => SiteConfig::siteName(),
                'admins' => $this->fetchSeedApprovedUsers(),
            ],
            'System State',
            'tools',
        );
    }

    private function renderFeatureFlags(): string
    {
        return $this->renderPageTemplate(
            'feature_flags.php',
            [
                'flags' => $this->featureFlags()->all(),
                'toolNavOptions' => $this->toolNavOptions('feature-flags'),
            ],
            'Feature Flags',
            'tools',
            ['/assets/feature_flags.js'],
        );
    }

    private function renderSqliteViewer(): string
    {
        return $this->renderPageTemplate(
            'sqlite_viewer.php',
            [
                'toolNavOptions' => $this->toolNavOptions('sqlite'),
            ],
            'SQLite Viewer',
            'tools',
            ['/assets/sql-wasm.js', '/assets/sqlite_viewer.js'],
        );
    }

    private function handleLlmExchangeList(): void
    {
        if (!$this->viewerCanInspectLlmExchanges()) {
            $this->sendHtml($this->renderMessagePage('LLM Exchanges', 'LLM Exchanges', 'Only approved users can view LLM exchanges, and the exchange UI must be enabled.', 'tools'), 403);
            return;
        }

        $this->sendHtml($this->renderLlmExchangeList(), 200);
    }

    private function handleLlmExchangeDetail(int $exchangeId): void
    {
        if (!$this->viewerCanInspectLlmExchanges()) {
            $this->sendHtml($this->renderMessagePage('LLM Exchange', 'LLM Exchange', 'Only approved users can view LLM exchanges, and the exchange UI must be enabled.', 'tools'), 403);
            return;
        }

        $exchange = $this->llmExchangeStore()?->find($exchangeId);
        if ($exchange === null) {
            $this->sendHtml($this->renderMessagePage('Not Found', 'Not Found', 'LLM exchange not found.', 'tools'), 404);
            return;
        }

        $this->sendHtml($this->renderPageTemplate(
            'llm_exchange.php',
            [
                'exchange' => $exchange,
                'toolNavOptions' => $this->toolNavOptions('llm-exchanges'),
            ],
            'LLM Exchange ' . $exchangeId,
            'tools'
        ), 200);
    }

    private function renderLlmExchangeList(): string
    {
        return $this->renderPageTemplate(
            'llm_exchanges.php',
            [
                'exchanges' => $this->llmExchangeStore()?->recent() ?? [],
                'toolNavOptions' => $this->toolNavOptions('llm-exchanges'),
            ],
            'LLM Exchanges',
            'tools'
        );
    }

    private function viewerCanInspectLlmExchanges(): bool
    {
        $viewerProfile = $this->resolveViewerProfileFromIdentityHint();

        return $this->featureFlags()->isEnabled(FeatureFlagRegistry::LLM_CONVERSATION_UI_ENABLED)
            && $viewerProfile !== null
            && ((int) ($viewerProfile['is_approved'] ?? 0)) === 1;
    }

    private function renderBookmarklets(): string
    {
        return $this->renderPageTemplate(
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
                'toolNavOptions' => $this->toolNavOptions('bookmarklets'),
            ],
            'Bookmarklets',
            'tools',
            ['/assets/tools_bookmarklets.js'],
        );
    }

    private function toolNavOptions(?string $activeKey): array
    {
        return [
            [
                'key' => 'bookmarklets',
                'label' => 'Bookmarklets',
                'href' => '/tools/bookmarklets/',
                'is_active' => $activeKey === 'bookmarklets',
            ],
            [
                'key' => 'backup',
                'label' => 'Backup',
                'href' => '/tools/backup/',
                'is_active' => $activeKey === 'backup',
            ],
            [
                'key' => 'sqlite',
                'label' => 'SQLite Viewer',
                'href' => '/tools/sqlite/',
                'is_active' => $activeKey === 'sqlite',
            ],
            [
                'key' => 'llm-exchanges',
                'label' => 'LLM Exchanges',
                'href' => '/tools/llm-exchanges/',
                'is_active' => $activeKey === 'llm-exchanges',
            ],
            [
                'key' => 'codebase',
                'label' => 'System State',
                'href' => '/tools/codebase/',
                'is_active' => $activeKey === 'codebase',
            ],
            [
                'key' => 'feature-flags',
                'label' => 'Feature Flags',
                'href' => '/tools/feature-flags/',
                'is_active' => $activeKey === 'feature-flags',
            ],
        ];
    }

    private function renderComposeThreadPage(
        string $boardTags = 'general',
        string $subject = '',
        string $body = '',
        ?string $notice = null,
        ?string $error = null
    ): string
    {
        return $this->renderPageTemplate('compose_thread.php', [
            'boardTags' => $boardTags !== '' ? $boardTags : 'general',
            'subject' => $subject,
            'body' => $body,
            'notice' => $notice,
            'error' => $error,
        ], 'Compose Thread', 'compose', $this->identityScripts());
    }

    private function renderComposeReply(string $threadId, string $parentId): string
    {
        return $this->renderComposeReplyPage($threadId, $parentId);
    }

    private function renderComposeReplyPage(
        string $threadId,
        string $parentId,
        ?string $notice = null,
        ?string $error = null,
        string $boardTags = 'general',
        string $body = ''
    ): string
    {
        $parentPost = $parentId !== '' ? $this->fetchPost($parentId) : null;
        if (is_array($parentPost) && $threadId !== '' && (string) ($parentPost['thread_id'] ?? '') !== $threadId) {
            $parentPost = null;
        }

        return $this->renderPageTemplate('compose_reply.php', [
            'threadId' => $threadId,
            'parentId' => $parentId,
            'parentPost' => $parentPost,
            'notice' => $notice,
            'error' => $error,
            'boardTags' => $boardTags !== '' ? $boardTags : 'general',
            'body' => $body,
        ], 'Compose Reply', 'compose', $this->identityScripts());
    }

    private function renderAccountKey(): string
    {
        return $this->renderAccountKeyPage();
    }

    private function renderAccountKeyPage(?string $notice = null, ?string $error = null): string
    {
        $viewerProfile = $this->resolveViewerProfileFromIdentityHint();

        return $this->renderPageTemplate('account_key.php', [
            'identityHint' => $_COOKIE['identity_hint'] ?? '',
            'viewerProfile' => $viewerProfile,
            'notice' => $notice,
            'error' => $error,
        ], 'Account Key', 'account', $this->identityScripts(['/assets/private_site_auth.js']));
    }

    private function renderApiIndex(): string
    {
        return "GET /api/\nGET /api/version\nGET /api/auth_challenge\nGET /api/auth_status\nGET /api/list_index\nGET /api/get_thread?thread_id=<id>\nGET /api/get_post?post_id=<id>\nGET /api/get_profile?profile_slug=<slug>\nGET /api/get_username_claim_cta\nGET /api/codex_handoff?handoff_id=<id>\nPOST /api/set_identity_hint\nPOST /api/clear_identity\nPOST /api/authenticate_identity\nPOST /api/prepare_identity\nPOST /api/create_identity\nPOST /api/analyze_post\nPOST /api/generate_agent_reply\nPOST /api/codex_handoff\nPOST /api/codex_handoff_approval\nPOST /api/apply_thread_tag\nPOST /api/apply_post_tag\n";
    }

    private function renderApiListIndex(): string
    {
        $lines = [];
        foreach ($this->fetchThreads() as $thread) {
            $subject = $this->displayThreadTitle($thread);
            $lines[] = $thread['root_post_id'] . "\t" . $subject . "\t" . $thread['reply_count'];
        }

        return implode("\n", $lines) . "\n";
    }

    private function renderApiGetThread(string $threadId): ?string
    {
        $thread = $this->fetchThread($threadId);
        if ($thread === null) {
            return null;
        }

        $lines = [
            'Thread-ID: ' . $thread['root_post_id'],
            'Created-At: ' . $thread['root_post_created_at'],
            'Last-Activity-At: ' . $thread['last_activity_at'],
            'Subject: ' . ($thread['subject'] ?: ''),
            'Reply-Count: ' . $thread['reply_count'],
            'Score-Total: ' . $thread['score_total'],
            'Labels: ' . implode(' ', $thread['thread_labels']),
            '',
        ];

        foreach ($this->fetchThreadPosts($threadId) as $post) {
            $lines[] = '[' . $post['post_id'] . '] ' . trim(str_replace("\n", ' ', $post['body']));
        }

        return implode("\n", $lines) . "\n";
    }

    private function renderApiGetPost(string $postId): ?string
    {
        $post = $this->fetchPost($postId);
        if ($post === null) {
            return null;
        }

        return "Post-ID: {$post['post_id']}\nCreated-At: {$post['created_at']}\nThread-ID: {$post['thread_id']}\nAuthor: {$post['author_label']}\n\n{$post['body']}";
    }

    private function renderApiGetProfile(string $slug): ?string
    {
        $profile = $this->fetchProfileBySlug($slug);
        if ($profile === null) {
            return null;
        }

        $approved = ((int) $profile['is_approved']) === 1 ? 'yes' : 'no';

        $approvedBy = ((int) $profile['is_approved']) === 1 ? (string) ($profile['approved_by_label'] ?? '') : '';

        return "Profile-Slug: {$profile['profile_slug']}\nIdentity-ID: {$profile['identity_id']}\nUsername: {$profile['username']}\nApproved: {$approved}\nApproved-By: {$approvedBy}\nPosts: {$profile['post_count']}\nThreads: {$profile['thread_count']}\n";
    }

    private function renderBoardRss(): string
    {
        $items = [];
        foreach ($this->fetchThreads() as $thread) {
            $title = $this->displayThreadTitle($thread);
            $items[] = $this->renderRssItem($title, '/threads/' . $thread['root_post_id'], $thread['body_preview'], (string) $thread['last_activity_at']);
        }

        return $this->renderRssFeed('Board', '/?format=rss', $items);
    }

    private function renderThreadRss(string $threadId): ?string
    {
        $thread = $this->fetchThread($threadId);
        if ($thread === null) {
            return null;
        }

        $items = [];
        foreach ($this->fetchThreadPosts($threadId) as $post) {
            $items[] = $this->renderRssItem(
                $post['post_id'],
                '/posts/' . $post['post_id'],
                trim($post['body']),
                (string) $post['created_at']
            );
        }

        return $this->renderRssFeed($this->displayThreadTitle($thread), '/threads/' . $threadId . '?format=rss', $items);
    }

    private function renderActivityRss(string $view): string
    {
        $view = $this->normalizeActivityView($view);
        $items = [];
        foreach ($this->fetchActivity($view, 'date', 'desc')['items'] as $item) {
            $link = match ($item['kind']) {
                'thread_label_add' => '/threads/' . $item['thread_id'],
                'site_feature_flag' => '/tools/feature-flags/',
                default => '/posts/' . $item['post_id'],
            };
            $items[] = $this->renderRssItem($item['label'], $link, $item['kind'], (string) $item['created_at']);
        }

        return $this->renderRssFeed('Activity ' . $view, '/activity/?view=' . rawurlencode($view) . '&format=rss', $items);
    }

    private function renderLlmsTxt(): string
    {
        return "Local test slice\nGET /api/\nGET /api/list_index\nGET /api/get_thread\nPOST /api/analyze_post\nGET /about/\nGET /compose/thread\nGET /compose/reply\nGET /account/key/\nGET /instance/\nGET /backup/\n";
    }

    /**
     * @param array<string, mixed> $pageData
     * @param string[] $scriptPaths
     */
    private function renderPageTemplate(
        string $pageTemplate,
        array $pageData,
        string $title,
        string $activeSection,
        array $scriptPaths = [],
    ): string {
        if (!array_key_exists('viewerProfile', $pageData)) {
            $pageData['viewerProfile'] = $this->authenticatedViewerProfile();
        }
        $publicAuthenticationResume = !$this->approvedMembersOnlyEnabled()
            && $pageData['viewerProfile'] === null;

        return $this->renderer()->renderPageTemplate(
            $pageTemplate,
            $pageData,
            $title,
            $activeSection,
            $scriptPaths,
            $this->routeSource,
            $publicAuthenticationResume,
        );
    }

    /**
     * Every page that needs browser-key signing loads these two scripts
     * first; callers add whatever page-specific scripts come after them.
     *
     * @param string[] $extra
     * @return string[]
     */
    private function identityScripts(array $extra = []): array
    {
        return array_merge([
            '/assets/openpgp_loader.js',
            '/assets/browser_signing.js',
        ], $extra);
    }

    private function renderer(): TemplateRenderer
    {
        return new TemplateRenderer($this->projectRoot . '/templates', $this->appVersion(), $this->featureFlags());
    }

    private function featureFlags(): FeatureFlagEvaluator
    {
        if ($this->featureFlags === null) {
            $this->featureFlags = FeatureFlagEvaluator::forApplication($this->repositoryRoot, $this->projectRoot);
        }

        return $this->featureFlags;
    }

    private function appVersion(): string
    {
        if ($this->appVersion !== null) {
            return $this->appVersion;
        }

        $this->appVersion = ReadModelMetadata::repositoryHead($this->repositoryRoot);

        return $this->appVersion;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchThreads(): array
    {
        $rows = $this->pdo()->query(
            'SELECT threads.root_post_id, threads.root_post_created_at, threads.last_activity_at, threads.subject, threads.body_preview,
                    threads.reply_count, threads.score_total, threads.board_tags_json, threads.thread_labels_json, posts.author_label, posts.author_profile_slug,
                    posts.body AS root_post_body,
                    posts.post_score_total AS root_post_score_total,
                    profiles.username_token AS author_username_token, COALESCE(profiles.is_approved, 0) AS author_is_approved
             FROM threads
             JOIN posts ON posts.post_id = threads.root_post_id
             LEFT JOIN profiles ON profiles.identity_id = posts.author_identity_id
             ORDER BY last_activity_at DESC, root_post_id DESC'
        )->fetchAll();

        $rows = array_values(array_filter(
            $rows,
            fn (array $thread): bool => !$this->isHiddenBootstrapBoardTagsJson((string) $thread['board_tags_json'])
        ));

        return $this->hydrateThreadRows($rows);
    }

    /**
     * One thread's own row, in the exact shape `fetchThreads()` produces -
     * unlike `fetchThreads()`, this doesn't exclude identity/bootstrap/
     * approval-only threads, since it's for resolving a single thread a
     * caller already knows the id of (a direct permalink), not for
     * populating the board's own listing.
     *
     * @return array<string, mixed>|null
     */
    private function fetchThreadById(string $threadId): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT threads.root_post_id, threads.root_post_created_at, threads.last_activity_at, threads.subject, threads.body_preview,
                    threads.reply_count, threads.score_total, threads.board_tags_json, threads.thread_labels_json, posts.author_label, posts.author_profile_slug,
                    posts.body AS root_post_body,
                    posts.post_score_total AS root_post_score_total,
                    profiles.username_token AS author_username_token, COALESCE(profiles.is_approved, 0) AS author_is_approved
             FROM threads
             JOIN posts ON posts.post_id = threads.root_post_id
             LEFT JOIN profiles ON profiles.identity_id = posts.author_identity_id
             WHERE threads.root_post_id = :root_post_id'
        );
        $stmt->execute(['root_post_id' => $threadId]);
        $thread = $stmt->fetch();

        return $thread === false ? null : $this->hydrateThreadRow($thread);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchBoardThreads(string $view, string $sort): array
    {
        $view = $this->normalizeBoardView($view);
        $sort = $this->normalizeBoardSort($sort);
        $threads = array_values(array_filter(
            $this->fetchThreads(),
            fn (array $thread): bool => $this->matchesBoardView($thread, $view)
        ));

        usort($threads, fn (array $left, array $right): int => $this->compareBoardThreads($left, $right, $sort));

        return $threads;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchThread(string $threadId): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT threads.root_post_id, threads.root_post_created_at, threads.last_activity_at, threads.subject, threads.body_preview,
                    threads.reply_count, threads.last_post_id, threads.score_total, threads.board_tags_json, threads.thread_labels_json, posts.author_label, posts.author_profile_slug,
                    profiles.username_token AS author_username_token, COALESCE(profiles.is_approved, 0) AS author_is_approved
             FROM threads
             JOIN posts ON posts.post_id = threads.root_post_id
             LEFT JOIN profiles ON profiles.identity_id = posts.author_identity_id
             WHERE threads.root_post_id = :thread_id'
        );
        $stmt->execute(['thread_id' => $threadId]);
        $thread = $stmt->fetch();

        if ($thread === false) {
            return null;
        }

        return $this->hydrateThreadRow($thread);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchThreadPosts(string $threadId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT posts.post_id, posts.thread_id, posts.parent_id, posts.subject, posts.body, posts.author_identity_id, posts.author_label,
                    posts.created_at, posts.board_tags_json,
                    posts.author_profile_slug, profiles.username_token AS author_username_token,
                    COALESCE(profiles.is_approved, 0) AS author_is_approved,
                    profiles.public_key AS author_public_key
             FROM posts
             LEFT JOIN profiles ON profiles.identity_id = posts.author_identity_id
             WHERE posts.thread_id = :thread_id
               AND posts.is_hidden = 0
             ORDER BY posts.sequence_number ASC'
        );
        $stmt->execute(['thread_id' => $threadId]);

        return $stmt->fetchAll();
    }

    /**
     * Bulk equivalent of fetchThreadPosts() across every thread at once (a
     * single query grouped in PHP), used by the Forte board view so it can
     * render every thread's reply tree without one query per thread.
     *
     * @return array<string, array<int, array<string, mixed>>> posts keyed by thread_id
     */
    private function fetchAllThreadReplyPosts(): array
    {
        $rows = $this->pdo()->query(
            'SELECT posts.post_id, posts.thread_id, posts.parent_id, posts.subject, posts.body, posts.author_identity_id, posts.author_label,
                    posts.created_at, posts.board_tags_json,
                    posts.author_profile_slug, profiles.username_token AS author_username_token,
                    COALESCE(profiles.is_approved, 0) AS author_is_approved,
                    profiles.public_key AS author_public_key
             FROM posts
             LEFT JOIN profiles ON profiles.identity_id = posts.author_identity_id
             WHERE posts.thread_id != posts.post_id
               AND posts.is_hidden = 0
             ORDER BY posts.thread_id ASC, posts.sequence_number ASC'
        )->fetchAll();

        $postsByThreadId = [];
        foreach ($rows as $row) {
            $postsByThreadId[(string) $row['thread_id']][] = $row;
        }

        return $postsByThreadId;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchPost(string $postId, bool $includeHidden = false): ?array
    {
        $hiddenWhere = $includeHidden ? '' : ' AND posts.is_hidden = 0';
        $stmt = $this->pdo()->prepare(
            'SELECT posts.post_id, posts.thread_id, posts.parent_id, posts.subject, posts.body, posts.author_label,
                    posts.created_at, posts.board_tags_json, posts.post_score_total, posts.is_hidden, posts.hidden_reason,
                    posts.author_identity_id, posts.author_profile_slug, profiles.username_token AS author_username_token,
                    COALESCE(profiles.is_approved, 0) AS author_is_approved,
                    profiles.public_key AS author_public_key
             FROM posts
             LEFT JOIN profiles ON profiles.identity_id = posts.author_identity_id
             WHERE posts.post_id = :post_id'
             . $hiddenWhere
        );
        $stmt->execute(['post_id' => $postId]);
        $post = $stmt->fetch();

        return $post === false ? null : $this->withPostSourceMetadata($post);
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private function withPostSourceMetadata(array $post): array
    {
        $sourcePath = $this->postSourcePath((string) $post['post_id']);
        $sourceCommitSha = $this->sourceCommitShaForPost((string) $post['post_id'], $sourcePath);
        $signature = $this->sourceSignatureLink($sourcePath);

        $post['source_path'] = $sourcePath;
        $post['source_commit_sha'] = $sourceCommitSha;
        $post['source_path_href'] = $this->sourcePathHref($sourcePath, $sourceCommitSha);
        $post['source_commit_href'] = $this->sourceCommitHref($sourceCommitSha);
        $post['source_signature_path'] = $signature['path'];
        $post['source_signature_href'] = $signature['href'];
        $post['source_signature_status'] = $this->sourceSignatureStatus(
            $sourcePath,
            (string) ($post['author_identity_id'] ?? ''),
            $signature['path']
        );

        return $this->withAuthorPublicKeyMetadata($post);
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private function withAuthorPublicKeyMetadata(array $post): array
    {
        $post['author_public_key_path'] = '';
        $post['author_public_key_href'] = '';
        if (trim((string) ($post['author_public_key'] ?? '')) === '') {
            return $post;
        }

        $identityId = strtolower(trim((string) ($post['author_identity_id'] ?? '')));
        if (preg_match('/^openpgp:([a-f0-9]{40})$/', $identityId, $matches) !== 1) {
            return $post;
        }

        $fingerprint = $matches[1];
        foreach ([strtoupper($fingerprint), $fingerprint] as $storedFingerprint) {
            $path = 'records/public-keys/openpgp-' . $storedFingerprint . '.asc';
            if (!$this->currentSourcePathExists($path)) {
                continue;
            }

            $post['author_public_key_path'] = $path;
            $post['author_public_key_href'] = '/source/current/' . $this->encodeSourcePathForUrl($path);
            break;
        }

        return $post;
    }

    private function postSourcePath(string $postId): string
    {
        $stmt = $this->pdo()->prepare(
            'SELECT source_path
             FROM activity
             WHERE post_id = :post_id
               AND kind IN (\'thread\', \'reply\')
               AND source_path IS NOT NULL
             ORDER BY id ASC
             LIMIT 1'
        );
        $stmt->execute(['post_id' => $postId]);
        $value = $stmt->fetchColumn();
        if (is_string($value) && $value !== '') {
            return $value;
        }

        foreach (CanonicalPathResolver::postCandidates($postId) as $candidate) {
            if (is_file($this->repositoryRoot . '/' . $candidate)) {
                return $candidate;
            }
        }

        foreach ($this->postRecordPaths() as $candidate) {
            if (basename($candidate) === $postId . '.txt') {
                return $candidate;
            }
        }

        return CanonicalPathResolver::post($postId);
    }

    /**
     * @return list<string>
     */
    private function postRecordPaths(): array
    {
        $paths = [];
        $postsRoot = $this->repositoryRoot . '/records/posts';
        if (!is_dir($postsRoot)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($postsRoot, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if (!$item->isFile() || !str_ends_with($item->getFilename(), '.txt')) {
                continue;
            }

            $paths[] = str_replace('\\', '/', substr($item->getPathname(), strlen($this->repositoryRoot) + 1));
        }

        sort($paths);

        return $paths;
    }

    private function sourceCommitShaForPost(string $postId, string $sourcePath): string
    {
        $stmt = $this->pdo()->prepare(
            'SELECT source_commit_sha
             FROM activity
             WHERE post_id = :post_id
               AND source_path = :source_path
             ORDER BY CASE WHEN kind IN (\'thread\', \'reply\') THEN 0 ELSE 1 END, id ASC
             LIMIT 1'
        );
        $stmt->execute([
            'post_id' => $postId,
            'source_path' => $sourcePath,
        ]);
        $value = $stmt->fetchColumn();

        return $value === false ? '' : (string) $value;
    }

    private function createdPostIdForThread(string $threadId, string $createdPostId): string
    {
        $createdPostId = trim($createdPostId);
        if ($createdPostId === '') {
            return '';
        }

        $post = $this->fetchPost($createdPostId);
        if ($post === null || (string) $post['thread_id'] !== $threadId) {
            return '';
        }

        return $createdPostId;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchProfileBySlug(string $slug): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT identity_id, profile_slug, username, username_token, fallback_label, signer_fingerprint, bootstrap_post_id,
                    bootstrap_thread_id, public_key, is_approved, approved_by_identity_id, approved_by_profile_slug,
                    approved_by_label, post_count, thread_count
             FROM profiles WHERE profile_slug = :profile_slug'
        );
        $stmt->execute(['profile_slug' => $slug]);
        $profile = $stmt->fetch();

        return $profile === false ? null : $profile;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchProfileByIdentityId(string $identityId): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT identity_id, profile_slug, username, username_token, fallback_label, signer_fingerprint, bootstrap_post_id,
                    bootstrap_thread_id, public_key, is_approved, approved_by_identity_id, approved_by_profile_slug,
                    approved_by_label, post_count, thread_count
             FROM profiles WHERE identity_id = :identity_id'
        );
        $stmt->execute(['identity_id' => $identityId]);
        $profile = $stmt->fetch();

        return $profile === false ? null : $profile;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchProfilesByUsernameToken(string $usernameToken): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT identity_id, profile_slug, username, username_token, fallback_label, signer_fingerprint, bootstrap_post_id,
                    bootstrap_thread_id, public_key, is_approved, approved_by_identity_id, approved_by_profile_slug,
                    approved_by_label, post_count, thread_count
             FROM profiles WHERE username_token = :username_token
             ORDER BY is_approved DESC, profile_slug ASC'
        );
        $stmt->execute(['username_token' => $usernameToken]);

        return $stmt->fetchAll();
    }

    /**
     * @param array<int, array<string, mixed>> $posts
     * @return array<string, array<string, mixed>>
     */
    private function fetchPostAnalysesForPosts(array $posts): array
    {
        if ($posts === []) {
            return [];
        }

        try {
            $store = new SqlitePostAnalysisStore($this->pdo());
            $unicodeRiskStore = new SqliteUnicodeRiskStore($this->pdo());
        } catch (\Throwable) {
            return [];
        }

        $analyses = [];
        foreach ($posts as $post) {
            $context = $this->postAnalysisContext($post, false);
            $analysis = $store->find((string) $context['post_id'], (string) $context['content_hash']);
            if ($analysis === null) {
                continue;
            }
            $unicodeRisk = $unicodeRiskStore->find((string) $context['post_id'], (string) $context['content_hash']);
            if ($unicodeRisk !== null) {
                $analysis['unicode_risk'] = $unicodeRisk;
            }

            $analyses[(string) $context['post_id']] = $analysis;
        }

        return $analyses;
    }

    /**
     * @param array<int, array<string, mixed>> $posts
     * @return array<string, array<string, mixed>>
     */
    private function fetchAgentReplyGenerationsForPosts(array $posts): array
    {
        if ($posts === []) {
            return [];
        }

        try {
            $store = new SqliteAgentReplyGenerationStore($this->pdo());
        } catch (\Throwable) {
            return [];
        }

        $generations = [];
        foreach ($posts as $post) {
            $context = $this->postAnalysisContext($post, false);
            $generation = $store->findByTarget((string) $context['post_id'], (string) $context['content_hash']);
            if ($generation === null) {
                continue;
            }

            $generations[(string) $context['post_id']] = $generation;
        }

        return $generations;
    }

    /**
     * @param array<int, array<string, mixed>> $posts
     * @return array<string, list<array<string, mixed>>>
     */
    private function fetchLlmExchangesForPosts(array $posts): array
    {
        $store = $this->llmExchangeStore();
        if ($store === null) {
            return [];
        }

        $exchanges = [];
        foreach ($posts as $post) {
            $postId = (string) ($post['post_id'] ?? '');
            if ($postId === '') {
                continue;
            }

            $rows = $store->forPost($postId, 20);
            if ($rows !== []) {
                $exchanges[$postId] = $rows;
            }
        }

        return $exchanges;
    }

    /**
     * @param array<int, array<string, mixed>> $posts
     * @return array<string, array<string, mixed>>
     */
    private function fetchCodexHandoffsForPosts(array $posts): array
    {
        if ($posts === []) {
            return [];
        }

        try {
            $store = $this->codexHandoffStore();
        } catch (\Throwable) {
            return [];
        }

        $handoffs = [];
        foreach ($posts as $post) {
            $handoff = $store->findByPost($post);
            if ($handoff === null) {
                continue;
            }

            $handoffs[(string) $post['post_id']] = $handoff;
        }

        return $handoffs;
    }

    /**
     * @param array<int, array<string, mixed>> $posts
     * @param array<string, array<string, mixed>> $analysesByPostId
     * @param array<string, array<string, mixed>> $agentRepliesByPostId
     * @return array<string, string>
     */
    private function agentReplyWorkByPostId(
        array $posts,
        string $createdPostId,
        array $analysesByPostId,
        array $agentRepliesByPostId
    ): array {
        if ($createdPostId === '' || !$this->agentRepliesAutomaticEnabled()) {
            return [];
        }

        foreach ($posts as $post) {
            if ((string) ($post['post_id'] ?? '') !== $createdPostId) {
                continue;
            }

            $work = $this->agentReplyWorkForPost(
                $post,
                $analysesByPostId[$createdPostId] ?? null,
                $agentRepliesByPostId[$createdPostId] ?? null
            );

            return $work === 'none' ? [] : [$createdPostId => $work];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed>|null $analysis
     * @param array<string, mixed>|null $agentReply
     */
    private function agentReplyWorkForPost(array $post, ?array $analysis, ?array $agentReply): string
    {
        if ((string) ($post['author_label'] ?? '') === AgentIdentityService::USERNAME) {
            return 'none';
        }

        if ($agentReply !== null) {
            if ($agentReply['agent_post_id'] !== null) {
                return 'none';
            }

            if ((string) ($agentReply['status'] ?? '') !== 'complete') {
                return 'none';
            }
        }

        if ($analysis === null) {
            return 'analyze';
        }

        if (($analysis['status'] ?? null) !== 'complete') {
            return 'none';
        }

        return $this->agentReplyGateFailure($post, $analysis) === null ? 'publish' : 'none';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchSeedApprovedUsers(): array
    {
        $stmt = $this->pdo()->query(
            'SELECT username_token, MIN(username) AS username
             FROM profiles
             WHERE approved_by_label = \'root\'
             GROUP BY username_token
             ORDER BY username_token ASC'
        );

        return $stmt->fetchAll();
    }

    /**
     * @param list<string> $identityIds
     * @return array<int, array<string, mixed>>
     */
    private function fetchVisibleAuthoredThreads(array $identityIds): array
    {
        if ($identityIds === []) {
            return [];
        }

        $stmt = $this->prepareIdentityListQuery(
            'SELECT threads.root_post_id, threads.root_post_created_at, threads.last_activity_at, threads.subject, threads.body_preview,
                    threads.reply_count, threads.last_post_id, threads.score_total, threads.board_tags_json, threads.thread_labels_json, posts.author_label, posts.author_profile_slug,
                    profiles.username_token AS author_username_token, COALESCE(profiles.is_approved, 0) AS author_is_approved
             FROM threads
             JOIN posts ON posts.post_id = threads.root_post_id
             LEFT JOIN profiles ON profiles.identity_id = posts.author_identity_id
             WHERE threads.root_post_id IN (
                 SELECT post_id FROM posts
                 WHERE post_id = thread_id AND author_identity_id IN (%s)
             )
             ORDER BY last_activity_at DESC, root_post_id ASC',
            $identityIds
        );
        $stmt->execute($identityIds);
        $rows = $stmt->fetchAll();

        $rows = array_values(array_filter(
            $rows,
            fn (array $thread): bool => !$this->isHiddenBootstrapBoardTagsJson((string) $thread['board_tags_json'])
        ));

        return $this->hydrateThreadRows($rows);
    }

    /**
     * @param list<string> $identityIds
     * @return array<int, array<string, mixed>>
     */
    private function fetchVisibleAuthoredPosts(array $identityIds): array
    {
        if ($identityIds === []) {
            return [];
        }

        $stmt = $this->prepareIdentityListQuery(
            'SELECT posts.post_id, posts.created_at, posts.thread_id, posts.parent_id, posts.subject, posts.body, posts.author_label,
                    posts.author_profile_slug, profiles.username_token AS author_username_token,
                    COALESCE(profiles.is_approved, 0) AS author_is_approved, posts.board_tags_json
             FROM posts
             LEFT JOIN profiles ON profiles.identity_id = posts.author_identity_id
             WHERE author_identity_id IN (%s)
               AND posts.is_hidden = 0
             ORDER BY created_at DESC, sequence_number DESC, post_id DESC',
            $identityIds
        );
        $stmt->execute($identityIds);
        $rows = $stmt->fetchAll();

        return array_values(array_filter(
            $rows,
            fn (array $post): bool => !$this->isHiddenBootstrapBoardTagsJson((string) $post['board_tags_json'])
        ));
    }

    /**
     * @param list<string> $identityIds
     */
    private function countVisibleAuthoredRows(array $identityIds, bool $threadsOnly): int
    {
        return count($threadsOnly ? $this->fetchVisibleAuthoredThreads($identityIds) : $this->fetchVisibleAuthoredPosts($identityIds));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function hydrateThreadRows(array $rows): array
    {
        return array_map(fn (array $thread): array => $this->hydrateThreadRow($thread), $rows);
    }

    /**
     * @param array<string, mixed> $thread
     * @return array<string, mixed>
     */
    private function hydrateThreadRow(array $thread): array
    {
        $thread['score_total'] = (int) ($thread['score_total'] ?? 0);
        $thread['root_post_score_total'] = (int) ($thread['root_post_score_total'] ?? 0);
        $thread['board_tags'] = $this->decodeStringList((string) ($thread['board_tags_json'] ?? '[]'));
        $thread['thread_labels'] = $this->decodeStringList((string) ($thread['thread_labels_json'] ?? '[]'));

        return $thread;
    }

    private function normalizeBoardView(string $view): string
    {
        return in_array($view, ['all', 'liked'], true) ? $view : 'all';
    }

    private function normalizeBoardSort(string $sort): string
    {
        return in_array($sort, ['newest', 'oldest', 'top'], true) ? $sort : 'newest';
    }

    /**
     * @return array<int, array{label:string,href:string,is_active:bool,key:string}>
     */
    private function boardViewOptions(string $activeView, string $activeSort): array
    {
        return [
            [
                'key' => 'all',
                'label' => 'All',
                'href' => '/threads/?view=all&sort=' . rawurlencode($activeSort),
                'is_active' => $activeView === 'all',
            ],
            [
                'key' => 'liked',
                'label' => 'Liked',
                'href' => '/threads/?view=liked&sort=' . rawurlencode($activeSort),
                'is_active' => $activeView === 'liked',
            ],
        ];
    }

    /**
     * @return array<int, array{label:string,href:string,is_active:bool,key:string}>
     */
    private function boardSortOptions(string $activeView, string $activeSort): array
    {
        return [
            [
                'key' => 'newest',
                'label' => 'Newest',
                'href' => '/threads/?view=' . rawurlencode($activeView) . '&sort=newest',
                'is_active' => $activeSort === 'newest',
            ],
            [
                'key' => 'oldest',
                'label' => 'Oldest',
                'href' => '/threads/?view=' . rawurlencode($activeView) . '&sort=oldest',
                'is_active' => $activeSort === 'oldest',
            ],
            [
                'key' => 'top',
                'label' => 'Top',
                'href' => '/threads/?view=' . rawurlencode($activeView) . '&sort=top',
                'is_active' => $activeSort === 'top',
            ],
        ];
    }

    /**
     * @param array<int, array{label:string,href:string,is_active:bool,key:string}> $options
     */
    private function activeBoardOptionLabel(array $options, string $activeKey): string
    {
        foreach ($options as $option) {
            if ($option['key'] === $activeKey) {
                return $option['label'];
            }
        }

        return $activeKey;
    }

    /**
     * @param array<string, mixed> $thread
     */
    private function matchesBoardView(array $thread, string $view): bool
    {
        return match ($view) {
            'all' => true,
            'liked' => in_array('like', $thread['thread_labels'] ?? [], true)
                && ((int) ($thread['root_post_score_total'] ?? 0)) >= 0,
            default => true,
        };
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function compareBoardThreads(array $left, array $right, string $sort): int
    {
        $pinnedCompare = $this->compareBoardThreadPinnedStatus($left, $right);
        if ($pinnedCompare !== 0) {
            return $pinnedCompare;
        }

        return match ($sort) {
            'oldest' => $this->compareBoardThreadOldest($left, $right),
            'top' => $this->compareBoardThreadTop($left, $right),
            default => $this->compareBoardThreadNewest($left, $right),
        };
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function compareBoardThreadPinnedStatus(array $left, array $right): int
    {
        return ((int) $this->isPinnedThread($right)) <=> ((int) $this->isPinnedThread($left));
    }

    /**
     * @param array<string, mixed> $thread
     */
    private function isPinnedThread(array $thread): bool
    {
        return in_array('pinned', $thread['thread_labels'] ?? [], true);
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function compareBoardThreadNewest(array $left, array $right): int
    {
        $createdCompare = strcmp((string) $right['root_post_created_at'], (string) $left['root_post_created_at']);
        if ($createdCompare !== 0) {
            return $createdCompare;
        }

        return strcmp((string) $right['root_post_id'], (string) $left['root_post_id']);
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function compareBoardThreadOldest(array $left, array $right): int
    {
        $createdCompare = strcmp((string) $left['root_post_created_at'], (string) $right['root_post_created_at']);
        if ($createdCompare !== 0) {
            return $createdCompare;
        }

        return strcmp((string) $left['root_post_id'], (string) $right['root_post_id']);
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function compareBoardThreadTop(array $left, array $right): int
    {
        $scoreCompare = ((int) $right['score_total']) <=> ((int) $left['score_total']);
        if ($scoreCompare !== 0) {
            return $scoreCompare;
        }

        return $this->compareBoardThreadNewest($left, $right);
    }

    /**
     * @param array<int, array<string, mixed>> $threads
     * @return array<int, array{tag:string,count:int,threads:array<int, array<string, mixed>>}>
     */
    private function groupThreadsByTag(array $threads): array
    {
        $groups = [];

        foreach ($threads as $thread) {
            $tags = [];
            foreach (['board_tags', 'thread_labels'] as $field) {
                $values = $thread[$field] ?? [];
                if (!is_array($values)) {
                    continue;
                }

                foreach ($values as $value) {
                    if (is_string($value) && $value !== '' && !in_array($value, $tags, true)) {
                        $tags[] = $value;
                    }
                }
            }

            foreach ($tags as $tag) {
                if (!is_string($tag) || $tag === '') {
                    continue;
                }

                $groups[$tag] ??= [
                    'tag' => $tag,
                    'count' => 0,
                    'threads' => [],
                ];
                if (!in_array($thread['root_post_id'], array_column($groups[$tag]['threads'], 'root_post_id'), true)) {
                    $groups[$tag]['count']++;
                    $groups[$tag]['threads'][] = $thread;
                }
            }
        }

        uasort($groups, static function (array $left, array $right): int {
            if ($left['count'] !== $right['count']) {
                return $right['count'] <=> $left['count'];
            }

            return $left['tag'] <=> $right['tag'];
        });

        return array_values($groups);
    }

    /**
     * @param array<int, array{tag:string,count:int,threads:array<int, array<string, mixed>>}> $groups
     * @return array<int, array{tag:string,count:int,threads:array<int, array<string, mixed>>,preview_threads:array<int, array<string, mixed>>,href:string,has_more:bool}>
     */
    private function limitTagGroupThreads(array $groups, int $limit): array
    {
        $limited = [];

        foreach ($groups as $group) {
            $threads = $group['threads'];
            $group['preview_threads'] = array_slice($threads, 0, $limit);
            $group['has_more'] = count($threads) > $limit;
            $group['href'] = '/tags/' . $group['tag'];
            $limited[] = $group;
        }

        return $limited;
    }

    /**
     * @param array<int, array{tag:string,count:int,threads:array<int, array<string, mixed>>}> $groups
     * @return array{tag:string,count:int,threads:array<int, array<string, mixed>>}|null
     */
    private function findTagGroup(array $groups, string $tag): ?array
    {
        foreach ($groups as $group) {
            if ($group['tag'] === $tag) {
                return $group;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function decodeStringList(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $values = [];
        foreach ($decoded as $value) {
            if (is_string($value)) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * @param list<string> $identityIds
     */
    private function prepareIdentityListQuery(string $sql, array $identityIds): PDOStatement
    {
        $placeholders = implode(', ', array_fill(0, count($identityIds), '?'));

        return $this->pdo()->prepare(sprintf($sql, $placeholders));
    }

    private function handleRepositoryDownload(string $method, string $format): void
    {
        if ($method !== 'GET') {
            $this->sendHtml($this->renderMessagePage('Method Not Allowed', 'Method Not Allowed', 'Only GET is supported for downloads.', 'none'), 405);
            return;
        }

        $download = $this->buildRepositoryArchive($format);
        $this->sendDownload(
            $download['path'],
            $download['contentType'],
            $this->repositoryArchiveDownloadFilename($download['extension']),
            true
        );
    }

    private function repositoryArchiveDownloadFilename(string $extension): string
    {
        return SiteConfig::siteName()
            . '-repository-'
            . $this->downloadTimestamp()
            . '-'
            . $this->repositoryShortCommit()
            . '.'
            . $extension;
    }

    private function downloadTimestamp(): string
    {
        return gmdate('Y-m-d_H-i-s\Z');
    }

    /**
     * @return array{path: string, contentType: string, extension: string}
     */
    private function buildRepositoryArchive(string $format): array
    {
        $archivePath = tempnam(sys_get_temp_dir(), 'forum-repo-');
        if ($archivePath === false) {
            throw new RuntimeException('Unable to create temporary archive path.');
        }

        @unlink($archivePath);

        $parent = dirname($this->repositoryRoot);
        $base = basename($this->repositoryRoot);

        if ($format === 'tar.gz') {
            $archiveTarget = $archivePath . '.tar.gz';
            $command = sprintf(
                'tar -czf %s -C %s %s 2>&1',
                escapeshellarg($archiveTarget),
                escapeshellarg($parent),
                escapeshellarg($base)
            );
            $contentType = 'application/gzip';
        } elseif ($format === 'zip') {
            $archiveTarget = $archivePath . '.zip';
            $command = sprintf(
                'cd %s && zip -qr %s %s 2>&1',
                escapeshellarg($parent),
                escapeshellarg($archiveTarget),
                escapeshellarg($base)
            );
            $contentType = 'application/zip';
        } else {
            throw new RuntimeException('Unsupported repository archive format.');
        }

        exec($command, $output, $exitCode);
        if ($exitCode !== 0 || !is_file($archiveTarget)) {
            @unlink($archiveTarget);
            throw new RuntimeException('Unable to archive repository download.');
        }

        return [
            'path' => $archiveTarget,
            'contentType' => $contentType,
            'extension' => $format,
        ];
    }

    private function handleReadModelDatabaseDownload(string $method): void
    {
        if ($method !== 'GET') {
            $this->sendHtml($this->renderMessagePage('Method Not Allowed', 'Method Not Allowed', 'Only GET is supported for downloads.', 'none'), 405);
            return;
        }

        if (!is_file($this->databasePath)) {
            $this->sendHtml($this->renderMessagePage('Not Found', 'Not Found', 'Read-model database is not available yet.', 'instance'), 404);
            return;
        }

        $this->sendDownload($this->databasePath, 'application/x-sqlite3', SiteConfig::siteName() . '-read-model.sqlite3');
    }

    private function handleSqliteQueryCatalogDownload(string $method): void
    {
        if ($method !== 'GET') {
            $this->sendHtml($this->renderMessagePage('Method Not Allowed', 'Method Not Allowed', 'Only GET is supported for downloads.', 'none'), 405);
            return;
        }

        $path = $this->projectRoot . '/public/assets/sqlite_query_catalog.sql';
        if (!is_file($path)) {
            $this->sendHtml($this->renderMessagePage('Not Found', 'Not Found', 'SQLite query catalog is not available yet.', 'instance'), 404);
            return;
        }

        $this->sendDownload($path, 'application/sql; charset=utf-8', SiteConfig::siteName() . '-sqlite-query-catalog.sql');
    }

    private function sendDownload(string $path, string $contentType, string $filename, bool $deleteAfterSend = false): void
    {
        $size = filesize($path);
        if ($size === false) {
            if ($deleteAfterSend) {
                @unlink($path);
            }
            throw new RuntimeException('Unable to determine download size.');
        }

        http_response_code(200);
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        header('Content-Length: ' . (string) $size);
        readfile($path);

        if ($deleteAfterSend) {
            @unlink($path);
        }
    }

    private function repositoryShortCommit(): string
    {
        $command = sprintf('git -C %s rev-parse --short HEAD 2>&1', escapeshellarg($this->repositoryRoot));
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            return 'unknown';
        }

        $shortCommit = trim(implode("\n", $output));

        return $shortCommit !== '' ? $shortCommit : 'unknown';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveViewerProfileFromIdentityHint(): ?array
    {
        if ($this->approvedMembersOnlyEnabled()) {
            return $this->lobbyViewerProfile();
        }

        $authenticatedIdentityId = strtolower(trim((string) (($_SESSION ?? [])['authenticated_identity_id'] ?? '')));
        if ($authenticatedIdentityId !== '') {
            $authenticatedProfile = $this->fetchProfileByIdentityId($authenticatedIdentityId);
            if ($authenticatedProfile !== null) {
                $authenticatedProfile['_authenticated_identity'] = true;
                return $authenticatedProfile;
            }
        }

        $hint = strtolower(trim((string) ($_COOKIE['identity_hint'] ?? '')));
        if ($hint === '') {
            return null;
        }

        $stmt = $this->pdo()->prepare('SELECT identity_id FROM username_routes WHERE username_token = :username_token');
        $stmt->execute(['username_token' => $hint]);
        $route = $stmt->fetch();
        if ($route !== false) {
            $profile = $this->fetchProfileByIdentityId((string) $route['identity_id']);
            if ($profile !== null) {
                $profile['_authenticated_identity'] = false;
            }
            return $profile;
        }

        if (str_starts_with($hint, 'openpgp:')) {
            $profile = $this->fetchProfileByIdentityId($hint);
            if ($profile !== null) {
                $profile['_authenticated_identity'] = false;
            }
            return $profile;
        }

        $profile = $this->fetchProfileBySlug($hint);
        if ($profile !== null) {
            $profile['_authenticated_identity'] = false;
        }
        return $profile;
    }

    private function approvedMembersOnlyEnabled(): bool
    {
        return $this->featureFlags()->isEnabled(FeatureFlagRegistry::APPROVED_MEMBERS_ONLY);
    }

    private function authenticatedViewerProfile(): ?array
    {
        $identityId = strtolower(trim((string) ($_SESSION['authenticated_identity_id'] ?? '')));
        if ($identityId === '') {
            return null;
        }

        return $this->fetchProfileByIdentityId($identityId);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lobbyViewerProfile(): ?array
    {
        $profile = $this->authenticatedViewerProfile();
        if ($profile !== null) {
            $profile['_authenticated_identity'] = true;
            $profile['_members_only_access'] = ((int) ($profile['is_approved'] ?? 0)) === 1;
            return $profile;
        }

        $identityId = strtolower(trim((string) ($_SESSION['lobby_identity_id'] ?? '')));
        if ($identityId === '') {
            return null;
        }

        $profile = $this->fetchProfileByIdentityId($identityId);
        if ($profile === null) {
            unset($_SESSION['lobby_identity_id']);
            return null;
        }

        $profile['_authenticated_identity'] = false;
        $profile['_members_only_access'] = false;
        return $profile;
    }

    private function membersOnlyRequestAllowed(string $method, string $path): bool
    {
        if ($path === '/lobby/' || $path === '/lobby'
            || $path === '/account/key/' || $path === '/account/key'
            || $path === '/api/auth_challenge' || $path === '/api/authenticate_identity' || $path === '/api/auth_status'
            || $path === '/api/set_identity_hint' || $path === '/api/clear_identity'
            || $path === '/api/link_identity'
            || $path === '/api/prepare_identity' || $path === '/api/create_identity'
            || $path === '/api/prepare_invitation_redemption'
            || $path === '/api/create_prepared_invitation'
        ) {
            return true;
        }

        $viewerProfile = $this->authenticatedViewerProfile();
        if ($viewerProfile !== null && ((int) ($viewerProfile['is_approved'] ?? 0)) === 1) {
            return true;
        }

        $lobbyViewerProfile = $this->lobbyViewerProfile();
        if ($lobbyViewerProfile === null || $method !== 'GET') {
            return false;
        }

        if (preg_match('#^/profiles/([^/]+)/?$#', $path, $matches) !== 1) {
            return false;
        }

        return hash_equals(
            strtolower((string) ($lobbyViewerProfile['profile_slug'] ?? '')),
            strtolower(rawurldecode($matches[1]))
        );
    }

    private function isApplicationRoute(string $path): bool
    {
        if ($this->isForteApplicationRoute($path)) {
            return true;
        }

        if (in_array($path, [
            '', '/',
            '/threads', '/threads/',
            '/about', '/about/',
            '/lobby', '/lobby/',
            '/instance', '/instance/', '/backup', '/backup/', '/tools/backup', '/tools/backup/',
            '/tools/sqlite', '/tools/sqlite/',
            '/tools/llm-exchanges', '/tools/llm-exchanges/',
            '/downloads/repository.tar.gz', '/downloads/repository.zip',
            '/downloads/read_model.sqlite3', '/downloads/sqlite_query_catalog.sql',
            '/activity', '/activity/',
            '/users', '/users/', '/users/pending', '/users/pending/',
            '/tags', '/tags/',
            '/tools', '/tools/', '/tools/bookmarklets', '/tools/bookmarklets/',
            '/tools/codebase', '/tools/codebase/', '/tools/feature-flags', '/tools/feature-flags/',
            '/compose/thread', '/compose/reply',
            '/account/key', '/account/key/', '/invites', '/invites/',
            '/api', '/api/', '/api/version', '/api/list_index',
            '/api/get_thread', '/api/get_post', '/api/get_profile', '/api/get_username_claim_cta',
            '/api/read_model_status', '/api/set_identity_hint', '/api/clear_identity',
            '/api/auth_challenge', '/api/authenticate_identity', '/api/auth_status', '/api/create_thread',
            '/api/prepare_thread', '/api/prepare_identity', '/api/create_reply',
            '/api/prepare_reply', '/api/create_prepared_post', '/api/create_identity',
            '/api/analyze_post', '/api/generate_agent_reply', '/api/codex_handoff',
            '/api/codex_handoff_approval', '/api/apply_thread_tag', '/api/apply_post_tag',
            '/api/prepare_invitation', '/api/create_prepared_invitation', '/api/prepare_invitation_redemption',
            '/api/set_feature_flag', '/api/link_identity', '/api/approve_user',
            '/forte', '/forte/', '/llms.txt',
        ], true)) {
            return true;
        }

        return preg_match(
            '#^/(?:tools/llm-exchanges/\d+|source/current/.+|source/blob/[^/]+/.+|source/commits/[^/]+|threads/[^/]+(?:/forte)?|forte/threads/[^/]+/replies|tags/[a-z0-9]+(?:-[a-z0-9]+)*|posts/[^/]+|profiles/[^/]+(?:/approve)?|user/[^/]+)/?$#',
            $path,
        ) === 1;
    }

    private function isForteApplicationRoute(string $path): bool
    {
        return $path === '/forte'
            || str_starts_with($path, '/forte/')
            || str_starts_with($path, '/api/forte_')
            || str_starts_with($path, '/api/get_forte_');
    }

    /**
     * @param array<string, mixed> $query
     */
    private function membersOnlyLobbyRedirect(string $method, string $path, array $query): bool
    {
        $viewerProfile = $this->authenticatedViewerProfile();
        $hasApprovedMemberAccess = $viewerProfile !== null
            && ((int) ($viewerProfile['is_approved'] ?? 0)) === 1;

        return $this->lobbyViewerProfile() !== null
            && $method === 'GET' && in_array($path, ['/', '/threads', '/threads/'], true)
            && $query === []
            && !$hasApprovedMemberAccess;
    }

    /** @param array<string, mixed> $query */
    private function shouldRenderAuthenticationResume(string $method, string $path, array $query): bool
    {
        if ($method !== 'GET'
            || $this->lobbyViewerProfile() !== null
            || str_starts_with($path, '/api')
            || str_starts_with($path, '/downloads/')
            || (($query['format'] ?? null) === 'rss')
        ) {
            return false;
        }

        return true;
    }

    private function renderAuthenticationResumePage(string $returnTo): string
    {
        return $this->renderPageTemplate(
            'authentication_resume.php',
            ['returnTo' => $returnTo],
            'Reconnecting',
            'account',
            $this->identityScripts(['/assets/private_site_auth.js']),
        );
    }

    private function handleAuthenticationStatus(string $method): void
    {
        if ($method !== 'GET') {
            $this->sendText("method not allowed\n", 405, $this->noStoreHeaders());
            return;
        }

        $viewerProfile = $this->authenticatedViewerProfile();
        $isApproved = $viewerProfile !== null && ((int) ($viewerProfile['is_approved'] ?? 0)) === 1;
        $this->sendText(
            $isApproved ? "status=authenticated\n" : "status=unauthenticated\n",
            $isApproved ? 200 : 401,
            $this->noStoreHeaders(),
        );
    }

    private function renderLobby(): string
    {
        return $this->renderPageTemplate(
            'lobby.php',
            [
                'viewerProfile' => $this->lobbyViewerProfile(),
            ],
            'Lobby',
            'lobby',
            $this->identityScripts(['/assets/private_site_auth.js', '/assets/invite_redemption.js'])
        );
    }

    /** @param array<string, mixed> $query */
    private function renderInvitationPage(array $query): string
    {
        $viewer = $this->authenticatedViewerProfile();
        if ($viewer === null || ((int) ($viewer['is_approved'] ?? 0)) !== 1) {
            return $this->renderMessagePage('Invitation required', 'Invitation required', 'Only authenticated approved members can generate invitations.', 'account');
        }

        return $this->renderPageTemplate(
            'invites.php',
            ['destination' => trim((string) ($query['destination'] ?? ''))],
            'Generate invite',
            'invite',
            $this->identityScripts(['/assets/invite_issuance.js']),
        );
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

    /**
     * @param array<string, mixed>|null $viewerProfile
     */
    private function viewerCanUseCodexHandoff(?array $viewerProfile): bool
    {
        return $viewerProfile !== null
            && ((int) ($viewerProfile['is_approved'] ?? 0)) === 1
            && $this->requestIsLocalhost();
    }

    /**
     * @param array<int, array<string, mixed>> $posts
     * @param array<string, mixed>|null $thread
     * @return array<string, bool>
     */
    private function codexHandoffEligiblePostIds(array $posts, ?array $thread = null): array
    {
        $eligible = [];
        foreach ($posts as $post) {
            if ($this->postCanUseCodexHandoffTarget($post, $thread)) {
                $eligible[(string) ($post['post_id'] ?? '')] = true;
            }
        }

        return $eligible;
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed>|null $thread
     */
    private function postCanUseCodexHandoffTarget(array $post, ?array $thread = null): bool
    {
        if ((string) ($post['author_label'] ?? '') === AgentIdentityService::USERNAME) {
            return false;
        }

        if ($this->hasCodexHandoffTag($this->decodeStringList((string) ($post['board_tags_json'] ?? '[]')))) {
            return true;
        }

        if ((string) ($post['post_id'] ?? '') !== (string) ($post['thread_id'] ?? '')) {
            return false;
        }

        $threadLabels = [];
        if ($thread !== null) {
            $threadLabels = is_array($thread['thread_labels'] ?? null)
                ? array_map('strval', $thread['thread_labels'])
                : $this->decodeStringList((string) ($thread['thread_labels_json'] ?? '[]'));
        }

        return $this->hasCodexHandoffTag($threadLabels);
    }

    /**
     * @param list<string> $tags
     */
    private function hasCodexHandoffTag(array $tags): bool
    {
        foreach ($tags as $tag) {
            if (in_array(strtolower($tag), self::CODEX_HANDOFF_DEVELOPMENT_TAGS, true)) {
                return true;
            }
        }

        return false;
    }

    private function requestIsLocalhost(): bool
    {
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
        if ($host !== '') {
            $host = strtolower($host);
            if (str_starts_with($host, '[')) {
                $closingBracket = strpos($host, ']');
                $host = $closingBracket === false ? $host : substr($host, 1, $closingBracket - 1);
            } elseif (str_contains($host, ':')) {
                $host = explode(':', $host, 2)[0];
            }

            return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
                || str_ends_with($host, '.localhost');
        }

        $remoteAddress = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($remoteAddress === '') {
            return PHP_SAPI === 'cli';
        }

        return in_array($remoteAddress, ['127.0.0.1', '::1'], true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchApprovedUserDirectoryUsers(): array
    {
        $stmt = $this->pdo()->query(
            'SELECT username_token, MIN(username) AS username,
                    COUNT(*) AS approved_profile_count,
                    SUM(thread_count) AS thread_count,
                    SUM(post_count) AS post_count
             FROM profiles
             WHERE is_approved = 1
             GROUP BY username_token
             ORDER BY SUM(thread_count) DESC, SUM(post_count) DESC, username_token ASC'
        );

        return $stmt->fetchAll();
    }

    /**
     * Earliest/latest authored, non-hidden post per approved
     * `username_token`, rolled up across every identity that shares the
     * token (mirrors `fetchApprovedUserDirectoryUsers()`'s own `SUM(...)
     * GROUP BY username_token` rollup) - backs the "Recently Active" filter
     * category and the Users pane's "Active"/"Joined" columns. No per-user
     * timestamp is precomputed anywhere else.
     *
     * @return array<string, array{earliest: string, latest: string}> username_token => bounds (ISO 8601 UTC)
     */
    private function fetchUserDirectoryActivityBoundsByToken(): array
    {
        $stmt = $this->pdo()->query(
            'SELECT profiles.username_token,
                    MIN(posts.created_at) AS earliest_activity_at,
                    MAX(posts.created_at) AS latest_activity_at
             FROM posts
             JOIN profiles ON profiles.identity_id = posts.author_identity_id
             WHERE profiles.is_approved = 1 AND posts.is_hidden = 0
             GROUP BY profiles.username_token'
        );

        $boundsByToken = [];
        foreach ($stmt->fetchAll() as $row) {
            $boundsByToken[(string) $row['username_token']] = [
                'earliest' => (string) $row['earliest_activity_at'],
                'latest' => (string) $row['latest_activity_at'],
            ];
        }

        return $boundsByToken;
    }

    /**
     * Per-user semantic filter flags for the Users pane's category filter
     * (replaces the alphabetical grouping): "established" needs at least
     * one thread AND at least one reply, since `post_count` already
     * includes every reply plus every thread's root post - a thread with no
     * replies from anyone else still leaves `post_count - thread_count`
     * at 0. A user can carry multiple flags at once (e.g. no threads yet
     * but active this week), so these are independent membership flags,
     * not a mutually-exclusive partition like the old letter buckets.
     * Reads `active_at` straight off each `$users` row (the caller already
     * merges the activity bounds in) rather than taking a second lookup.
     *
     * @param array<int, array<string, mixed>> $users
     * @return array<string, array{new: bool, established: bool, no_threads: bool, recently_active: bool}>
     */
    private function buildUserDirectoryCategoryFlags(array $users): array
    {
        $recentActivityThreshold = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify('-7 days')
            ->format('Y-m-d\TH:i:s\Z');

        $flagsByToken = [];
        foreach ($users as $user) {
            $token = (string) $user['username_token'];
            $threadCount = (int) $user['thread_count'];
            $replyCount = ((int) $user['post_count']) - $threadCount;
            $established = $threadCount >= 1 && $replyCount >= 1;
            $activeAt = (string) ($user['active_at'] ?? '');

            $flagsByToken[$token] = [
                'new' => !$established,
                'established' => $established,
                'no_threads' => $threadCount === 0,
                'recently_active' => $activeAt !== '' && $activeAt >= $recentActivityThreshold,
            ];
        }

        return $flagsByToken;
    }

    /**
     * Fixed-category counts for the Users pane's filter list, mirroring
     * `renderForteActivity()`'s `$viewCounts` shape. Category keys are
     * hyphenated for the URL/DOM (`no-threads`, `recently-active`,
     * `not-approved`) even though `buildUserDirectoryCategoryFlags()`'s
     * internal flag keys use underscores - only two need translating.
     *
     * @param array<string, array{new: bool, established: bool, no_threads: bool, recently_active: bool}> $flagsByToken
     * @return list<array{key: string, label: string, count: int}>
     */
    private function buildUserDirectoryCategoryCounts(int $totalApprovedCount, array $flagsByToken, int $pendingCount): array
    {
        $counts = ['new' => 0, 'established' => 0, 'no_threads' => 0, 'recently_active' => 0];
        foreach ($flagsByToken as $flags) {
            foreach ($flags as $key => $value) {
                if ($value) {
                    $counts[$key]++;
                }
            }
        }

        return [
            ['key' => 'all', 'label' => 'All Users', 'count' => $totalApprovedCount],
            ['key' => 'new', 'label' => 'New', 'count' => $counts['new']],
            ['key' => 'established', 'label' => 'Established', 'count' => $counts['established']],
            ['key' => 'no-threads', 'label' => 'No Threads', 'count' => $counts['no_threads']],
            ['key' => 'recently-active', 'label' => 'Recently Active', 'count' => $counts['recently_active']],
            ['key' => 'not-approved', 'label' => 'Not Approved', 'count' => $pendingCount],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchPendingUserDirectoryProfiles(): array
    {
        $stmt = $this->pdo()->query(
            'SELECT profile_slug, username, username_token, fallback_label, post_count, thread_count, bootstrap_post_id, bootstrap_thread_id
             FROM profiles
             WHERE is_approved = 0
             ORDER BY thread_count DESC, post_count DESC, username_token ASC, profile_slug ASC'
        );

        return $stmt->fetchAll();
    }

    /**
     * Pending profiles for the Forte Users pane's "Not Approved" category,
     * rolled up by `username_token` like `fetchApprovedUserDirectoryUsers()`
     * - but excluding any token that already has an approved profile.
     * Real data has both: a `username_token` can carry several duplicate
     * pending submissions (seen locally: "guest" x10), and an already-
     * approved user can independently accumulate further pending profiles
     * under their own name (seen locally: "ilyag"). Without the exclusion,
     * an approved, already-listed user would also turn up under Not
     * Approved as if they were a second, different pending user - the
     * opposite of "not visible anywhere else".
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchNeverApprovedPendingUserDirectoryUsers(): array
    {
        $stmt = $this->pdo()->query(
            'SELECT username_token, MIN(username) AS username,
                    COUNT(*) AS pending_profile_count,
                    SUM(thread_count) AS thread_count,
                    SUM(post_count) AS post_count
             FROM profiles
             WHERE is_approved = 0
               AND username_token NOT IN (SELECT username_token FROM profiles WHERE is_approved = 1)
             GROUP BY username_token
             ORDER BY SUM(thread_count) DESC, SUM(post_count) DESC, username_token ASC'
        );

        return $stmt->fetchAll();
    }

    /**
     * @return array{column: string, dir: string}
     */
    private function resolveUserDirectorySort(string $requestedColumn, string $requestedDir): array
    {
        $validColumns = ['username', 'threads', 'posts', 'active', 'joined'];
        if (!in_array($requestedColumn, $validColumns, true)) {
            return ['column' => '', 'dir' => ''];
        }

        $defaultDir = $requestedColumn === 'username' ? 'asc' : 'desc';
        $dir = in_array($requestedDir, ['asc', 'desc'], true) ? $requestedDir : $defaultDir;

        return ['column' => $requestedColumn, 'dir' => $dir];
    }

    /**
     * @param array<int, array<string, mixed>> $users
     * @return array<int, array<string, mixed>>
     */
    private function applyUserDirectorySort(array $users, string $column, string $dir): array
    {
        if ($column === '') {
            return $users;
        }

        $sorted = $users;
        usort($sorted, function (array $left, array $right) use ($column): int {
            return $this->userDirectorySortValue($left, $column) <=> $this->userDirectorySortValue($right, $column);
        });

        return $dir === 'desc' ? array_reverse($sorted) : $sorted;
    }

    private function userDirectorySortValue(array $user, string $column): string|int
    {
        return match ($column) {
            'username' => mb_strtolower((string) ($user['username'] ?? '')),
            'threads' => (int) ($user['thread_count'] ?? 0),
            'posts' => (int) ($user['post_count'] ?? 0),
            'active' => (string) ($user['active_at'] ?? ''),
            'joined' => (string) ($user['joined_at'] ?? ''),
            default => '',
        };
    }

    private function viewerHasThreadTag(string $threadId, string $tag, string $identityId): bool
    {
        $repository = new CanonicalRecordRepository($this->repositoryRoot);
        foreach (glob($this->repositoryRoot . '/records/thread-labels/*.txt') ?: [] as $path) {
            $record = $repository->loadThreadLabel('records/thread-labels/' . basename($path));
            if ($record->threadId !== $threadId || $record->authorIdentityId !== $identityId) {
                continue;
            }

            if (in_array($tag, $record->labels, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, mixed> $postIds
     * @return array<string, true>
     */
    private function viewerPostTagsForPosts(array $postIds, string $tag, string $identityId): array
    {
        $postLookup = array_fill_keys(array_map(static fn (mixed $value): string => (string) $value, $postIds), true);
        if ($postLookup === []) {
            return [];
        }

        $repository = new CanonicalRecordRepository($this->repositoryRoot);
        $taggedPostIds = [];
        foreach (glob($this->repositoryRoot . '/records/post-reactions/*.txt') ?: [] as $path) {
            $record = $repository->loadPostReaction('records/post-reactions/' . basename($path));
            if (!isset($postLookup[$record->postId]) || $record->authorIdentityId !== $identityId) {
                continue;
            }

            if (in_array($tag, $record->tags, true)) {
                $taggedPostIds[$record->postId] = true;
            }
        }

        return $taggedPostIds;
    }

    /**
     * Bulk sibling to viewerHasThreadTag(): one glob/scan of thread-label
     * records covering many threads at once, instead of one scan per thread.
     *
     * @param array<int, mixed> $threadIds
     * @return array<string, true>
     */
    private function viewerThreadTagsForThreads(array $threadIds, string $tag, string $identityId): array
    {
        $threadLookup = array_fill_keys(array_map(static fn (mixed $value): string => (string) $value, $threadIds), true);
        if ($threadLookup === []) {
            return [];
        }

        $repository = new CanonicalRecordRepository($this->repositoryRoot);
        $taggedThreadIds = [];
        foreach (glob($this->repositoryRoot . '/records/thread-labels/*.txt') ?: [] as $path) {
            $record = $repository->loadThreadLabel('records/thread-labels/' . basename($path));
            if (!isset($threadLookup[$record->threadId]) || $record->authorIdentityId !== $identityId) {
                continue;
            }

            if (in_array($tag, $record->labels, true)) {
                $taggedThreadIds[$record->threadId] = true;
            }
        }

        return $taggedThreadIds;
    }

    private function hasPendingUserDirectoryProfiles(): bool
    {
        $stmt = $this->pdo()->query('SELECT 1 FROM profiles WHERE is_approved = 0 LIMIT 1');

        return $stmt->fetchColumn() !== false;
    }

    private function handlePendingUserDirectory(string $method): void
    {
        if ($method !== 'GET') {
            $this->sendHtml(
                $this->renderMessagePage(
                    'Method Not Allowed',
                    'Method Not Allowed',
                    'Only GET is supported for the pending user directory.',
                    'none'
                ),
                405
            );
            return;
        }

        $viewerProfile = $this->resolveViewerProfileFromIdentityHint();
        if ($viewerProfile === null || ((int) $viewerProfile['is_approved']) !== 1) {
            $this->sendHtml(
                $this->renderMessagePage(
                    'Forbidden',
                    'Forbidden',
                    'Only approved users can view the pending approval directory.',
                    'profiles'
                ),
                403
            );
            return;
        }

        $this->sendHtml($this->renderPendingUserDirectory(), 200);
    }

    /**
     * @param array{sort_value: string, id: int}|null $afterCursor
     *        Keyset cursor identifying the last item of the previous page,
     *        matching the ORDER BY below. Pass null for the first page.
     * @return array{items: array<int, array<string, mixed>>, has_more: bool}
     */
    private function fetchActivity(string $view, string $sortColumn, string $sortDirection, ?array $afterCursor = null): array
    {
        $view = $this->normalizeActivityView($view);
        ['column' => $sortColumn, 'direction' => $sortDirection] = $this->resolveActivitySort($sortColumn, $sortDirection);
        $sortColumnSql = $this->activitySortSql($sortColumn);
        $sortDirectionSql = $sortDirection === 'desc' ? 'DESC' : 'ASC';
        [$viewWhere, $viewParameters] = $this->activityViewSql($view);

        $cursorWhere = '';
        if ($afterCursor !== null) {
            // `id` is the sole tiebreaker (rather than also comparing
            // post_id, as the old date-only cursor did): id is already
            // unique, so it alone guarantees a stable, gapless order
            // regardless of which column is being sorted on.
            $comparisonOperator = $sortDirection === 'desc' ? '<' : '>';
            $cursorWhere = 'AND (
                ' . $sortColumnSql . ' ' . $comparisonOperator . ' :cursor_sort_value
                OR (' . $sortColumnSql . ' = :cursor_sort_value AND activity.id ' . $comparisonOperator . ' :cursor_id)
            )';
            $viewParameters['cursor_sort_value'] = $afterCursor['sort_value'];
            $viewParameters['cursor_id'] = $afterCursor['id'];
        }

        $stmt = $this->pdo()->prepare(
            'SELECT activity.created_at, activity.kind, activity.record_family, activity.action_key,
                    activity.post_id, activity.thread_id, activity.label, activity.board_tags_json,
                    activity.author_identity_id,
                    activity.source_path, activity.source_commit_sha,
                    activity.id, activity.author_label, activity.author_profile_slug,
                    activity.author_username_token, activity.author_is_approved
             FROM activity
             LEFT JOIN posts ON posts.post_id = activity.post_id
             WHERE 1 = 1
             ' . $viewWhere . '
             ' . $cursorWhere . '
             ORDER BY ' . $sortColumnSql . ' ' . $sortDirectionSql . ', activity.id ' . $sortDirectionSql . '
             LIMIT :limit'
        );
        foreach ($viewParameters as $parameter => $value) {
            $stmt->bindValue($parameter, $value);
        }
        // Fetch one extra row to detect whether a next page exists, then
        // trim it back off before building the returned item set.
        $stmt->bindValue('limit', self::ACTIVITY_ITEM_LIMIT + 1, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $hasMore = count($rows) > self::ACTIVITY_ITEM_LIMIT;
        if ($hasMore) {
            $rows = array_slice($rows, 0, self::ACTIVITY_ITEM_LIMIT);
        }

        $items = array_map(function (array $post): array {
            $sourcePath = $post['source_path'] !== null ? (string) $post['source_path'] : '';
            $sourceCommitSha = $post['source_commit_sha'] !== null ? (string) $post['source_commit_sha'] : '';
            $signature = $this->sourceSignatureLink($sourcePath);
            $item = [
                'created_at' => $post['created_at'],
                'kind' => $post['kind'],
                'record_family' => $post['record_family'],
                'action_key' => $post['action_key'],
                'post_id' => $post['post_id'],
                'thread_id' => $post['thread_id'],
                'label' => $post['label'],
                'board_tags_json' => $post['board_tags_json'],
                'source_path' => $sourcePath,
                'source_commit_sha' => $sourceCommitSha,
                'source_path_href' => $this->sourcePathHref($sourcePath, $sourceCommitSha),
                'source_commit_href' => $this->sourceCommitHref($sourceCommitSha),
                'source_commit_files' => $this->activityCommitManifest($sourceCommitSha) ?? [],
                'source_signature_path' => $signature['path'],
                'source_signature_href' => $signature['href'],
                'source_signature_status' => $this->sourceSignatureStatus(
                    $sourcePath,
                    (string) ($post['author_identity_id'] ?? ''),
                    $signature['path'],
                    true
                ),
                'id' => (int) $post['id'],
                'author_label' => $post['author_label'],
                'author_profile_slug' => $post['author_profile_slug'],
                'author_username_token' => $post['author_username_token'],
                'author_is_approved' => (int) $post['author_is_approved'],
            ];
            $item['relevant_files'] = $this->activityItemRelevantFiles($item);

            return $item;
        }, $rows);

        $items = array_values(array_filter($items, function (array $item) use ($view): bool {
            $boardTagsJson = (string) $item['board_tags_json'];
            $hidden = $this->isHiddenBootstrapBoardTagsJson($boardTagsJson);

            return match ($view) {
                'all' => true,
                'content' => !$hidden,
                'identity' => $this->hasBoardTag($boardTagsJson, 'identity'),
                'bootstrap' => $this->hasBoardTag($boardTagsJson, 'identity') && $this->hasBoardTag($boardTagsJson, 'internal'),
                'approval' => $this->hasBoardTag($boardTagsJson, 'identity') && $this->hasBoardTag($boardTagsJson, 'approval'),
                default => true,
            };
        }));

        return ['items' => $items, 'has_more' => $hasMore];
    }

    /**
     * Counts the full number of activity rows matching a view, independent
     * of `ACTIVITY_ITEM_LIMIT`/pagination - for the left-pane folder counts,
     * which should show real totals rather than "however many happen to be
     * loaded so far". Mirrors `fetchActivity()`'s exact two-stage filter
     * (the SQL `WHERE` from `activityViewSql()`, then the same tag-based
     * PHP re-check) but selects only the columns that check needs, and
     * skips the per-item transform `fetchActivity()` does for rendering
     * (signature checks, link building, etc.) - unneeded and expensive
     * across a potentially large, unlimited row set.
     */
    private function countActivityViewTotal(string $view): int
    {
        $view = $this->normalizeActivityView($view);
        [$viewWhere, $viewParameters] = $this->activityViewSql($view);
        $stmt = $this->pdo()->prepare(
            'SELECT activity.board_tags_json
             FROM activity
             LEFT JOIN posts ON posts.post_id = activity.post_id
             WHERE 1 = 1
             ' . $viewWhere
        );
        foreach ($viewParameters as $parameter => $value) {
            $stmt->bindValue($parameter, $value);
        }
        $stmt->execute();

        $count = 0;
        while (($row = $stmt->fetch()) !== false) {
            $boardTagsJson = (string) $row['board_tags_json'];
            $hidden = $this->isHiddenBootstrapBoardTagsJson($boardTagsJson);
            $matches = match ($view) {
                'all' => true,
                'content' => !$hidden,
                'identity' => $this->hasBoardTag($boardTagsJson, 'identity'),
                'bootstrap' => $this->hasBoardTag($boardTagsJson, 'identity') && $this->hasBoardTag($boardTagsJson, 'internal'),
                'approval' => $this->hasBoardTag($boardTagsJson, 'identity') && $this->hasBoardTag($boardTagsJson, 'approval'),
                default => true,
            };
            if ($matches) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array{0:string,1:array<string, string>}
     */
    private function activityViewSql(string $view): array
    {
        $quotedHiddenTag = '%"' . self::HIDDEN_BOOTSTRAP_TAG . '"%';

        return match ($view) {
            'identity' => ['AND activity.board_tags_json LIKE :identity_tag', ['identity_tag' => $quotedHiddenTag]],
            'bootstrap' => [
                'AND activity.board_tags_json LIKE :identity_tag AND activity.board_tags_json LIKE :internal_tag',
                ['identity_tag' => $quotedHiddenTag, 'internal_tag' => '%"internal"%'],
            ],
            'approval' => [
                'AND activity.board_tags_json LIKE :identity_tag AND activity.board_tags_json LIKE :approval_tag',
                ['identity_tag' => $quotedHiddenTag, 'approval_tag' => '%"approval"%'],
            ],
            'content' => [
                'AND activity.board_tags_json NOT LIKE :hidden_tag
                 AND (activity.post_id IS NULL OR COALESCE(posts.is_hidden, 0) = 0)',
                ['hidden_tag' => $quotedHiddenTag],
            ],
            default => ['', []],
        };
    }

    /**
     * Resolves requested ?sort=/?dir= values for the Activity list against
     * its three sortable columns, falling back to 'date' when the column is
     * missing or unrecognized (today's default order). An unrecognized
     * direction falls back to a per-column default: descending for date,
     * ascending for the text columns - mirroring `resolveForteBoardSort()`'s
     * pattern, though Activity always resolves to a real column (never an
     * empty-string sentinel) since its cursor needs one to key off.
     *
     * @return array{column: string, direction: string}
     */
    private function resolveActivitySort(string $requestedColumn, string $requestedDirection): array
    {
        $validColumns = ['date', 'kind', 'label'];
        $column = in_array($requestedColumn, $validColumns, true) ? $requestedColumn : 'date';

        $defaultDirection = $column === 'date' ? 'desc' : 'asc';
        $direction = in_array($requestedDirection, ['asc', 'desc'], true) ? $requestedDirection : $defaultDirection;

        return ['column' => $column, 'direction' => $direction];
    }

    /**
     * Maps a column key already validated by `resolveActivitySort()` to its
     * SQL expression. Not parameterized/bound (like `activityViewSql()`'s
     * fragments) since it only ever returns one of these fixed literals.
     */
    private function activitySortSql(string $column): string
    {
        return match ($column) {
            'kind' => 'activity.kind',
            'label' => 'activity.label',
            default => 'activity.created_at',
        };
    }

    /**
     * Reads the value of whichever column is currently the active sort key
     * out of an already-built `fetchActivity()` item, for constructing that
     * item's keyset cursor (`{sort_value, id}`). Keeps the column-to-field
     * mapping in one place alongside `activitySortSql()`'s column-to-SQL
     * mapping, rather than duplicating a match() at each call site.
     *
     * @param array<string, mixed> $item
     */
    private function activitySortValueFromItem(array $item, string $column): string
    {
        return match ($column) {
            'kind' => (string) $item['kind'],
            'label' => (string) $item['label'],
            default => (string) $item['created_at'],
        };
    }

    /**
     * Mirrors `fetchActivity()`'s keyset-cursor pagination shape, over the
     * `commits` table instead of `activity` - a commit has no "view" to
     * filter by, so this is simpler than `fetchActivity()` (no WHERE
     * fragment beyond the cursor itself).
     *
     * @param array{sort_value: string, id: int}|null $afterCursor
     * @return array{items: array<int, array<string, mixed>>, has_more: bool}
     */
    private function fetchCommits(string $sortColumn, string $sortDirection, ?array $afterCursor = null): array
    {
        ['column' => $sortColumn, 'direction' => $sortDirection] = $this->resolveCommitSort($sortColumn, $sortDirection);
        $sortColumnSql = $this->commitSortSql($sortColumn);
        $sortDirectionSql = $sortDirection === 'desc' ? 'DESC' : 'ASC';

        $cursorWhere = '';
        $parameters = [];
        if ($afterCursor !== null) {
            $comparisonOperator = $sortDirection === 'desc' ? '<' : '>';
            $cursorWhere = 'WHERE (
                ' . $sortColumnSql . ' ' . $comparisonOperator . ' :cursor_sort_value
                OR (' . $sortColumnSql . ' = :cursor_sort_value AND commits.id ' . $comparisonOperator . ' :cursor_id)
            )';
            $parameters['cursor_sort_value'] = $afterCursor['sort_value'];
            $parameters['cursor_id'] = $afterCursor['id'];
        }

        $stmt = $this->pdo()->prepare(
            'SELECT commits.sha, commits.author_name, commits.author_email, commits.committed_at,
                    commits.subject, commits.file_count, commits.id
             FROM commits
             ' . $cursorWhere . '
             ORDER BY ' . $sortColumnSql . ' ' . $sortDirectionSql . ', commits.id ' . $sortDirectionSql . '
             LIMIT :limit'
        );
        foreach ($parameters as $parameter => $value) {
            $stmt->bindValue($parameter, $value);
        }
        // Reuses the same page size as fetchActivity(): fetch one extra row
        // to detect whether a next page exists, then trim it back off.
        $stmt->bindValue('limit', self::ACTIVITY_ITEM_LIMIT + 1, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $hasMore = count($rows) > self::ACTIVITY_ITEM_LIMIT;
        if ($hasMore) {
            $rows = array_slice($rows, 0, self::ACTIVITY_ITEM_LIMIT);
        }

        $items = array_map(static fn (array $row): array => [
            'sha' => (string) $row['sha'],
            'author_name' => (string) $row['author_name'],
            'author_email' => (string) $row['author_email'],
            'committed_at' => (string) $row['committed_at'],
            'subject' => (string) $row['subject'],
            'file_count' => (int) $row['file_count'],
            'id' => (int) $row['id'],
        ], $rows);

        return ['items' => $items, 'has_more' => $hasMore];
    }

    /**
     * Full commit count for the Commits view's left-pane folder count -
     * mirrors `countActivityViewTotal()`'s purpose, but every commit
     * counts (no per-view filter to apply).
     */
    private function countCommitsTotal(): int
    {
        return (int) $this->pdo()->query('SELECT COUNT(*) FROM commits')->fetchColumn();
    }

    /**
     * Mirrors `resolveActivitySort()`'s shape for the Commits view. Only
     * `date` is sortable for now - kept as its own small resolver (not
     * folded into `resolveActivitySort()`) since the two views' valid
     * column sets are unrelated.
     *
     * @return array{column: string, direction: string}
     */
    private function resolveCommitSort(string $requestedColumn, string $requestedDirection): array
    {
        $validColumns = ['date'];
        $column = in_array($requestedColumn, $validColumns, true) ? $requestedColumn : 'date';
        $direction = in_array($requestedDirection, ['asc', 'desc'], true) ? $requestedDirection : 'desc';

        return ['column' => $column, 'direction' => $direction];
    }

    private function commitSortSql(string $column): string
    {
        return match ($column) {
            default => 'commits.committed_at',
        };
    }

    /**
     * Mirrors `activitySortValueFromItem()`, for building a commit's
     * keyset cursor from the last item on a page.
     *
     * @param array<string, mixed> $item
     */
    private function commitSortValueFromItem(array $item, string $column): string
    {
        return match ($column) {
            default => (string) $item['committed_at'],
        };
    }

    /**
     * Computes each sortable column header's `aria-sort` state and its
     * click target URL: the active column points at the *toggled*
     * direction, every other column points at its own default direction
     * (from `resolveActivitySort()`, so this never drifts out of sync with
     * the backend's own validation) - mirroring Board's `resolveForteBoardSort`
     * default-direction table, but resolved into links since Activity's
     * click behavior is a real navigation, not a client-side re-sort.
     *
     * @return array<string, array{ariaSort: string, href: string}>
     */
    private function activitySortHeaderLinks(string $view, string $activeColumn, string $activeDirection): array
    {
        $links = [];
        foreach (['kind', 'label', 'date'] as $column) {
            if ($column === $activeColumn) {
                $ariaSort = $activeDirection === 'desc' ? 'descending' : 'ascending';
                $targetDirection = $activeDirection === 'desc' ? 'asc' : 'desc';
            } else {
                $ariaSort = 'none';
                $targetDirection = $this->resolveActivitySort($column, '')['direction'];
            }

            $params = [];
            if ($view !== 'all') {
                $params['view'] = $view;
            }
            $params['sort'] = $column;
            $params['dir'] = $targetDirection;

            $links[$column] = [
                'ariaSort' => $ariaSort,
                'href' => '/forte/activity/?' . http_build_query($params),
            ];
        }

        return $links;
    }

    private function sourcePathHref(string $sourcePath, string $sourceCommitSha): ?string
    {
        if ($sourcePath === '' || !$this->isValidCanonicalSourcePath($sourcePath)) {
            return null;
        }

        $encodedPath = $this->encodeSourcePathForUrl($sourcePath);
        if ($this->isValidSourceCommitSha($sourceCommitSha)) {
            return '/source/blob/' . $sourceCommitSha . '/' . $encodedPath;
        }

        return '/source/current/' . $encodedPath;
    }

    private function sourceCommitHref(string $sourceCommitSha): ?string
    {
        if (!$this->isValidSourceCommitSha($sourceCommitSha)) {
            return null;
        }

        return '/source/commits/' . $sourceCommitSha;
    }

    /**
     * @return array{path:string,href:string}
     */
    private function sourceSignatureLink(string $sourcePath): array
    {
        foreach ($this->sourceSignaturePathCandidates($sourcePath) as $signaturePath) {
            if ($this->currentSourcePathExists($signaturePath)) {
                return [
                    'path' => $signaturePath,
                    'href' => '/source/current/' . $this->encodeSourcePathForUrl($signaturePath),
                ];
            }
        }

        return ['path' => '', 'href' => ''];
    }

    private function sourceSignatureStatus(
        string $sourcePath,
        string $authorIdentityId,
        string $sourceSignaturePath,
        bool $authorIdentityIdIsCanonical = false
    ): string
    {
        if ($sourceSignaturePath !== '' || !str_starts_with($sourcePath, 'records/posts/')) {
            return '';
        }

        if (!$authorIdentityIdIsCanonical) {
            $canonicalAuthorIdentityId = $this->canonicalPostAuthorIdentityId($sourcePath);
            if ($canonicalAuthorIdentityId !== null) {
                return $canonicalAuthorIdentityId === '' ? 'anonymous unsigned' : 'legacy unsigned';
            }
        }

        return $authorIdentityId === '' ? 'anonymous unsigned' : 'legacy unsigned';
    }

    private function canonicalPostAuthorIdentityId(string $sourcePath): ?string
    {
        try {
            $post = (new CanonicalRecordRepository($this->repositoryRoot))->loadPost($sourcePath);
        } catch (RuntimeException) {
            return null;
        }

        return $post->authorIdentityId ?? '';
    }

    /**
     * @return list<string>
     */
    private function sourceSignaturePathCandidates(string $sourcePath): array
    {
        if (!$this->isValidCanonicalRecordSourcePath($sourcePath)) {
            return [];
        }

        return [
            $sourcePath . '.asc',
            $sourcePath . '.sig',
        ];
    }

    private function encodeSourcePathForUrl(string $sourcePath): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $sourcePath)));
    }

    private function renderRssFeed(string $title, string $link, array $items): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<rss version="2.0"><channel><title>' . $this->escapeXml($title) . '</title>'
            . '<link>' . $this->escapeXml('http://localhost' . $link) . '</link>'
            . '<description>' . $this->escapeXml($title . ' feed') . '</description>'
            . implode('', $items)
            . '</channel></rss>';
    }

    private function renderRssItem(string $title, string $link, string $description, ?string $publishedAt = null): string
    {
        $item = '<item><title>' . $this->escapeXml($title) . '</title>'
            . '<link>' . $this->escapeXml('http://localhost' . $link) . '</link>'
            . '<description>' . $this->escapeXml($description) . '</description>';

        if ($publishedAt !== null && $publishedAt !== '') {
            $timestamp = strtotime($publishedAt);
            if ($timestamp !== false) {
                $item .= '<pubDate>' . $this->escapeXml(gmdate(DATE_RSS, $timestamp)) . '</pubDate>';
            }
        }

        return $item . '</item>';
    }

    private function normalizeActivityView(string $view): string
    {
        return in_array($view, ['all', 'content', 'identity', 'bootstrap', 'approval', 'commits'], true) ? $view : 'all';
    }

    private function normalizeUserDirectoryCategory(string $category): string
    {
        return in_array($category, ['all', 'new', 'established', 'no-threads', 'recently-active', 'not-approved'], true)
            ? $category
            : 'all';
    }

    private function hasBoardTag(string $boardTagsJson, string $tag): bool
    {
        $boardTags = json_decode($boardTagsJson, true);
        if (!is_array($boardTags)) {
            return false;
        }

        return in_array($tag, $boardTags, true);
    }

    private function isHiddenBootstrapBoardTagsJson(string $boardTagsJson): bool
    {
        $boardTags = json_decode($boardTagsJson, true);
        if (!is_array($boardTags)) {
            return false;
        }

        return in_array(self::HIDDEN_BOOTSTRAP_TAG, $boardTags, true);
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleSetIdentityHint(string $method, array $query): void
    {
        if (!in_array($method, ['GET', 'POST'], true)) {
            $this->sendText("method not allowed\n", 405);
            return;
        }

        $hint = strtolower(trim((string) ($query['identity_hint'] ?? $query['value'] ?? '')));
        if ($hint === '') {
            $hint = 'guest';
        }

        setcookie('identity_hint', $hint, [
            'expires' => time() + 86400 * 30,
            'path' => '/',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);

        $_COOKIE['identity_hint'] = $hint;
        $this->sendText("identity_hint={$hint}\n", 200);
    }

    private function handleClearIdentity(string $method): void
    {
        if ($method !== 'POST') {
            $this->sendText("method not allowed\n", 405, $this->noStoreHeaders());
            return;
        }

        $authenticatedIdentityId = strtolower(trim((string) ($_SESSION['authenticated_identity_id'] ?? '')));
        if ($authenticatedIdentityId !== '') {
            $_SESSION['lobby_identity_id'] = $authenticatedIdentityId;
        }

        unset(
            $_SESSION['authenticated_identity_id'],
            $_SESSION['forum_auth_challenges'],
        );
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        setcookie('identity_hint', 'guest', [
            'expires' => time() + 86400 * 30,
            'path' => '/',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['identity_hint'] = 'guest';

        $this->sendText("status=ok\nidentity_hint=guest\n", 200, $this->noStoreHeaders());
    }

    private function startViewerSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (headers_sent()) {
            return;
        }

        session_start([
            'cookie_lifetime' => self::PERSISTENT_VIEWER_SESSION_COOKIE_LIFETIME,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'use_strict_mode' => true,
        ]);
    }

    /** @param array<string, mixed> $query */
    private function shouldResumeViewerSession(string $method, string $path, array $query): bool
    {
        if (!$this->hasViewerSessionCookie()) {
            return false;
        }

        if ($method === 'POST') {
            return $path === '/api/prepare_invitation';
        }

        if ($method !== 'GET' || !$this->isApplicationRoute($path)) {
            return false;
        }

        if (str_starts_with($path, '/api/') || str_starts_with($path, '/downloads/')) {
            return false;
        }

        return ($query['format'] ?? null) !== 'rss';
    }

    private function hasViewerSessionCookie(): bool
    {
        $value = $_COOKIE[session_name()] ?? null;

        return is_string($value) && $value !== '';
    }

    private function resumeViewerSession(): void
    {
        $sessionName = session_name();
        $requestedSessionId = (string) ($_COOKIE[$sessionName] ?? '');
        $this->startViewerSession();

        if (session_status() !== PHP_SESSION_ACTIVE || $requestedSessionId === session_id()) {
            return;
        }

        session_abort();
        session_id('');
        unset($_COOKIE[$sessionName]);
        $params = session_get_cookie_params();
        setcookie($sessionName, '', [
            'expires' => time() - 3600,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    private function handleAuthChallenge(string $method): void
    {
        if ($method !== 'GET') {
            $this->sendText("method not allowed\n", 405);
            return;
        }

        $now = time();
        $challenges = is_array($_SESSION['forum_auth_challenges'] ?? null)
            ? $_SESSION['forum_auth_challenges']
            : [];
        foreach ($challenges as $value => $expiresAt) {
            if (!is_string($value) || (int) $expiresAt < $now) {
                unset($challenges[$value]);
            }
        }

        $challenge = bin2hex(random_bytes(32));
        $challenges[$challenge] = $now + 300;
        $_SESSION['forum_auth_challenges'] = $challenges;
        session_write_close();
        $this->sendText("challenge={$challenge}\n", 200, $this->noStoreHeaders());
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleAuthenticateIdentity(string $method, array $query): void
    {
        if ($method !== 'POST') {
            $this->sendText("method not allowed\n", 405);
            return;
        }

        $input = $this->requestData($query);
        $challenge = trim((string) ($input['challenge'] ?? ''));
        $signature = trim((string) ($input['detached_signature'] ?? ''));
        $identityId = strtolower(trim((string) ($input['identity_id'] ?? '')));
        $challenges = is_array($_SESSION['forum_auth_challenges'] ?? null)
            ? $_SESSION['forum_auth_challenges']
            : [];
        $expiresAt = (int) ($challenges[$challenge] ?? 0);

        if ($challenge === '' || $expiresAt < time()) {
            $this->sendText("error=Authentication challenge is missing or expired.\n", 400, $this->noStoreHeaders());
            return;
        }

        $profile = $this->fetchProfileByIdentityId($identityId);
        if ($profile === null) {
            $this->sendText("error=Identity not found.\n", 400, $this->noStoreHeaders());
            return;
        }

        $fingerprint = strtoupper(trim((string) ($profile['signer_fingerprint'] ?? '')));
        $verification = (new OpenPgpSignatureVerifier())->verifyDetached(
            (string) ($profile['public_key'] ?? ''),
            $challenge,
            $signature,
            $fingerprint,
        );
        if (!$verification['ok']) {
            $this->sendText("error=Identity signature verification failed.\n", 403, $this->noStoreHeaders());
            return;
        }

        session_regenerate_id(true);
        $_SESSION['authenticated_identity_id'] = $identityId;
        unset(
            $_SESSION['lobby_identity_id'],
            $_SESSION['forum_auth_challenges'],
        );
        session_write_close();
        $approved = ((int) ($profile['is_approved'] ?? 0)) === 1 ? '1' : '0';
        $this->sendText("status=ok\nidentity_id={$identityId}\napproved={$approved}\n", 200, $this->noStoreHeaders());
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleCreateThread(string $method, array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        if ($method !== 'POST') {
            $this->sendText("method not allowed\n", 405);
            return;
        }

        $phaseStartedAt = hrtime(true);
        $input = $this->requestData($query);
        $timings['request_data'] = $this->elapsedMilliseconds($phaseStartedAt);
        try {
            $result = $this->writer()->createThread($input);
            $result = $this->mergeResultTimings($result, $timings, $totalStartedAt);
            $this->sendText(
                "status=ok\npost_id={$result['post_id']}\nthread_id={$result['thread_id']}\ncommit_sha={$result['commit_sha']}\n",
                200,
                $this->serverTimingHeaders($result)
            );
        } catch (RuntimeException $exception) {
            $this->sendText(
                "error=" . $exception->getMessage() . "\n",
                400,
                $this->serverTimingHeaders(['timings' => $this->timingsWithTotal($timings, $totalStartedAt)])
            );
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleCreateReply(string $method, array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        if ($method !== 'POST') {
            $this->sendText("method not allowed\n", 405);
            return;
        }

        $phaseStartedAt = hrtime(true);
        $input = $this->requestData($query);
        $timings['request_data'] = $this->elapsedMilliseconds($phaseStartedAt);
        try {
            $result = $this->writer()->createReply($input);
            $result = $this->mergeResultTimings($result, $timings, $totalStartedAt);
            $this->sendText(
                "status=ok\npost_id={$result['post_id']}\nthread_id={$result['thread_id']}\ncommit_sha={$result['commit_sha']}\n",
                200,
                $this->serverTimingHeaders($result)
            );
        } catch (RuntimeException $exception) {
            $this->sendText(
                "error=" . $exception->getMessage() . "\n",
                400,
                $this->serverTimingHeaders(['timings' => $this->timingsWithTotal($timings, $totalStartedAt)])
            );
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handlePrepareThread(string $method, array $query): void
    {
        $this->handlePreparePost($method, $query, 'thread');
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handlePrepareIdentity(string $method, array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        if ($method !== 'POST') {
            $this->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405);
            return;
        }

        try {
            $phaseStartedAt = hrtime(true);
            $input = $this->requestData($query);
            $timings['request_data'] = $this->elapsedMilliseconds($phaseStartedAt);
            $result = $this->writer()->prepareIdentityBootstrap($input);
            $result = $this->mergeResultTimings($result, $timings, $totalStartedAt);
            $headers = $this->serverTimingHeaders($result);
            unset($result['timings']);
            $this->sendJson($result, 200, $headers);
        } catch (IdentityBootstrapTimingException $exception) {
            $this->sendJson(
                ['status' => 'error', 'error' => $exception->getMessage()],
                400,
                $this->serverTimingHeaders(['timings' => $this->timingsWithTotal(array_merge($timings, $exception->timings()), $totalStartedAt)])
            );
        } catch (RuntimeException $exception) {
            $this->sendJson(
                ['status' => 'error', 'error' => $exception->getMessage()],
                400,
                $this->serverTimingHeaders(['timings' => $this->timingsWithTotal($timings, $totalStartedAt)])
            );
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handlePrepareReply(string $method, array $query): void
    {
        $this->handlePreparePost($method, $query, 'reply');
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handlePreparePost(string $method, array $query, string $kind): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        if ($method !== 'POST') {
            $this->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405);
            return;
        }

        $phaseStartedAt = hrtime(true);
        $input = $this->requestData($query);
        $timings['request_data'] = $this->elapsedMilliseconds($phaseStartedAt);

        try {
            $result = $kind === 'reply'
                ? $this->writer()->prepareReply($input)
                : $this->writer()->prepareThread($input);
            $result = $this->mergeResultTimings($result, $timings, $totalStartedAt);
            $headers = $this->serverTimingHeaders($result);
            unset($result['timings']);
            $this->sendJson($result, 200, $headers);
        } catch (RuntimeException $exception) {
            $this->sendJson(
                ['status' => 'error', 'error' => $exception->getMessage()],
                400,
                $this->serverTimingHeaders(['timings' => $this->timingsWithTotal($timings, $totalStartedAt)])
            );
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleCreatePreparedPost(string $method, array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        if ($method !== 'POST') {
            $this->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405);
            return;
        }

        $phaseStartedAt = hrtime(true);
        $input = $this->requestData($query);
        $timings['request_data'] = $this->elapsedMilliseconds($phaseStartedAt);

        try {
            $result = $this->writer()->createPreparedPost($input);
            $result = $this->mergeResultTimings($result, $timings, $totalStartedAt);
            $headers = $this->serverTimingHeaders($result);
            unset($result['timings']);
            $this->sendJson($result, 200, $headers);
        } catch (RuntimeException $exception) {
            $this->sendJson(
                ['status' => 'error', 'error' => $exception->getMessage()],
                400,
                $this->serverTimingHeaders(['timings' => $this->timingsWithTotal($timings, $totalStartedAt)])
            );
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleCreateIdentity(string $method, array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        if ($method !== 'POST') {
            $this->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405);
            return;
        }

        try {
            $phaseStartedAt = hrtime(true);
            $input = $this->requestData($query);
            $timings['request_data'] = $this->elapsedMilliseconds($phaseStartedAt);
            $result = $this->writer()->createIdentityBootstrap($input);
            $result = $this->mergeResultTimings($result, $timings, $totalStartedAt);
            $headers = $this->serverTimingHeaders($result);
            unset($result['timings']);
            $this->sendJson($result, 200, $headers);
        } catch (IdentityBootstrapTimingException $exception) {
            $this->sendJson(
                ['status' => 'error', 'error' => $exception->getMessage()],
                400,
                $this->serverTimingHeaders(['timings' => $this->timingsWithTotal(array_merge($timings, $exception->timings()), $totalStartedAt)])
            );
        } catch (RuntimeException $exception) {
            $this->sendJson(
                ['status' => 'error', 'error' => $exception->getMessage()],
                400,
                $this->serverTimingHeaders(['timings' => $this->timingsWithTotal($timings, $totalStartedAt)])
            );
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleAnalyzePost(string $method, array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        $headersWithTimings = function () use (&$timings, $totalStartedAt): array {
            $timings['total'] = $this->elapsedMilliseconds($totalStartedAt);
            return $this->noStoreTimingHeaders($timings);
        };

        if ($method !== 'POST') {
            $this->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405, $headersWithTimings());
            return;
        }

        $phaseStartedAt = hrtime(true);
        $input = $this->requestData($query);
        $timings['request_data'] = $this->elapsedMilliseconds($phaseStartedAt);
        $postId = trim((string) ($input['post_id'] ?? ''));
        if ($postId === '') {
            $this->sendJson(['status' => 'error', 'error' => 'Missing post_id.'], 400, $headersWithTimings());
            return;
        }

        $phaseStartedAt = hrtime(true);
        $post = $this->fetchPost($postId);
        $timings['fetch_post'] = $this->elapsedMilliseconds($phaseStartedAt);
        if ($post === null) {
            $this->sendJson(['status' => 'error', 'error' => 'post not found'], 404, $headersWithTimings());
            return;
        }

        $phaseStartedAt = hrtime(true);
        $context = $this->postAnalysisContext($post);
        $timings['analysis_context'] = $this->elapsedMilliseconds($phaseStartedAt);

        $phaseStartedAt = hrtime(true);
        $result = $this->postAnalysisService()->analyze($context);
        $timings['post_analysis'] = $this->elapsedMilliseconds($phaseStartedAt);
        foreach ($this->timingMetricsFrom($result['timings'] ?? null) as $name => $duration) {
            $timings[$name] = $duration;
        }

        $phaseStartedAt = hrtime(true);
        $viewerProfile = $this->resolveViewerProfileFromIdentityHint();
        $viewerCanSeePostAnalysis = $viewerProfile !== null && ((int) ($viewerProfile['is_approved'] ?? 0)) === 1;
        $timings['viewer_profile'] = $this->elapsedMilliseconds($phaseStartedAt);

        $phaseStartedAt = hrtime(true);
        $response = $this->postAnalysisResponse($result, $viewerCanSeePostAnalysis);
        $agentReplyEnabled = $this->agentRepliesEnabled();
        $analysisComplete = ($result['status'] ?? null) === 'complete';
        $gateFailure = $analysisComplete ? $this->agentReplyGateFailure($post, $result) : null;
        $agentReplyAllowed = $agentReplyEnabled && $analysisComplete && $gateFailure === null;
        $timings['analysis_response'] = $this->elapsedMilliseconds($phaseStartedAt);

        $phaseStartedAt = hrtime(true);
        if (!$agentReplyEnabled) {
            $agentReplyResult = $this->agentReplyStatusResponse('not_recommended', $postId, [
                'reason' => 'config_disabled',
            ]);
        } elseif (!$analysisComplete) {
            $agentReplyResult = $this->agentReplyStatusResponse('analysis_required', $postId, [
                'reason' => array_key_exists('status', $result) ? 'analysis_not_complete' : 'missing_analysis',
            ]);
        } elseif ($gateFailure !== null) {
            $agentReplyResult = $this->agentReplyStatusResponse('not_recommended', $postId, [
                'reason' => $viewerCanSeePostAnalysis ? ($gateFailure['reason'] ?? 'not_recommended') : 'not_recommended',
            ]);
        } else {
            $agentReplyResult = $this->agentReplyResultForPost($post);
        }
        $timings['agent_reply'] = $this->elapsedMilliseconds($phaseStartedAt);

        $phaseStartedAt = hrtime(true);
        $response['agent_reply_generation_allowed'] = $agentReplyAllowed;
        $response = array_merge($response, $this->agentReplySummaryForAnalysisResponse($agentReplyResult));
        $timings['response_summary'] = $this->elapsedMilliseconds($phaseStartedAt);

        $this->sendJson($response, 200, $headersWithTimings());
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleGenerateAgentReply(string $method, array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        $headersWithTimings = function () use (&$timings, $totalStartedAt): array {
            return $this->noStoreTimingHeaders($this->timingsWithTotal($timings, $totalStartedAt));
        };

        if ($method !== 'POST') {
            $this->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405, $headersWithTimings());
            return;
        }

        $phaseStartedAt = hrtime(true);
        $input = $this->requestData($query);
        $timings['request_data'] = $this->elapsedMilliseconds($phaseStartedAt);
        $postId = trim((string) ($input['post_id'] ?? ''));
        if ($postId === '') {
            $this->sendJson(['status' => 'error', 'error' => 'Missing post_id.'], 400, $headersWithTimings());
            return;
        }

        $phaseStartedAt = hrtime(true);
        $post = $this->fetchPost($postId);
        $timings['fetch_post'] = $this->elapsedMilliseconds($phaseStartedAt);
        if ($post === null) {
            $this->sendJson(['status' => 'error', 'error' => 'post not found'], 404, $headersWithTimings());
            return;
        }

        $phaseStartedAt = hrtime(true);
        $viewerProfile = $this->resolveViewerProfileFromIdentityHint();
        $timings['viewer_profile'] = $this->elapsedMilliseconds($phaseStartedAt);
        if ($viewerProfile === null || ((int) ($viewerProfile['is_approved'] ?? 0)) !== 1) {
            $this->sendJson(['status' => 'error', 'error' => 'forbidden'], 403, $headersWithTimings());
            return;
        }

        $phaseStartedAt = hrtime(true);
        $response = $this->agentReplyRequestResultForPost($post, $viewerProfile);
        $timings['agent_reply'] = $this->elapsedMilliseconds($phaseStartedAt);
        foreach ($this->timingMetricsFrom($response['timings'] ?? null) as $name => $duration) {
            $timings[$name] = $duration;
        }

        $this->sendJson($response, 200, $headersWithTimings());
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleCodexHandoff(string $method, array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        $headersWithTimings = function () use (&$timings, $totalStartedAt): array {
            return $this->noStoreTimingHeaders($this->timingsWithTotal($timings, $totalStartedAt));
        };

        if (!in_array($method, ['GET', 'POST'], true)) {
            $this->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405, $headersWithTimings());
            return;
        }

        $phaseStartedAt = hrtime(true);
        $viewerProfile = $this->resolveViewerProfileFromIdentityHint();
        $timings['viewer_profile'] = $this->elapsedMilliseconds($phaseStartedAt);
        if (!$this->viewerCanUseCodexHandoff($viewerProfile)) {
            $this->sendJson(['status' => 'error', 'error' => 'forbidden'], 403, $headersWithTimings());
            return;
        }

        $phaseStartedAt = hrtime(true);
        $input = $this->requestData($query);
        $timings['request_data'] = $this->elapsedMilliseconds($phaseStartedAt);

        try {
            if ($method === 'GET') {
                $handoff = $this->findCodexHandoffFromInput($input);
                if ($handoff === null) {
                    $this->sendJson(['status' => 'error', 'error' => 'handoff not found'], 404, $headersWithTimings());
                    return;
                }

                $this->sendJson($this->codexHandoffResponse($handoff), 200, $headersWithTimings());
                return;
            }

            $postId = trim((string) ($input['post_id'] ?? ''));
            if ($postId === '') {
                $this->sendJson(['status' => 'error', 'error' => 'Missing post_id.'], 400, $headersWithTimings());
                return;
            }

            $phaseStartedAt = hrtime(true);
            $post = $this->fetchPost($postId);
            $timings['fetch_post'] = $this->elapsedMilliseconds($phaseStartedAt);
            if ($post === null) {
                $this->sendJson(['status' => 'error', 'error' => 'post not found'], 404, $headersWithTimings());
                return;
            }

            if ((string) ($post['author_label'] ?? '') === AgentIdentityService::USERNAME) {
                $this->sendJson(['status' => 'error', 'error' => 'codex handoff target is not eligible'], 400, $headersWithTimings());
                return;
            }
            $thread = $this->fetchThread((string) ($post['thread_id'] ?? ''));
            if (!$this->postCanUseCodexHandoffTarget($post, $thread)) {
                $this->sendJson(['status' => 'error', 'error' => 'codex handoff target requires a development tag or thread label'], 400, $headersWithTimings());
                return;
            }

            $phaseStartedAt = hrtime(true);
            $store = $this->codexHandoffStore();
            $handoff = $store->requestForPost($post, $viewerProfile ?? []);
            if ((string) ($handoff['status'] ?? '') === 'requested') {
                $draft = $this->codexHandoffDraftService()->prepare($handoff, $post);
                $handoff = $store->updateStatus((string) $handoff['handoff_id'], 'draft_ready', $draft);
            }
            $timings['codex_handoff'] = $this->elapsedMilliseconds($phaseStartedAt);

            $this->sendJson($this->codexHandoffResponse($handoff), 200, $headersWithTimings());
        } catch (RuntimeException $exception) {
            $this->sendJson(['status' => 'error', 'error' => $exception->getMessage()], 400, $headersWithTimings());
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleCodexHandoffApproval(string $method, array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        $headersWithTimings = function () use (&$timings, $totalStartedAt): array {
            return $this->noStoreTimingHeaders($this->timingsWithTotal($timings, $totalStartedAt));
        };

        if ($method !== 'POST') {
            $this->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405, $headersWithTimings());
            return;
        }

        $phaseStartedAt = hrtime(true);
        $viewerProfile = $this->resolveViewerProfileFromIdentityHint();
        $timings['viewer_profile'] = $this->elapsedMilliseconds($phaseStartedAt);
        if (!$this->viewerCanUseCodexHandoff($viewerProfile)) {
            $this->sendJson(['status' => 'error', 'error' => 'forbidden'], 403, $headersWithTimings());
            return;
        }

        $phaseStartedAt = hrtime(true);
        $input = $this->requestData($query);
        $timings['request_data'] = $this->elapsedMilliseconds($phaseStartedAt);
        $handoffId = trim((string) ($input['handoff_id'] ?? ''));
        $decision = trim((string) ($input['decision'] ?? ''));
        if ($handoffId === '') {
            $this->sendJson(['status' => 'error', 'error' => 'Missing handoff_id.'], 400, $headersWithTimings());
            return;
        }

        try {
            $store = $this->codexHandoffStore();
            $handoff = $store->findByHandoffId($handoffId);
            if ($handoff === null) {
                $this->sendJson(['status' => 'error', 'error' => 'handoff not found'], 404, $headersWithTimings());
                return;
            }

            if ($decision === 'approve') {
                if ((string) ($handoff['status'] ?? '') !== 'draft_ready') {
                    $this->sendJson(['status' => 'error', 'error' => 'Codex handoff approval requires draft_ready status.'], 400, $headersWithTimings());
                    return;
                }

                $handoff = $store->updateStatus($handoffId, 'approved', [
                    'approved_by_identity_id' => (string) ($viewerProfile['identity_id'] ?? ''),
                    'approved_by_profile_slug' => (string) ($viewerProfile['profile_slug'] ?? ''),
                    'approved_by_username' => (string) ($viewerProfile['username'] ?? ''),
                ]);
            } elseif ($decision === 'reject') {
                $handoff = $store->updateStatus($handoffId, 'rejected', [
                    'rejected_by_identity_id' => (string) ($viewerProfile['identity_id'] ?? ''),
                    'rejected_by_profile_slug' => (string) ($viewerProfile['profile_slug'] ?? ''),
                    'rejected_by_username' => (string) ($viewerProfile['username'] ?? ''),
                ]);
            } else {
                $this->sendJson(['status' => 'error', 'error' => 'decision must be approve or reject'], 400, $headersWithTimings());
                return;
            }

            $this->sendJson($this->codexHandoffResponse($handoff), 200, $headersWithTimings());
        } catch (RuntimeException $exception) {
            $this->sendJson(['status' => 'error', 'error' => $exception->getMessage()], 400, $headersWithTimings());
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|null
     */
    private function findCodexHandoffFromInput(array $input): ?array
    {
        $store = $this->codexHandoffStore();
        $handoffId = trim((string) ($input['handoff_id'] ?? ''));
        if ($handoffId !== '') {
            return $store->findByHandoffId($handoffId);
        }

        $postId = trim((string) ($input['post_id'] ?? ''));
        if ($postId === '') {
            return null;
        }

        $post = $this->fetchPost($postId);
        if ($post === null) {
            return null;
        }

        return $store->findByPost($post);
    }

    /**
     * @param array<string, mixed> $handoff
     * @return array<string, mixed>
     */
    private function codexHandoffResponse(array $handoff): array
    {
        return [
            'status' => 'ok',
            'handoff_id' => (string) ($handoff['handoff_id'] ?? ''),
            'handoff_status' => (string) ($handoff['status'] ?? ''),
            'origin_post_id' => (string) ($handoff['origin_post_id'] ?? ''),
            'origin_thread_id' => (string) ($handoff['origin_thread_id'] ?? ''),
            'user_story' => $handoff['user_story'] ?? null,
            'fdp_step1' => $handoff['fdp_step1'] ?? null,
            'confidence_summary' => $handoff['confidence_summary'] ?? null,
            'draft_text' => $handoff['draft_text'] ?? null,
            'requested_at' => $handoff['requested_at'] ?? null,
            'draft_ready_at' => $handoff['draft_ready_at'] ?? null,
            'approved_at' => $handoff['approved_at'] ?? null,
            'rejected_at' => $handoff['rejected_at'] ?? null,
            'running_at' => $handoff['running_at'] ?? null,
            'completed_at' => $handoff['completed_at'] ?? null,
            'failed_at' => $handoff['failed_at'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $viewerProfile
     * @return array<string, mixed>
     */
    private function agentReplyRequestResultForPost(array $post, array $viewerProfile): array
    {
        $postId = (string) ($post['post_id'] ?? '');
        if ((string) ($post['author_label'] ?? '') === AgentIdentityService::USERNAME) {
            return $this->agentReplyStatusResponse('not_recommended', $postId, [
                'reason' => 'agent_loop_prevention',
            ]);
        }

        $context = $this->postAnalysisContext($post, false);
        $store = new SqliteAgentReplyGenerationStore($this->pdo());
        $row = $store->requestForTarget($context, [
            'requested_by_identity_id' => (string) ($viewerProfile['identity_id'] ?? ''),
            'requested_by_profile_slug' => (string) ($viewerProfile['profile_slug'] ?? ''),
            'requested_by_username' => (string) ($viewerProfile['username'] ?? ''),
        ]);

        return $this->agentReplyResponseForStoredRequest($row, $postId);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function agentReplyResponseForStoredRequest(array $row, string $postId): array
    {
        if ($row['agent_post_id'] !== null) {
            return $this->agentReplyStatusResponse('already_posted', $postId, [
                'agent_post_id' => $row['agent_post_id'],
                'agent_post_url' => '/posts/' . $row['agent_post_id'],
            ]);
        }

        $status = (string) ($row['status'] ?? '');
        if ($status === 'requested') {
            return $this->agentReplyStatusResponse('requested', $postId);
        }

        if (in_array($status, ['pending', 'complete', 'posting'], true)) {
            return $this->agentReplyStatusResponse('in_progress', $postId);
        }

        if ($status === 'skipped') {
            return $this->agentReplyStatusResponse('not_recommended', $postId, [
                'reason' => (string) ($row['failure_code'] ?? 'not_recommended'),
            ]);
        }

        if ($status === 'failed') {
            return $this->failedAgentReplyResponse($row);
        }

        return $this->agentReplyStatusResponse('in_progress', $postId);
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private function agentReplyResultForPost(array $post): array
    {
        $postId = (string) ($post['post_id'] ?? '');

        if (!$this->agentRepliesEnabled()) {
            return $this->agentReplyStatusResponse('not_recommended', $postId, [
                'reason' => 'config_disabled',
            ]);
        }

        return $this->agentReplyFulfillmentService()->publishForPost($post);
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleApplyThreadTag(string $method, array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        if ($method !== 'POST') {
            $this->sendText("method not allowed\n", 405);
            return;
        }

        $phaseStartedAt = hrtime(true);
        $viewerProfile = $this->resolveViewerProfileFromIdentityHint();
        $timings['viewer_profile'] = $this->elapsedMilliseconds($phaseStartedAt);
        if ($viewerProfile === null) {
            $this->sendText(
                "error=You must set an identity hint before applying a tag.\n",
                400,
                $this->serverTimingHeaders(['timings' => $this->timingsWithTotal($timings, $totalStartedAt)])
            );
            return;
        }

        $phaseStartedAt = hrtime(true);
        $input = $this->requestData($query);
        $timings['request_data'] = $this->elapsedMilliseconds($phaseStartedAt);
        $input['author_identity_id'] = (string) $viewerProfile['identity_id'];

        try {
            $result = $this->writer()->applyThreadTag($input);
            $result = $this->mergeResultTimings($result, $timings, $totalStartedAt);
            $response = "status=ok\n"
                . "thread_id={$result['thread_id']}\n"
                . "tag={$result['tag']}\n"
                . "score_total={$result['score_total']}\n"
                . "viewer_identity_id={$result['author_identity_id']}\n"
                . "viewer_is_approved={$result['viewer_is_approved']}\n"
                . "wrote_record={$result['wrote_record']}\n";
            if (isset($result['commit_sha'])) {
                $response .= "commit_sha={$result['commit_sha']}\n";
            }

            $this->sendText($response, 200, $this->serverTimingHeaders($result));
        } catch (RuntimeException $exception) {
            $this->sendText(
                "error=" . $exception->getMessage() . "\n",
                400,
                $this->serverTimingHeaders(['timings' => $this->timingsWithTotal($timings, $totalStartedAt)])
            );
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleApplyPostTag(string $method, array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        if ($method !== 'POST') {
            $this->sendText("method not allowed\n", 405);
            return;
        }

        $phaseStartedAt = hrtime(true);
        $viewerProfile = $this->resolveViewerProfileFromIdentityHint();
        $timings['viewer_profile'] = $this->elapsedMilliseconds($phaseStartedAt);
        if ($viewerProfile === null) {
            $this->sendText(
                "error=You must set an identity hint before applying a tag.\n",
                400,
                $this->serverTimingHeaders(['timings' => $this->timingsWithTotal($timings, $totalStartedAt)])
            );
            return;
        }

        $phaseStartedAt = hrtime(true);
        $input = $this->requestData($query);
        $timings['request_data'] = $this->elapsedMilliseconds($phaseStartedAt);
        $input['author_identity_id'] = (string) $viewerProfile['identity_id'];

        try {
            $result = $this->writer()->applyPostTag($input);
            $result = $this->mergeResultTimings($result, $timings, $totalStartedAt);
            $response = "status=ok\n"
                . "post_id={$result['post_id']}\n"
                . "thread_id={$result['thread_id']}\n"
                . "tag={$result['tag']}\n"
                . "post_score_total={$result['post_score_total']}\n"
                . "approved_flag_count={$result['approved_flag_count']}\n"
                . "is_hidden={$result['is_hidden']}\n"
                . "viewer_identity_id={$result['author_identity_id']}\n"
                . "viewer_is_approved={$result['viewer_is_approved']}\n"
                . "wrote_record={$result['wrote_record']}\n";
            if (isset($result['commit_sha'])) {
                $response .= "commit_sha={$result['commit_sha']}\n";
            }

            $this->sendText($response, 200, $this->serverTimingHeaders($result));
        } catch (RuntimeException $exception) {
            $this->sendText(
                "error=" . $exception->getMessage() . "\n",
                400,
                $this->serverTimingHeaders(['timings' => $this->timingsWithTotal($timings, $totalStartedAt)])
            );
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleSetFeatureFlagApi(string $method, array $query): void
    {
        if ($method !== 'POST') {
            $this->sendText("method not allowed\n", 405);
            return;
        }

        $viewerProfile = $this->resolveViewerProfileFromIdentityHint();
        if (!$this->viewerCanManageFeatureFlags($viewerProfile)) {
            $this->sendText("error=Feature flag changes require a root-approved identity.\n", 403);
            return;
        }

        try {
            $result = $this->writer()->setFeatureFlag($this->requestData($query));
            $this->featureFlags = null;
            $response = "status=ok\n"
                . "key={$result['key']}\n"
                . "site_value={$result['site_value']}\n"
                . "effective_value={$result['effective_value']}\n"
                . "source={$result['source']}\n"
                . "wrote_record={$result['wrote_record']}\n";
            if (isset($result['commit_sha'])) {
                $response .= "commit_sha={$result['commit_sha']}\n";
            }

            $this->sendText($response, 200, $this->serverTimingHeaders($result));
        } catch (RuntimeException $exception) {
            $this->sendText("error=" . $exception->getMessage() . "\n", 400);
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleSetFeatureFlagSubmit(array $query): void
    {
        $viewerProfile = $this->resolveViewerProfileFromIdentityHint();
        if (!$this->viewerCanManageFeatureFlags($viewerProfile)) {
            $this->sendHtml(
                $this->renderMessagePage(
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
            $result = $this->writer()->setFeatureFlag($this->requestData($query));
            $this->featureFlags = null;
            $message = 'Feature flag updated.';
            if (isset($result['commit_sha'])) {
                $message .= ' Commit: ' . $result['commit_sha'];
            }

            $this->sendRedirect('/tools/feature-flags/', $message, activeSection: 'tools');
        } catch (RuntimeException $exception) {
            $this->sendHtml(
                $this->renderMessagePage(
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
     * @param array<string, mixed> $query
     */
    private function handleLinkIdentity(string $method, array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        if ($method !== 'POST') {
            $this->sendText("method not allowed\n", 405);
            return;
        }

        $phaseStartedAt = hrtime(true);
        $input = $this->requestData($query);
        $timings['request_data'] = $this->elapsedMilliseconds($phaseStartedAt);
        try {
            $result = $this->writer()->linkIdentity($input);
            $result = $this->mergeResultTimings($result, $timings, $totalStartedAt);
            $this->sendText(
                "status=ok\nidentity_id={$result['identity_id']}\nprofile_slug={$result['profile_slug']}\nusername={$result['username']}\nbootstrap_post_id={$result['bootstrap_post_id']}\nbootstrap_thread_id={$result['bootstrap_thread_id']}\ncommit_sha={$result['commit_sha']}\n",
                200,
                $this->serverTimingHeaders($result)
            );
        } catch (RuntimeException $exception) {
            $this->sendText(
                "error=" . $exception->getMessage() . "\n",
                400,
                $this->serverTimingHeaders(['timings' => $this->timingsWithTotal($timings, $totalStartedAt)])
            );
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleApproveUserApi(string $method, array $query): void
    {
        if ($method !== 'POST') {
            $this->sendText("method not allowed\n", 405);
            return;
        }

        $this->sendText("error=Approval requires a browser signature. Refresh this page and try again.\n", 400);
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handlePrepareUserApproval(string $method, array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        if ($method !== 'POST') {
            $this->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405);
            return;
        }

        $phaseStartedAt = hrtime(true);
        $profileSlug = trim((string) ($this->requestData($query)['profile_slug'] ?? ''));
        $timings['request_data'] = $this->elapsedMilliseconds($phaseStartedAt);
        if ($profileSlug === '') {
            $this->sendJson(
                ['status' => 'error', 'error' => 'Missing profile_slug.'],
                400,
                $this->serverTimingHeaders(['timings' => $this->timingsWithTotal($timings, $totalStartedAt)])
            );
            return;
        }

        try {
            $result = $this->prepareUserApprovalBySlug($profileSlug, $timings);
            $result = $this->mergeResultTimings($result, $timings, $totalStartedAt);
            unset($result['timings']);
            $this->sendJson($result, 200, $this->serverTimingHeaders($result));
        } catch (RuntimeException $exception) {
            $this->sendJson(
                ['status' => 'error', 'error' => $exception->getMessage()],
                400,
                $this->serverTimingHeaders(['timings' => $this->timingsWithTotal($timings, $totalStartedAt)])
            );
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleCreatePreparedApproval(string $method, array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        if ($method !== 'POST') {
            $this->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405);
            return;
        }

        $phaseStartedAt = hrtime(true);
        $input = $this->requestData($query);
        $timings['request_data'] = $this->elapsedMilliseconds($phaseStartedAt);
        try {
            $result = $this->writer()->finalizePreparedApproval($input);
            $result = $this->mergeResultTimings($result, $timings, $totalStartedAt);
            unset($result['timings']);
            $this->sendJson($result, 200, $this->serverTimingHeaders($result));
        } catch (RuntimeException $exception) {
            $this->sendJson(
                ['status' => 'error', 'error' => $exception->getMessage()],
                400,
                $this->serverTimingHeaders(['timings' => $this->timingsWithTotal($timings, $totalStartedAt)])
            );
        }
    }

    /** @param array<string, mixed> $query */
    private function handlePrepareInvitation(string $method, array $query): void
    {
        if ($method !== 'POST') {
            $this->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405);
            return;
        }
        try {
            $viewer = $this->authenticatedViewerProfile();
            if ($viewer === null || ((int) ($viewer['is_approved'] ?? 0)) !== 1) {
                throw new RuntimeException('Only authenticated approved users can issue invitations.');
            }
            $input = $this->requestData($query);
            $result = $this->writer()->prepareInvitation([
                'issuer_identity_id' => (string) $viewer['identity_id'],
                'thread_id' => (string) $viewer['bootstrap_thread_id'],
                'parent_id' => (string) $viewer['bootstrap_post_id'],
                'verification_hash' => (string) ($input['verification_hash'] ?? ''),
                'expires_at' => (string) ($input['expires_at'] ?? ''),
                'destination' => (string) ($input['destination'] ?? ''),
                'action' => (string) ($input['action'] ?? 'issue'),
                'invitation_id' => (string) ($input['invitation_id'] ?? ''),
            ]);
            $this->sendJson($result, 200, $this->noStoreHeaders());
        } catch (RuntimeException $exception) {
            $this->sendJson(['status' => 'error', 'error' => $exception->getMessage()], 400, $this->noStoreHeaders());
        }
    }

    /** @param array<string, mixed> $query */
    private function handleCreatePreparedInvitation(string $method, array $query): void
    {
        if ($method !== 'POST') {
            $this->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405);
            return;
        }
        try {
            $input = $this->requestData($query);
            $this->sendJson($this->writer()->finalizePreparedInvitation($input), 200, $this->noStoreHeaders());
        } catch (RuntimeException $exception) {
            $this->sendJson(['status' => 'error', 'error' => $exception->getMessage()], 400, $this->noStoreHeaders());
        }
    }

    /** @param array<string, mixed> $query */
    private function handlePrepareInvitationRedemption(string $method, array $query): void
    {
        if ($method !== 'POST') {
            $this->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405);
            return;
        }
        try {
            $input = $this->requestData($query);
            $identityId = strtolower(trim((string) ($input['identity_id'] ?? '')));
            $profile = $this->fetchProfileByIdentityId($identityId);
            if ($profile === null) {
                throw new RuntimeException('Identity not found.');
            }
            $this->sendJson($this->writer()->prepareInvitationRedemption([
                'recipient_identity_id' => $identityId,
                'thread_id' => (string) $profile['bootstrap_thread_id'],
                'parent_id' => (string) $profile['bootstrap_post_id'],
                'invite_token' => (string) ($input['invite_token'] ?? ''),
            ]), 200, $this->noStoreHeaders());
        } catch (RuntimeException $exception) {
            $this->sendJson(['status' => 'error', 'error' => $exception->getMessage()], 400, $this->noStoreHeaders());
        }
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function requestData(array $query): array
    {
        $data = $query;

        foreach ($_POST as $key => $value) {
            $data[$key] = $value;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $rawBody = (string) file_get_contents('php://input');

        return $this->mergeRequestBodyData($data, $contentType, $rawBody);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function mergeRequestBodyData(array $data, string $contentType, string $rawBody): array
    {
        $normalizedContentType = strtolower(trim(explode(';', $contentType, 2)[0]));
        if ($rawBody === '') {
            return $data;
        }

        if ($normalizedContentType === 'application/json') {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                foreach ($decoded as $key => $value) {
                    if (is_string($key)) {
                        $data[$key] = $value;
                    }
                }
            }

            return $data;
        }

        if ($normalizedContentType === 'application/x-www-form-urlencoded') {
            $decoded = [];
            parse_str($rawBody, $decoded);
            foreach ($decoded as $key => $value) {
                if (is_string($key)) {
                    $data[$key] = $value;
                }
            }
        }

        return $data;
    }

    private function writer(): LocalWriteService
    {
        return new LocalWriteService(
            $this->repositoryRoot,
            $this->databasePath,
            $this->artifactRoot ?? ($this->projectRoot . '/public'),
            new CanonicalRecordRepository($this->repositoryRoot),
            featureFlags: $this->featureFlags(),
            additionalArtifactRoots: $this->additionalArtifactRoots(),
        );
    }

    /**
     * @return list<string>
     */
    private function additionalArtifactRoots(): array
    {
        $roots = [];
        if ($this->staticHtmlRoot !== null && $this->staticHtmlRoot !== ($this->artifactRoot ?? ($this->projectRoot . '/public'))) {
            $roots[] = $this->staticHtmlRoot;
        }

        return $roots;
    }

    private function agentIdentityService(): AgentIdentityService
    {
        return new AgentIdentityService(
            $this->repositoryRoot,
            $this->databasePath,
            $this->artifactRoot ?? ($this->projectRoot . '/public'),
            $this->projectRoot . '/state/private/agent-reply',
            new CanonicalRecordRepository($this->repositoryRoot),
        );
    }

    private function agentReplyFulfillmentService(): AgentReplyFulfillmentService
    {
        return new AgentReplyFulfillmentService(
            new SqliteAgentReplyGenerationStore($this->pdo()),
            new SqlitePostAnalysisStore($this->pdo()),
            $this->postAnalysisService(),
            $this->agentIdentityService(),
            $this->writer(),
            $this->featureFlags(),
            fn (string $postId): ?array => $this->fetchPost($postId),
            fn (array $post): array => $this->postAnalysisContext($post),
            fn (array $post, array $analysis): ?array => $this->agentReplyGateFailure($post, $analysis),
        );
    }

    private function codexHandoffStore(): CodexHandoffStore
    {
        return new CodexHandoffStore($this->pdo());
    }

    private function codexHandoffDraftService(): CodexHandoffDraftService
    {
        return new CodexHandoffDraftService();
    }

    private function postAnalysisService(): PostAnalysisService
    {
        $config = PrivateConfig::load($this->projectRoot);
        $analyzer = PostAnalyzerFactory::fromPrivateConfig($config, $this->projectRoot, $this->llmExchangeRecorder());

        return new PostAnalysisService(
            new SqlitePostAnalysisStore($this->pdo()),
            $analyzer,
            new UnicodeRiskInspector(),
            new SqliteUnicodeRiskStore($this->pdo())
        );
    }

    private function llmExchangeRecorder(): ?LlmExchangeRecorder
    {
        if ($this->llmExchangeRecorderInitialized) {
            return $this->llmExchangeRecorder;
        }

        $this->llmExchangeRecorderInitialized = true;
        if (!$this->featureFlags()->isEnabled(FeatureFlagRegistry::LLM_CONVERSATION_RECORDING_ENABLED)) {
            return null;
        }

        $config = PrivateConfig::load($this->projectRoot);
        $path = LlmExchangeDatabaseConfig::path($this->projectRoot, $config);
        $directory = dirname($path);
        if ($directory !== '' && !is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('LLM exchange database directory is not writable: ' . $directory);
        }

        $this->llmExchangeRecorder = new LlmExchangeRecorder(new PDO('sqlite:' . $path));

        return $this->llmExchangeRecorder;
    }

    /**
     * Lazily opens the persistent commit-manifest cache alongside the main
     * read-model database. Returns null (never throws) on any failure to
     * open/create it, since this is purely a performance optimization -
     * activityCommitManifest() must still work correctly, just slower,
     * when this is unavailable.
     */
    private function activityCommitManifestCacheStore(): ?SqliteActivityCommitManifestCache
    {
        if ($this->activityCommitManifestCacheStoreInitialized) {
            return $this->activityCommitManifestCacheStore;
        }

        $this->activityCommitManifestCacheStoreInitialized = true;
        $path = dirname($this->databasePath) . '/activity_commit_manifest_cache.sqlite3';
        try {
            $this->activityCommitManifestCacheStore = new SqliteActivityCommitManifestCache(new PDO('sqlite:' . $path));
        } catch (\Throwable) {
            $this->activityCommitManifestCacheStore = null;
        }

        return $this->activityCommitManifestCacheStore;
    }

    private function llmExchangeStore(): ?SqliteLlmExchangeStore
    {
        if ($this->llmExchangeStoreInitialized) {
            return $this->llmExchangeStore;
        }

        $this->llmExchangeStoreInitialized = true;
        if (!$this->featureFlags()->isEnabled(FeatureFlagRegistry::LLM_CONVERSATION_UI_ENABLED)) {
            return null;
        }

        $config = PrivateConfig::load($this->projectRoot);
        $path = LlmExchangeDatabaseConfig::path($this->projectRoot, $config);
        if (!is_file($path)) {
            return null;
        }

        $this->llmExchangeStore = new SqliteLlmExchangeStore(new PDO('sqlite:' . $path));

        return $this->llmExchangeStore;
    }

    private function agentRepliesEnabled(): bool
    {
        $config = PrivateConfig::load($this->projectRoot);

        return $this->configFlagEnabled($config, 'DEDALUS_AGENT_REPLIES_ENABLED', true);
    }

    private function agentRepliesAutomaticEnabled(): bool
    {
        if (!$this->agentRepliesEnabled()) {
            return false;
        }

        $config = PrivateConfig::load($this->projectRoot);

        return $this->configFlagEnabled($config, 'DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED', true);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configFlagEnabled(array $config, string $key, bool $default): bool
    {
        if (!array_key_exists($key, $config)) {
            return $default;
        }

        $value = $config[$key];
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value !== 0.0;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return $default;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        return $default;
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $analysis
     * @return array<string, mixed>|null
     */
    private function agentReplyGateFailure(array $post, array $analysis): ?array
    {
        if ((string) ($post['author_label'] ?? '') === AgentIdentityService::USERNAME) {
            return ['reason' => 'agent_loop_prevention'];
        }

        $respondability = is_array($analysis['respondability'] ?? null) ? $analysis['respondability'] : [];
        $moderation = is_array($analysis['moderation'] ?? null) ? $analysis['moderation'] : [];
        $engagement = is_array($analysis['engagement'] ?? null) ? $analysis['engagement'] : [];

        if (($respondability['should_generate_response'] ?? false) !== true) {
            return ['reason' => 'respondability_not_recommended'];
        }

        if (($engagement['response_should_be_public'] ?? false) !== true) {
            return ['reason' => 'response_not_public'];
        }

        if ((float) ($respondability['overall_score'] ?? 0) < 0.65) {
            return ['reason' => 'respondability_score_low'];
        }

        if ((string) ($respondability['response_risk'] ?? '') === 'high') {
            return ['reason' => 'response_risk_high'];
        }

        if (in_array((string) ($moderation['severity'] ?? ''), ['high', 'critical'], true)) {
            return ['reason' => 'moderation_severity_high'];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function agentReplyStatusResponse(string $generationStatus, string $postId, array $extra = []): array
    {
        return array_merge([
            'status' => 'ok',
            'post_id' => $postId,
            'generation_status' => $generationStatus,
        ], $extra);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function failedAgentReplyResponse(array $row): array
    {
        return [
            'status' => 'ok',
            'post_id' => (string) ($row['target_post_id'] ?? ''),
            'generation_status' => 'failed',
            'failure_code' => $row['failure_code'] ?? null,
            'failure_message' => $row['failure_message'] ?? null,
            'retry_after' => $row['retry_after'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $replyResult
     * @return array<string, mixed>
     */
    private function agentReplySummaryForAnalysisResponse(array $replyResult): array
    {
        $generationStatus = (string) ($replyResult['generation_status'] ?? 'failed');
        $agentPostId = isset($replyResult['agent_post_id']) ? (string) $replyResult['agent_post_id'] : null;
        $agentPostUrl = isset($replyResult['agent_post_url']) ? (string) $replyResult['agent_post_url'] : null;
        $reason = isset($replyResult['reason']) ? (string) $replyResult['reason'] : null;
        $failureCode = isset($replyResult['failure_code']) ? (string) $replyResult['failure_code'] : null;

        $summary = [
            'agent_reply_generation_status' => $generationStatus,
            'agent_reply_posted' => false,
            'agent_reply_post_id' => null,
            'agent_reply_post_url' => null,
            'agent_reply_reason' => null,
            'agent_reply_failure_code' => null,
        ];

        if ($generationStatus === 'generated') {
            $posted = ($replyResult['posted'] ?? false) === true;
            $summary['agent_reply_posted'] = $posted;
            $summary['agent_reply_post_id'] = $posted ? $agentPostId : null;
            $summary['agent_reply_post_url'] = $posted ? $agentPostUrl : null;
            $summary['agent_reply_reason'] = $reason;
            $summary['agent_reply_failure_code'] = $failureCode;

            return $summary;
        }

        if ($generationStatus === 'already_posted') {
            $summary['agent_reply_posted'] = true;
            $summary['agent_reply_post_id'] = $agentPostId;
            $summary['agent_reply_post_url'] = $agentPostUrl;

            return $summary;
        }

        if ($generationStatus === 'not_recommended') {
            $summary['agent_reply_reason'] = $reason;

            return $summary;
        }

        if ($generationStatus === 'analysis_required') {
            $summary['agent_reply_reason'] = $reason;

            return $summary;
        }

        if ($generationStatus === 'failed') {
            $summary['agent_reply_post_id'] = $agentPostId;
            $summary['agent_reply_post_url'] = $agentPostUrl;
            $summary['agent_reply_failure_code'] = $failureCode;

            return $summary;
        }

        if ($generationStatus === 'in_progress') {
            return $summary;
        }

        $summary['agent_reply_generation_status'] = 'failed';
        $summary['agent_reply_failure_code'] = 'unexpected_agent_reply_status';

        return $summary;
    }

    /**
     * @return list<string>
     */
    private function noStoreHeaders(): array
    {
        return [
            'Cache-Control: no-store, no-cache, must-revalidate, max-age=0',
            'Pragma: no-cache',
            'Expires: 0',
        ];
    }

    /**
     * @param array<string, float|int> $timings
     * @return list<string>
     */
    private function noStoreTimingHeaders(array $timings): array
    {
        return array_merge($this->noStoreHeaders(), $this->serverTimingHeaders(['timings' => $timings]));
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, float|int> $timings
     * @return array<string, mixed>
     */
    private function mergeResultTimings(array $result, array $timings, int $totalStartedAt): array
    {
        $existing = isset($result['timings']) && is_array($result['timings'])
            ? $result['timings']
            : [];

        if (isset($existing['total']) && (is_int($existing['total']) || is_float($existing['total']))) {
            $existing['write_total'] = $existing['total'];
            unset($existing['total']);
        }

        $result['timings'] = array_merge($timings, $existing);
        $result['timings']['total'] = $this->elapsedMilliseconds($totalStartedAt);

        return $result;
    }

    /**
     * @param array<string, float|int> $timings
     * @return array<string, float|int>
     */
    private function timingsWithTotal(array $timings, int $totalStartedAt): array
    {
        $timings['total'] = $this->elapsedMilliseconds($totalStartedAt);

        return $timings;
    }

    /**
     * @return array<string, float>
     */
    private function timingMetricsFrom(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $timings = [];
        foreach ($value as $name => $duration) {
            if (!is_string($name) || !preg_match('/^[a-z_][a-z0-9_]*$/', $name)) {
                continue;
            }

            if (!is_int($duration) && !is_float($duration)) {
                continue;
            }

            $timings[$name] = (float) $duration;
        }

        return $timings;
    }

    private function elapsedMilliseconds(int $startedAt): float
    {
        return round((hrtime(true) - $startedAt) / 1000000, 1);
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private function postAnalysisContext(array $post, bool $includeThreadComments = true): array
    {
        $thread = $this->fetchPost((string) $post['thread_id']);
        $parent = trim((string) ($post['parent_id'] ?? '')) !== '' ? $this->fetchPost((string) $post['parent_id']) : null;
        $body = (string) $post['body'];

        $context = [
            'post_id' => (string) $post['post_id'],
            'content_hash' => $this->postAnalysisContentHash($post),
            'analysis_schema_version' => self::ANALYSIS_SCHEMA_VERSION,
            'post_kind' => (string) $post['post_id'] === (string) $post['thread_id'] ? 'thread' : 'reply',
            'thread_id' => (string) $post['thread_id'],
            'parent_id' => isset($post['parent_id']) ? (string) $post['parent_id'] : null,
            'subject' => isset($post['subject']) ? (string) $post['subject'] : '',
            'body' => $this->limitAnalysisText($body, 6000),
            'board_tags' => $this->decodeStringList((string) ($post['board_tags_json'] ?? '[]')),
            'author_label' => (string) ($post['author_label'] ?? 'guest'),
            'thread_subject' => $thread !== null ? (string) ($thread['subject'] ?? '') : '',
            'thread_body_preview' => $thread !== null ? $this->limitAnalysisText((string) ($thread['body'] ?? ''), 1200) : '',
            'parent_body_preview' => $parent !== null ? $this->limitAnalysisText((string) ($parent['body'] ?? ''), 1200) : '',
        ];

        if ($includeThreadComments) {
            $context['thread_comments'] = $this->threadCommentsContext($post);
            $relatedContent = $this->relatedContentContext($post);
            if ($relatedContent !== []) {
                $context['related_content'] = $relatedContent;
            }
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $post
     */
    private function postAnalysisContentHash(array $post): string
    {
        return hash('sha256', json_encode([
            'analysis_schema_version' => self::ANALYSIS_SCHEMA_VERSION,
            'post_id' => (string) $post['post_id'],
            'subject' => (string) ($post['subject'] ?? ''),
            'body' => (string) $post['body'],
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $targetPost
     * @return array<int, array<string, mixed>>
     */
    private function threadCommentsContext(array $targetPost): array
    {
        $posts = $this->fetchThreadPosts((string) $targetPost['thread_id']);
        if ($posts === []) {
            return [];
        }

        $store = new SqlitePostAnalysisStore($this->pdo());
        $targetPostId = (string) $targetPost['post_id'];
        $parentPostId = isset($targetPost['parent_id']) ? (string) $targetPost['parent_id'] : '';
        $threadPostId = (string) $targetPost['thread_id'];
        $remainingBodyBudget = self::THREAD_CONTEXT_TOTAL_BODY_LIMIT;
        $comments = [];

        foreach ($posts as $post) {
            $postId = (string) $post['post_id'];
            $isRequired = $postId === $targetPostId || $postId === $parentPostId || $postId === $threadPostId;
            if ($remainingBodyBudget <= 0 && !$isRequired) {
                continue;
            }

            $bodyLimit = $isRequired
                ? self::THREAD_CONTEXT_COMMENT_BODY_LIMIT
                : min(self::THREAD_CONTEXT_COMMENT_BODY_LIMIT, $remainingBodyBudget);
            $body = $this->limitThreadCommentBody((string) $post['body'], max(0, $bodyLimit));
            $remainingBodyBudget -= strlen($body);
            $analysis = $store->find($postId, $this->postAnalysisContentHash($post));

            $comments[] = [
                'post_id' => $postId,
                'parent_id' => isset($post['parent_id']) ? (string) $post['parent_id'] : null,
                'author_label' => (string) ($post['author_label'] ?? 'guest'),
                'created_at' => (string) ($post['created_at'] ?? ''),
                'post_kind' => $postId === $threadPostId ? 'thread' : 'reply',
                'is_thread_root' => $postId === $threadPostId,
                'is_parent' => $postId === $parentPostId,
                'is_target' => $postId === $targetPostId,
                'is_agent_authored' => (string) ($post['author_label'] ?? '') === 'reply-agent',
                'post_summary' => is_array($analysis) && ($analysis['status'] ?? null) === 'complete'
                    ? (string) ($analysis['post_summary'] ?? '')
                    : '',
                'body' => $body,
                'body_truncated' => strlen((string) $post['body']) > strlen($body),
            ];
        }

        return $comments;
    }

    /**
     * @param array<string, mixed> $targetPost
     * @return list<array<string, mixed>>
     */
    private function relatedContentContext(array $targetPost): array
    {
        return (new RelatedContentSearchService($this->pdo()))->findRelatedContent($targetPost, 5);
    }

    private function limitThreadCommentBody(string $value, int $maxLength): string
    {
        if ($maxLength <= 0) {
            return '';
        }

        if (strlen($value) <= $maxLength) {
            return $value;
        }

        return substr($value, 0, max(0, $maxLength - 12)) . "\n[truncated]";
    }

    private function limitAnalysisText(string $value, int $maxLength): string
    {
        if (strlen($value) <= $maxLength) {
            return $value;
        }

        return substr($value, 0, $maxLength) . "\n[truncated]";
    }

    /**
     * @param array<string, mixed> $analysis
     * @return array<string, mixed>
     */
    private function postAnalysisResponse(array $analysis, bool $includeDetails): array
    {
        $response = [
            'status' => 'ok',
            'post_id' => (string) ($analysis['post_id'] ?? ''),
            'analysis_status' => (string) ($analysis['status'] ?? 'unknown'),
            'cached' => (bool) ($analysis['cached'] ?? false),
            'failure_code' => $analysis['failure_code'] ?? null,
            'failure_message' => $analysis['failure_message'] ?? ($analysis['message'] ?? null),
            'retry_after' => $analysis['retry_after'] ?? null,
            'viewer_can_see_analysis' => $includeDetails,
        ];

        if (!$includeDetails) {
            return $response;
        }

        $response['provider'] = $analysis['provider'] ?? null;
        $response['provider_model'] = $analysis['provider_model'] ?? null;
        $response['post_summary'] = $analysis['post_summary'] ?? '';
        $response['moderation'] = $analysis['moderation'] ?? null;
        $response['engagement'] = $analysis['engagement'] ?? null;
        $response['quality'] = $analysis['quality'] ?? null;
        $response['respondability'] = $analysis['respondability'] ?? null;
        $response['related_content'] = $analysis['related_content'] ?? [];
        $response['related_content_assessment'] = $analysis['related_content_assessment'] ?? [];
        $response['unicode_risk'] = $analysis['unicode_risk'] ?? null;

        return $response;
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleComposeThreadSubmit(array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        $phaseStartedAt = hrtime(true);
        $input = $this->requestData($query);
        $timings['request_data'] = $this->elapsedMilliseconds($phaseStartedAt);
        try {
            $result = $this->writer()->createThread($input);
            $result = $this->mergeResultTimings($result, $timings, $totalStartedAt);
            $this->queueComposeDraftClear($this->composeDraftStorageKey('thread'));
            $returnTo = $this->resolveComposeThreadReturnTo((string) ($input['return_to'] ?? ''), (string) $result['thread_id']);
            $location = $returnTo
                . (str_contains($returnTo, '?') ? '&' : '?') . 'created_post_id=' . rawurlencode($result['post_id'])
                . '&__v=' . rawurlencode($result['commit_sha']);
            $this->sendRedirect(
                $location,
                'Created thread ' . $result['thread_id'] . '. Commit ' . $result['commit_sha'] . '.',
                303,
                $this->serverTimingHeaders($result)
            );
        } catch (RuntimeException $exception) {
            $this->sendHtml(
                $this->renderComposeThreadPage(
                    (string) ($input['board_tags'] ?? 'general'),
                    (string) ($input['subject'] ?? ''),
                    (string) ($input['body'] ?? ''),
                    null,
                    $exception->getMessage()
                ),
                400,
                $this->serverTimingHeaders(['timings' => $this->timingsWithTotal($timings, $totalStartedAt)])
            );
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleComposeReplySubmit(array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        $phaseStartedAt = hrtime(true);
        $input = $this->requestData($query);
        $timings['request_data'] = $this->elapsedMilliseconds($phaseStartedAt);
        $threadId = (string) ($input['thread_id'] ?? '');
        $parentId = (string) ($input['parent_id'] ?? '');

        try {
            $result = $this->writer()->createReply($input);
            $result = $this->mergeResultTimings($result, $timings, $totalStartedAt);
            $this->queueComposeDraftClear($this->composeDraftStorageKey('reply', $threadId, $parentId));
            $returnTo = $this->resolveComposeReplyReturnTo((string) ($input['return_to'] ?? ''), $result['thread_id']);
            $location = $returnTo
                . (str_contains($returnTo, '?') ? '&' : '?') . 'created_post_id=' . rawurlencode($result['post_id'])
                . '&__v=' . rawurlencode($result['commit_sha'])
                . '#post-' . rawurlencode($result['post_id']);
            $this->sendRedirect(
                $location,
                'Created reply ' . $result['post_id'] . '. Commit ' . $result['commit_sha'] . '.',
                303,
                $this->serverTimingHeaders($result)
            );
        } catch (RuntimeException $exception) {
            $this->sendHtml(
                $this->renderComposeReplyPage(
                    $threadId,
                    $parentId,
                    null,
                    $exception->getMessage(),
                    (string) ($input['board_tags'] ?? 'general'),
                    (string) ($input['body'] ?? '')
                ),
                400,
                $this->serverTimingHeaders(['timings' => $this->timingsWithTotal($timings, $totalStartedAt)])
            );
        }
    }

    private function resolveComposeReplyReturnTo(string $requestedReturnTo, string $threadId): string
    {
        if (preg_match('#^/forte(?:\?(.*))?$#', $requestedReturnTo, $matches) === 1) {
            return $this->buildForteBoardReturnTo($matches[1] ?? '');
        }

        return '/threads/' . $threadId;
    }

    /**
     * Sibling to resolveComposeReplyReturnTo() for thread creation: there's
     * no existing thread to whitelist a single-thread return path against
     * (the thread doesn't exist until after this call), so this only
     * recognizes the `/forte` board shape and always selects the
     * newly-created thread there, overriding anything the client sent.
     */
    private function resolveComposeThreadReturnTo(string $requestedReturnTo, string $newThreadId): string
    {
        if (preg_match('#^/forte(?:\?(.*))?$#', $requestedReturnTo, $matches) === 1) {
            return $this->buildForteBoardReturnTo($matches[1] ?? '', $newThreadId);
        }

        return '/threads/' . $newThreadId;
    }

    /**
     * Rebuilds a `/forte` return URL from only a fixed, character-restricted
     * allowlist of query params (`tag`, `selected`), discarding anything else
     * so the client-supplied query string is never passed through verbatim.
     * $overrideSelected, when given, wins over any `selected` present in
     * $requestedQueryString (used by thread creation, where the client can't
     * know the new thread's ID up front).
     */
    private function buildForteBoardReturnTo(string $requestedQueryString, ?string $overrideSelected = null): string
    {
        parse_str($requestedQueryString, $params);
        $allowed = [];

        $tag = (string) ($params['tag'] ?? '');
        if ($tag !== '' && preg_match('/^[a-z0-9-]+$/', $tag) === 1) {
            $allowed['tag'] = $tag;
        }

        $selected = $overrideSelected ?? (string) ($params['selected'] ?? '');
        if ($selected !== '' && preg_match('/^[A-Za-z0-9._:-]+$/', $selected) === 1) {
            $allowed['selected'] = $selected;
        }

        $queryString = http_build_query($allowed);

        return '/forte' . ($queryString !== '' ? '?' . $queryString : '');
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleAccountKeySubmit(array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        $phaseStartedAt = hrtime(true);
        $input = $this->requestData($query);
        $timings['request_data'] = $this->elapsedMilliseconds($phaseStartedAt);
        try {
            $result = $this->writer()->linkIdentity($input);
            $result = $this->mergeResultTimings($result, $timings, $totalStartedAt);
            $location = '/profiles/' . $result['profile_slug'];
            $this->sendRedirect(
                $location,
                'Linked identity ' . $result['identity_id'] . ' as ' . $result['username'] . '. Commit ' . $result['commit_sha'] . '.',
                303,
                $this->serverTimingHeaders($result)
            );
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'Identity already exists for this fingerprint.') {
                try {
                    $key = (new OpenPgpKeyInspector())->inspect((string) ($input['public_key'] ?? ''));
                    $this->sendRedirect(
                        '/profiles/openpgp-' . strtolower($key['fingerprint']),
                        'This identity is already linked. Showing its existing profile.',
                    );
                    return;
                } catch (RuntimeException) {
                    // Keep the normal form error when the submitted key cannot be inspected.
                }
            }
            $this->sendHtml(
                $this->renderAccountKeyPage(null, $exception->getMessage()),
                400,
                $this->serverTimingHeaders(['timings' => $this->timingsWithTotal($timings, $totalStartedAt)])
            );
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleApproveUserSubmit(string $slug, array $query): void
    {
        $profile = $this->fetchProfileBySlug($slug);
        if ($profile === null) {
            $this->notFound();
            return;
        }

        $this->sendHtml(
            $this->renderProfilePage(
                $profile,
                false,
                null,
                'Approval requires a browser signature. Enable JavaScript and refresh this page before trying again.'
            ),
            400
        );
    }

    /**
     * @param array<string, float> $timings
     * @return array<string, mixed>
     */
    private function prepareUserApprovalBySlug(string $slug, array &$timings = []): array
    {
        $phaseStartedAt = hrtime(true);
        $profile = $this->fetchProfileBySlug($slug);
        $timings['target_profile'] = $this->elapsedMilliseconds($phaseStartedAt);
        if ($profile === null) {
            throw new RuntimeException('Profile not found.');
        }

        $phaseStartedAt = hrtime(true);
        $viewerProfile = $this->resolveViewerProfileFromIdentityHint();
        $timings['viewer_profile'] = $this->elapsedMilliseconds($phaseStartedAt);
        if ($viewerProfile === null || ((int) $viewerProfile['is_approved']) !== 1) {
            throw new RuntimeException('Only approved users can approve other users.');
        }

        if ((string) $viewerProfile['identity_id'] === (string) $profile['identity_id']) {
            throw new RuntimeException('Self-approval is not allowed.');
        }

        if ((int) $profile['is_approved'] === 1) {
            throw new RuntimeException('User is already approved.');
        }

        return $this->writer()->prepareApproval([
            'approver_identity_id' => (string) $viewerProfile['identity_id'],
            'target_identity_id' => (string) $profile['identity_id'],
            'target_profile_slug' => (string) $profile['profile_slug'],
            'thread_id' => (string) $profile['bootstrap_thread_id'],
            'parent_id' => (string) $profile['bootstrap_post_id'],
        ]);
    }

    private function pdo(): PDO
    {
        return (new ReadModelConnection($this->databasePath))->open();
    }

    private function commitsCapabilityAvailable(): bool
    {
        if ($this->commitsCapabilityAvailable !== null) {
            return $this->commitsCapabilityAvailable;
        }

        try {
            return $this->commitsCapabilityAvailable = (new ReadModelCapabilityInspector())->commitsAvailable($this->pdo());
        } catch (\Throwable) {
            return $this->commitsCapabilityAvailable = false;
        }
    }

    private function enqueueReadModelRecovery(): void
    {
        try {
            $this->taskQueueStore()->enqueue(SqliteTaskQueueStore::REBUILD_READ_MODEL, 'read-model');
        } catch (\Throwable) {
            // Visitor recovery remains safe even if the private queue is not
            // writable; status/cron tooling provides the operator diagnosis.
        }
    }

    private function sendReadModelCapabilityUnavailable(): void
    {
        $this->sendJson([
            'status' => 'error',
            'error' => 'read_model_capability_unavailable',
            'message' => 'Commit history is temporarily unavailable while site data updates.',
        ], 503);
    }

    private function taskQueueStore(): SqliteTaskQueueStore
    {
        if ($this->taskQueueStoreInitialized) {
            if ($this->taskQueueStore === null) {
                throw new RuntimeException('Task queue is unavailable.');
            }

            return $this->taskQueueStore;
        }

        $this->taskQueueStoreInitialized = true;
        $path = TaskQueueDatabaseConfig::path($this->projectRoot);
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Task queue directory is not writable.');
        }

        $this->taskQueueStore = new SqliteTaskQueueStore(new PDO('sqlite:' . $path));

        return $this->taskQueueStore;
    }

    /**
     * @return array{status:string,queued:int,running:int,completed:int,failed:int}
     */
    private function taskQueueStatus(): array
    {
        $path = TaskQueueDatabaseConfig::path($this->projectRoot);
        if (!is_file($path)) {
            return ['status' => 'not_initialized', 'queued' => 0, 'running' => 0, 'completed' => 0, 'failed' => 0];
        }

        try {
            $store = $this->taskQueueStoreInitialized
                ? $this->taskQueueStore()
                : new SqliteTaskQueueStore(new PDO('sqlite:' . $path));
            $counts = $store->counts();

            return ['status' => 'available'] + $counts;
        } catch (\Throwable) {
            return ['status' => 'unavailable', 'queued' => 0, 'running' => 0, 'completed' => 0, 'failed' => 0];
        }
    }

    private function executionLock(): ExecutionLock
    {
        return new ExecutionLock(dirname($this->databasePath) . '/forum-rewrite.lock');
    }

    private function staleMarker(): ReadModelStaleMarker
    {
        return new ReadModelStaleMarker($this->databasePath);
    }

    /**
     * @return array<string, string>
     */
    private function readMetadata(PDO $pdo): array
    {
        $rows = $pdo->query('SELECT key, value FROM metadata')->fetchAll();
        $metadata = [];
        foreach ($rows as $row) {
            $metadata[(string) $row['key']] = (string) $row['value'];
        }

        return $metadata;
    }

    private function notFound(): void
    {
        $this->sendHtml(
            $this->renderMessagePage(
                'Not Found',
                'Not Found',
                'The requested route does not exist in the local test slice.',
                'none'
            ),
            404
        );
    }

    private function sendHtml(string $html, int $statusCode, array $headers = []): void
    {
        http_response_code($statusCode);
        header('Content-Type: text/html; charset=utf-8');
        foreach ($headers as $headerValue) {
            header($headerValue);
        }
        echo $html;
    }

    private function sendText(string $text, int $statusCode, array $headers = []): void
    {
        http_response_code($statusCode);
        header('Content-Type: text/plain; charset=utf-8');
        foreach ($headers as $headerValue) {
            header($headerValue);
        }
        echo $text;
    }

    private function normalizeSourceRoutePath(string $encodedRelativePath): ?string
    {
        $relativePath = rawurldecode($encodedRelativePath);
        $relativePath = str_replace('\\', '/', $relativePath);
        if ($relativePath === '' || str_starts_with($relativePath, '/')) {
            return null;
        }

        foreach (explode('/', $relativePath) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        return $relativePath;
    }

    private function isValidCanonicalSourcePath(string $relativePath): bool
    {
        return $this->isValidCanonicalRecordSourcePath($relativePath)
            || $this->isValidCanonicalDetachedSignaturePath($relativePath);
    }

    private function isValidCanonicalRecordSourcePath(string $relativePath): bool
    {
        if (!str_starts_with($relativePath, 'records/')) {
            return false;
        }

        return $relativePath === 'records/instance/public.txt'
            || $relativePath === 'records/instance/feature-flags.txt'
            || preg_match('#^records/posts/(?:\d{4}/\d{2}/\d{2}/)?[A-Za-z0-9][A-Za-z0-9._-]*\.txt$#', $relativePath) === 1
            || preg_match('#^records/thread-labels/[A-Za-z0-9][A-Za-z0-9._-]*\.txt$#', $relativePath) === 1
            || preg_match('#^records/post-reactions/[A-Za-z0-9][A-Za-z0-9._-]*\.txt$#', $relativePath) === 1
            || preg_match('#^records/identity/identity-openpgp-[A-Fa-f0-9]{40}\.txt$#', $relativePath) === 1
            || preg_match('#^records/approval-seeds/openpgp-[A-Fa-f0-9]{40}\.txt$#', $relativePath) === 1
            || preg_match('#^records/public-keys/openpgp-[A-Fa-f0-9]{40}\.asc$#', $relativePath) === 1;
    }

    private function isValidCanonicalDetachedSignaturePath(string $relativePath): bool
    {
        foreach (['.asc', '.sig'] as $suffix) {
            if (str_ends_with($relativePath, $suffix)) {
                return $this->isValidCanonicalRecordSourcePath(substr($relativePath, 0, -strlen($suffix)));
            }
        }

        return false;
    }

    private function isValidSourceCommitSha(string $commitSha): bool
    {
        return preg_match('/^[A-Fa-f0-9]{40}$/', $commitSha) === 1;
    }

    private function readCurrentSourceFile(string $relativePath): ?string
    {
        if (!$this->currentSourcePathExists($relativePath)) {
            return null;
        }

        $contents = file_get_contents($this->repositoryRoot . '/' . $relativePath);
        return $contents === false ? null : $contents;
    }

    private function currentSourcePathExists(string $relativePath): bool
    {
        $repositoryRoot = realpath($this->repositoryRoot);
        if ($repositoryRoot === false) {
            return false;
        }

        $path = $this->repositoryRoot . '/' . $relativePath;
        $realPath = realpath($path);
        if ($realPath === false || !is_file($realPath)) {
            return false;
        }

        $rootPrefix = rtrim($repositoryRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($realPath, $rootPrefix)) {
            return false;
        }

        return true;
    }

    private function readSourceBlob(string $commitSha, string $relativePath): ?string
    {
        if (!is_dir($this->repositoryRoot . '/.git')) {
            return null;
        }

        $object = $commitSha . ':' . $relativePath;
        $command = sprintf(
            'git -C %s show --no-ext-diff %s 2>/dev/null',
            escapeshellarg($this->repositoryRoot),
            escapeshellarg($object)
        );
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            return null;
        }

        return implode("\n", $output) . "\n";
    }

    private function sourceCommitDetails(string $commitSha): ?string
    {
        if (!is_dir($this->repositoryRoot . '/.git')) {
            return null;
        }

        $metadataCommand = sprintf(
            'git -C %s show -s --format=%%H%%n%%aI%%n%%s %s 2>/dev/null',
            escapeshellarg($this->repositoryRoot),
            escapeshellarg($commitSha)
        );
        $metadataOutput = [];
        $metadataExitCode = 0;
        exec($metadataCommand, $metadataOutput, $metadataExitCode);
        if ($metadataExitCode !== 0 || count($metadataOutput) < 3) {
            return null;
        }

        $files = $this->sourceCommitFiles($commitSha);
        if ($files === null) {
            return null;
        }

        $lines = [
            'Commit: ' . trim($metadataOutput[0]),
            'Author-Date: ' . trim($metadataOutput[1]),
            'Subject: ' . trim($metadataOutput[2]),
            'Files:',
        ];

        foreach ($files as $file) {
            $line = $file['status'] . ' ';
            if ($file['previous_path'] !== '') {
                $line .= $file['previous_path'] . ' -> ';
            }
            $lines[] = $line . $file['path'];
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @return list<array{status:string,path:string,previous_path:string}>|null
     */
    private function sourceCommitFiles(string $commitSha): ?array
    {
        if (!$this->isValidSourceCommitSha($commitSha) || !is_dir($this->repositoryRoot . '/.git')) {
            return null;
        }

        if (array_key_exists($commitSha, $this->sourceCommitFileManifestCache)) {
            return $this->sourceCommitFileManifestCache[$commitSha];
        }

        $command = sprintf(
            'git -C %s diff-tree --root --no-commit-id --name-status -r -M %s 2>/dev/null',
            escapeshellarg($this->repositoryRoot),
            escapeshellarg($commitSha)
        );
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            $this->sourceCommitFileManifestCache[$commitSha] = null;
            return null;
        }

        $files = [];
        foreach ($output as $line) {
            $parts = explode("\t", $line);
            $rawStatus = trim((string) array_shift($parts));
            $status = match ($rawStatus[0] ?? '') {
                'A' => 'added',
                'M' => 'modified',
                'D' => 'deleted',
                'R' => 'renamed',
                default => 'changed',
            };
            $previousPath = '';
            if ($status === 'renamed') {
                $previousPath = trim((string) ($parts[0] ?? ''));
                $path = trim((string) ($parts[1] ?? ''));
            } else {
                $path = trim((string) ($parts[0] ?? ''));
            }

            if ($path === '') {
                continue;
            }

            $files[] = [
                'status' => $status,
                'path' => $path,
                'previous_path' => $previousPath,
            ];
        }

        $this->sourceCommitFileManifestCache[$commitSha] = $files;

        return $files;
    }

    /**
     * @return list<array{status:string,path:string,previous_path:string,role:string,href:string,signature_signer_identity:string,signature_public_key_path:string,signature_public_key_href:string,signature_key_status:string}>|null
     */
    private function activityCommitManifest(string $commitSha): ?array
    {
        if (array_key_exists($commitSha, $this->activityCommitManifestCache)) {
            return $this->activityCommitManifestCache[$commitSha];
        }

        // The expensive part - a `git diff-tree` exec plus a signature/
        // OpenPGP lookup per file - is cached persistently by commit sha
        // (see SqliteActivityCommitManifestCache), since a commit's file
        // list and each file's role/signer never change. Only the
        // request-specific hrefs below are always recomputed - they're
        // cheap string formatting, not worth persisting.
        $rawFiles = $this->activityCommitManifestCacheStore()?->get($commitSha);
        if ($rawFiles === null) {
            $files = $this->sourceCommitFiles($commitSha);
            if ($files === null) {
                $this->activityCommitManifestCache[$commitSha] = null;
                return null;
            }

            $rawFiles = array_map(function (array $file): array {
                $signature = $this->activityCommitSignatureMetadata($file['path']);

                return [
                    'status' => $file['status'],
                    'path' => $file['path'],
                    'previous_path' => $file['previous_path'],
                    'role' => $this->sourceCommitFileRole($file['path']),
                    'signature_signer_identity' => $signature['signer_identity'],
                    'signature_public_key_path' => $signature['public_key_path'],
                    'signature_key_status' => $signature['status'],
                ];
            }, $files);

            $this->activityCommitManifestCacheStore()?->put($commitSha, $rawFiles);
        }

        $manifest = array_map(function (array $file) use ($commitSha): array {
            return [
                'status' => $file['status'],
                'path' => $file['path'],
                'previous_path' => $file['previous_path'],
                'role' => $file['role'],
                'href' => $this->sourceCommitFileHref($file['path'], $file['status'], $commitSha),
                'signature_signer_identity' => $file['signature_signer_identity'],
                'signature_public_key_path' => $file['signature_public_key_path'],
                'signature_public_key_href' => $file['signature_public_key_path'] !== ''
                    ? '/source/current/' . $this->encodeSourcePathForUrl($file['signature_public_key_path'])
                    : '',
                'signature_key_status' => $file['signature_key_status'],
            ];
        }, $rawFiles);

        $this->activityCommitManifestCache[$commitSha] = $manifest;

        return $manifest;
    }

    /**
     * Narrows a commit's full file manifest down to the files one action's
     * detail view should show: its own record, and that record's detached
     * signature (if any) - never the rest of the commit, which can run to
     * thousands of files for actions that happen to share a large
     * historical commit (e.g. the original archive import). identity_
     * bootstrap is the one compound action that also establishes a separate
     * identity record in the same commit.
     *
     * The signer's public key only gets its own row when this action's own
     * commit actually introduced it (e.g. a fresh identity_bootstrap, which
     * adds the key alongside the record and signature it authenticates) -
     * genuinely one of the files this action added, not just referenced.
     * When the key instead already existed from some earlier, unrelated
     * commit (the common case: a key is normally established once and
     * reused for everything it later signs), it isn't a file this action
     * added, and it's already named and linked on the signature's own entry
     * ("Public key:"), so a standalone row for it would just repeat the
     * same file a second time without saying anything new.
     *
     * The signature itself is resolved independent of whether it's actually
     * part of *this* item's own commit - it can occasionally live in a
     * different commit than the record it signs. Only the item's own record
     * - and, for identity_bootstrap, its paired identity record - is
     * guaranteed to be part of the item's own commit (that's precisely the
     * commit source_commit_sha names); everything else falls back to a
     * standalone entry built the same way activityCommitManifest() would
     * build it, just not sourced from that one commit's diff.
     *
     * @param array<string, mixed> $item
     * @return list<array{status:string,path:string,previous_path:string,role:string,href:string,signature_signer_identity:string,signature_public_key_path:string,signature_public_key_href:string,signature_key_status:string}>
     */
    private function activityItemRelevantFiles(array $item): array
    {
        $sourcePath = (string) ($item['source_path'] ?? '');
        if ($sourcePath === '') {
            return [];
        }

        $byPath = [];
        foreach (($item['source_commit_files'] ?? []) as $file) {
            $byPath[$file['path']] = $file;
        }

        $files = [];

        $files[] = $byPath[$sourcePath] ?? $this->standaloneRelevantFile(
            $sourcePath,
            (string) ($item['source_path_href'] ?? ''),
            $this->sourceCommitFileRole($sourcePath),
        );

        if ((string) ($item['record_family'] ?? '') === 'identity_bootstrap') {
            $identityId = $this->signatureSignerIdentityId($sourcePath);
            $fingerprint = $identityId !== null ? $this->openPgpFingerprintFromIdentityId($identityId) : null;
            if ($fingerprint !== null) {
                $identityRecordPath = CanonicalPathResolver::identity(strtolower($fingerprint));
                $identityRecordHref = $this->currentSourcePathExists($identityRecordPath)
                    ? '/source/current/' . $this->encodeSourcePathForUrl($identityRecordPath)
                    : '';
                $files[] = $byPath[$identityRecordPath] ?? $this->standaloneRelevantFile(
                    $identityRecordPath,
                    $identityRecordHref,
                    'identity record',
                );
            }
        }

        $signaturePath = (string) ($item['source_signature_path'] ?? '');
        if ($signaturePath !== '') {
            $signatureEntry = $byPath[$signaturePath] ?? $this->standaloneSignatureRelevantFile(
                $signaturePath,
                (string) ($item['source_signature_href'] ?? ''),
            );
            $files[] = $signatureEntry;

            // The public key only gets its own row when this action's own
            // commit actually added/touched it - e.g. a fresh
            // identity_bootstrap, which introduces the key alongside the
            // record and signature it authenticates. When the key instead
            // already existed from some earlier, unrelated commit (the
            // common case for an ordinary signed post - a key is normally
            // established once and reused for everything it later signs),
            // it isn't really a file *this* action added, and it's already
            // named and linked on the signature entry itself ("Public
            // key:"), so a standalone row for it would just repeat the same
            // file a second time without saying anything new.
            $publicKeyPath = $signatureEntry['signature_public_key_path'];
            if ($publicKeyPath !== '' && isset($byPath[$publicKeyPath])) {
                $files[] = $byPath[$publicKeyPath];
            }
        }

        return $files;
    }

    /**
     * @return array{status:string,path:string,previous_path:string,role:string,href:string,signature_signer_identity:string,signature_public_key_path:string,signature_public_key_href:string,signature_key_status:string}
     */
    private function standaloneRelevantFile(string $path, string $href, string $role): array
    {
        return [
            'status' => 'current',
            'path' => $path,
            'previous_path' => '',
            'role' => $role,
            'href' => $href,
            'signature_signer_identity' => '',
            'signature_public_key_path' => '',
            'signature_public_key_href' => '',
            'signature_key_status' => '',
        ];
    }

    /**
     * @return array{status:string,path:string,previous_path:string,role:string,href:string,signature_signer_identity:string,signature_public_key_path:string,signature_public_key_href:string,signature_key_status:string}
     */
    private function standaloneSignatureRelevantFile(string $signaturePath, string $href): array
    {
        $signature = $this->activityCommitSignatureMetadata($signaturePath);

        return [
            'status' => 'current',
            'path' => $signaturePath,
            'previous_path' => '',
            'role' => 'detached signature',
            'href' => $href,
            'signature_signer_identity' => $signature['signer_identity'],
            'signature_public_key_path' => $signature['public_key_path'],
            'signature_public_key_href' => $signature['public_key_href'],
            'signature_key_status' => $signature['status'],
        ];
    }

    private function sourceCommitFileRole(string $path): string
    {
        if ($this->isValidCanonicalDetachedSignaturePath($path)) {
            return 'detached signature';
        }

        return match (true) {
            str_starts_with($path, 'records/posts/') => 'post record',
            str_starts_with($path, 'records/thread-labels/') => 'thread label record',
            str_starts_with($path, 'records/post-reactions/') => 'post reaction record',
            str_starts_with($path, 'records/identity/') => 'identity record',
            str_starts_with($path, 'records/approval-seeds/') => 'approval seed record',
            str_starts_with($path, 'records/public-keys/') => 'public key',
            $path === 'records/instance/public.txt' => 'instance record',
            $path === 'records/instance/feature-flags.txt' => 'feature flags record',
            $this->isValidCanonicalRecordSourcePath($path) => 'canonical record',
            default => 'other committed file',
        };
    }

    private function sourceCommitFileHref(string $path, string $status, string $commitSha): string
    {
        if ($status === 'deleted' || !$this->isValidCanonicalSourcePath($path)) {
            return '';
        }

        return $this->sourcePathHref($path, $commitSha) ?? '';
    }

    /**
     * @return array{signer_identity:string,public_key_path:string,public_key_href:string,status:string}
     */
    private function activityCommitSignatureMetadata(string $signaturePath): array
    {
        $empty = [
            'signer_identity' => '',
            'public_key_path' => '',
            'public_key_href' => '',
            'status' => '',
        ];
        $recordPath = $this->sourceSignatureRecordPath($signaturePath);
        if ($recordPath === null) {
            return $empty;
        }

        $identityId = $this->signatureSignerIdentityId($recordPath);
        if ($identityId === null) {
            return [...$empty, 'status' => 'signing identity unavailable'];
        }

        $fingerprint = $this->openPgpFingerprintFromIdentityId($identityId);
        if ($fingerprint === null) {
            return [...$empty, 'signer_identity' => $identityId, 'status' => 'signing key unavailable'];
        }

        foreach ([CanonicalPathResolver::publicKey($fingerprint), 'records/public-keys/openpgp-' . strtolower($fingerprint) . '.asc'] as $path) {
            if (!$this->currentSourcePathExists($path)) {
                continue;
            }

            return [
                'signer_identity' => $identityId,
                'public_key_path' => $path,
                'public_key_href' => '/source/current/' . $this->encodeSourcePathForUrl($path),
                'status' => 'ok',
            ];
        }

        return [...$empty, 'signer_identity' => $identityId, 'status' => 'signing key unavailable'];
    }

    private function sourceSignatureRecordPath(string $signaturePath): ?string
    {
        foreach (['.asc', '.sig'] as $suffix) {
            if (str_ends_with($signaturePath, $suffix)) {
                $recordPath = substr($signaturePath, 0, -strlen($suffix));
                return $this->isValidCanonicalRecordSourcePath($recordPath) ? $recordPath : null;
            }
        }

        return null;
    }

    private function signatureSignerIdentityId(string $recordPath): ?string
    {
        try {
            $repository = new CanonicalRecordRepository($this->repositoryRoot);
            if (str_starts_with($recordPath, 'records/posts/')) {
                return $repository->loadPost($recordPath)->authorIdentityId;
            }
            if (str_starts_with($recordPath, 'records/identity/')) {
                return $repository->loadIdentity($recordPath)->identityId;
            }
            if (str_starts_with($recordPath, 'records/thread-labels/')) {
                return $repository->loadThreadLabel($recordPath)->authorIdentityId;
            }
            if (str_starts_with($recordPath, 'records/post-reactions/')) {
                return $repository->loadPostReaction($recordPath)->authorIdentityId;
            }
        } catch (RuntimeException) {
            return null;
        }

        return null;
    }

    private function openPgpFingerprintFromIdentityId(string $identityId): ?string
    {
        if (preg_match('/^openpgp:([a-f0-9]{40})$/i', trim($identityId), $matches) !== 1) {
            return null;
        }

        return strtoupper($matches[1]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sendJson(array $payload, int $statusCode, array $headers = []): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($headers as $headerValue) {
            header($headerValue);
        }
        echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
    }

    private function sendXml(string $xml, int $statusCode): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/rss+xml; charset=utf-8');
        echo $xml;
    }

    private function sendRedirect(string $location, string $message, int $statusCode = 303, array $headers = [], string $activeSection = 'compose'): void
    {
        http_response_code($statusCode);
        header('Location: ' . $location);
        header('Content-Type: text/html; charset=utf-8');
        foreach ($headers as $headerValue) {
            header($headerValue);
        }

        echo $this->renderPageTemplate(
            'redirect.php',
            [
                'location' => $location,
                'message' => $message,
            ],
            'Redirecting',
            $activeSection
        );
    }

    private function composeDraftStorageKey(string $kind, string $threadId = '', string $parentId = ''): string
    {
        if ($kind === 'reply') {
            return 'forum_compose_draft:reply:' . $threadId . ':' . $parentId;
        }

        return 'forum_compose_draft:' . $kind;
    }

    /**
     * @param array<string, mixed> $result
     * @return list<string>
     */
    private function serverTimingHeaders(array $result): array
    {
        if (!isset($result['timings']) || !is_array($result['timings'])) {
            return [];
        }

        $metrics = [];
        foreach ($result['timings'] as $name => $duration) {
            if (!is_string($name) || !preg_match('/^[a-z_][a-z0-9_]*$/', $name)) {
                continue;
            }

            if (!is_int($duration) && !is_float($duration)) {
                continue;
            }

            $metrics[] = sprintf('%s;dur=%.1f', $name, (float) $duration);
        }

        if ($metrics === []) {
            return [];
        }

        return ['Server-Timing: ' . implode(', ', $metrics)];
    }

    private function queueComposeDraftClear(string $storageKey): void
    {
        setcookie('forum_clear_compose_draft', $storageKey, [
            'expires' => time() + 300,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function renderMessagePage(string $title, string $heading, string $message, string $activeSection): string
    {
        return $this->renderPageTemplate(
            'message.php',
            [
                'heading' => $heading,
                'message' => $message,
            ],
            $title,
            $activeSection,
        );
    }

    private function renderLobbyAccessRequiredPage(): string
    {
        return $this->renderPageTemplate(
            'message.php',
            [
                'heading' => 'Approval required',
                'message' => 'Your access is pending approval. Once you are fully authenticated, you can access this page.',
                'viewerProfile' => $this->lobbyViewerProfile(),
            ],
            'Approval required',
            'lobby',
        );
    }

}
