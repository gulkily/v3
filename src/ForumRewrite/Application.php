<?php

declare(strict_types=1);

namespace ForumRewrite;

use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\SourcePathValidator;
use ForumRewrite\Http\AboutPageController;
use ForumRewrite\Http\ActivityPageController;
use ForumRewrite\Http\ApiTextController;
use ForumRewrite\Http\AuthApiController;
use ForumRewrite\Http\BoardPageController;
use ForumRewrite\Http\BoardViewOptions;
use ForumRewrite\Http\CodebaseStateController;
use ForumRewrite\Http\ComposeAndAccountKeyController;
use ForumRewrite\Http\ForteActivityController;
use ForumRewrite\Http\ForteBoardController;
use ForumRewrite\Http\ForteContentAndUserDetailApiController;
use ForumRewrite\Http\ForteProfileController;
use ForumRewrite\Http\ForteUserDirectoryController;
use ForumRewrite\Http\IdentityApprovalAndInvitationApiController;
use ForumRewrite\Http\IdentityHintController;
use ForumRewrite\Http\InstancePageController;
use ForumRewrite\Http\LlmExchangesController;
use ForumRewrite\Http\LobbyController;
use ForumRewrite\Http\PostWorkflowApiController;
use ForumRewrite\Http\ProfilePageController;
use ForumRewrite\Http\RouteServices;
use ForumRewrite\Http\SourceFileController;
use ForumRewrite\Http\TagApiController;
use ForumRewrite\Http\TagsPageController;
use ForumRewrite\Http\ThreadAndPostPageController;
use ForumRewrite\Http\ToolsPageController;
use ForumRewrite\Http\WritePostAndIdentityApiController;
use ForumRewrite\ReadModel\AuthoredContentRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelCapabilityInspector;
use ForumRewrite\ReadModel\ReadModelConnection;
use ForumRewrite\ReadModel\ProfileRepository;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\ReadModel\ReadModelStaleMarker;
use ForumRewrite\ReadModel\ThreadRepository;
use ForumRewrite\ReadModel\ThreadRowSupport;
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
use ForumRewrite\Tools\ToolsPageSupport;
use PDO;
use RuntimeException;

final class Application
{
    private const PERSISTENT_VIEWER_SESSION_COOKIE_LIFETIME = 34560000;
    private const LOBBY_GATE_LOG_ROTATE_LINES = 1000;
    private ?string $appVersion = null;
    private ?FeatureFlagEvaluator $featureFlags = null;
    private ?RouteServices $routeServices = null;
    private ?LlmExchangeRecorder $llmExchangeRecorder = null;
    private bool $llmExchangeRecorderInitialized = false;
    private ?SqliteLlmExchangeStore $llmExchangeStore = null;
    private bool $llmExchangeStoreInitialized = false;
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
        return $this->postWorkflowService()->fulfillAgentReplyRequest($requestRow);
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
            $this->logLobbyGateDiagnostics('lobby_redirect', $method, $path);
            $this->sendRedirect('/lobby/', 'Entering lobby.', statusCode: 303, activeSection: 'account');
            return;
        }

        if ($this->approvedMembersOnlyEnabled() && !$this->membersOnlyRequestAllowed($method, $path)) {
            if (!$this->isApplicationRoute($path)) {
                $this->notFound();
                return;
            }

            if ($this->shouldRenderAuthenticationResume($method, $path, $query)) {
                $this->logLobbyGateDiagnostics('resume', $method, $path);
                $this->sendHtml(
                    $this->renderAuthenticationResumePage(ResumeTarget::fromRequestUri($requestUri)),
                    401,
                );
                return;
            }

            $this->logLobbyGateDiagnostics('lobby_required_403', $method, $path);
            $this->sendHtml(
                $this->renderLobbyAccessRequiredPage(),
                403
            );
            return;
        }

        if ($path === '/api/set_identity_hint') {
            $this->identityHintController()->setIdentityHint($method, $query);
            return;
        }

        if ($path === '/api/clear_identity') {
            $this->identityHintController()->clearIdentity($method);
            return;
        }

        if ($path === '/api/auth_challenge') {
            $this->authApiController()->authChallenge($method);
            return;
        }

        if ($path === '/api/authenticate_identity') {
            $this->authApiController()->authenticateIdentity($method, $query);
            return;
        }

        if ($path === '/api/auth_status') {
            $this->authApiController()->authenticationStatus($method);
            return;
        }

        if ($path === '/api/create_thread') {
            $this->writePostAndIdentityApiController()->createThread($method, $query);
            return;
        }

        if ($path === '/api/prepare_thread') {
            $this->writePostAndIdentityApiController()->prepareThread($method, $query);
            return;
        }

        if ($path === '/api/prepare_identity') {
            $this->writePostAndIdentityApiController()->prepareIdentity($method, $query);
            return;
        }

        if ($path === '/api/create_reply') {
            $this->writePostAndIdentityApiController()->createReply($method, $query);
            return;
        }

        if ($path === '/api/prepare_reply') {
            $this->writePostAndIdentityApiController()->prepareReply($method, $query);
            return;
        }

        if ($path === '/api/create_prepared_post') {
            $this->writePostAndIdentityApiController()->createPreparedPost($method, $query);
            return;
        }

        if ($path === '/api/create_identity') {
            $this->writePostAndIdentityApiController()->createIdentity($method, $query);
            return;
        }

        if ($path === '/api/analyze_post') {
            $this->postWorkflowApiController()->analyzePost($method, $query);
            return;
        }

        if ($path === '/api/generate_agent_reply') {
            $this->postWorkflowApiController()->generateAgentReply($method, $query);
            return;
        }

        if ($path === '/api/codex_handoff') {
            $this->postWorkflowApiController()->codexHandoff($method, $query);
            return;
        }

        if ($path === '/api/codex_handoff_approval') {
            $this->postWorkflowApiController()->codexHandoffApproval($method, $query);
            return;
        }

        if ($path === '/api/apply_thread_tag') {
            $this->tagApiController()->applyThreadTag($method, $query);
            return;
        }

        if ($path === '/api/apply_post_tag') {
            $this->tagApiController()->applyPostTag($method, $query);
            return;
        }

        if ($path === '/api/set_feature_flag') {
            $this->toolsPageController()->submitFeatureFlagApi($method, $query);
            return;
        }

        if ($path === '/api/link_identity') {
            $this->composeAndAccountKeyController()->linkIdentityApi($method, $query);
            return;
        }

        if ($path === '/api/approve_user') {
            $this->identityApprovalAndInvitationApiController()->approveUser($method);
            return;
        }

        if ($path === '/api/prepare_approval') {
            $this->identityApprovalAndInvitationApiController()->prepareApproval($method, $query);
            return;
        }

        if ($path === '/api/create_prepared_approval') {
            $this->identityApprovalAndInvitationApiController()->createPreparedApproval($method, $query);
            return;
        }

        if ($path === '/api/prepare_invitation') {
            $this->identityApprovalAndInvitationApiController()->prepareInvitation($method, $query);
            return;
        }

        if ($path === '/api/create_prepared_invitation') {
            $this->identityApprovalAndInvitationApiController()->createPreparedInvitation($method, $query);
            return;
        }

        if ($path === '/api/prepare_invitation_redemption') {
            $this->identityApprovalAndInvitationApiController()->prepareInvitationRedemption($method, $query);
            return;
        }

        if ($path === '/invites/' || $path === '/invites') {
            $this->sendHtml($this->lobbyController()->invitation($query), 200);
            return;
        }

        if ($path === '/compose/thread' && $method === 'POST') {
            $this->composeAndAccountKeyController()->submitComposeThread($query);
            return;
        }

        if ($path === '/compose/reply' && $method === 'POST') {
            $this->composeAndAccountKeyController()->submitComposeReply($query);
            return;
        }

        if (($path === '/account/key/' || $path === '/account/key') && $method === 'POST') {
            $this->composeAndAccountKeyController()->submitAccountKey($query);
            return;
        }

        if ($method === 'POST' && preg_match('#^/profiles/([^/]+)/approve/?$#', $path, $matches) === 1) {
            $this->handleApproveUserSubmit($matches[1], $query);
            return;
        }

        if (($path === '/tools/feature-flags/' || $path === '/tools/feature-flags') && $method === 'POST') {
            $this->toolsPageController()->submitFeatureFlagSubmit($query);
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
                $this->sendXml($this->boardPageController()->rss(), 200);
                return;
            }

            $this->sendHtml(
                $this->boardPageController()->board(
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
            $this->sendHtml($this->lobbyController()->lobby(), 200);
            return;
        }

        if ($path === '/instance/' || $path === '/instance' || $path === '/backup/' || $path === '/backup' || $path === '/tools/backup/' || $path === '/tools/backup') {
            $this->sendHtml($this->instancePageController()->renderBackup(), 200);
            return;
        }

        if ($path === '/tools/sqlite/' || $path === '/tools/sqlite') {
            $this->sendHtml($this->toolsPageController()->sqliteViewer(), 200);
            return;
        }

        if ($path === '/tools/llm-exchanges/' || $path === '/tools/llm-exchanges') {
            $this->llmExchangesController()->list();
            return;
        }

        if (preg_match('#^/tools/llm-exchanges/(\d+)/?$#', $path, $matches) === 1) {
            $this->llmExchangesController()->detail((int) $matches[1]);
            return;
        }

        if ($path === '/downloads/repository.tar.gz') {
            $this->instancePageController()->downloadRepository($method, 'tar.gz');
            return;
        }

        if ($path === '/downloads/repository.zip') {
            $this->instancePageController()->downloadRepository($method, 'zip');
            return;
        }

        if ($path === '/downloads/read_model.sqlite3') {
            $this->instancePageController()->downloadReadModelDatabase($method);
            return;
        }

        if ($path === '/downloads/sqlite_query_catalog.sql') {
            $this->instancePageController()->downloadSqliteQueryCatalog($method);
            return;
        }

        if ($path === '/activity/' || $path === '/activity') {
            if (($query['format'] ?? null) === 'rss') {
                $this->sendXml($this->activityPageController()->rss((string) ($query['view'] ?? 'all')), 200);
                return;
            }

            $this->sendHtml($this->activityPageController()->board((string) ($query['view'] ?? 'all')), 200);
            return;
        }

        if (preg_match('#^/source/current/(.+)$#', $path, $matches) === 1) {
            $this->sourceFileController()->currentFile($matches[1]);
            return;
        }

        if (preg_match('#^/source/blob/([^/]+)/(.+)$#', $path, $matches) === 1) {
            $this->sourceFileController()->blob($matches[1], $matches[2]);
            return;
        }

        if (preg_match('#^/source/commits/([^/]+)$#', $path, $matches) === 1) {
            $this->sourceFileController()->commit($matches[1]);
            return;
        }

        if ($path === '/users/pending/' || $path === '/users/pending') {
            $this->profilePageController()->pendingDirectory($method);
            return;
        }

        if ($path === '/users/' || $path === '/users') {
            $this->sendHtml($this->profilePageController()->directory(), 200);
            return;
        }

        if ($path === '/tags/' || $path === '/tags') {
            $this->sendHtml($this->tagsPageController()->index(), 200);
            return;
        }

        if ($path === '/tools/' || $path === '/tools') {
            $this->sendHtml($this->toolsPageController()->index(), 200);
            return;
        }

        if ($path === '/tools/bookmarklets/' || $path === '/tools/bookmarklets') {
            $this->sendHtml($this->toolsPageController()->bookmarklets(), 200);
            return;
        }

        if ($path === '/tools/codebase/' || $path === '/tools/codebase') {
            $this->sendHtml($this->codebaseStateController()->render(), 200);
            return;
        }

        if ($path === '/tools/feature-flags/' || $path === '/tools/feature-flags') {
            $this->sendHtml($this->toolsPageController()->featureFlags(), 200);
            return;
        }

        if ($path === '/compose/thread') {
            $this->sendHtml($this->composeAndAccountKeyController()->composeThread($query), 200);
            return;
        }

        if ($path === '/compose/reply') {
            $this->sendHtml($this->composeAndAccountKeyController()->composeReply($query), 200);
            return;
        }

        if ($path === '/account/key/' || $path === '/account/key') {
            $this->sendHtml($this->composeAndAccountKeyController()->accountKey(), 200);
            return;
        }

        if ($path === '/api/' || $path === '/api') {
            $this->apiTextController()->index();
            return;
        }

        if ($path === '/api/list_index') {
            $this->apiTextController()->listIndex();
            return;
        }

        if ($path === '/api/get_thread') {
            $this->apiTextController()->getThread((string) ($query['thread_id'] ?? ''));
            return;
        }

        if ($path === '/api/get_post') {
            $this->apiTextController()->getPost((string) ($query['post_id'] ?? ''));
            return;
        }

        if ($path === '/api/get_profile') {
            $this->apiTextController()->getProfile((string) ($query['profile_slug'] ?? ''));
            return;
        }

        if ($path === '/api/forte_activity_page') {
            $this->forteActivityController()->paginationPage($query);
            return;
        }

        if ($path === '/api/forte_commit_detail') {
            $this->forteActivityController()->commitDetail($query);
            return;
        }

        if ($path === '/api/get_forte_content_summary') {
            $this->forteContentAndUserDetailApiController()->contentSummary($query);
            return;
        }

        if ($path === '/api/forte_user_detail') {
            $this->forteContentAndUserDetailApiController()->userDetail($query);
            return;
        }

        if ($path === '/api/get_username_claim_cta') {
            $this->apiTextController()->usernameClaimCta();
            return;
        }

        if ($path === '/api/read_model_status') {
            $this->sendText($this->codebaseStateController()->apiStatus(), 200);
            return;
        }

        if (preg_match('#^/threads/([^/]+)/?$#', $path, $matches) === 1) {
            if (($query['format'] ?? null) === 'rss') {
                $xml = $this->threadAndPostPageController()->threadRss($matches[1]);
                if ($xml === null) {
                    $this->notFound();
                    return;
                }

                $this->sendXml($xml, 200);
                return;
            }

            $html = $this->threadAndPostPageController()->thread($matches[1], (string) ($query['created_post_id'] ?? ''));
            if ($html === null) {
                $this->notFound();
                return;
            }

            $this->sendHtml($html, 200);
            return;
        }

        if (preg_match('#^/forte/?$#', $path) === 1) {
            $this->sendHtml($this->forteBoardController()->board(
                (string) ($query['tag'] ?? ''),
                (string) ($query['sort'] ?? ''),
                (string) ($query['dir'] ?? ''),
                (string) ($query['selected'] ?? ''),
                (string) ($query['created_post_id'] ?? ''),
            ), 200);
            return;
        }

        if ($path === '/forte/users/' || $path === '/forte/users') {
            $this->sendHtml($this->forteUserDirectoryController()->directory(
                (string) ($query['view'] ?? ''),
                (string) ($query['selected'] ?? ''),
                (string) ($query['sort'] ?? ''),
                (string) ($query['dir'] ?? ''),
            ), 200);
            return;
        }

        if ($path === '/forte/activity/' || $path === '/forte/activity') {
            $this->sendHtml($this->forteActivityController()->board(
                (string) ($query['view'] ?? ''),
                (string) ($query['selected'] ?? ''),
                (string) ($query['sort'] ?? ''),
                (string) ($query['dir'] ?? ''),
            ), 200);
            return;
        }

        if (preg_match('#^/forte/profiles/([^/]+)/?$#', $path, $matches) === 1) {
            $html = $this->forteProfileController()->profile($matches[1]);
            if ($html === null) {
                $this->notFound();
                return;
            }

            $this->sendHtml($html, 200);
            return;
        }

        if (preg_match('#^/forte/user/([^/]+)/?$#', $path, $matches) === 1) {
            $html = $this->forteProfileController()->username($matches[1]);
            if ($html === null) {
                $this->notFound();
                return;
            }

            $this->sendHtml($html, 200);
            return;
        }

        if (preg_match('#^/tags/([a-z0-9]+(?:-[a-z0-9]+)*)/?$#', $path, $matches) === 1) {
            $html = $this->tagsPageController()->tag($matches[1]);
            if ($html === null) {
                $this->notFound();
                return;
            }

            $this->sendHtml($html, 200);
            return;
        }

        if (preg_match('#^/posts/([^/]+)/?$#', $path, $matches) === 1) {
            $html = $this->threadAndPostPageController()->post($matches[1]);
            if ($html === null) {
                $this->notFound();
                return;
            }

            $this->sendHtml($html, 200);
            return;
        }

        if (preg_match('#^/profiles/([^/]+)/?$#', $path, $matches) === 1) {
            $html = $this->profilePageController()->profile($matches[1], isset($query['self']), $query);
            if ($html === null) {
                $this->notFound();
                return;
            }

            $this->sendHtml($html, 200);
            return;
        }

        if (preg_match('#^/user/([^/]+)/?$#', $path, $matches) === 1) {
            $html = $this->profilePageController()->username($matches[1]);
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

    /**
     * @return array<string, mixed>
     */
    private function codebaseStateController(): CodebaseStateController
    {
        return new CodebaseStateController(
            $this->routeServices(),
            $this->repositoryRoot,
            $this->databasePath,
            $this->executionLock(),
            $this->staleMarker(),
            $this->commitsCapabilityAvailable(...),
            $this->taskQueueStatus(...),
        );
    }

    private function tagsPageController(): TagsPageController
    {
        return new TagsPageController($this->routeServices());
    }

    private function boardPageController(): BoardPageController
    {
        return new BoardPageController($this->routeServices());
    }

    private function forteBoardController(): ForteBoardController
    {
        return new ForteBoardController(
            $this->routeServices(),
            $this->repositoryRoot,
            $this->resolveViewerProfileFromIdentityHint(...),
        );
    }

    private function forteProfileController(): ForteProfileController
    {
        return new ForteProfileController($this->routeServices());
    }

    private function forteUserDirectoryController(): ForteUserDirectoryController
    {
        return new ForteUserDirectoryController($this->routeServices());
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
            $canApprove ? $this->identityScripts(['/assets/pending_approvals.js']) : [],
        );
    }

    private function profilePageController(): ProfilePageController
    {
        return new ProfilePageController(
            $this->routeServices(),
            $this->resolveViewerProfileFromIdentityHint(...),
            $this->renderProfilePage(...),
        );
    }

    private function instancePageController(): InstancePageController
    {
        return new InstancePageController(
            $this->routeServices(),
            $this->repositoryRoot,
            $this->databasePath,
            $this->projectRoot,
            $this->fetchActivity(...),
        );
    }

    private function renderAbout(): string
    {
        return (new AboutPageController($this->renderPageTemplate(...)))->render();
    }

    private function activityPageController(): ActivityPageController
    {
        return new ActivityPageController($this->routeServices());
    }

    private function postWorkflowApiController(): PostWorkflowApiController
    {
        return new PostWorkflowApiController(
            $this->routeServices(),
            $this->fetchPost(...),
            $this->fetchThreadPosts(...),
            $this->llmExchangeRecorder(...),
            $this->resolveViewerProfileFromIdentityHint(...),
            $this->fetchThread(...),
        );
    }

    private function postWorkflowService(): \ForumRewrite\Agent\PostWorkflowService
    {
        return $this->routeServices()->postWorkflowService(
            $this->fetchPost(...),
            $this->fetchThreadPosts(...),
            $this->llmExchangeRecorder(...),
        );
    }

    private function threadAndPostPageController(): ThreadAndPostPageController
    {
        return new ThreadAndPostPageController(
            $this->routeServices(),
            $this->repositoryRoot,
            $this->fetchThread(...),
            $this->fetchThreadPosts(...),
            $this->fetchPost(...),
            $this->displayThreadTitle(...),
            $this->resolveViewerProfileFromIdentityHint(...),
            $this->llmExchangeRecorder(...),
            $this->viewerCanInspectLlmExchanges(...),
            $this->fetchLlmExchangesForPosts(...),
        );
    }

    private function sourceFileController(): SourceFileController
    {
        return new SourceFileController(
            $this->routeServices(),
            $this->repositoryRoot,
            $this->sourceCommitDetails(...),
        );
    }

    private function toolsPageController(): ToolsPageController
    {
        return new ToolsPageController(
            $this->routeServices(),
            $this->featureFlags(),
            $this->resolveViewerProfileFromIdentityHint(...),
            $this->invalidateFeatureFlagsCache(...),
        );
    }

    private function invalidateFeatureFlagsCache(): void
    {
        $this->featureFlags = null;
    }

    private function llmExchangesController(): LlmExchangesController
    {
        return new LlmExchangesController(
            $this->routeServices(),
            $this->viewerCanInspectLlmExchanges(...),
            $this->llmExchangeStore(...),
        );
    }

    private function viewerCanInspectLlmExchanges(): bool
    {
        $viewerProfile = $this->resolveViewerProfileFromIdentityHint();

        return $this->featureFlags()->isEnabled(FeatureFlagRegistry::LLM_CONVERSATION_UI_ENABLED)
            && $viewerProfile !== null
            && ((int) ($viewerProfile['is_approved'] ?? 0)) === 1;
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
        return $this->routeServices()->renderPageTemplate($pageTemplate, $pageData, $title, $activeSection, $scriptPaths);
    }

    /**
     * The framework-level dependencies (DB access, response senders, page
     * rendering) shared by every route handler, bundled so route-group
     * controllers extracted out of this class can depend on this instead of
     * on Application itself. See src/ForumRewrite/Http/RouteServices.php.
     * Built once per request; Application's own send*()/render*()/pdo()
     * methods now delegate here too, so there's one implementation, not two
     * that can drift apart.
     */
    private function routeServices(): RouteServices
    {
        if ($this->routeServices === null) {
            $this->routeServices = new RouteServices(
                $this->databasePath,
                $this->renderer(),
                $this->routeSource,
                $this->approvedMembersOnlyEnabled(),
                $this->authenticatedViewerProfile(...),
                $this->repositoryRoot,
                $this->projectRoot,
                $this->artifactRoot,
                $this->staticHtmlRoot,
                $this->featureFlags(),
            );
        }

        return $this->routeServices;
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

    private function appVersion(): string
    {
        if ($this->appVersion !== null) {
            return $this->appVersion;
        }

        $this->appVersion = ReadModelMetadata::repositoryHead($this->repositoryRoot);

        return $this->appVersion;
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

    /**
     * @return array<string, mixed>|null
     */
    private function fetchProfileBySlug(string $slug): ?array
    {
        return ProfileRepository::bySlug($this->pdo(), $slug);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchProfileByIdentityId(string $identityId): ?array
    {
        return ProfileRepository::byIdentityId($this->pdo(), $identityId);
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
     * @param array<string, mixed> $thread
     * @return array<string, mixed>
     */
    private function hydrateThreadRow(array $thread): array
    {
        return ThreadRowSupport::hydrateThreadRow($thread);
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
     * Only redirects straight to the Lobby when this session has already
     * confirmed the viewer's identity (authenticated_identity_id is set)
     * and confirmed they're not an approved member - a fact we actually
     * know. A session that only carries the weaker lobby_identity_id signal
     * (currently only set by handleClearIdentity()'s downgrade-not-forget
     * behavior) hasn't been freshly verified either way, so it falls
     * through to shouldRenderAuthenticationResume() instead, which gives
     * the browser's saved key a chance to silently re-authenticate before
     * assuming the viewer needs to register. Without this distinction, an
     * approved member whose session was downgraded to lobby-only got
     * bounced to a hard "you need to be registered and approved" redirect
     * even when their browser key could resolve it immediately - which is
     * exactly what already happens silently when they click a nav link
     * instead, since auth_navigation.js's in-page guard performs this same
     * resume attempt before every same-origin navigation. (A fully empty
     * session - no signal at all - already fell through to the resume flow
     * correctly before this change; only the lobby_identity_id-only case
     * was affected.)
     *
     * @param array<string, mixed> $query
     */
    private function membersOnlyLobbyRedirect(string $method, string $path, array $query): bool
    {
        $viewerProfile = $this->authenticatedViewerProfile();

        return $viewerProfile !== null
            && ((int) ($viewerProfile['is_approved'] ?? 0)) !== 1
            && $method === 'GET' && in_array($path, ['/', '/threads', '/threads/'], true)
            && $query === [];
    }

    /** @param array<string, mixed> $query */
    private function shouldRenderAuthenticationResume(string $method, string $path, array $query): bool
    {
        if ($method !== 'GET'
            || $this->authenticatedViewerProfile() !== null
            || str_starts_with($path, '/api')
            || str_starts_with($path, '/downloads/')
            || (($query['format'] ?? null) === 'rss')
        ) {
            return false;
        }

        return true;
    }

    /**
     * Temporary diagnostic instrumentation for tracking down what session
     * state real visitors actually land in when the Lobby gate doesn't
     * treat them as a confirmed approved member. Appends one JSON line per
     * gate decision to state/logs/lobby_gate.log (gitignored), rotating to
     * lobby_gate.log.1 every LOBBY_GATE_LOG_ROTATE_LINES lines so it can't
     * grow unbounded while still keeping one full prior generation instead
     * of silently discarding old entries. Safe to remove once the open
     * question in docs/plans/ (why a real visitor's session ends up here
     * without an explicit /api/clear_identity call) is resolved.
     */
    private function logLobbyGateDiagnostics(string $decision, string $method, string $path): void
    {
        $logPath = $this->projectRoot . '/state/logs/lobby_gate.log';
        $logDir = dirname($logPath);
        if (!is_dir($logDir) && !@mkdir($logDir, 0777, true) && !is_dir($logDir)) {
            return;
        }

        if (is_file($logPath) && $this->countLines($logPath) >= self::LOBBY_GATE_LOG_ROTATE_LINES) {
            @rename($logPath, $logPath . '.1');
        }

        $authenticatedIdentityId = strtolower(trim((string) ($_SESSION['authenticated_identity_id'] ?? '')));
        $lobbyIdentityId = strtolower(trim((string) ($_SESSION['lobby_identity_id'] ?? '')));
        $identityHint = strtolower(trim((string) ($_COOKIE['identity_hint'] ?? '')));
        $viewerProfile = $authenticatedIdentityId !== '' ? $this->fetchProfileByIdentityId($authenticatedIdentityId) : null;

        $line = [
            'time' => gmdate('Y-m-d\TH:i:s\Z'),
            'decision' => $decision,
            'method' => $method,
            'path' => $path,
            'session_cookie_present' => $this->hasViewerSessionCookie(),
            'has_authenticated_identity_id' => $authenticatedIdentityId !== '',
            'authenticated_identity_suffix' => $authenticatedIdentityId === '' ? null : substr($authenticatedIdentityId, -8),
            'authenticated_identity_is_approved' => $viewerProfile === null ? null : ((int) ($viewerProfile['is_approved'] ?? 0)) === 1,
            'has_lobby_identity_id' => $lobbyIdentityId !== '',
            'lobby_identity_suffix' => $lobbyIdentityId === '' ? null : substr($lobbyIdentityId, -8),
            'identity_hint' => $identityHint === '' ? null : $identityHint,
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 160),
        ];

        @file_put_contents($logPath, json_encode($line, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
    }

    private function countLines(string $path): int
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return 0;
        }

        $lines = 0;
        while (!feof($handle)) {
            $lines += substr_count((string) fread($handle, 1024 * 1024), "\n");
        }
        fclose($handle);

        return $lines;
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

    private function lobbyController(): LobbyController
    {
        return new LobbyController(
            $this->routeServices(),
            $this->lobbyViewerProfile(...),
            $this->authenticatedViewerProfile(...),
        );
    }

    private function composeAndAccountKeyController(): ComposeAndAccountKeyController
    {
        return new ComposeAndAccountKeyController(
            $this->routeServices(),
            $this->fetchPost(...),
            $this->resolveViewerProfileFromIdentityHint(...),
        );
    }

    private function apiTextController(): ApiTextController
    {
        return new ApiTextController(
            $this->routeServices(),
            $this->fetchThread(...),
            $this->fetchThreadPosts(...),
            $this->fetchPost(...),
            $this->fetchProfileBySlug(...),
            $this->displayThreadTitle(...),
        );
    }

    private function tagApiController(): TagApiController
    {
        return new TagApiController(
            $this->routeServices(),
            $this->resolveViewerProfileFromIdentityHint(...),
        );
    }

    private function identityHintController(): IdentityHintController
    {
        return new IdentityHintController($this->routeServices());
    }

    private function authApiController(): AuthApiController
    {
        return new AuthApiController(
            $this->routeServices(),
            $this->authenticatedViewerProfile(...),
        );
    }

    private function writePostAndIdentityApiController(): WritePostAndIdentityApiController
    {
        return new WritePostAndIdentityApiController($this->routeServices());
    }

    private function identityApprovalAndInvitationApiController(): IdentityApprovalAndInvitationApiController
    {
        return new IdentityApprovalAndInvitationApiController(
            $this->routeServices(),
            $this->authenticatedViewerProfile(...),
            $this->fetchProfileBySlug(...),
            $this->resolveViewerProfileFromIdentityHint(...),
        );
    }

    private function forteContentAndUserDetailApiController(): ForteContentAndUserDetailApiController
    {
        return new ForteContentAndUserDetailApiController(
            $this->routeServices(),
            $this->fetchPost(...),
        );
    }

    private function forteActivityController(): ForteActivityController
    {
        return new ForteActivityController(
            $this->routeServices(),
            $this->commitsCapabilityAvailable(...),
            $this->enqueueReadModelRecovery(...),
            $this->sendReadModelCapabilityUnavailable(...),
        );
    }

    /**
     * @param array<string, mixed>|null $viewerProfile
     */
    private function viewerCanUseCodexHandoff(?array $viewerProfile): bool
    {
        return $this->postWorkflowService()->viewerCanUseCodexHandoff($viewerProfile);
    }

    /**
     * @param array{sort_value: string, id: int}|null $afterCursor
     * @return array{items: array<int, array<string, mixed>>, has_more: bool}
     */
    private function fetchActivity(string $view, string $sortColumn, string $sortDirection, ?array $afterCursor = null): array
    {
        return $this->routeServices()->activityService()->fetchActivity($view, $sortColumn, $sortDirection, $afterCursor);
    }

    private function sourcePathHref(string $sourcePath, string $sourceCommitSha): ?string
    {
        return $this->routeServices()->activityService()->sourcePathHref($sourcePath, $sourceCommitSha);
    }

    private function sourceCommitHref(string $sourceCommitSha): ?string
    {
        return $this->routeServices()->activityService()->sourceCommitHref($sourceCommitSha);
    }

    /**
     * @return array{path:string,href:string}
     */
    private function sourceSignatureLink(string $sourcePath): array
    {
        return $this->routeServices()->activityService()->sourceSignatureLink($sourcePath);
    }

    private function sourceSignatureStatus(
        string $sourcePath,
        string $authorIdentityId,
        string $sourceSignaturePath,
        bool $authorIdentityIdIsCanonical = false
    ): string
    {
        return $this->routeServices()->activityService()->sourceSignatureStatus($sourcePath, $authorIdentityId, $sourceSignaturePath, $authorIdentityIdIsCanonical);
    }

    private function encodeSourcePathForUrl(string $sourcePath): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $sourcePath)));
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

    /**
     * @return list<string>
     */
    private function noStoreHeaders(): array
    {
        return $this->routeServices()->noStoreHeaders();
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
        return $this->routeServices()->mergeResultTimings($result, $timings, $totalStartedAt);
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

    private function pdo(): PDO
    {
        return $this->routeServices()->pdo();
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
        return ReadModelMetadata::readMetadata($pdo);
    }

    private function notFound(): void
    {
        $this->routeServices()->notFound();
    }

    private function sendHtml(string $html, int $statusCode, array $headers = []): void
    {
        $this->routeServices()->sendHtml($html, $statusCode, $headers);
    }

    private function sendText(string $text, int $statusCode, array $headers = []): void
    {
        $this->routeServices()->sendText($text, $statusCode, $headers);
    }

    private function currentSourcePathExists(string $relativePath): bool
    {
        return SourcePathValidator::currentPathExists($this->repositoryRoot, $relativePath);
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

        $files = $this->routeServices()->activityService()->sourceCommitFiles($commitSha);
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
     * @return list<array{status:string,path:string,previous_path:string,role:string,href:string,signature_signer_identity:string,signature_public_key_path:string,signature_public_key_href:string,signature_key_status:string}>|null
     */
    private function activityCommitManifest(string $commitSha): ?array
    {
        return $this->routeServices()->activityService()->activityCommitManifest($commitSha);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sendJson(array $payload, int $statusCode, array $headers = []): void
    {
        $this->routeServices()->sendJson($payload, $statusCode, $headers);
    }

    private function sendXml(string $xml, int $statusCode): void
    {
        $this->routeServices()->sendXml($xml, $statusCode);
    }

    private function sendRedirect(string $location, string $message, int $statusCode = 303, array $headers = [], string $activeSection = 'compose'): void
    {
        $this->routeServices()->sendRedirect($location, $message, $statusCode, $headers, $activeSection);
    }

    /**
     * @param array<string, mixed> $result
     * @return list<string>
     */
    private function serverTimingHeaders(array $result): array
    {
        return $this->routeServices()->serverTimingHeaders($result);
    }

    private function renderMessagePage(string $title, string $heading, string $message, string $activeSection): string
    {
        return $this->routeServices()->renderMessagePage($title, $heading, $message, $activeSection);
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
