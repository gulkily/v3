<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Agent\SqliteAgentReplyGenerationStore;
use ForumRewrite\Analysis\SqlitePostAnalysisStore;
use ForumRewrite\Activity\ActivityService;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Host\AssetFingerprint;
use ForumRewrite\Host\FrontController;
use ForumRewrite\Host\StaticArtifactBuilder;
use ForumRewrite\Host\StaticArtifactReleasePublisher;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\Http\InstancePageController;
use ForumRewrite\Http\RouteServices;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\ExecutionLock;
use ForumRewrite\Support\LocalRepositoryBootstrap;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;
use ForumRewrite\View\TemplateRenderer;
use ForumRewrite\Write\StaticArtifactInvalidator;

final class LocalAppSmokeTest
{
    private string $databasePath;
    private string $repositoryRoot;

    public function __construct()
    {
        $this->repositoryRoot = __DIR__ . '/fixtures/parity_minimal_v1';
        $this->databasePath = sys_get_temp_dir() . '/forum-rewrite-smoke-' . bin2hex(random_bytes(6)) . '.sqlite3';
    }

    public function testRebuildCommandCreatesDatabase(): void
    {
        @unlink($this->databasePath);
        $command = sprintf(
            'php %s %s %s',
            escapeshellarg(__DIR__ . '/../scripts/rebuild_read_model.php'),
            escapeshellarg($this->repositoryRoot),
            escapeshellarg($this->databasePath),
        );
        exec($command, $output, $exitCode);
        $text = implode("\n", $output);

        assertSame(0, $exitCode);
        assertTrue(is_file($this->databasePath));
        assertStringContains('Starting read-model rebuild.', $text);
        assertStringContains('[1/3] Scanning source record counts...', $text);
        assertStringContains('[2/3] Building and validating a read-model candidate...', $text);
        assertStringContains('[2/3] Read model: resolving legacy creation times for ', $text);
        assertStringContains('[2/3] Read model: resolving source commits for ', $text);
        assertStringContains('[2/3] Read model: parsing post records (0/', $text);
        assertStringContains('[3/3] Waiting for the exclusive read-model lock...', $text);
        assertStringContains('[3/3] Read model promoted.', $text);
    }

    public function testRebuildCommandExplainsSqliteSidecarPromotionFailure(): void
    {
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-rebuild-sidecar-' . bin2hex(random_bytes(6)) . '.sqlite3';
        try {
            (new ReadModelBuilder($this->repositoryRoot, $databasePath, new CanonicalRecordRepository($this->repositoryRoot)))->rebuild();
            file_put_contents($databasePath . '-journal', 'test journal');

            $command = sprintf(
                'php %s %s %s 2>&1',
                escapeshellarg(__DIR__ . '/../scripts/rebuild_read_model.php'),
                escapeshellarg($this->repositoryRoot),
                escapeshellarg($databasePath),
            );
            exec($command, $output, $exitCode);
            $text = implode("\n", $output);

            assertSame(1, $exitCode);
            assertStringContains('Read-model rebuild failed while promoting the read-model candidate', $text);
            assertStringContains('A SQLite sidecar prevents safe read-model promotion', $text);
            assertStringContains('Do not delete the sidecar manually', $text);
            assertStringNotContains('PHP Fatal error', $text);
        } finally {
            @unlink($databasePath);
            @unlink($databasePath . '-journal');
        }
    }

    public function testRebuildDiagnosisAndRecoveryArchiveSQLiteSidecarsBeforeRebuilding(): void
    {
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-rebuild-recovery-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $recoveryDirectory = null;
        try {
            (new ReadModelBuilder($this->repositoryRoot, $databasePath, new CanonicalRecordRepository($this->repositoryRoot)))->rebuild();
            file_put_contents($databasePath . '-journal', 'test journal');

            $diagnoseCommand = sprintf(
                '%s rebuild diagnose %s %s 2>&1',
                escapeshellarg(__DIR__ . '/../v3'),
                escapeshellarg($this->repositoryRoot),
                escapeshellarg($databasePath),
            );
            exec($diagnoseCommand, $diagnoseOutput, $diagnoseExitCode);
            $diagnoseText = implode("\n", $diagnoseOutput);

            assertSame(0, $diagnoseExitCode);
            assertStringContains('Read-model SQLite diagnosis', $diagnoseText);
            assertStringContains('SQLite sidecars:', $diagnoseText);
            assertStringContains('Next action: ./v3 rebuild recover --confirm', $diagnoseText);

            $recoverCommand = sprintf(
                '%s rebuild recover --confirm %s %s 2>&1',
                escapeshellarg(__DIR__ . '/../v3'),
                escapeshellarg($this->repositoryRoot),
                escapeshellarg($databasePath),
            );
            exec($recoverCommand, $recoverOutput, $recoverExitCode);
            $recoverText = implode("\n", $recoverOutput);
            foreach (explode("\n", $recoverText) as $line) {
                $prefix = 'Archived the live read-model database and sidecars: ';
                if (str_starts_with($line, $prefix)) {
                    $recoveryDirectory = substr($line, strlen($prefix));
                    break;
                }
            }

            assertSame(0, $recoverExitCode, $recoverText);
            assertTrue($recoveryDirectory !== null && is_dir($recoveryDirectory));
            assertTrue(is_file($recoveryDirectory . '/snapshot/' . basename($databasePath)));
            assertTrue(is_file($recoveryDirectory . '/retired/' . basename($databasePath . '-journal')));
            assertTrue(is_file($databasePath));
            assertTrue(!is_file($databasePath . '-journal'));
        } finally {
            @unlink($databasePath);
            @unlink($databasePath . '-journal');
            if ($recoveryDirectory !== null) {
                $this->deleteTree($recoveryDirectory);
            }
        }
    }

    public function testBuildStaticCommandReportsProgressAndArtifactSummary(): void
    {
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-build-static-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $artifactRoot = sys_get_temp_dir() . '/forum-rewrite-build-static-artifacts-' . bin2hex(random_bytes(6));
        $command = sprintf(
            'php %s %s %s %s 2>&1',
            escapeshellarg(__DIR__ . '/../scripts/build_static_artifacts.php'),
            escapeshellarg($this->repositoryRoot),
            escapeshellarg($databasePath),
            escapeshellarg($artifactRoot),
        );

        try {
            exec($command, $output, $exitCode);
            $text = implode("\n", $output);

            assertSame(0, $exitCode, $text);
            assertStringContains('Starting static HTML release build', $text);
            assertStringContains("Repository: {$this->repositoryRoot}", $text);
            assertStringContains("Database: {$databasePath}", $text);
            assertStringContains("Static artifact root: {$artifactRoot}", $text);
            assertStringContains('[1/4] Building and validating a read-model candidate...', $text);
            assertStringContains('[1/4] Read model: index posts...', $text);
            assertStringContains('[1/4] Read model: parsing post records (0/', $text);
            assertStringContains('[1/4] Read-model candidate is ready.', $text);
            assertStringContains('[2/4] Rendering static HTML and fingerprinted assets...', $text);
            assertStringContains('[2/4] Fingerprinting and copying assets referenced by rendered pages...', $text);
            assertStringContains('[2/4] Rendering shared pages (1/20): /.', $text);
            assertStringContains('[2/4] Rendering thread pages (0/', $text);
            assertStringContains('[2/4] Static release is ready:', $text);
            assertStringContains('Static artifacts:', $text);
            assertStringContains('[3/4] Promoting the read model...', $text);
            assertStringContains('[3/4] Read model promoted.', $text);
            assertStringContains('[4/4] Activating the static release...', $text);
            assertStringContains('[4/4] Static release activated.', $text);
            assertStringContains('Built and activated static HTML release', $text);
            assertStringContains('Elapsed:', $text);
            assertOrdered($text, '[1/4] Building', '[2/4] Rendering');
            assertOrdered($text, '[2/4] Rendering', '[3/4] Promoting');
            assertOrdered($text, '[3/4] Promoting', '[4/4] Activating');
        } finally {
            @unlink($databasePath);
            $this->deleteTree($artifactRoot);
        }
    }

    public function testBuildStaticHelpDoesNotTreatOptionsAsRepositoryPaths(): void
    {
        $command = escapeshellarg(__DIR__ . '/../v3') . ' build-static --help 2>&1';
        exec($command, $output, $exitCode);
        $text = implode("\n", $output);

        assertSame(0, $exitCode, $text);
        assertStringContains('Usage:', $text);
        assertStringContains('./v3 build-static --shared-only', $text);
        assertStringNotContains('Starting static HTML release build', $text);
        assertStringNotContains('Repository: --help', $text);
    }

    public function testSharedStaticRefreshCommandKeepsDetailArtifactsWithoutRebuildingTheReadModel(): void
    {
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-static-shared-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $artifactRoot = sys_get_temp_dir() . '/forum-rewrite-static-shared-artifacts-' . bin2hex(random_bytes(6));

        try {
            (new ReadModelBuilder(
                $this->repositoryRoot,
                $databasePath,
                new CanonicalRecordRepository($this->repositoryRoot),
            ))->rebuild();
            $publisher = new StaticArtifactReleasePublisher(dirname(__DIR__), $this->repositoryRoot, $artifactRoot);
            $initialRelease = $publisher->build($databasePath);
            $publisher->activate($initialRelease);
            $detailArtifact = $artifactRoot . '/current/threads/root-001.html';
            $detailContents = (string) file_get_contents($detailArtifact);

            $command = sprintf(
                'php %s %s %s %s 2>&1',
                escapeshellarg(__DIR__ . '/../scripts/refresh_static_shared_artifacts.php'),
                escapeshellarg($this->repositoryRoot),
                escapeshellarg($databasePath),
                escapeshellarg($artifactRoot),
            );
            exec($command, $output, $exitCode);
            $text = implode("\n", $output);

            assertSame(0, $exitCode, $text);
            assertStringContains('Starting shared static release refresh', $text);
            assertStringContains('does not rebuild the read model', $text);
            assertStringContains('[1/2] Rendering shared pages (10/20): /tags/.', $text);
            assertStringContains('[2/2] Shared static release activated.', $text);
            assertStringNotContains('Rendering thread pages', $text);
            assertStringNotContains('Rendering post pages', $text);
            assertStringNotContains('Read model: index posts', $text);
            assertTrue(is_link($artifactRoot . '/current'));
            assertSame($detailContents, (string) file_get_contents($artifactRoot . '/current/threads/root-001.html'));
            assertStringContains('route-source: static-html', (string) file_get_contents($artifactRoot . '/current/index.html'));
            assertSame((string) file_get_contents(dirname(__DIR__) . '/public/service_worker.js'), (string) file_get_contents($artifactRoot . '/current/service_worker.js'));
        } finally {
            @unlink($databasePath);
            $this->deleteTree($artifactRoot);
        }
    }

    public function testApprovedPrivateSessionCanViewOwnProfileAndBoard(): void
    {
        $previousFlag = getenv('FORUM_APPROVED_MEMBERS_ONLY');
        $previousSession = $_SESSION ?? null;
        putenv('FORUM_APPROVED_MEMBERS_ONLY=true');
        $sessionId = 'private-approved-' . bin2hex(random_bytes(8));

        try {
            session_id($sessionId);
            session_start();
            $_SESSION['authenticated_identity_id'] = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
            session_write_close();

            $databasePath = sys_get_temp_dir() . '/forum-rewrite-private-session-' . bin2hex(random_bytes(6)) . '.sqlite3';
            $application = new Application(dirname(__DIR__), $this->repositoryRoot, $databasePath);
            $profile = $this->render($application, '/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954');
            $board = $this->render($application, '/');
            $authStatus = $this->render($application, '/api/auth_status');

            assertStringContains('This is your profile.', $profile);
            assertStringContains('Board', $board);
            assertStringContains('/assets/auth_navigation.', $board);
            assertSame("status=authenticated\n", $authStatus);
        } finally {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            session_id('');
            if ($previousSession === null) {
                unset($_SESSION);
            } else {
                $_SESSION = $previousSession;
            }
            @unlink($databasePath ?? '');
            if ($previousFlag === false) {
                putenv('FORUM_APPROVED_MEMBERS_ONLY');
            } else {
                putenv('FORUM_APPROVED_MEMBERS_ONLY=' . $previousFlag);
            }
        }
    }

    public function testApprovedPublicSessionIsResumedForBoardAndInvite(): void
    {
        $script = <<<'PHP'
require $argv[1] . '/autoload.php';

use ForumRewrite\Application;

$sessionId = 'public-approved-' . bin2hex(random_bytes(8));
$databasePath = sys_get_temp_dir() . '/forum-rewrite-public-session-' . bin2hex(random_bytes(6)) . '.sqlite3';

try {
    session_id($sessionId);
    session_start();
    $_SESSION['authenticated_identity_id'] = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
    session_write_close();
    session_id('');
    $_COOKIE[session_name()] = $sessionId;
    // The CLI test process does not populate the session module from $_COOKIE,
    // so mirror the web SAPI's request initialization before handling the page.
    session_id($sessionId);

    $application = new Application($argv[1], $argv[2], $databasePath);
    ob_start();
    $application->handle('GET', '/');
    $board = (string) ob_get_clean();
    ob_start();
    $application->handle('GET', '/invites/');
    $invites = (string) ob_get_clean();
    ob_start();
    $application->handle('GET', '/api/auth_status');
    $authStatus = (string) ob_get_clean();
    ob_start();
    $application->handle('POST', '/api/prepare_invitation');
    $invitationPreparation = (string) ob_get_clean();

    if (!str_contains($board, 'href="/invites/" data-invite-navigation>Invite</a>')
        || !str_contains($invites, '<h1>Generate invite</h1>')
        || $authStatus !== "status=authenticated\n"
        || str_contains($invitationPreparation, 'Only authenticated approved users can issue invitations.')) {
        throw new RuntimeException('Public session was not resumed for Board and Invite.');
    }
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    @unlink($databasePath);
}
PHP;
        $command = sprintf(
            'php -r %s %s %s',
            escapeshellarg($script),
            escapeshellarg(dirname(__DIR__)),
            escapeshellarg($this->repositoryRoot),
        );
        exec($command . ' 2>&1', $output, $exitCode);

        assertSame(0, $exitCode, implode("\n", $output));
    }

    public function testAnonymousPublicBoardDoesNotStartViewerSession(): void
    {
        $previousCookie = $_COOKIE;
        $previousSession = $_SESSION ?? null;
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-public-anonymous-' . bin2hex(random_bytes(6)) . '.sqlite3';

        try {
            $_COOKIE = [];
            session_id('');
            $application = new Application(dirname(__DIR__), $this->repositoryRoot, $databasePath);

            $board = $this->render($application, '/');

            assertSame(PHP_SESSION_NONE, session_status());
            assertStringNotContains('href="/invites/" data-invite-navigation>Invite</a>', $board);
            assertStringContains('data-public-auth-resume="true"', $board);
            assertFingerprintedAsset($board, 'private_site_auth.js');
            assertTrue(
                strpos($board, '/assets/theme_toggle.') < strpos($board, '/assets/openpgp_loader.'),
                'Theme controls must initialize before the OpenPGP loader.'
            );
        } finally {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            session_id('');
            $_COOKIE = $previousCookie;
            if ($previousSession === null) {
                unset($_SESSION);
            } else {
                $_SESSION = $previousSession;
            }
            @unlink($databasePath);
        }
    }

    public function testPublicNotFoundPageDoesNotAttemptIdentityResume(): void
    {
        $previousCookie = $_COOKIE;
        $previousSession = $_SESSION ?? null;
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-public-not-found-' . bin2hex(random_bytes(6)) . '.sqlite3';

        try {
            $_COOKIE = [];
            session_id('');
            $application = new Application(dirname(__DIR__), $this->repositoryRoot, $databasePath);

            $notFound = $this->render($application, '/missing-route');

            assertStringContains('<h1>Not Found</h1>', $notFound);
            assertStringNotContains('data-public-auth-resume="true"', $notFound);
            assertStringNotContains('private_site_auth.', $notFound);
        } finally {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            session_id('');
            $_COOKIE = $previousCookie;
            if ($previousSession === null) {
                unset($_SESSION);
            } else {
                $_SESSION = $previousSession;
            }
            @unlink($databasePath);
        }
    }

    public function testPrivateViewerSessionCookiePersistsAcrossBrowserRestart(): void
    {
        $previousFlag = getenv('FORUM_APPROVED_MEMBERS_ONLY');
        $previousCookieParameters = session_get_cookie_params();
        putenv('FORUM_APPROVED_MEMBERS_ONLY=true');
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-private-cookie-' . bin2hex(random_bytes(6)) . '.sqlite3';

        try {
            $application = new Application(dirname(__DIR__), $this->repositoryRoot, $databasePath);
            $this->render($application, '/api/auth_status');

            assertSame(34560000, session_get_cookie_params()['lifetime']);
        } finally {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            session_id('');
            session_set_cookie_params($previousCookieParameters);
            @unlink($databasePath);
            if ($previousFlag === false) {
                putenv('FORUM_APPROVED_MEMBERS_ONLY');
            } else {
                putenv('FORUM_APPROVED_MEMBERS_ONLY=' . $previousFlag);
            }
        }
    }

    public function testInviteNavigationHighlightsOnlyInvite(): void
    {
        $renderer = new \ForumRewrite\View\TemplateRenderer(dirname(__DIR__) . '/templates');
        $html = $renderer->renderLayout(
            'Invite',
            '<main></main>',
            'invite',
            viewerProfile: ['is_approved' => 1, '_authenticated_identity' => true],
        );

        assertStringContains('class="nav-link" href="/account/key/">Account</a>', $html);
        assertStringContains('class="nav-link is-active" href="/invites/" data-invite-navigation>Invite</a>', $html);
    }

    public function testClearingIdentityRevokesApprovedPrivateSession(): void
    {
        $previousFlag = getenv('FORUM_APPROVED_MEMBERS_ONLY');
        $previousCookie = $_COOKIE;
        $previousSession = $_SESSION ?? null;
        putenv('FORUM_APPROVED_MEMBERS_ONLY=true');
        $sessionId = 'private-clear-identity-' . bin2hex(random_bytes(8));

        try {
            session_id($sessionId);
            session_start();
            $_SESSION['authenticated_identity_id'] = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
            session_write_close();

            $databasePath = sys_get_temp_dir() . '/forum-rewrite-private-clear-identity-' . bin2hex(random_bytes(6)) . '.sqlite3';
            $application = new Application(dirname(__DIR__), $this->repositoryRoot, $databasePath);

            assertStringContains('class="nav-link is-active" href="/">Board</a>', $this->render($application, '/'));

            $response = $this->renderMethod($application, 'POST', '/api/clear_identity');
            $boardAfterClear = $this->render($application, '/');
            $authStatusAfterClear = $this->render($application, '/api/auth_status');
            $aboutAfterClear = $this->render($application, '/about/');
            $ownProfileAfterClear = $this->render(
                $application,
                '/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954',
            );
            $lobbyAfterClear = $this->render($application, '/lobby/');

            assertStringContains("status=ok\n", $response);
            assertSame("status=unauthenticated\n", $authStatusAfterClear);
            assertSame('guest', $_COOKIE['identity_hint'] ?? null);
            // A cleared-but-not-reauthenticated session hasn't been confirmed
            // either way (no authenticated_identity_id this session), so
            // protected GET pages give the browser key a chance to silently
            // resume before assuming the viewer needs to register - the same
            // treatment any other stale/unconfirmed session gets.
            assertStringContains('<h1>Reconnecting</h1>', $boardAfterClear);
            assertStringContains('data-auth-return-to="/"', $boardAfterClear);
            assertStringContains('<h1>Reconnecting</h1>', $aboutAfterClear);
            assertStringContains('data-auth-return-to="/about/"', $aboutAfterClear);
            assertStringContains('This is your profile.', $ownProfileAfterClear);
            assertStringContains('Your identity is recognized in the lobby, but member access is cleared.', $lobbyAfterClear);
            assertStringContains(
                'class="nav-link" href="/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954">Profile</a>',
                $lobbyAfterClear,
            );
            assertStringNotContains('href="/">Board</a>', $lobbyAfterClear);
        } finally {
            $_COOKIE = $previousCookie;
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            session_id('');
            if ($previousSession === null) {
                unset($_SESSION);
            } else {
                $_SESSION = $previousSession;
            }
            @unlink($databasePath ?? '');
            if ($previousFlag === false) {
                putenv('FORUM_APPROVED_MEMBERS_ONLY');
            } else {
                putenv('FORUM_APPROVED_MEMBERS_ONLY=' . $previousFlag);
            }
        }
    }

    public function testPrivateAuthenticationEndpointReachesSignatureVerifier(): void
    {
        $previousFlag = getenv('FORUM_APPROVED_MEMBERS_ONLY');
        $previousPost = $_POST;
        $previousSession = $_SESSION ?? null;
        putenv('FORUM_APPROVED_MEMBERS_ONLY=true');
        $sessionId = 'private-auth-verifier-' . bin2hex(random_bytes(8));
        $challenge = bin2hex(random_bytes(32));

        try {
            session_id($sessionId);
            session_start();
            $_SESSION['forum_auth_challenges'] = [
                bin2hex(random_bytes(32)) => time() + 300,
                $challenge => time() + 300,
            ];
            session_write_close();

            $_POST = [
                'identity_id' => 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954',
                'challenge' => $challenge,
                'detached_signature' => 'invalid detached signature',
            ];
            $databasePath = sys_get_temp_dir() . '/forum-rewrite-private-auth-' . bin2hex(random_bytes(6)) . '.sqlite3';
            $application = new Application(dirname(__DIR__), $this->repositoryRoot, $databasePath);

            $response = $this->renderMethod($application, 'POST', '/api/authenticate_identity');

            assertStringContains('error=Identity signature verification failed.', $response);
        } finally {
            $_POST = $previousPost;
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            session_id('');
            if ($previousSession === null) {
                unset($_SESSION);
            } else {
                $_SESSION = $previousSession;
            }
            @unlink($databasePath ?? '');
            if ($previousFlag === false) {
                putenv('FORUM_APPROVED_MEMBERS_ONLY');
            } else {
                putenv('FORUM_APPROVED_MEMBERS_ONLY=' . $previousFlag);
            }
        }
    }

    public function testPrivateLobbyOnlyExposesLobbyAccountAndAuthenticationSurfaces(): void
    {
        $previousFlag = getenv('FORUM_APPROVED_MEMBERS_ONLY');
        putenv('FORUM_APPROVED_MEMBERS_ONLY=true');
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-private-lobby-' . bin2hex(random_bytes(6)) . '.sqlite3';

        try {
            $application = new Application(dirname(__DIR__), $this->repositoryRoot, $databasePath);
            $lobby = $this->render($application, '/lobby/');
            $account = $this->render($application, '/account/key/');
            assertStringContains('<h1>Lobby</h1>', $lobby);
            assertStringContains('class="nav-link is-active" href="/lobby/"', $lobby);
            assertStringContains('class="nav-link" href="/account/key/"', $lobby);
            assertStringNotContains('href="/">Board</a>', $lobby);
            assertStringNotContains('href="/about/">About</a>', $lobby);
            assertStringNotContains('href="/users/">Users</a>', $lobby);
            assertStringNotContains('href="/tools/">Tools</a>', $lobby);
            assertStringContains('Account Key', $account);
            assertStringContains('data-profile-link-authorized="0"', $account);
            assertStringContains('data-role="profile-link-wrap" hidden', $account);
            assertStringContains('class="nav-link" href="/lobby/"', $account);
            assertStringContains('class="nav-link is-active" href="/account/key/"', $account);
            assertStringNotContains('href="/">Board</a>', $account);
            $threadResume = $this->render($application, '/threads/root-001');
            assertStringContains('<h1>Reconnecting</h1>', $threadResume);
            assertStringContains('data-auth-return-to="/threads/root-001"', $threadResume);
            $boardResume = $this->render($application, '/?view=recent');
            assertStringContains('data-auth-return-to="/?view=recent"', $boardResume);
            $profileResume = $this->render($application, '/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954?tab=activity');
            assertStringContains('data-auth-return-to="/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954?tab=activity"', $profileResume);
            assertStringContains('Your access is pending approval. Once you are fully authenticated, you can access this page.', $this->render($application, '/api/get_profile?profile_slug=openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954'));
            assertStringContains('<h1>Reconnecting</h1>', $this->render($application, '/backup/'));
            assertStringContains('Your access is pending approval. Once you are fully authenticated, you can access this page.', $this->render($application, '/?format=rss'));
            assertStringContains('Identity not found.', $this->renderMethod($application, 'POST', '/api/prepare_invitation_redemption'));
            assertStringContains('The requested route does not exist in the local test slice.', $this->render($application, '/asdf'));
        } finally {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            session_id('');
            @unlink($databasePath);
            if ($previousFlag === false) {
                putenv('FORUM_APPROVED_MEMBERS_ONLY');
            } else {
                putenv('FORUM_APPROVED_MEMBERS_ONLY=' . $previousFlag);
            }
        }
    }

    public function testPrivateForteRoutesRecoverExpiredSessionsInsteadOfReturningFalseNotFound(): void
    {
        $previousFlag = getenv('FORUM_APPROVED_MEMBERS_ONLY');
        $previousSession = $_SESSION ?? null;
        putenv('FORUM_APPROVED_MEMBERS_ONLY=true');
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-private-forte-resume-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $sessionId = 'private-forte-resume-' . bin2hex(random_bytes(8));

        try {
            $application = new Application(dirname(__DIR__), $this->repositoryRoot, $databasePath);
            foreach ([
                '/forte?selected=root-001',
                '/forte/users/?page=2',
                '/forte/activity/?view=content',
                '/forte/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954',
                '/forte/user/forum-user',
            ] as $path) {
                $resume = $this->render($application, $path);
                assertStringContains('<h1>Reconnecting</h1>', $resume);
                assertStringContains('data-auth-return-to="' . $path . '"', $resume);
                assertStringNotContains('The requested route does not exist in the local test slice.', $resume);
            }

            foreach ([
                '/api/forte_activity_page?view=all',
                '/api/forte_commit_detail?sha=abc123',
                '/api/get_forte_content_summary?post_id=root-001',
            ] as $path) {
                $response = $this->render($application, $path);
                assertStringContains('Approval required', $response);
                assertStringNotContains('The requested route does not exist in the local test slice.', $response);
            }

            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            session_id($sessionId);
            session_start();
            $_SESSION['authenticated_identity_id'] = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
            session_write_close();

            assertStringContains('Forte', $this->render($application, '/forte/users/'));
            assertStringContains('The requested route does not exist in the local test slice.', $this->render($application, '/forte/not-a-route'));
        } finally {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            session_id('');
            if ($previousSession === null) {
                unset($_SESSION);
            } else {
                $_SESSION = $previousSession;
            }
            @unlink($databasePath);
            if ($previousFlag === false) {
                putenv('FORUM_APPROVED_MEMBERS_ONLY');
            } else {
                putenv('FORUM_APPROVED_MEMBERS_ONLY=' . $previousFlag);
            }
        }
    }

    public function testPendingPrivateSessionNavigationOnlyShowsLobbyOwnProfileAndAccount(): void
    {
        $previousFlag = getenv('FORUM_APPROVED_MEMBERS_ONLY');
        $previousSession = $_SESSION ?? null;
        putenv('FORUM_APPROVED_MEMBERS_ONLY=true');
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-private-pending-nav-' . bin2hex(random_bytes(6));
        mkdir($repositoryRoot, 0777, true);
        $this->copyDirectory($this->repositoryRoot, $repositoryRoot);
        $this->deleteDirectoryContents($repositoryRoot . '/records/approval-seeds');
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-private-pending-nav-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $sessionId = 'private-pending-nav-' . bin2hex(random_bytes(8));

        try {
            session_id($sessionId);
            session_start();
            $_SESSION['authenticated_identity_id'] = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
            session_write_close();

            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
            $lobby = $this->render($application, '/lobby/');
            $root = $this->render($application, '/');

            assertStringContains('class="nav-link is-active" href="/lobby/"', $lobby);
            assertStringContains(
                'class="nav-link" href="/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954">Profile</a>',
                $lobby,
            );
            assertStringContains('class="nav-link" href="/account/key/"', $lobby);
            assertStringNotContains('href="/">Board</a>', $lobby);
            assertStringNotContains('href="/about/">About</a>', $lobby);
            assertStringNotContains('href="/users/">Users</a>', $lobby);
            assertStringNotContains('href="/tools/">Tools</a>', $lobby);
            assertStringContains('Entering lobby.', $root);
        } finally {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            session_id('');
            if ($previousSession === null) {
                unset($_SESSION);
            } else {
                $_SESSION = $previousSession;
            }
            $this->deleteTree($repositoryRoot);
            @unlink($databasePath);
            if ($previousFlag === false) {
                putenv('FORUM_APPROVED_MEMBERS_ONLY');
            } else {
                putenv('FORUM_APPROVED_MEMBERS_ONLY=' . $previousFlag);
            }
        }
    }

    public function testAssetFingerprintPathsUseContentHashFilenames(): void
    {
        $publicRoot = dirname(__DIR__) . '/public';

        $siteCssPath = AssetFingerprint::fingerprintedPath($publicRoot, '/assets/site.css');
        assertStringMatches('#^/assets/site\.[a-f0-9]{12}\.css$#', $siteCssPath);
        assertSame($publicRoot . '/assets/site.css', AssetFingerprint::sourcePathForFingerprint($publicRoot, $siteCssPath));
        assertSame(null, AssetFingerprint::sourcePathForFingerprint($publicRoot, '/assets/site.000000000000.css'));
    }

    public function testLayoutUsesOnlyAValidatedThemeHintForTheInitialStylesheet(): void
    {
        $previousCookie = $_COOKIE;
        $renderer = new \ForumRewrite\View\TemplateRenderer(dirname(__DIR__) . '/templates');
        $publicRoot = dirname(__DIR__) . '/public';

        try {
            $_COOKIE = ['theme-hint' => 'word97'];
            $hintedHtml = $renderer->renderLayout('Theme', '<main></main>', 'board');

            assertStringContains(
                'id="theme-stylesheet" rel="stylesheet" href="'
                . AssetFingerprint::fingerprintedPath($publicRoot, '/assets/theme-word97.css')
                . '" fetchpriority="high"',
                $hintedHtml
            );
            assertStringContains('var themeStylesheetPaths = ', $hintedHtml);
            assertStringContains("document.getElementById('theme-stylesheet')", $hintedHtml);
            assertStringContains('data-theme-hint-cookie="theme-hint"', $hintedHtml);
            assertStringContains('window.forumUpdateThemeHint = updateThemeHint;', $hintedHtml);
            assertStringContains(
                '"openpgpV6":"' . AssetFingerprint::fingerprintedPath($publicRoot, '/assets/openpgp.min.js') . '"',
                $hintedHtml,
            );
            assertStringContains(
                '"openpgpV5":"' . AssetFingerprint::fingerprintedPath($publicRoot, '/assets/openpgp.v5.11.3.min.js') . '"',
                $hintedHtml,
            );

            $_COOKIE = ['theme-hint' => 'auto'];
            $invalidHintHtml = $renderer->renderLayout('Theme', '<main></main>', 'board');

            assertStringContains(
                'id="theme-stylesheet" rel="stylesheet" href="'
                . AssetFingerprint::fingerprintedPath($publicRoot, '/assets/theme-light.css')
                . '" fetchpriority="high"',
                $invalidHintHtml
            );
        } finally {
            $_COOKIE = $previousCookie;
        }
    }

    public function testThemeToggleWarmsAlternateThemeStylesheetsAtLowPriority(): void
    {
        $script = file_get_contents(dirname(__DIR__) . '/public/assets/theme_toggle.js');

        assertSame(true, $script !== false);
        assertStringContains('function warmAlternateThemes()', (string) $script);
        assertStringContains('window.requestIdleCallback(warmAlternateThemes, { timeout: 1000 });', (string) $script);
        assertStringContains('link.setAttribute("fetchpriority", highPriority ? "high" : "low");', (string) $script);
        assertStringContains('data-theme-loading', (string) $script);
    }

    public function testAssetFingerprintDistinguishesCurrentAndStaleAssetPaths(): void
    {
        $publicRoot = sys_get_temp_dir() . '/forum-rewrite-fingerprint-' . bin2hex(random_bytes(6));
        mkdir($publicRoot . '/assets', 0777, true);
        file_put_contents($publicRoot . '/assets/example.css', 'body { color: red; }');

        try {
            $currentPath = AssetFingerprint::fingerprintedPath($publicRoot, '/assets/example.css');
            $recursivePath = substr($currentPath, 0, -4) . '.000000000000.css';
            assertSame($publicRoot . '/assets/example.css', AssetFingerprint::sourcePathForFingerprint($publicRoot, $currentPath));
            assertSame(null, AssetFingerprint::sourcePathForFingerprint($publicRoot, '/assets/example.000000000000.css'));
            assertSame(null, AssetFingerprint::sourcePathForFingerprint($publicRoot, $recursivePath));
            assertSame($currentPath, AssetFingerprint::replacementPathForFingerprint($publicRoot, '/assets/example.000000000000.css'));
            assertSame($currentPath, AssetFingerprint::replacementPathForFingerprint($publicRoot, $recursivePath));
            assertSame(null, AssetFingerprint::replacementPathForFingerprint($publicRoot, $currentPath));
            assertSame(null, AssetFingerprint::replacementPathForFingerprint($publicRoot, '/assets/missing.000000000000.css'));
            assertSame(null, AssetFingerprint::sourcePathForFingerprint($publicRoot, '/assets/missing.000000000000.css'));
        } finally {
            @unlink($publicRoot . '/assets/example.css');
            @rmdir($publicRoot . '/assets');
            @rmdir($publicRoot);
        }
    }

    public function testCompactModeMenuStylesUseScopedDensitySelectors(): void
    {
        $css = file_get_contents(dirname(__DIR__) . '/public/assets/thread-list.css');
        $word97Css = file_get_contents(dirname(__DIR__) . '/public/assets/theme-word97.css');
        if ($css === false) {
            throw new RuntimeException('Unable to read site stylesheet.');
        }
        if ($word97Css === false) {
            throw new RuntimeException('Unable to read Word 97 stylesheet.');
        }

        assertStringContains(':root[data-thread-density="compact"] .thread-card__preview', $css);
        assertStringContains(':root[data-thread-density="compact"] .thread-list .thread-card', $css);
        assertStringContains(':root[data-thread-density="compact"] .thread-list > * + *', $css);
        assertStringContains(':root[data-thread-density="compact"] article.card:has(> .board-controls-nav)', $css);
        assertStringContains(':root[data-thread-density="compact"] .compact-thread-compose', $css);
        assertStringContains(':root[data-thread-density="compact"] .compact-thread-compose', $css);
        assertStringContains('.inline-reply-summary {', $css);
        assertStringContains(':root[data-thread-density="compact"] .thread-list > .compact-thread-compose', $css);
        assertStringContains('margin-top: 0', $css);
        assertStringContains(':root[data-thread-density="compact"] .thread-list > .card', $css);
        assertStringContains('border-left: 0', $css);
        assertStringContains('border-right: 0', $css);
        assertStringContains(':root[data-theme="word97"][data-thread-density="compact"]', $word97Css);
    }

    public function testAssetFingerprintCopySkipsAlreadyFingerprintedSourceFiles(): void
    {
        $sourceRoot = sys_get_temp_dir() . '/forum-rewrite-source-assets-' . bin2hex(random_bytes(6));
        $targetRoot = sys_get_temp_dir() . '/forum-rewrite-target-assets-' . bin2hex(random_bytes(6));
        mkdir($sourceRoot . '/assets', 0777, true);
        file_put_contents($sourceRoot . '/assets/site.css', 'body { color: #111; }');
        file_put_contents($sourceRoot . '/assets/site.123456789abc.css', 'body { color: #222; }');

        try {
            AssetFingerprint::copyFingerprintedAssets($sourceRoot, $targetRoot);

            $copied = array_values(array_filter(
                scandir($targetRoot . '/assets') ?: [],
                static fn (string $entry): bool => $entry !== '.' && $entry !== '..'
            ));

            assertSame(1, count($copied));
            assertStringMatches('#^site\.[a-f0-9]{12}\.css$#', $copied[0]);
            assertStringNotContains('123456789abc', $copied[0]);
        } finally {
            $this->deleteTree($sourceRoot);
            $this->deleteTree($targetRoot);
        }
    }

    public function testUnicodeRiskBackfillScansExistingPostsDeterministically(): void
    {
        @unlink($this->databasePath);
        $rebuildCommand = sprintf(
            'php %s %s %s',
            escapeshellarg(__DIR__ . '/../scripts/rebuild_read_model.php'),
            escapeshellarg($this->repositoryRoot),
            escapeshellarg($this->databasePath),
        );
        exec($rebuildCommand, $rebuildOutput, $rebuildExitCode);

        $command = sprintf(
            'php %s %s %s',
            escapeshellarg(__DIR__ . '/../scripts/backfill_unicode_risk.php'),
            escapeshellarg($this->repositoryRoot),
            escapeshellarg($this->databasePath),
        );
        exec($command, $output, $exitCode);

        $pdo = new PDO('sqlite:' . $this->databasePath);
        $postCount = (int) $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn();
        $riskCount = (int) $pdo->query('SELECT COUNT(*) FROM post_unicode_risks')->fetchColumn();
        $combinedOutput = implode("\n", $output);

        assertSame(0, $rebuildExitCode);
        assertSame(0, $exitCode);
        assertSame($postCount, $riskCount);
        assertStringContains('Mode: deterministic-only', $combinedOutput);
        assertStringContains('Scanned: ' . $postCount, $combinedOutput);
    }

    public function testPrivateConfigViewRedactsSecretAndShowsUpdateReminder(): void
    {
        $secretsPath = sys_get_temp_dir() . '/forum-rewrite-private-config-' . bin2hex(random_bytes(6)) . '/secrets.php';
        mkdir(dirname($secretsPath), 0700, true);
        file_put_contents($secretsPath, "<?php\n\nreturn [\n"
            . "    'DEDALUS_API_KEY' => 'prod-secret-value',\n"
            . "    'DEDALUS_MODEL' => 'openai/gpt-5-nano',\n"
            . "    'DEDALUS_AGENT_REPLIES_ENABLED' => true,\n"
            . "    'DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED' => false,\n"
            . "    'EXTRA_SERVICE_TOKEN' => 'do-not-print',\n"
            . "];\n");

        try {
            $output = $this->runCommand(
                dirname(__DIR__),
                'FORUM_SECRETS_PATH=' . escapeshellarg($secretsPath) . ' DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED=true ./v3 private-config view'
            );

            assertStringContains('Private config path: ' . $secretsPath, $output);
            assertStringContains('Status: present', $output);
            assertStringContains('DEDALUS_API_KEY = <set> (file)', $output);
            assertStringContains("DEDALUS_AGENT_REPLIES_ENABLED = true (file)", $output);
            assertStringContains("DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED = 'true' (environment override)", $output);
            assertStringContains('Update commands:', $output);
            assertStringContains('./v3 private-config --force', $output);
            assertStringContains('./v3 private-config --api-key-stdin', $output);
            assertStringContains('Edit ' . $secretsPath . ' directly for booleans', $output);
            assertStringNotContains('prod-secret-value', $output);
            assertStringNotContains('do-not-print', $output);
        } finally {
            @unlink($secretsPath);
            @rmdir(dirname($secretsPath));
        }
    }

    public function testAgentReplyCronReferenceCommandShowsInstallInstructions(): void
    {
        $output = $this->runCommand(
            dirname(__DIR__),
            './v3 agent-reply cron --log=/tmp/forum-agent-replies-test.log'
        );

        assertStringContains('Agent reply request cron reference', $output);
        assertStringContains('crontab -e', $output);
        assertStringContains('cd ' . escapeshellarg(dirname(__DIR__)), $output);
        assertStringContains('php scripts/run_agent_reply_requests.php --quiet --limit=10', $output);
        assertStringContains('/tmp/forum-agent-replies-test.log', $output);
        assertStringContains('./v3 private-config view', $output);
        assertStringContains('./v3 agent-reply test', $output);
        assertStringContains('./v3 agent-reply test-local', $output);
        assertStringContains('php scripts/run_agent_reply_requests.php --dry-run', $output);
        assertStringContains('worker exits cleanly if a previous run is still active', $output);
    }

    public function testAgentReplyStatusCommandShowsSkippedReasonAndAnalysisFailure(): void
    {
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-agent-status-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $pdo = new PDO('sqlite:' . $databasePath);
        $postId = 'root-agent-status';
        $contentHash = 'hash-agent-status';

        try {
            (new SqlitePostAnalysisStore($pdo))->saveFailed($postId, $contentHash, 'provider_error', 'Dedalus request failed.', [
                'request' => [
                    'url' => 'https://api.dedaluslabs.ai/v1/chat/completions',
                    'body' => '{"model":"openai/gpt-5-nano"}',
                ],
                'response' => [
                    'status_code' => 400,
                    'body' => '{"error":{"message":"bad request"}}',
                ],
            ]);
            (new SqliteAgentReplyGenerationStore($pdo))->markSkipped($postId, $contentHash, 'analysis_not_complete', [
                'reason' => 'analysis_not_complete',
                'analysis_status' => 'failed',
                'failure_code' => 'provider_error',
                'failure_message' => 'Dedalus request failed.',
            ]);

            $output = $this->runCommand(
                dirname(__DIR__),
                './v3 agent-reply status ' . escapeshellarg($postId) . ' --database-path=' . escapeshellarg($databasePath)
            );

            assertStringContains('Agent reply status', $output);
            assertStringContains('target_post_id: ' . $postId, $output);
            assertStringContains('reply_status: skipped', $output);
            assertStringContains('reply_failure: analysis_not_complete', $output);
            assertStringContains('skip_details:', $output);
            assertStringContains('failure_code: provider_error', $output);
            assertStringContains('failure_message: Dedalus request failed.', $output);
            assertStringContains('analysis:', $output);
            assertStringContains('status: failed', $output);
            assertStringContains('provider_diagnostics:', $output);
            assertStringContains('request.url: https://api.dedaluslabs.ai/v1/chat/completions', $output);
            assertStringContains('request.body: {"model":"openai/gpt-5-nano"}', $output);
            assertStringContains('response.status_code: 400', $output);
            assertStringContains('response.body: {"error":{"message":"bad request"}}', $output);
        } finally {
            @unlink($databasePath);
        }
    }

    public function testInjectApprovalScriptUsesInstanceOverridesAcrossSiteProfiles(): void
    {
        [$projectRoot, $repositoryRoot, $databasePath, $artifactRoot] = $this->createGitBackedEnvironmentWithArtifacts();
        $this->deleteDirectoryContents($repositoryRoot . '/records/approval-seeds');

        $command = sprintf(
            'FORUM_SITE_ID=chouse FORUM_REPOSITORY_ROOT=%s FORUM_DATABASE_PATH=%s %s approval seed %s %s',
            escapeshellarg($repositoryRoot),
            escapeshellarg($databasePath),
            escapeshellarg(__DIR__ . '/../v3'),
            escapeshellarg('openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954'),
            escapeshellarg('script seeded approval'),
        );
        exec($command, $output, $exitCode);

        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $profile = $this->render($application, '/api/get_profile?profile_slug=openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954');

        assertSame(0, $exitCode);
        assertStringContains('Seeded approval for openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954', implode("\n", $output));
        assertTrue(is_file($repositoryRoot . '/records/approval-seeds/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954.txt'));
        assertStringContains('Approved: yes', $profile);
    }

    public function testApproveShortcutSeedsIdentity(): void
    {
        [$projectRoot, $repositoryRoot, $databasePath, $artifactRoot] = $this->createGitBackedEnvironmentWithArtifacts();
        $this->deleteDirectoryContents($repositoryRoot . '/records/approval-seeds');

        $command = sprintf(
            '%s %s %s %s %s %s',
            escapeshellarg(__DIR__ . '/../v3'),
            'approve',
            escapeshellarg('openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954'),
            escapeshellarg('shortcut seeded approval'),
            escapeshellarg($repositoryRoot),
            escapeshellarg($databasePath),
        );
        exec($command, $output, $exitCode);

        assertSame(0, $exitCode);
        assertStringContains('Seeded approval for openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954', implode("\n", $output));
        assertTrue(is_file($repositoryRoot . '/records/approval-seeds/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954.txt'));
    }

    public function testStartAcceptsPortShorthand(): void
    {
        $port = random_int(18000, 18999);
        $command = sprintf(
            'timeout 1s %s start %s 2>&1',
            escapeshellarg(__DIR__ . '/../v3'),
            escapeshellarg((string) $port),
        );
        exec($command, $output, $exitCode);

        assertSame(124, $exitCode);
        assertStringContains('127.0.0.1:' . $port, implode("\n", $output));
    }

    public function testStartAcceptsColonPortShorthand(): void
    {
        $port = random_int(19000, 19999);
        $command = sprintf(
            'timeout 1s %s start %s 2>&1',
            escapeshellarg(__DIR__ . '/../v3'),
            escapeshellarg(':' . $port),
        );
        exec($command, $output, $exitCode);

        assertSame(124, $exitCode);
        assertStringContains('127.0.0.1:' . $port, implode("\n", $output));
    }

    public function testStartRejectsOutOfRangePortShorthand(): void
    {
        $command = sprintf(
            '%s start 65536 2>&1',
            escapeshellarg(__DIR__ . '/../v3'),
        );
        exec($command, $output, $exitCode);

        assertSame(1, $exitCode);
        assertStringContains('Invalid port: 65536', implode("\n", $output));
    }

    public function testInjectApprovalScriptApprovesExistingUser(): void
    {
        [$projectRoot, $repositoryRoot, $databasePath, $artifactRoot] = $this->createGitBackedEnvironmentWithArtifacts();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $_POST = [
            'public_key' => $this->generatePublicKey('alice'),
        ];
        $response = $this->renderMethod($application, 'POST', '/api/link_identity');
        $_POST = [];
        $targetIdentityId = $this->extractResponseValue($response, 'identity_id');
        $targetProfileSlug = $this->extractResponseValue($response, 'profile_slug');
        $unapprovedProfile = $this->render($application, '/profiles/' . $targetProfileSlug);

        assertStringContains('Approved:</strong> no', $unapprovedProfile);
        assertStringContains('Public key', $unapprovedProfile);
        assertStringContains('BEGIN PGP PUBLIC KEY BLOCK', $unapprovedProfile);

        $command = sprintf(
            '%s approval approve %s %s %s %s %s',
            escapeshellarg(__DIR__ . '/../v3'),
            escapeshellarg('openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954'),
            escapeshellarg(str_replace('openpgp:', 'openpgp-', $targetIdentityId)),
            escapeshellarg($repositoryRoot),
            escapeshellarg($databasePath),
            escapeshellarg($artifactRoot),
        );
        exec($command, $output, $exitCode);

        $profile = $this->render($application, '/api/get_profile?profile_slug=' . rawurlencode($targetProfileSlug));
        $approvedProfile = $this->render($application, '/profiles/' . $targetProfileSlug);

        assertSame(0, $exitCode);
        assertStringContains('Approved ' . $targetIdentityId, implode("\n", $output));
        assertStringContains('Approved: yes', $profile);
        assertStringContains('Approved:</strong> yes', $approvedProfile);
        assertStringContains('Public key', $approvedProfile);
        assertStringContains('BEGIN PGP PUBLIC KEY BLOCK', $approvedProfile);
    }

    public function testInjectApprovalScriptRejectsMissingApproveArguments(): void
    {
        $command = sprintf(
            '%s approval approve 2>&1',
            escapeshellarg(__DIR__ . '/../v3'),
        );
        exec($command, $output, $exitCode);

        $combinedOutput = implode("\n", $output);

        assertSame(1, $exitCode);
        assertStringContains('Missing required argument: approver_identity_id.', $combinedOutput);
        assertStringContains('Usage:', $combinedOutput);
        assertStringNotContains('PHP Fatal error', $combinedOutput);
    }

    public function testCodexHandoffEligibilityRequiresApprovedLocalhostViewer(): void
    {
        $application = new Application(dirname(__DIR__), $this->repositoryRoot, $this->databasePath);
        $method = new ReflectionMethod(Application::class, 'viewerCanUseCodexHandoff');
        $approvedViewer = ['is_approved' => 1];
        $unapprovedViewer = ['is_approved' => 0];
        $originalServer = $_SERVER;

        try {
            $_SERVER['HTTP_HOST'] = 'localhost:8000';
            unset($_SERVER['SERVER_NAME'], $_SERVER['REMOTE_ADDR']);
            assertSame(true, $method->invoke($application, $approvedViewer));
            assertSame(false, $method->invoke($application, $unapprovedViewer));
            assertSame(false, $method->invoke($application, null));

            $_SERVER['HTTP_HOST'] = 'example.com';
            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
            assertSame(false, $method->invoke($application, $approvedViewer));

            unset($_SERVER['HTTP_HOST'], $_SERVER['SERVER_NAME']);
            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
            assertSame(true, $method->invoke($application, $approvedViewer));
        } finally {
            $_SERVER = $originalServer;
        }
    }

    public function testDeleteRecordCommandRemovesRecordCommitsAndRefreshesDerivedState(): void
    {
        [, $repositoryRoot, $databasePath, $artifactRoot] = $this->createGitBackedEnvironmentWithArtifacts();
        $recordId = 'thread-label-20260530000001-zenrules';
        $relativePath = 'records/thread-labels/' . $recordId . '.txt';
        $recordPath = $repositoryRoot . '/' . $relativePath;

        $command = sprintf(
            '%s delete-record %s %s %s %s',
            escapeshellarg(__DIR__ . '/../v3'),
            escapeshellarg($relativePath),
            escapeshellarg($repositoryRoot),
            escapeshellarg($databasePath),
            escapeshellarg($artifactRoot),
        );
        exec($command, $output, $exitCode);

        $pdo = new PDO('sqlite:' . $databasePath);
        $labels = $pdo->query("SELECT thread_labels_json FROM threads WHERE root_post_id = 'thread-zenmemes-rules'")->fetchColumn();
        exec(
            sprintf('git -C %s log -1 --pretty=%%s', escapeshellarg($repositoryRoot)),
            $gitLogOutput,
            $gitLogExitCode,
        );
        $combinedOutput = implode("\n", $output);

        assertSame(0, $exitCode);
        assertFalse(is_file($recordPath));
        assertSame('[]', $labels);
        assertSame(0, $gitLogExitCode);
        assertSame('Delete canonical record ' . $relativePath, $gitLogOutput[0] ?? '');
        assertTrue(is_file($artifactRoot . '/threads/thread-zenmemes-rules.html'));
        assertStringContains('Deleted canonical record.', $combinedOutput);
        assertStringContains('Rebuilt static artifacts:', $combinedOutput);
    }

    public function testThreadAttributesCommandResolvesDeletedLabelRecordToThread(): void
    {
        [, $repositoryRoot, $databasePath, $artifactRoot] = $this->createGitBackedEnvironmentWithArtifacts();
        $recordId = 'thread-label-20260530000001-zenrules';

        $deleteCommand = sprintf(
            '%s delete-record %s %s %s %s',
            escapeshellarg(__DIR__ . '/../v3'),
            escapeshellarg($recordId),
            escapeshellarg($repositoryRoot),
            escapeshellarg($databasePath),
            escapeshellarg($artifactRoot),
        );
        exec($deleteCommand, $deleteOutput, $deleteExitCode);

        $attributesCommand = sprintf(
            '%s thread-attributes %s %s %s',
            escapeshellarg(__DIR__ . '/../v3'),
            escapeshellarg($recordId),
            escapeshellarg($repositoryRoot),
            escapeshellarg($databasePath),
        );
        exec($attributesCommand, $attributesOutput, $attributesExitCode);
        $combinedOutput = implode("\n", $attributesOutput);

        assertSame(0, $deleteExitCode);
        assertSame(0, $attributesExitCode);
        assertStringContains('target_type: deleted-thread-label', $combinedOutput);
        assertStringContains('record_id: ' . $recordId, $combinedOutput);
        assertStringContains('thread_id: thread-zenmemes-rules', $combinedOutput);
        assertStringContains('subject: The Rules of ZenMemes.com', $combinedOutput);
        assertStringContains('labels: (none)', $combinedOutput);
    }

    public function testForteReplyLikesRenderAndRestoreViewerState(): void
    {
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-forte-reply-likes-' . bin2hex(random_bytes(6));
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-forte-reply-likes-' . bin2hex(random_bytes(6)) . '.sqlite3';
        mkdir($repositoryRoot, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);
        mkdir($repositoryRoot . '/records/post-reactions');
        file_put_contents(
            $repositoryRoot . '/records/posts/reply-002.txt',
            "Post-ID: reply-002\nCreated-At: 2026-04-10T12:06:00Z\nBoard-Tags: general\nThread-ID: root-001\nParent-ID: reply-001\n\nNested reply body.\n"
        );

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);

            $_COOKIE = [];
            $anonymousForte = $this->render($application, '/forte?selected=root-001');
            assertStringContains('data-action="apply-post-tag" data-tag="like" data-post-id="reply-001" data-applied-label="Liked" aria-pressed="false">Like</button>', $anonymousForte);
            assertStringContains('data-action="apply-post-tag" data-tag="like" data-post-id="reply-002" data-applied-label="Liked" aria-pressed="false">Like</button>', $anonymousForte);
            assertStringContains('data-action="apply-post-tag" data-tag="flag" data-post-id="reply-001"', $anonymousForte);

            file_put_contents(
                $repositoryRoot . '/records/post-reactions/post-reaction-20261002120000-replylike.txt',
                "Record-ID: post-reaction-20261002120000-replylike\nCreated-At: 2026-10-02T12:00:00Z\nPost-ID: reply-002\nOperation: add\nTags: like\nAuthor-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954\n\n"
            );
            $_COOKIE = ['identity_hint' => 'guest'];
            $likedForte = $this->render($application, '/forte?selected=root-001');

            assertStringContains('data-action="apply-post-tag" data-tag="like" data-post-id="reply-002" data-applied-label="Liked" aria-pressed="true" disabled>Liked</button>', $likedForte);
        } finally {
            $_COOKIE = [];
            $this->deleteTree($repositoryRoot);
            @unlink($databasePath);
        }
    }

    public function testApplicationRendersCoreRoutes(): void
    {
        @unlink($this->databasePath);
        $application = new Application(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
        );

        $board = $this->render($application, '/');
        $threadsIndex = $this->render($application, '/threads/');
        $threadsIndexNoSlash = $this->render($application, '/threads');
        $boardAllThreads = $this->render($application, '/threads/?view=all&sort=newest');
        $about = $this->render($application, '/about/');
        $thread = $this->render($application, '/threads/root-001');
        $post = $this->render($application, '/posts/root-001');
        $instance = $this->render($application, '/instance/');
        $backup = $this->render($application, '/backup/');
        $toolsBackup = $this->render($application, '/tools/backup/');
        $tools = $this->render($application, '/tools/');
        $codebase = $this->render($application, '/tools/codebase/');
        $featureFlags = $this->render($application, '/tools/feature-flags/');
        $profile = $this->render($application, '/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954');
        $username = $this->render($application, '/user/guest');
        $_COOKIE = ['identity_hint' => 'guest'];
        $users = $this->render($application, '/users/');
        $tags = $this->render($application, '/tags/');
        $tagPage = $this->render($application, '/tags/bug');
        $pendingUsers = $this->render($application, '/users/pending/');
        $_COOKIE = [];
        $composeThread = $this->render($application, '/compose/thread');
        $bookmarklets = $this->render($application, '/tools/bookmarklets/');
        $composeReply = $this->render($application, '/compose/reply?thread_id=root-001&parent_id=root-001');
        $account = $this->render($application, '/account/key/');
        $activity = $this->render($application, '/activity/?view=content');
        $forteActivity = $this->render($application, '/forte/activity/?view=content');
        $forte = $this->render($application, '/forte?selected=root-001');
        $llms = $this->render($application, '/llms.txt');

        assertStringContains('Board', $board);
        assertStringContains('Board', $threadsIndex);
        assertStringContains('Board', $threadsIndexNoSlash);
        assertSame($board, $threadsIndex);
        assertSame($board, $threadsIndexNoSlash);
        assertStringContains('href="/">Board</a>', $board);
        assertStringContains('href="/about/">About</a>', $board);
        assertStringContains('New Post', $board);
        assertStringContains('href="/compose/thread"', $board);
        assertStringNotContains('href="/compose/thread">Compose</a>', $board);
        assertStringContains('>Tags</a>', $board);
        assertStringContains('href="/tags/"', $board);
        assertStringContains('data-compose-root', $board);
        assertStringContains('data-inline-reply-details', $board);
        assertStringContains('class="inline-reply-prompt compact-thread-compose-prompt"', $board);
        assertStringContains('method="post" action="/compose/thread" class="stack compact-thread-compose-form" data-compose-form data-compose-kind="thread"', $board);
        assertStringContains('data-compose-kind="thread"', $board);
        assertStringContains('data-unicode-authored-text="0"', $board);
        assertStringContains('data-emoji-authored-text="0"', $board);
        assertStringContains('name="subject"', $board);
        assertStringContains('name="body"', $board);
        assertStringContains('name="board_tags" value="general"', $board);
        assertFingerprintedAsset($board, 'inline_reply_form.js');
        assertFingerprintedAsset($board, 'lazy_compose_signing.js');
        assertStringNotContains('/assets/openpgp_loader.js', $board);
        assertStringNotContains('/assets/browser_signing.js', $board);
        assertStringNotContains('View: All', $board);
        assertStringNotContains('Sort: Newest', $board);
        assertStringContains('/threads/?view=all&amp;sort=newest', $board);
        assertStringContains('/threads/?view=liked&amp;sort=newest', $board);
        assertStringContains('/threads/?view=liked&amp;sort=oldest', $board);
        assertStringContains('/threads/?view=liked&amp;sort=top', $board);
        assertStringNotContains('href="/tags/board/', $board);
        assertStringNotContains('href="/tags/label/', $board);
        assertStringNotContains('Score: 0', $board);
        assertStringContains('Score: 0', $post);
        assertStringNotContains('Labels: bug, needs-review', $board);
        assertStringContains('Hello world', $thread);
        assertStringContains('Labels: bug, needs-review', $thread);
        assertFingerprintedAsset($forte, 'lazy_compose_signing.js');
        assertFingerprintedAsset($forte, 'thread_reactions.js');
        assertStringContains(
            '"openpgpLoader":"' . AssetFingerprint::fingerprintedPath(dirname(__DIR__) . '/public', '/assets/openpgp_loader.js') . '"',
            $forte,
        );
        assertStringContains(
            '"browserSigning":"' . AssetFingerprint::fingerprintedPath(dirname(__DIR__) . '/public', '/assets/browser_signing.js') . '"',
            $forte,
        );
        assertFingerprintedAsset($thread, 'openpgp_loader.js');
        assertFingerprintedAsset($thread, 'browser_signing.js');
        assertFingerprintedAsset($thread, 'inline_reply_form.js');
        assertFingerprintedAsset($thread, 'thread_reactions.js');
        assertFingerprintedAsset($thread, 'post_analysis.js');
        assertStringContains('data-thread-reactions-root', $thread);
        assertStringContains('data-action="apply-thread-tag"', $thread);
        assertStringContains('class="card post-card thread-root-card meta-deferred"', $thread);
        assertStringContains('data-thread-id="root-001" data-post-id="root-001"', $thread);
        // root-001 has a same-author continuation (reply-001) right after it, so the byline moves
        // to reply-001 (the run's tail) instead of showing on the root card.
        assertSame(1, substr_count($thread, 'by guest on <time datetime="2026-04-10T12:05:00Z">Apr 10, 2026 at 12:05 UTC</time>'));
        assertOrdered($thread, '<h1>Hello world</h1>', 'First line preview.');
        assertOrdered($thread, 'First line preview.', 'id="post-reply-001"');
        assertStringContains('inline-reply-composer', $thread);
        assertStringContains('data-unicode-authored-text="0"', $thread);
        assertStringContains('data-emoji-authored-text="0"', $thread);
        assertStringContains('data-inline-reply-details', $thread);
        assertStringContains('class="inline-reply-prompt"', $thread);
        assertStringContains('placeholder="Write a reply..."', $thread);
        $inlineReplyScript = (string) file_get_contents(__DIR__ . '/../public/assets/inline_reply_form.js');
        assertStringContains('function scrollFullyIntoView(node)', $inlineReplyScript);
        assertStringContains('node.scrollIntoView({', $inlineReplyScript);
        assertStringContains('inline-reply-identity-status', $thread);
        assertStringContains('method="post" action="/compose/reply" class="stack" data-compose-form data-compose-kind="reply"', $thread);
        assertStringContains('method="post" action="/compose/reply" class="stack" data-compose-form data-compose-kind="reply"', $composeReply);
        assertStringContains('<h2 id="reply-context-title">Replying to</h2>', $composeReply);
        assertStringContains('Post root-001</a>', $composeReply);
        assertStringContains('First line preview.<br />', $composeReply);
        assertStringContains('Second line body.', $composeReply);
        assertStringNotContains('<h1>Compose Reply</h1>', $composeReply);
        assertStringNotContains('Thread ID:', $composeReply);
        assertStringNotContains('Parent ID:', $composeReply);
        assertStringNotContains('<label>Body<textarea name="body"', $composeReply);
        assertStringContains('aria-label="Body"', $composeReply);
        assertStringContains('name="thread_id" value="root-001"', $composeReply);
        assertStringContains('name="parent_id" value="root-001"', $composeReply);
        assertStringContains('name="thread_id" value="root-001"', $thread);
        assertStringContains('name="parent_id" value="root-001"', $thread);
        assertStringContains('type="hidden" name="board_tags" value="general"', $thread);
        assertStringContains('aria-label="Body"', $thread);
        assertStringContains('Post reply', $thread);
        assertStringNotContains('<h2>Reply to thread</h2>', $thread);
        assertStringNotContains('Open full reply page', $thread);
        assertStringNotContains('<label>Body<textarea name="body"', $thread);
        assertStringNotContains('Score: 0', $thread);
        assertStringNotContains('Set up or choose an identity in <a href="/account/key/">Account</a> to use Like.', $thread);
        assertStringNotContains('disabled="disabled"', $thread);
        // root-001's own byline (which links to /user/guest) is now deferred to reply-001, the
        // run's tail; reply-001 has no claimed profile, so it renders a plain, unlinked "guest".
        assertStringNotContains('/user/guest', $thread);
        assertStringContains('by guest on <time datetime="2026-04-10T12:05:00Z">Apr 10, 2026 at 12:05 UTC</time>', $thread);
        assertStringNotContains('Last activity <time datetime=', $thread);
        assertStringContains('id="post-root-001"', $thread);
        assertStringContains('id="post-reply-001"', $thread);
        assertOrdered($thread, 'href="/posts/root-001" title="Post root-001" aria-label="Post root-001">#</a>', 'href="/posts/reply-001" title="Post reply-001" aria-label="Post reply-001">#</a>');
        assertStringNotContains('Post <a href="/posts/root-001">root-001</a>', $thread);
        assertStringContains('/compose/reply?thread_id=root-001&amp;parent_id=root-001', $thread);
        assertStringContains('/compose/reply?thread_id=root-001&amp;parent_id=reply-001', $thread);
        assertStringContains('First line preview.', $post);
        assertStringNotContains('/source/current/records/public-keys/', $thread);
        assertStringContains('Public key', $post);
        assertStringContains('/source/current/records/public-keys/openpgp-0168FF20EB09C3EA6193BD3C92A73AA7D20A0954.asc', $post);
        assertStringNotContains('BEGIN PGP PUBLIC KEY BLOCK', $post);
        assertStringContains('by <a href="/user/guest">guest</a> on <time datetime="2026-04-10T12:00:00Z">Apr 10, 2026 at 12:00 UTC</time>', $post);
        assertStringContains('/compose/reply?thread_id=root-001&amp;parent_id=root-001', $post);
        assertStringContains('Source:', $post);
        assertStringContains('href="/source/current/records/posts/root-001.txt"', $post);
        assertStringContains('Commit:', $post);
        assertStringContains('commit unavailable', $post);
        assertStringContains('Signature:', $post);
        assertStringContains('anonymous unsigned', $post);
        assertStringContains('zenmemes', $instance);
        assertStringContains('Backup', $backup);
        assertStringContains('Backup', $toolsBackup);
        assertStringContains('Snapshot freshness', $instance);
        assertStringContains('Generated at:', $instance);
        assertStringContains('Recent included items', $instance);
        assertStringContains('href="/activity/">See all recent activity</a>', $instance);
        assertStringContains('Repository snapshot:', $instance);
        assertStringContains('preview, not a complete archive listing', $instance);
        assertStringContains('class="nav"', $toolsBackup);
        assertStringContains('class="nav-link is-active" href="/tools/backup/"', $toolsBackup);
        assertStringNotContains('class="nav-link" href="/tools/">Tools</a>', $toolsBackup);
        assertStringContains('/user/guest', $instance);
        assertStringContains('/downloads/repository.tar.gz', $instance);
        assertStringContains('/downloads/repository.zip', $instance);
        assertStringContains('/downloads/read_model.sqlite3', $instance);
        assertStringContains('complete snapshots of the forum data', $instance);
        assertStringContains('insurance policy of sorts', $instance);
        assertStringContains('backup copy of the whole forum', $instance);
        assertStringContains('sufficient to reconstruct the board', $instance);
        assertStringContains('reduce trust requirements', $instance);
        assertStringNotContains('Contact:', $instance);
        assertStringNotContains('Retention:', $instance);
        assertStringNotContains('Installed:', $instance);
        assertStringContains('/activity/', $tools);
        assertStringContains('Recent forum activity across content, approvals, and identity events.', $tools);
        assertStringContains('class="nav-link is-active" href="/tools/"', $tools);
        assertStringContains('class="nav"', $tools);
        assertSame(1, substr_count($tools, 'href="/tools/">Tools</a>'));
        assertStringContains('/tools/bookmarklets/', $tools);
        assertStringContains('/tools/backup/', $tools);
        assertStringContains('/tools/codebase/', $tools);
        assertStringContains('Current application version, repository head, and read-model health.', $tools);
        assertStringContains('/tools/feature-flags/', $tools);
        assertStringContains('Registered site feature flags, defaults, effective values, and override sources.', $tools);
        assertStringContains('/forte', $tools);
        assertStringContains('Classic three-pane newsreader view of the whole board - folders, thread list, and preview.', $tools);
        assertStringNotContains('tool-launcher-button" href="/account/key/"', $tools);
        assertStringContains('tools-nav-divider', $tools);
        assertStringContains('class="nav-link nav-link-standalone" href="/activity/"', $tools);
        assertStringContains('class="nav-link nav-link-standalone" href="/forte"', $tools);
        assertStringContains('System State', $codebase);
        assertStringContains('<strong>Name:</strong> zenmemes', $codebase);
        assertStringContains('<strong>Admin:</strong>', $codebase);
        assertStringContains('grep &#039;^Instance-Name:&#039; state/local_repository/records/instance/public.txt', $codebase);
        assertStringContains("SELECT username_token, MIN(username) AS username FROM profiles WHERE approved_by_label = &#039;root&#039; GROUP BY username_token ORDER BY username_token ASC;", $codebase);
        assertStringContains('class="nav"', $codebase);
        assertStringContains('class="nav-link is-active" href="/tools/codebase/"', $codebase);
        assertStringNotContains('class="nav-link" href="/tools/">Tools</a>', $codebase);
        assertStringContains('Repository head', $codebase);
        assertStringContains('Read model', $codebase);
        assertStringContains('Schema version', $codebase);
        assertStringContains('Lock status', $codebase);
        assertStringContains('Read-model rows', $codebase);
        assertStringContains('git -C state/local_repository rev-parse HEAD', $codebase);
        assertStringContains("SELECT value FROM metadata WHERE key = &#039;schema_version&#039;;", $codebase);
        assertStringContains('flock -n state/cache/forum-rewrite.lock -c true', $codebase);
        assertStringContains('SELECT COUNT(*) FROM posts;', $codebase);
        assertStringContains('/downloads/repository.tar.gz', $codebase);
        assertFingerprintedAsset($codebase, 'tool-details.css');
        assertStringContains('Feature Flags', $featureFlags);
        assertStringContains('class="nav"', $featureFlags);
        assertStringContains('class="nav-link is-active" href="/tools/feature-flags/"', $featureFlags);
        assertStringNotContains('class="nav-link" href="/tools/">Tools</a>', $featureFlags);
        assertStringContains('tools-nav-divider', $featureFlags);
        assertStringContains('class="nav-link nav-link-standalone" href="/activity/"', $featureFlags);
        assertStringContains('class="nav-link nav-link-standalone" href="/forte"', $featureFlags);
        assertFingerprintedAsset($featureFlags, 'feature_flags.js');
        assertFingerprintedAsset($featureFlags, 'tool-details.css');
        assertStringContains('FORUM_UNICODE_AUTHORED_TEXT', $featureFlags);
        assertStringContains('FORUM_EMOJI_AUTHORED_TEXT', $featureFlags);
        assertStringContains('FORUM_APP_VERSION_NOTIFICATION', $featureFlags);
        assertStringContains('FORUM_THREAD_DENSITY_TOGGLE_ENABLED', $featureFlags);
        assertStringContains('DEDALUS_AGENT_REPLIES_ENABLED', $featureFlags);
        assertStringContains('DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED', $featureFlags);
        assertStringContains('data-role="feature-flag-source">default</span>', $featureFlags);
        assertStringContains('feature-flag-row', $featureFlags);
        assertStringContains('role="switch"', $featureFlags);
        assertStringContains('About zenmemes', $about);
        assertStringContains('extraordinary people', $about);
        assertStringContains('Harvard St Commons', $about);
        assertStringContains('continuous social graph', $about);
        assertStringContains('/tools/backup/', $about);
        assertStringContains('Identity ID', $profile);
        assertStringContains('Public key', $profile);
        assertStringContains('BEGIN PGP PUBLIC KEY BLOCK', $profile);
        assertStringContains('Approved by:</strong>', $profile);
        assertStringContains('root', $profile);
        assertStringContains('User guest', $username);
        assertStringContains('Approved Profiles', $username);
        assertStringContains('Combined threads:', $username);
        assertStringContains('Combined posts:', $username);
        assertStringContains('Users', $users);
        assertStringContains('/user/guest', $users);
        assertStringNotContains('Username route:', $users);
        assertStringNotContains('Profile:', $users);
        assertStringNotContains('/users/pending/', $users);
        assertStringContains('href="/tags/"', $tags);
        assertStringContains('class="nav-link is-active" href="/tags/"', $tags);
        assertStringNotContains('class="nav-link is-active" href="/threads/?view=all&amp;sort=newest"', $tags);
        assertStringNotContains('class="nav-link is-active" href="/threads/?view=all&amp;sort=oldest"', $tags);
        assertStringContains('/threads/?view=all&amp;sort=newest', $tags);
        assertStringContains('/threads/?view=liked&amp;sort=newest', $tags);
        assertStringContains('/threads/?view=all&amp;sort=oldest', $tags);
        assertStringContains('/threads/?view=all&amp;sort=top', $tags);
        assertStringContains('href="/compose/thread"', $tags);
        assertStringContains('New Post', $tags);
        assertStringContains('/tags/general', $tags);
        assertStringContains('/tags/bug', $tags);
        assertStringContains('Tag', $tagPage);
        assertStringContains('#bug', $tagPage);
        assertStringNotContains('Score: 0', $tagPage);
        assertStringContains('/threads/root-001', $tagPage);
        assertStringContains('Users Awaiting Approval', $pendingUsers);
        assertFingerprintedAsset($pendingUsers, 'pending_approvals.js');
        assertStringContains('meta name="app-version" content="no-git"', $board);
        assertStringContains('var allowed = ["light","dark","console","lcd","chicago","vapor","forge","sticker","arena","thermal","whitehot","word97","chouse","qdb"];', $board);
        assertStringContains('data-role="theme-menu"', $board);
        assertStringContains('aria-haspopup="menu"', $board);
        assertStringContains('data-theme-option="auto"', $board);
        assertStringContains('data-theme-option="thermal"', $board);
        assertStringContains('data-theme-option="whitehot"', $board);
        assertStringContains('data-theme-option="word97"', $board);
        assertStringContains('data-theme-option="qdb"', $board);
        assertStringContains('class="site-status-bar"', $board);
        assertStringContains('data-heat="', $thread);
        assertStringContains('data-heat="', $tagPage);
        assertStringContains('data-action="theme-cycle"', $board);
        assertStringContains('<style data-role="critical-css">', $board);
        assertFingerprintedAsset($board, 'theme-light.css');
        assertStringNotContains('/assets/about.', $board);
        assertFingerprintedAsset($about, 'about.css');
        assertStringContains('class="card"', $board);
        assertStringContains('<link rel="preload" href="/assets/site.', $board);
        assertStringContains('as="style" fetchpriority="high">', $board);
        assertStringContains('media="print" onload="this.media=\'all\'"', $board);
        assertFingerprintedAsset($board, 'site.css');
        assertFingerprintedAsset($board, 'theme_toggle.js');
        assertFingerprintedAsset($board, 'compose_draft_clear.js');
        assertFingerprintedAsset($board, 'version_check.js');
        assertStringNotContains('/assets/thread_density_toggle.', $board);
        assertStringNotContains('data-role="thread-density-toggle"', $board);
        assertStringNotContains('data-role="thread-density-toggle"', $tagPage);
        assertStringNotContains('data-role="thread-density-toggle"', $thread);
        assertStringNotContains('data-role="thread-density-toggle"', $about);
        assertStringNotContains('data-role="thread-density-toggle"', $tags);
        assertStringNotContains('data-role="thread-density-toggle"', $tools);
        assertFingerprintedAsset($tools, 'tools.css');
        assertFingerprintedAsset($bookmarklets, 'tools.css');
        assertFingerprintedAsset($tags, 'tags.css');
        assertStringNotContains('/assets/tags.', $board);
        assertStringNotContains('/assets/tools.', $board);
        assertFingerprintedAsset($activity, 'activity.css');
        assertFingerprintedAsset($forteActivity, 'activity.css');
        assertFingerprintedAsset($account, 'identity.css');
        assertFingerprintedAsset($account, 'account.css');
        assertFingerprintedAsset($profile, 'identity.css');
        assertStringNotContains('/assets/account.', $profile);
        assertStringNotContains('/assets/pending_approvals.', $profile);
        assertFingerprintedAsset($thread, 'identity.css');
        assertFingerprintedAsset($post, 'identity.css');
        assertFingerprintedAsset($thread, 'content-interactions.css');
        assertFingerprintedAsset($post, 'content-interactions.css');
        assertStringNotContains('/assets/identity.', $board);
        assertStringNotContains('/assets/content-interactions.', $board);
        assertStringNotContains('/assets/activity.', $board);
        assertStringNotContains('data-role="thread-density-menu"', $board);
        assertStringNotContains('data-thread-density-option="comfortable"', $board);
        assertStringNotContains('data-thread-density-option="compact"', $board);
        assertStringNotContains('thread-density-menu-popover', $board);
        assertStringContains('class="card thread-card"', $boardAllThreads);
        assertStringContains('class="card thread-card"', $tagPage);
        assertStringContains('data-role="app-version-banner"', $board);
        assertStringContains('Compose Thread', $composeThread);
        assertFingerprintedAsset($composeThread, 'browser_signing.js');
        assertStringContains('data-role="compose-identity-status" hidden', $composeThread);
        assertStringContains('data-action="submit-anonymous-compose"', $composeThread);
        assertStringContains('Bookmarklets', $bookmarklets);
        assertStringContains('class="nav"', $bookmarklets);
        assertStringContains('class="nav-link is-active" href="/tools/bookmarklets/"', $bookmarklets);
        assertStringNotContains('class="nav-link" href="/tools/">Tools</a>', $bookmarklets);
        assertFingerprintedAsset($bookmarklets, 'tools_bookmarklets.js');
        assertStringContains('data-bookmarklet-kind="clip"', $bookmarklets);
        assertStringContains('data-bookmarklet-kind="tweet"', $bookmarklets);
        assertStringNotContains('Thread ID:', $composeReply);
        assertStringNotContains('Parent ID:', $composeReply);
        assertFingerprintedAsset($composeReply, 'browser_signing.js');
        assertStringNotContains('/assets/inline_reply_form.js', $composeReply);
        assertStringContains('data-role="compose-identity-status" hidden', $composeReply);
        assertStringContains('data-action="submit-anonymous-compose"', $composeReply);
        assertStringContains('Advanced / technical details', $account);
        assertStringContains('Set up this browser', $account);
        assertStringContains('data-action="clear-browser-identity"', $account);
        assertStringContains('Clear identity', $account);
        assertStringNotContains('View user page', $account);
        assertStringContains('Link identity', $account);
        assertStringContains('Saved browser identity:', $account);
        assertFingerprintedAsset($account, 'openpgp_loader.js');
        assertFingerprintedAsset($account, 'browser_signing.js');
        assertStringNotContains('Bootstrap post ID', $account);
        assertStringContains('class="nav-link is-active" href="/activity/?view=content"', $activity);
        assertStringContains('by guest on <time datetime="2026-04-10T12:05:00Z">Apr 10, 2026 at 12:05 UTC</time>', $activity);
        assertStringNotContains('Author: guest', $activity);
        assertStringContains('thread_label_add', $activity);
        assertStringContains('Labels added: bug, needs-review', $activity);
        assertStringContains('/threads/root-001', $activity);
        assertStringContains('Source:', $activity);
        assertStringContains('records/posts/root-001.txt', $activity);
        assertStringContains('records/thread-labels/thread-label-20260415153000-ab12cd34.txt', $activity);
        assertStringContains('href="/source/current/records/posts/root-001.txt"', $activity);
        assertStringContains('href="/source/current/records/thread-labels/thread-label-20260415153000-ab12cd34.txt"', $activity);
        assertStringContains('Commit:', $activity);
        assertStringContains('commit unavailable', $activity);
        assertStringContains('Signature:', $activity);
        assertStringContains('anonymous unsigned', $activity);
        assertStringNotContains('@ commit unavailable', $activity);
        assertStringContains('GET /about/', $llms);
        assertStringContains('POST /api/analyze_post', $llms);
        assertStringContains('GET /api/list_index', $llms);
    }

    public function testBootstrapPostShowsUnavailablePublicKeyWhenProfileKeyIsEmpty(): void
    {
        @unlink($this->databasePath);
        $application = new Application(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
        );

        $this->render($application, '/');

        $pdo = new PDO('sqlite:' . $this->databasePath);
        $pdo->exec("UPDATE profiles SET public_key = '' WHERE profile_slug = 'openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954'");
        $pdo->exec("UPDATE posts SET board_tags_json = '[\"identity\",\"internal\"]' WHERE post_id = 'root-001'");

        $post = $this->render($application, '/posts/root-001');

        assertStringContains('Public key unavailable.', $post);
        assertStringNotContains('BEGIN PGP PUBLIC KEY BLOCK', $post);
    }

    public function testPostPagesExposeAvailableAuthorPublicKeyLink(): void
    {
        [$projectRoot, $repositoryRoot, $databasePath, $artifactRoot] = $this->createGitBackedEnvironmentWithArtifacts();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $this->render($application, '/posts/root-001');
        $pdo = new PDO('sqlite:' . $databasePath);
        $pdo->exec("UPDATE posts SET author_identity_id = NULL, author_profile_slug = NULL WHERE post_id = 'root-001'");
        $keylessPost = $this->render($application, '/posts/root-001');
        assertStringNotContains('/source/current/records/public-keys/', $keylessPost);

        $statement = $pdo->prepare(
            'UPDATE posts
             SET author_identity_id = :identity_id, author_profile_slug = :profile_slug
             WHERE post_id = :post_id'
        );
        $statement->execute([
            'identity_id' => 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954',
            'profile_slug' => 'openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954',
            'post_id' => 'root-001',
        ]);

        $thread = $this->render($application, '/threads/root-001');
        $post = $this->render($application, '/posts/root-001');
        $href = 'href="/source/current/records/public-keys/openpgp-0168FF20EB09C3EA6193BD3C92A73AA7D20A0954.asc"';

        assertStringNotContains($href, $thread);
        assertStringContains($href, $post);
        assertSame(1, substr_count($post, $href));
        assertOrdered($post, 'Signature:', 'Public key:');
    }

    public function testThreadRootCardOmitsDuplicateTitleLine(): void
    {
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-title-dedupe-repo-' . bin2hex(random_bytes(6));
        mkdir($repositoryRoot, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);

        file_put_contents(
            $repositoryRoot . '/records/posts/title-match-001.txt',
            "Post-ID: title-match-001\n"
            . "Created-At: 2026-04-10T13:00:00Z\n"
            . "Board-Tags: general\n"
            . "Subject: Echoes at Dawn\n"
            . "\n"
            . "Echoes at Dawn\n"
            . "Second poem line.\n"
            . "Third poem line.\n"
        );
        file_put_contents(
            $repositoryRoot . '/records/posts/title-empty-001.txt',
            "Post-ID: title-empty-001\n"
            . "Created-At: 2026-04-10T13:05:00Z\n"
            . "Board-Tags: general\n"
            . "Subject: Just the title\n"
            . "\n"
            . "Just the title\n"
        );
        file_put_contents(
            $repositoryRoot . '/records/posts/no-subject-long-001.txt',
            "Post-ID: no-subject-long-001\n"
            . "Created-At: 2026-04-10T13:10:00Z\n"
            . "Board-Tags: general\n"
            . "\n"
            . "This is a fairly long single-line post body that exceeds the eighty character excerpt limit used for titles when no subject is provided at all.\n"
        );
        file_put_contents(
            $repositoryRoot . '/records/posts/title-match-blank-line-001.txt',
            "Post-ID: title-match-blank-line-001\n"
            . "Created-At: 2026-04-10T13:15:00Z\n"
            . "Board-Tags: general\n"
            . "Subject: On Accessibility\n"
            . "\n"
            . "On Accessibility\n"
            . "\n"
            . "First real line.\n"
            . "Second real line.\n"
        );

        $databasePath = sys_get_temp_dir() . '/forum-rewrite-title-dedupe-db-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);

        $titleMatch = $this->render($application, '/threads/title-match-001');
        $titleEmpty = $this->render($application, '/threads/title-empty-001');
        $noSubjectLong = $this->render($application, '/threads/no-subject-long-001');
        $titleMatchBlankLine = $this->render($application, '/threads/title-match-blank-line-001');

        assertStringContains('<h1>Echoes at Dawn</h1>', $titleMatch);
        assertStringContains('<div class="body">Second poem line.', $titleMatch);
        assertStringNotContains('<div class="body">Echoes at Dawn', $titleMatch);
        assertStringContains('Third poem line.', $titleMatch);

        assertStringContains('<h1>Just the title</h1>', $titleEmpty);
        assertStringContains('<div class="body"></div>', $titleEmpty);

        assertStringMatches('/<h1>[^<]*\.\.\.<\/h1>/', $noSubjectLong);
        assertStringContains('This is a fairly long single-line post body that exceeds the eighty character excerpt limit used for titles when no subject is provided at all.', $noSubjectLong);

        assertStringContains('<h1>On Accessibility</h1>', $titleMatchBlankLine);
        assertStringContains('<div class="body">First real line.', $titleMatchBlankLine);
        assertStringNotContains('<div class="body"><br', $titleMatchBlankLine);
    }

    public function testThreadMergesSameAuthorQuickRepliesIntoContinuations(): void
    {
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-continuation-merge-repo-' . bin2hex(random_bytes(6));
        mkdir($repositoryRoot, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);

        file_put_contents(
            $repositoryRoot . '/records/posts/cont-merge-root.txt',
            "Post-ID: cont-merge-root\n"
            . "Created-At: 2026-05-01T10:00:00Z\n"
            . "Board-Tags: general\n"
            . "Subject: Continuation merge test\n"
            . "\n"
            . "Root body.\n"
        );
        file_put_contents(
            $repositoryRoot . '/records/posts/cont-merge-r1.txt',
            "Post-ID: cont-merge-r1\n"
            . "Created-At: 2026-05-01T10:05:00Z\n"
            . "Board-Tags: general\n"
            . "Thread-ID: cont-merge-root\n"
            . "Parent-ID: cont-merge-root\n"
            . "\n"
            . "First quick reply.\n"
        );
        file_put_contents(
            $repositoryRoot . '/records/posts/cont-merge-r2.txt',
            "Post-ID: cont-merge-r2\n"
            . "Created-At: 2026-05-01T10:12:00Z\n"
            . "Board-Tags: general\n"
            . "Thread-ID: cont-merge-root\n"
            . "Parent-ID: cont-merge-root\n"
            . "\n"
            . "Second quick reply.\n"
        );
        file_put_contents(
            $repositoryRoot . '/records/posts/cont-merge-r3.txt',
            "Post-ID: cont-merge-r3\n"
            . "Created-At: 2026-05-01T10:35:00Z\n"
            . "Board-Tags: general\n"
            . "Thread-ID: cont-merge-root\n"
            . "Parent-ID: cont-merge-root\n"
            . "\n"
            . "Late reply after a gap.\n"
        );
        file_put_contents(
            $repositoryRoot . '/records/posts/cont-merge-r4.txt',
            "Post-ID: cont-merge-r4\n"
            . "Created-At: 2026-05-01T10:52:00Z\n"
            . "Board-Tags: general\n"
            . "Thread-ID: cont-merge-root\n"
            . "Parent-ID: cont-merge-root\n"
            . "\n"
            . "Another late reply after a second gap.\n"
        );

        $databasePath = sys_get_temp_dir() . '/forum-rewrite-continuation-merge-db-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);

        $thread = $this->render($application, '/threads/cont-merge-root');

        $tags = [];
        foreach (['root', 'r1', 'r2', 'r3', 'r4'] as $suffix) {
            $postId = $suffix === 'root' ? 'cont-merge-root' : 'cont-merge-' . $suffix;
            assertTrue(
                preg_match('/<article id="post-' . preg_quote($postId, '/') . '"[^>]*>/', $thread, $match) === 1,
                'Expected to find article tag for ' . $postId . '.'
            );
            $tags[$suffix] = $match[0];
        }

        assertStringNotContains('continuation', $tags['root']);
        assertStringContains('continuation', $tags['r1']);
        assertStringContains('continuation', $tags['r2']);
        assertStringNotContains('continuation', $tags['r3']);
        assertStringNotContains('continuation', $tags['r4']);
        assertStringContains('data-author="guest"', $tags['root']);
        assertStringContains('data-author="guest"', $tags['r1']);

        // The byline moves to the run's LAST post (r2): root and r1 (the run's head and middle
        // piece) hide their meta and instead carry a hover-revealed time; r2 shows the meta inline.
        assertStringContains('meta-deferred', $tags['root']);
        assertStringContains('data-time="10:00"', $tags['root']);
        assertStringContains('meta-deferred', $tags['r1']);
        assertStringContains('data-time="10:05"', $tags['r1']);
        assertStringNotContains('meta-deferred', $tags['r2']);
        assertStringNotContains('data-time=', $tags['r2']);

        assertStringContains('<a class="post-card-permalink" href="/posts/cont-merge-r1"', $thread);
        assertStringContains('<a class="post-card-permalink" href="/posts/cont-merge-r2"', $thread);

        assertTrue(
            preg_match('/<article id="post-cont-merge-root"[^>]*>.*?<\/article>/s', $thread, $rootBlockMatch) === 1,
            'Expected to find the root card block.'
        );
        assertStringNotContains('<p class="meta">', $rootBlockMatch[0]);

        assertTrue(
            preg_match('/<article id="post-cont-merge-r2"[^>]*>.*?<p class="meta">(.*?)<\/p>/s', $thread, $metaMatch) === 1,
            'Expected to find the run tail (r2) meta line.'
        );
        assertStringContains('2 replies', $metaMatch[1]);

        exec('rm -rf ' . escapeshellarg($repositoryRoot));
        @unlink($databasePath);
    }

    public function testThreadCardOmitsDuplicatePreviewLine(): void
    {
        $renderer = new \ForumRewrite\View\TemplateRenderer(dirname(__DIR__) . '/templates');

        $matchingThread = [
            'root_post_id' => 'card-preview-match-001',
            'subject' => 'Just a Title',
            'body_preview' => 'Just a Title',
            'thread_labels' => [],
            'reply_count' => 0,
            'last_activity_at' => null,
            'root_post_created_at' => null,
        ];
        $differingThread = [
            'root_post_id' => 'card-preview-differ-001',
            'subject' => 'Different Title',
            'body_preview' => 'Actual preview text',
            'thread_labels' => [],
            'reply_count' => 0,
            'last_activity_at' => null,
            'root_post_created_at' => null,
        ];
        $longNoSubjectBody = 'This is another fairly long single-line post body that exceeds the eighty character excerpt limit for titles when no subject is provided.';
        $noSubjectLongThread = [
            'root_post_id' => 'card-preview-no-subject-long-001',
            'subject' => '',
            'body_preview' => $longNoSubjectBody,
            'thread_labels' => [],
            'reply_count' => 0,
            'last_activity_at' => null,
            'root_post_created_at' => null,
        ];

        $matchingCard = $renderer->renderFragment('partials/thread_card.php', ['thread' => $matchingThread]);
        $differingCard = $renderer->renderFragment('partials/thread_card.php', ['thread' => $differingThread]);
        $noSubjectLongCard = $renderer->renderFragment('partials/thread_card.php', ['thread' => $noSubjectLongThread]);

        assertStringContains('<h2><a href="/threads/card-preview-match-001">Just a Title</a></h2>', $matchingCard);
        assertStringNotContains('thread-card__preview', $matchingCard);

        assertStringContains('<h2><a href="/threads/card-preview-differ-001">Different Title</a></h2>', $differingCard);
        assertStringContains('<p class="thread-card__preview">Actual preview text</p>', $differingCard);

        assertStringMatches('/<h2><a[^>]*>[^<]*\.\.\.<\/a><\/h2>/', $noSubjectLongCard);
        assertStringContains('<p class="thread-card__preview">' . $longNoSubjectBody . '</p>', $noSubjectLongCard);
    }

    public function testPostAndActivityLinkAdjacentSignatureFiles(): void
    {
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-signature-repo-' . bin2hex(random_bytes(6));
        mkdir($repositoryRoot, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);
        file_put_contents($repositoryRoot . '/records/posts/root-001.txt.asc', "detached signature\n");
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-signature-db-' . bin2hex(random_bytes(6)) . '.sqlite3';

        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);

        $post = $this->render($application, '/posts/root-001');
        $activity = $this->render($application, '/activity/?view=content');
        $forteActivity = $this->render($application, '/forte/activity/?view=content');
        $signature = $this->render($application, '/source/current/records/posts/root-001.txt.asc');

        assertStringContains('Signature:', $post);
        assertStringContains('href="/source/current/records/posts/root-001.txt.asc"', $post);
        assertStringContains('Signature:', $activity);
        assertStringContains('href="/source/current/records/posts/root-001.txt.asc"', $activity);
        assertStringContains('Signature:', $forteActivity);
        assertStringContains('href="/source/current/records/posts/root-001.txt.asc"', $forteActivity);
        assertSame("detached signature\n", $signature);
    }

    public function testAppVersionNotificationCanBeDisabled(): void
    {
        $previousFlag = getenv('FORUM_APP_VERSION_NOTIFICATION');
        putenv('FORUM_APP_VERSION_NOTIFICATION=false');

        try {
            @unlink($this->databasePath);
            $application = new Application(
                dirname(__DIR__),
                $this->repositoryRoot,
                $this->databasePath,
            );

            $board = $this->render($application, '/');

            assertStringNotContains('meta name="app-version"', $board);
            assertStringNotContains('meta name="app-version-endpoint"', $board);
            assertStringNotContains('/assets/version_check.', $board);
            assertStringNotContains('data-role="app-version-banner"', $board);
            assertStringNotContains('A new version is available.', $board);
            assertFingerprintedAsset($board, 'site.css');
            assertFingerprintedAsset($board, 'compose_draft_clear.js');
            assertFingerprintedAsset($board, 'theme_toggle.js');
        } finally {
            if ($previousFlag === false) {
                putenv('FORUM_APP_VERSION_NOTIFICATION');
            } else {
                putenv('FORUM_APP_VERSION_NOTIFICATION=' . $previousFlag);
            }
        }
    }

    public function testThreadDensityToggleIsHiddenByDefaultAndShownWhenFlagEnabled(): void
    {
        $previousFlag = getenv('FORUM_THREAD_DENSITY_TOGGLE_ENABLED');
        putenv('FORUM_THREAD_DENSITY_TOGGLE_ENABLED=true');

        try {
            @unlink($this->databasePath);
            $application = new Application(
                dirname(__DIR__),
                $this->repositoryRoot,
                $this->databasePath,
            );

            $board = $this->render($application, '/');
            $tagPage = $this->render($application, '/tags/bug');
            $thread = $this->render($application, '/threads/root-001');

            assertFingerprintedAsset($board, 'thread_density_toggle.js');
            assertStringContains('data-role="thread-density-toggle"', $board);
            assertStringContains('data-role="thread-density-toggle"', $tagPage);
            assertStringNotContains('data-role="thread-density-toggle"', $thread);
            assertStringContains("var densityStorageKey = 'zenmemes-thread-density';", $board);
            assertStringContains("var densityStorageKey = 'zenmemes-thread-density';", $tagPage);
            assertStringNotContains("var densityStorageKey = 'zenmemes-thread-density';", $thread);
            assertStringContains('data-role="thread-density-menu"', $board);
            assertStringContains('data-thread-density-option="comfortable"', $board);
            assertStringContains('data-thread-density-option="compact"', $board);
            assertStringContains('thread-density-menu-popover', $board);
        } finally {
            if ($previousFlag === false) {
                putenv('FORUM_THREAD_DENSITY_TOGGLE_ENABLED');
            } else {
                putenv('FORUM_THREAD_DENSITY_TOGGLE_ENABLED=' . $previousFlag);
            }
        }
    }

    public function testFeatureFlagsPageShowsSiteBackedValuesAndLayoutUsesThem(): void
    {
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-flags-render-' . bin2hex(random_bytes(6));
        mkdir($repositoryRoot, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);
        file_put_contents(
            $repositoryRoot . '/records/instance/feature-flags.txt',
            "Schema: site-feature-flags-v1\n\nFORUM_APP_VERSION_NOTIFICATION: false\n"
        );
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-flags-render-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);

        $board = $this->render($application, '/');
        $featureFlags = $this->render($application, '/tools/feature-flags/');

        assertStringNotContains('meta name="app-version"', $board);
        assertStringNotContains('/assets/version_check.', $board);
        assertStringContains('FORUM_APP_VERSION_NOTIFICATION', $featureFlags);
        assertStringContains('data-role="feature-flag-source">site</span>', $featureFlags);
        assertStringContains('badge-overridden', $featureFlags);
    }

    public function testFeatureFlagsPageReportsInvalidSiteRecordWithoutBreakingSite(): void
    {
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-flags-invalid-' . bin2hex(random_bytes(6));
        mkdir($repositoryRoot, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);
        file_put_contents(
            $repositoryRoot . '/records/instance/feature-flags.txt',
            "Schema: site-feature-flags-v1\n\nFORUM_APP_VERSION_NOTIFICATION: nope\n"
        );
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-flags-invalid-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);

        $board = $this->render($application, '/');
        $featureFlags = $this->render($application, '/tools/feature-flags/');

        assertStringContains('meta name="app-version"', $board);
        assertStringContains('FORUM_APP_VERSION_NOTIFICATION', $featureFlags);
        assertStringContains('data-role="feature-flag-source">invalid-site-value</span>', $featureFlags);
        assertStringContains('feedback feedback-error', $featureFlags);
    }

    public function testFeatureFlagsPageShowsLockedBadgeWithReasonForNonMutableFlags(): void
    {
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-flags-locked-' . bin2hex(random_bytes(6));
        mkdir($repositoryRoot, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-flags-locked-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);

        $featureFlags = $this->render($application, '/tools/feature-flags/');

        assertStringContains('badge-locked', $featureFlags);
        assertStringContains('title="Not configurable from the site."', $featureFlags);
        assertStringContains('locked</span>', $featureFlags);
        assertStringContains('feature-flag-info-icon', $featureFlags);
        assertStringContains('title="Not configurable from the site." aria-label="Not configurable from the site."', $featureFlags);
    }

    public function testFeatureFlagsPageDimsAndWarnsOnDependencyBlockedFlag(): void
    {
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-flags-dependency-' . bin2hex(random_bytes(6));
        mkdir($repositoryRoot, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);
        file_put_contents(
            $repositoryRoot . '/records/instance/feature-flags.txt',
            "Schema: site-feature-flags-v1\n\nFORUM_EMOJI_AUTHORED_TEXT: true\nFORUM_UNICODE_AUTHORED_TEXT: false\n"
        );
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-flags-dependency-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);

        $featureFlags = $this->render($application, '/tools/feature-flags/');

        assertStringContains('feature-flag-row is-blocked', $featureFlags);
        assertStringContains('inactive', $featureFlags);
        assertStringContains('requires Unicode authored text', $featureFlags);
    }

    public function testNegativeRootScoreIsFilteredOnlyFromLikedBoardListings(): void
    {
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-negative-liked-fixture-' . bin2hex(random_bytes(6));
        mkdir($repositoryRoot, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);
        mkdir($repositoryRoot . '/records/post-reactions');
        file_put_contents(
            $repositoryRoot . '/records/post-reactions/post-reaction-20260415153100-ab12cd35.txt',
            "Record-ID: post-reaction-20260415153100-ab12cd35\nCreated-At: 2026-04-15T15:31:00Z\nPost-ID: root-001\nOperation: add\nTags: flag\nAuthor-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954\n\n"
        );
        file_put_contents(
            $repositoryRoot . '/records/thread-labels/thread-label-20260415153200-ab12cd36.txt',
            "Record-ID: thread-label-20260415153200-ab12cd36\nCreated-At: 2026-04-15T15:32:00Z\nThread-ID: root-001\nOperation: add\nLabels: like\nAuthor-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954\n\n"
        );

        $databasePath = sys_get_temp_dir() . '/forum-rewrite-negative-liked-' . bin2hex(random_bytes(6)) . '.sqlite3';
        @unlink($databasePath);
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);

        $rootDefault = $this->render($application, '/');
        $likedNewest = $this->render($application, '/threads/?view=liked&sort=newest');
        $likedOldest = $this->render($application, '/threads/?view=liked&sort=oldest');
        $likedTop = $this->render($application, '/threads/?view=liked&sort=top');
        $allThreads = $this->render($application, '/threads/?view=all');
        $thread = $this->render($application, '/threads/root-001');
        $post = $this->render($application, '/posts/root-001');

        assertStringNotContains('href="/threads/root-001"', $rootDefault);
        assertStringNotContains('href="/threads/root-001"', $likedNewest);
        assertStringNotContains('href="/threads/root-001"', $likedOldest);
        assertStringNotContains('href="/threads/root-001"', $likedTop);
        assertStringContains('href="/threads/root-001"', $allThreads);
        assertStringContains('id="post-root-001"', $thread);
        assertStringContains('id="post-reply-001"', $thread);
        assertStringContains('Score: -100', $post);

        $this->deleteTree($repositoryRoot);
        @unlink($databasePath);
    }

    public function testApplicationRendersTextApisAndRss(): void
    {
        @unlink($this->databasePath);
        $application = new Application(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
        );

        $apiIndex = $this->render($application, '/api/');
        $listIndex = $this->render($application, '/api/list_index');
        $thread = $this->render($application, '/api/get_thread?thread_id=root-001');
        $post = $this->render($application, '/api/get_post?post_id=root-001');
        $profile = $this->render($application, '/api/get_profile?profile_slug=openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954');
        $version = $this->render($application, '/api/version');
        $readModelStatus = $this->render($application, '/api/read_model_status');
        $boardRss = $this->render($application, '/?format=rss');
        $threadRss = $this->render($application, '/threads/root-001?format=rss');
        $activityRss = $this->render($application, '/activity/?view=all&format=rss');

        assertStringContains('GET /api/version', $apiIndex);
        assertStringContains('GET /api/get_thread?thread_id=<id>', $apiIndex);
        assertStringContains('POST /api/analyze_post', $apiIndex);
        assertStringContains("root-001\tHello world\t1", $listIndex);
        assertStringContains('Created-At: 2026-04-10T12:00:00Z', $thread);
        assertStringContains('Last-Activity-At: 2026-04-10T12:05:00Z', $thread);
        assertStringContains('Score-Total: 0', $thread);
        assertStringContains('Labels: bug needs-review', $thread);
        assertStringContains('Thread-ID: root-001', $thread);
        assertStringContains('Post-ID: root-001', $post);
        assertStringContains('Created-At: 2026-04-10T12:00:00Z', $post);
        assertStringContains('Profile-Slug: openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954', $profile);
        assertStringContains('Approved: yes', $profile);
        assertSame("no-git\n", $version);
        assertStringContains('status=ready', $readModelStatus);
        assertStringContains('lock_status=unlocked', $readModelStatus);
        assertStringContains('stale_marker=absent', $readModelStatus);
        assertStringContains('schema_version=13', $readModelStatus);
        assertStringContains('<rss version="2.0">', $boardRss);
        assertStringContains('<title>Hello world</title>', $threadRss);
        assertStringContains('<pubDate>Fri, 10 Apr 2026 12:05:00 +0000</pubDate>', $threadRss);
        assertStringContains('<title>Activity all</title>', $activityRss);
    }

    public function testActivityShowsGitSourceCommitForCanonicalRecords(): void
    {
        [, $repositoryRoot, $databasePath, $artifactRoot] = $this->createGitBackedEnvironmentWithArtifacts();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $activity = $this->render($application, '/activity/?view=content');
        $postCommitSha = trim($this->runCommand($repositoryRoot, 'git log -1 --format=%H -- records/posts/root-001.txt'));

        assertStringContains('records/posts/root-001.txt', $activity);
        assertStringContains('href="/source/blob/' . $postCommitSha . '/records/posts/root-001.txt"', $activity);
        assertStringContains('href="/source/commits/' . $postCommitSha . '"', $activity);
        assertStringContains('title="' . $postCommitSha . '"', $activity);
        assertStringContains('Commit:', $activity);
        assertStringContains('>' . substr($postCommitSha, 0, 12) . '</a>', $activity);
        assertStringNotContains('@ ' . substr($postCommitSha, 0, 12), $activity);
    }

    public function testActivityCommitManifestLabelsCanonicalRecordsAndSafeLinks(): void
    {
        [, $repositoryRoot, $databasePath, $artifactRoot] = $this->createGitBackedEnvironmentWithArtifacts();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->render($application, '/activity/?view=content');
        $method = new ReflectionMethod($application, 'fetchActivity');
        $items = $method->invoke($application, 'content')['items'];
        $postCommitSha = trim($this->runCommand($repositoryRoot, 'git log -1 --format=%H -- records/posts/root-001.txt'));

        $item = array_values(array_filter(
            $items,
            static fn (array $item): bool => $item['post_id'] === 'root-001'
        ))[0];
        $entry = array_values(array_filter(
            $item['source_commit_files'],
            static fn (array $entry): bool => $entry['path'] === 'records/posts/root-001.txt'
        ))[0];

        assertSame('post record', $entry['role']);
        assertSame('/source/blob/' . $postCommitSha . '/records/posts/root-001.txt', $entry['href']);
    }

    public function testActivityCommitManifestLinksSignatureSignerKeyOutsideCommit(): void
    {
        [, $repositoryRoot, $databasePath, $artifactRoot] = $this->createGitBackedEnvironmentWithArtifacts();
        $signaturePath = 'records/thread-labels/thread-label-20260415153000-ab12cd34.txt.asc';
        file_put_contents($repositoryRoot . '/' . $signaturePath, "detached signature\n");
        $this->runCommand($repositoryRoot, 'git add ' . escapeshellarg($signaturePath));
        $this->runCommand($repositoryRoot, 'git commit -m "Add detached label signature"');

        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $method = new ReflectionMethod($application, 'activityCommitManifest');
        $commitSha = trim($this->runCommand($repositoryRoot, 'git rev-parse HEAD'));
        $manifest = $method->invoke($application, $commitSha);
        $entry = array_values(array_filter(
            $manifest,
            static fn (array $entry): bool => $entry['path'] === $signaturePath
        ))[0];
        $fingerprint = '0168FF20EB09C3EA6193BD3C92A73AA7D20A0954';
        $publicKeyPath = 'records/public-keys/openpgp-' . $fingerprint . '.asc';

        assertSame('openpgp:' . strtolower($fingerprint), $entry['signature_signer_identity']);
        assertSame($publicKeyPath, $entry['signature_public_key_path']);
        assertSame('/source/current/' . $publicKeyPath, $entry['signature_public_key_href']);
        assertSame('ok', $entry['signature_key_status']);
    }

    public function testClassicAndForteActivityRenderTheSameCommitManifest(): void
    {
        [, $repositoryRoot, $databasePath, $artifactRoot] = $this->createGitBackedEnvironmentWithArtifacts();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $classic = $this->render($application, '/activity/?view=content');
        $pdo = new PDO('sqlite:' . $databasePath);
        $activityId = (int) $pdo->query("SELECT id FROM activity WHERE source_path = 'records/posts/root-001.txt'")->fetchColumn();
        $fortePayload = json_decode($this->render($application, '/api/forte_activity_detail?id=' . $activityId), true);
        $forte = (string) $fortePayload['html'];

        assertStringContains('Commit files (', $classic);
        assertStringContains('Relevant files (', $forte);
        assertStringContains('records/posts/root-001.txt', $classic);
        assertStringContains('records/posts/root-001.txt', $forte);
        assertStringContains('post record', $classic);
        assertStringContains('post record', $forte);
        assertStringContains('class="activity-commit-manifest__path"', $classic);
        assertStringContains('class="activity-commit-manifest__path"', $forte);
        assertStringContains('Commit:', $classic);
        assertStringContains('Commit:', $forte);
        assertStringNotContains('<p class="meta">Source:', $classic);
        assertStringNotContains('<p class="meta">Source:', $forte);
    }

    public function testClassicAndForteActivityRenderSignatureKeyOutsideCommit(): void
    {
        [, $repositoryRoot, $databasePath, $artifactRoot] = $this->createGitBackedEnvironmentWithArtifacts();
        $labelPath = 'records/thread-labels/thread-label-20260415153000-ab12cd34.txt';
        $signaturePath = $labelPath . '.asc';
        file_put_contents($repositoryRoot . '/' . $labelPath, (string) file_get_contents($repositoryRoot . '/' . $labelPath) . "\n");
        file_put_contents($repositoryRoot . '/' . $signaturePath, "detached signature\n");
        $this->runCommand($repositoryRoot, 'git add ' . escapeshellarg($labelPath) . ' ' . escapeshellarg($signaturePath));
        $this->runCommand($repositoryRoot, 'git commit -m "Sign label record"');

        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $classic = $this->render($application, '/activity/?view=all');
        $pdo = new PDO('sqlite:' . $databasePath);
        $activityId = (int) $pdo->query('SELECT id FROM activity WHERE source_path = ' . $pdo->quote($labelPath))->fetchColumn();
        $fortePayload = json_decode($this->render($application, '/api/forte_activity_detail?id=' . $activityId), true);
        $forte = (string) $fortePayload['html'];
        $publicKeyPath = 'records/public-keys/openpgp-0168FF20EB09C3EA6193BD3C92A73AA7D20A0954.asc';

        assertTrue(preg_match('/Signer:\s+openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954/', $classic) === 1);
        assertTrue(preg_match('/Signer:\s+openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954/', $forte) === 1);
        assertStringContains('class="activity-commit-manifest__signature-divider"', $classic);
        assertStringContains('class="activity-commit-manifest__signature-divider"', $forte);
        assertTrue(preg_match('#Public key:\s+<a href="/source/current/' . preg_quote($publicKeyPath, '#') . '"#', $classic) === 1);
        assertTrue(preg_match('#Public key:\s+<a href="/source/current/' . preg_quote($publicKeyPath, '#') . '"#', $forte) === 1);
    }

    public function testEveryActivityItemWithSourceCommitCarriesManifestData(): void
    {
        [, $repositoryRoot, $databasePath, $artifactRoot] = $this->createGitBackedEnvironmentWithArtifacts();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->render($application, '/activity/?view=all');
        $method = new ReflectionMethod($application, 'fetchActivity');
        $items = $method->invoke($application, 'all')['items'];

        foreach ($items as $item) {
            assertTrue(array_key_exists('source_commit_files', $item));
            if (preg_match('/^[a-f0-9]{40}$/i', (string) $item['source_commit_sha']) === 1) {
                assertTrue($item['source_commit_files'] !== []);
            }
        }
    }

    public function testActivityRowsStayLightweightUntilDetailRequested(): void
    {
        [, $repositoryRoot, $databasePath, $artifactRoot] = $this->createGitBackedEnvironmentWithArtifacts();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->render($application, '/activity/?view=all');
        $service = new ActivityService(
            static fn (): PDO => new PDO('sqlite:' . $databasePath),
            $repositoryRoot,
            $databasePath,
        );

        $rows = $service->fetchActivityRows('all', 'date', 'desc')['items'];
        $items = $service->fetchActivity('all', 'date', 'desc')['items'];

        assertSame(
            array_column($items, 'id'),
            array_map(static fn (array $row): int => (int) $row['id'], $rows),
        );
        assertFalse(array_key_exists('source_commit_files', $rows[0]));

        $detail = $service->fetchActivityDetail((int) $rows[0]['id']);
        assertSame((int) $rows[0]['id'], $detail['id']);
        assertTrue(array_key_exists('source_commit_files', $detail));
    }

    public function testActivityFetchLimitsAfterApplyingViewFilter(): void
    {
        @unlink($this->databasePath);
        $application = new Application(dirname(__DIR__), $this->repositoryRoot, $this->databasePath);
        $this->render($application, '/api/read_model_status');

        $pdo = new PDO('sqlite:' . $this->databasePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $insert = $pdo->prepare(
            'INSERT INTO activity (
                created_at, kind, post_id, thread_id, label, board_tags_json,
                author_identity_id, author_profile_slug, author_username_token, author_label, author_is_approved,
                source_path, source_commit_sha
             ) VALUES (
                :created_at, :kind, NULL, NULL, :label, :board_tags_json,
                NULL, NULL, NULL, :author_label, 1,
                NULL, NULL
             )'
        );

        for ($i = 0; $i < 120; $i++) {
            $insert->execute([
                'created_at' => sprintf('2026-06-01T12:%02d:00Z', $i % 60),
                'kind' => 'identity_test',
                'label' => 'new identity row ' . $i,
                'board_tags_json' => '["identity","internal"]',
                'author_label' => 'identity test',
            ]);
        }

        for ($i = 0; $i < 3; $i++) {
            $insert->execute([
                'created_at' => sprintf('2026-05-01T12:%02d:00Z', $i),
                'kind' => 'content_test',
                'label' => 'older content row ' . $i,
                'board_tags_json' => '["general"]',
                'author_label' => 'content test',
            ]);
        }

        $method = new ReflectionMethod($application, 'fetchActivity');
        $method->setAccessible(true);
        $contentItems = $method->invoke($application, 'content')['items'];
        $identityItems = $method->invoke($application, 'identity')['items'];

        $contentLabels = array_map(static fn (array $item): string => (string) $item['label'], $contentItems);
        $identityLabels = array_map(static fn (array $item): string => (string) $item['label'], $identityItems);

        assertSame(true, count($identityItems) <= 100);
        assertStringContains('older content row 0', implode("\n", $contentLabels));
        assertStringContains('older content row 1', implode("\n", $contentLabels));
        assertStringContains('older content row 2', implode("\n", $contentLabels));
        assertStringNotContains('new identity row', implode("\n", $contentLabels));
        assertStringContains('new identity row', implode("\n", $identityLabels));
    }

    public function testCurrentSourceRouteServesOnlyCanonicalRecordFiles(): void
    {
        @unlink($this->databasePath);
        $application = new Application(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
        );

        $postSource = $this->render($application, '/source/current/records/posts/root-001.txt');
        $labelSource = $this->render($application, '/source/current/records/thread-labels/thread-label-20260415153000-ab12cd34.txt');
        $traversal = $this->render($application, '/source/current/records/posts/..%2F..%2FREADME.md');
        $absolute = $this->render($application, '/source/current/%2Frecords%2Fposts%2Froot-001.txt');
        $missing = $this->render($application, '/source/current/records/posts/missing.txt');

        assertStringContains('Post-ID: root-001', $postSource);
        assertStringContains('Subject: Hello world', $postSource);
        assertStringContains('Record-ID: thread-label-20260415153000-ab12cd34', $labelSource);
        assertSame("Invalid source path\n", $traversal);
        assertSame("Invalid source path\n", $absolute);
        assertSame("Source not found\n", $missing);
    }

    public function testBlobSourceRouteServesCanonicalRecordAtCommit(): void
    {
        [, $repositoryRoot, $databasePath, $artifactRoot] = $this->createGitBackedEnvironmentWithArtifacts();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $commitSha = trim($this->runCommand($repositoryRoot, 'git log -1 --format=%H -- records/posts/root-001.txt'));

        $postSource = $this->render($application, '/source/blob/' . $commitSha . '/records/posts/root-001.txt');
        $invalidSha = $this->render($application, '/source/blob/not-a-sha/records/posts/root-001.txt');
        $missing = $this->render($application, '/source/blob/' . $commitSha . '/records/posts/missing.txt');
        $traversal = $this->render($application, '/source/blob/' . $commitSha . '/records/posts/..%2F..%2FREADME.md');

        assertStringContains('Post-ID: root-001', $postSource);
        assertStringContains('Subject: Hello world', $postSource);
        assertSame("Invalid source commit\n", $invalidSha);
        assertSame("Source not found\n", $missing);
        assertSame("Invalid source path\n", $traversal);
    }

    public function testSourceCommitRouteShowsCommitDetails(): void
    {
        [, $repositoryRoot, $databasePath, $artifactRoot] = $this->createGitBackedEnvironmentWithArtifacts();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $commitSha = trim($this->runCommand($repositoryRoot, 'git rev-parse HEAD'));

        $commitDetails = $this->render($application, '/source/commits/' . $commitSha);
        $invalidSha = $this->render($application, '/source/commits/not-a-sha');
        $missingCommit = $this->render($application, '/source/commits/0000000000000000000000000000000000000000');

        assertStringContains('Commit: ' . $commitSha, $commitDetails);
        assertStringContains('Author-Date:', $commitDetails);
        assertStringContains('Subject: Initialize local repository', $commitDetails);
        assertStringContains('records/posts/root-001.txt', $commitDetails);
        assertSame("Invalid source commit\n", $invalidSha);
        assertSame("Commit not found\n", $missingCommit);
    }

    public function testSourceCommitRouteListsChangedFileStatuses(): void
    {
        [, $repositoryRoot, $databasePath, $artifactRoot] = $this->createGitBackedEnvironmentWithArtifacts();
        mkdir($repositoryRoot . '/scratch');
        file_put_contents($repositoryRoot . '/scratch/modified.txt', "before\n");
        file_put_contents($repositoryRoot . '/scratch/renamed.txt', "rename me\n");
        file_put_contents($repositoryRoot . '/scratch/deleted.txt', "delete me\n");
        $this->runCommand($repositoryRoot, 'git add scratch');
        $this->runCommand($repositoryRoot, 'git commit -m "Add source manifest fixtures"');

        file_put_contents($repositoryRoot . '/scratch/modified.txt', "after\n");
        rename($repositoryRoot . '/scratch/renamed.txt', $repositoryRoot . '/scratch/renamed-next.txt');
        unlink($repositoryRoot . '/scratch/deleted.txt');
        file_put_contents($repositoryRoot . '/scratch/added.txt', "add me\n");
        $this->runCommand($repositoryRoot, 'git add -A scratch');
        $this->runCommand($repositoryRoot, 'git commit -m "Change source manifest fixtures"');

        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $commitSha = trim($this->runCommand($repositoryRoot, 'git rev-parse HEAD'));
        $details = $this->render($application, '/source/commits/' . $commitSha);

        assertStringContains('added scratch/added.txt', $details);
        assertStringContains('modified scratch/modified.txt', $details);
        assertStringContains('deleted scratch/deleted.txt', $details);
        assertStringContains('renamed scratch/renamed.txt -> scratch/renamed-next.txt', $details);
    }

    public function testToolsPageRendersBookmarkletsAndComposeThreadAcceptsPrefills(): void
    {
        @unlink($this->databasePath);
        $application = new Application(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
        );

        $tools = $this->render($application, '/tools/');
        $prefilledCompose = $this->render(
            $application,
            '/compose/thread?board_tags=general&subject=Saved%20Title&body=Saved%20Body'
        );

        $bookmarklets = $this->render($application, '/tools/bookmarklets/');
        $bookmarkletAsset = (string) file_get_contents(__DIR__ . '/../public/assets/tools_bookmarklets.js');

        assertStringContains('Tools', $tools);
        assertStringContains('class="nav-link is-active" href="/tools/"', $tools);
        assertStringContains('Activity', $tools);
        assertStringContains('Bookmarklets', $tools);
        assertStringContains('Backup', $tools);
        assertStringContains('/activity/', $tools);
        assertStringContains('/tools/bookmarklets/', $tools);
        assertStringContains('/tools/backup/', $tools);
        assertStringContains('/tools/feature-flags/', $tools);
        assertFingerprintedAsset($bookmarklets, 'tools_bookmarklets.js');
        assertStringContains('class="nav"', $bookmarklets);
        assertStringContains('class="nav-link is-active" href="/tools/bookmarklets/"', $bookmarklets);
        assertStringNotContains('class="nav-link" href="/tools/">Tools</a>', $bookmarklets);
        assertStringContains('data-bookmarklet-kind="clip"', $bookmarklets);
        assertStringContains('data-bookmarklet-kind="tweet"', $bookmarklets);
        assertStringContains('window.getSelection().toString().trim()', $bookmarkletAsset);
        assertStringContains('tweetComposeUrl', $bookmarkletAsset);
        $word97Css = (string) file_get_contents(__DIR__ . '/../public/assets/theme-word97.css');
        assertStringContains(':root[data-theme="word97"] .tool-launcher-button[data-bookmarklet-kind="tweet"]::before', $word97Css);
        assertStringContains(':root[data-theme="word97"] .tool-launcher-button[href="/forte"]::before', $word97Css);
        assertStringContains(':root[data-theme="word97"] .tool-launcher-button[href="/tools/sqlite/"]::before', $word97Css);
        assertStringContains(':root[data-theme="word97"] .tool-launcher-button[href="/tools/llm-exchanges/"]::before', $word97Css);
        assertStringContains('value="Saved Title"', $prefilledCompose);
        assertStringContains('>Saved Body</textarea>', $prefilledCompose);
    }

    public function testComposePagesRenderNormalizationStatusAndRemoveAction(): void
    {
        @unlink($this->databasePath);
        $application = new Application(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
        );

        $thread = $this->render($application, '/compose/thread');
        $reply = $this->render($application, '/compose/reply?thread_id=root-001&parent_id=root-001');

        assertStringContains('compose-normalization-inline', $thread);
        assertStringContains('data-unicode-authored-text="0"', $thread);
        assertStringContains('data-emoji-authored-text="0"', $thread);
        assertStringContains('data-role="compose-normalization-status"', $thread);
        assertStringContains('data-role="compose-normalization-message"', $thread);
        assertStringContains('data-role="compose-field-normalization-status"', $thread);
        assertStringContains('data-compose-field-status-for="board_tags"', $thread);
        assertStringContains('data-compose-field-status-for="subject"', $thread);
        assertStringContains('data-compose-field-status-for="body"', $thread);
        assertStringContains('data-compose-field-label="Subject"', $thread);
        assertStringContains('data-compose-field-label="Body"', $thread);
        assertStringContains('data-action="remove-unsupported-compose-characters"', $thread);
        assertStringContains('data-compose-field-remove-for="board_tags"', $thread);
        assertStringContains('data-compose-field-remove-for="subject"', $thread);
        assertStringContains('data-compose-field-remove-for="body"', $thread);
        assertStringContains('hidden', $thread);
        assertStringContains('data-unicode-authored-text="0"', $reply);
        assertStringContains('data-emoji-authored-text="0"', $reply);
        assertStringContains('compose-normalization-inline', $reply);
        assertStringContains('data-role="compose-normalization-status"', $reply);
        assertStringContains('data-role="compose-normalization-message"', $reply);
        assertStringContains('data-compose-field-status-for="body"', $reply);
        assertStringContains('data-compose-field-label="Body"', $reply);
        assertStringContains('data-action="remove-unsupported-compose-characters"', $reply);
        assertStringContains('data-compose-field-remove-for="body"', $reply);
        assertStringContains('type="hidden" name="board_tags" value="general"', $reply);
        assertStringNotContains('<label>Board tags', $reply);
        assertStringNotContains('data-compose-field-status-for="board_tags"', $reply);
        assertStringNotContains('data-compose-field-remove-for="board_tags"', $reply);
        assertStringContains('hidden', $reply);
    }

    public function testComposeThreadSubmitErrorPreservesEnteredValues(): void
    {
        @unlink($this->databasePath);
        $application = new Application(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
        );

        $response = $this->renderMethod(
            $application,
            'POST',
            '/compose/thread?board_tags=ios&subject=Bad%E2%80%99Title&body=Saved%20Body'
        );
        $decoded = html_entity_decode($response, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        assertStringContains('value="ios"', $response);
        assertStringContains('value="Bad’Title"', $decoded);
        assertStringContains('>Saved Body</textarea>', $decoded);
    }

    public function testComposeReplySubmitErrorPreservesEnteredValues(): void
    {
        @unlink($this->databasePath);
        $application = new Application(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
        );

        $response = $this->renderMethod(
            $application,
            'POST',
            '/compose/reply?thread_id=root-001&parent_id=root-001&board_tags=custom&body=Emoji%20%F0%9F%99%82'
        );
        $decoded = html_entity_decode($response, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        assertStringContains('value="custom"', $response);
        assertStringContains('>Emoji 🙂</textarea>', $decoded);
    }

    public function testInstanceDownloadRoutesReturnRepositoryArchivesAndSqliteDatabase(): void
    {
        @unlink($this->databasePath);
        $application = new Application(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
        );

        $repoArchive = $this->renderMethod($application, 'GET', '/downloads/repository.tar.gz');
        $repoZipArchive = $this->renderMethod($application, 'GET', '/downloads/repository.zip');
        $sqliteDatabase = $this->renderMethod($application, 'GET', '/downloads/read_model.sqlite3');
        $queryPack = $this->renderMethod($application, 'GET', '/downloads/sqlite_query_catalog.sql');

        assertSame("\x1f\x8b", substr($repoArchive, 0, 2));
        assertSame("PK", substr($repoZipArchive, 0, 2));
        assertStringContains('SQLite format 3', substr($sqliteDatabase, 0, 32));
        assertStringContains('-- SQLite Viewer Query Catalog', $queryPack);
        assertStringContains('-- Board: Liked + Newest', $queryPack);
    }

    public function testSqliteViewerRouteUsesToolsShellAndPublishedSource(): void
    {
        @unlink($this->databasePath);
        $application = new Application(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
        );

        $viewer = $this->render($application, '/tools/sqlite/');

        assertStringContains('<h1>SQLite Viewer</h1>', $viewer);
        assertStringContains('<section class="stack" data-sqlite-viewer>', $viewer);
        assertStringContains('href="/downloads/read_model.sqlite3"', $viewer);
        assertStringContains('href="/downloads/sqlite_query_catalog.sql"', $viewer);
        assertStringContains('queries run locally', $viewer);
        assertStringContains('class="nav-link is-active" href="/tools/sqlite/"', $viewer);
        assertStringContains('href="/tools/sqlite/"', $this->render($application, '/tools/'));
    }

    public function testOfflineHealthRouteReportsDeviceReadiness(): void
    {
        $application = new Application(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
        );

        $health = $this->render($application, '/offline/');
        $tools = $this->render($application, '/tools/');
        $healthScript = (string) file_get_contents(dirname(__DIR__) . '/public/assets/offline_health.js');

        assertStringContains('data-offline-health', $health);
        assertStringContains('data-reader-url="/offline/reader/"', $health);
        assertStringContains('data-snapshot-url="/offline/snapshot.sqlite3"', $health);
        assertStringContains('data-role="offline-health-summary"', $health);
        assertStringContains('data-role="offline-health-checks"', $health);
        assertStringContains('data-role="offline-worker-checks"', $health);
        assertStringContains('data-role="offline-archive-checks"', $health);
        assertStringContains('data-role="offline-publication-checks"', $health);
        assertStringContains('<h2>Device</h2>', $health);
        assertStringContains('<h2>Saved archive</h2>', $health);
        assertStringContains('<h2>Actions</h2>', $health);
        assertStringContains('class="codebase-facts"', $health);
        assertStringContains('data-role="offline-health-status"', $health);
        assertStringContains('data-role="open-saved-archive"', $health);
        assertStringContains('data-action="refresh-offline-reader"', $health);
        assertStringContains('/assets/tool-details.', $health);
        assertStringContains('/assets/offline_health.', $health);
        assertStringMatches('#data-runtime-url="/assets/sql-wasm\.[a-f0-9]{12}\.wasm"#', $health);
        assertStringNotContains('data-offline-reader', $health);
        assertStringContains('href="/offline/"', $tools);
        assertStringContains('Offline Reading', $tools);
        assertStringContains('method: "HEAD"', $healthScript);
        assertStringContains('Not checked while offline', $healthScript);
        assertStringContains('window.caches.keys()', $healthScript);
        assertStringContains('Secure context', $healthScript);
        assertStringContains('Worker registrations', $healthScript);
        assertStringContains('Last registration error', $healthScript);
        assertStringContains('navigator.serviceWorker.getRegistrations()', $healthScript);
        assertStringContains('Offline reading requires HTTPS.', $healthScript);
        assertStringContains('Open https://', $healthScript);
        assertStringContains('Offline reader cache', $healthScript);
        assertStringContains('window.caches.open(matching[0])', $healthScript);
        assertStringContains('new SQL.Database(bytes)', $healthScript);
        assertStringContains('Saved archive size', $healthScript);
        assertStringContains('Archive generated', $healthScript);
        assertStringContains('Archive contents', $healthScript);
        assertStringContains('Archive capacity', $healthScript);
        assertStringContains('Saved reader revision', $healthScript);
        assertStringContains('Current reader revision', $healthScript);
        assertStringContains('Saved reader freshness', $healthScript);
        assertStringContains('Not checked while offline', $healthScript);
        assertStringContains('Different from current reader — refresh and recheck', $healthScript);
        assertStringContains('offline-reader-refreshed', $healthScript);
        assertStringContains('Saved reader refreshed and rechecked.', $healthScript);
        assertStringContains('setHealthStatus("ready", "READY")', $healthScript);
        assertStringContains('navigator.serviceWorker.getRegistrations()', $healthScript);
        assertStringContains('cache.match(absoluteUrl(url), { ignoreVary: true })', $healthScript);
    }

    public function testOutboxRouteIsAvailableFromToolsWithLocalStorageAssets(): void
    {
        $application = new Application(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
        );

        $outbox = $this->render($application, '/tools/outbox/');
        $tools = $this->render($application, '/tools/');

        assertStringContains('<h1>Outbox</h1>', $outbox);
        assertStringContains('data-outbox', $outbox);
        assertStringContains('data-role="outbox-status"', $outbox);
        assertStringContains('data-role="outbox-items"', $outbox);
        assertStringContains('class="nav-link is-active" href="/tools/outbox/"', $outbox);
        assertFingerprintedAsset($outbox, 'outbox_store.js');
        assertFingerprintedAsset($outbox, 'outbox_storage.js');
        assertFingerprintedAsset($outbox, 'outbox_sender.js');
        assertFingerprintedAsset($outbox, 'outbox.js');
        assertStringContains('href="/tools/outbox/"', $tools);
        assertStringContains('Outbox', $tools);
    }

    public function testOfflineReaderFallbackRouteUsesLocalSnapshotShell(): void
    {
        $application = new Application(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
        );

        $reader = $this->render($application, '/offline/reader/');

        assertStringContains('data-offline-reader', $reader);
        assertStringContains('class="stack thread-list" data-offline-reader', $reader);
        assertStringContains('data-snapshot-url="/offline/snapshot.sqlite3"', $reader);
        assertStringMatches('#data-reader-revision="/assets/offline_reader\.[a-f0-9]{12}\.js"#', $reader);
        assertStringContains('data-role="offline-reader-status"', $reader);
        assertStringContains('data-role="offline-mode-bar"', $reader);
        assertStringContains('data-role="offline-mode-indicators"', $reader);
        assertStringContains('data-role="offline-archive-indicator"', $reader);
        assertStringContains('data-role="offline-reader-indicator"', $reader);
        assertStringNotContains('data-role="offline-reader-details"', $reader);
        assertStringContains('offline mode', $reader);
        assertStringContains('class="offline-mode-bar__outbox" href="/tools/outbox/">Outbox</a>', $reader);
        assertStringContains('/assets/sql-wasm.', $reader);
        assertStringMatches('#data-runtime-url="/assets/sql-wasm\.[a-f0-9]{12}\.wasm"#', $reader);
        assertFingerprintedAsset($reader, 'outbox_store.js');
        assertFingerprintedAsset($reader, 'outbox_storage.js');
        assertFingerprintedAsset($reader, 'outbox_intent.js');
        assertFingerprintedAsset($reader, 'outbox_sender.js');
        assertStringContains('/assets/offline_reader.', $reader);
        assertStringContains('/assets/thread-list.', $reader);
        assertStringContains('/assets/tags.', $reader);
        assertFingerprintedAsset($reader, 'content-interactions.css');
        assertStringContains('class="nav-link is-active" href="/"', $reader);
        assertStringNotContains('class="nav-link is-active" href="/offline/"', $reader);
        assertStringContains('rel="manifest" href="/manifest.webmanifest"', $reader);
        assertStringContains('/assets/pwa_registration.', $reader);
    }

    public function testPublicLayoutRegistersTheNormalNavigationOfflineWorker(): void
    {
        $application = new Application(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
        );

        $board = $this->render($application, '/');
        $serviceWorker = (string) file_get_contents(dirname(__DIR__) . '/public/service_worker.js');
        $registration = (string) file_get_contents(dirname(__DIR__) . '/public/assets/pwa_registration.js');

        assertStringContains('rel="manifest" href="/manifest.webmanifest"', $board);
        assertStringContains('/assets/pwa_registration.', $board);
        assertStringNotContains('href="/offline/"', $board);
        assertStringContains('refresh-offline-reader', $serviceWorker);
        assertStringContains('networkFirstNavigation', $serviceWorker);
        assertStringContains('zenmemes-offline-reader-v13', $serviceWorker);
        assertStringContains('[offline reading] worker install started', $serviceWorker);
        assertStringContains('[offline reading] fetch failed', $serviceWorker);
        assertStringContains('offline reader shell', $serviceWorker);
        assertStringContains('__offline_bootstrap', $serviceWorker);
        assertStringContains('cache.match(cacheKey(url), { ignoreVary: true })', $serviceWorker);
        assertStringContains('cache.put(cacheKey(url), response.clone())', $serviceWorker);
        assertStringContains('[offline reading] cache refresh stored', $serviceWorker);
        assertStringContains('const OFFLINE_HEALTH_URL = "/offline/"', $serviceWorker);
        assertStringContains('const OFFLINE_READER_URL = "/offline/reader/"', $serviceWorker);
        assertStringContains('data-runtime-url', $serviceWorker);
        assertStringNotContains('"/assets/sql-wasm.wasm"', $serviceWorker);
        assertStringContains('url.pathname.startsWith("/assets/")', $serviceWorker);
        assertStringNotContains('/api/', $serviceWorker);
        assertStringContains('navigator.serviceWorker.register("/service_worker.js", { scope: "/" })', $registration);
        assertStringContains('registration.update()', $registration);
        assertStringContains('!registration.installing && !registration.waiting', $registration);
        assertStringContains('logOfflineState("update check completed"', $registration);
        assertStringContains('offlineCaches', $registration);
        assertStringContains('registration.unregister()', $registration);
        assertStringNotContains('querySelectorAll(\'link[href], script[src]\')', $registration);
        assertStringNotContains('urls: cacheUrls()', $registration);
        assertStringNotContains('/offline/reader/', $registration);
        assertStringNotContains('/offline/snapshot.sqlite3', $registration);
        assertStringNotContains('/assets/sql-wasm.wasm', $registration);
    }

    public function testOfflineReaderUsesBoardControlsAndPinnedSnapshotPresentation(): void
    {
        $readerScript = (string) file_get_contents(dirname(__DIR__) . '/public/assets/offline_reader.js');

        assertStringContains('appendBoardControls', $readerScript);
        assertStringContains('thread_labels_json', $readerScript);
        assertStringContains('pinned-thread-marker', $readerScript);
        assertStringContains('group: "view"', $readerScript);
        assertStringContains('group: "sort"', $readerScript);
        assertStringContains('window.history.pushState', $readerScript);
        assertStringContains('card thread-card', $readerScript);
        assertStringContains('card post-card thread-root-card', $readerScript);
        assertStringContains('bodyExcerpt', $readerScript);
        assertStringContains('heatLevel', $readerScript);
        assertStringContains('dataset.heat', $readerScript);
        assertStringContains('fileName === "sql-wasm.wasm" ? runtimeUrl', $readerScript);
    }

    public function testPrivateLayoutDoesNotRegisterOfflineReaderPwa(): void
    {
        $previousFlag = getenv('FORUM_APPROVED_MEMBERS_ONLY');
        putenv('FORUM_APPROVED_MEMBERS_ONLY=true');

        try {
            $application = new Application(
                dirname(__DIR__),
                $this->repositoryRoot,
                $this->databasePath,
            );

            $lobby = $this->render($application, '/lobby/');

            assertStringNotContains('rel="manifest" href="/manifest.webmanifest"', $lobby);
            assertStringNotContains('/assets/pwa_registration.', $lobby);
            assertStringNotContains('href="/offline/"', $lobby);
        } finally {
            if ($previousFlag === false) {
                putenv('FORUM_APPROVED_MEMBERS_ONLY');
            } else {
                putenv('FORUM_APPROVED_MEMBERS_ONLY=' . $previousFlag);
            }
        }
    }

    public function testSqliteViewerLoadsLocalRuntimeAssets(): void
    {
        $application = new Application(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
        );

        $viewer = $this->render($application, '/tools/sqlite/');
        $script = (string) file_get_contents(dirname(__DIR__) . '/public/assets/sqlite_viewer.js');

        assertStringMatches('#/assets/sql-wasm\.[a-f0-9]{12}\.js#', $viewer);
        assertStringMatches('#/assets/sqlite_viewer\.[a-f0-9]{12}\.js#', $viewer);
        assertTrue(is_file(dirname(__DIR__) . '/public/assets/sql-wasm.js'));
        assertTrue(is_file(dirname(__DIR__) . '/public/assets/sql-wasm.wasm'));
        assertStringMatches('#data-runtime-url="/assets/sql-wasm\.[a-f0-9]{12}\.wasm"#', $viewer);
        assertStringContains('/downloads/read_model.sqlite3', $script);
        assertStringContains('initSqlJs', $script);
        assertStringContains('locateFile', $script);
        assertStringContains('fileName === "sql-wasm.wasm" ? runtimeUrl', $script);
    }

    public function testSqliteViewerIncludesSchemaExplorerContract(): void
    {
        $application = new Application(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
        );

        $viewer = $this->render($application, '/tools/sqlite/');
        $script = (string) file_get_contents(dirname(__DIR__) . '/public/assets/sqlite_viewer.js');
        $css = (string) file_get_contents(dirname(__DIR__) . '/public/assets/sqlite.css');

        assertStringMatches('#/assets/sqlite\\.[a-f0-9]{12}\\.css#', $viewer);
        assertStringContains('data-role="sqlite-explorer"', $viewer);
        assertStringContains('data-role="sqlite-table-select"', $viewer);
        assertStringContains('data-role="sqlite-table-details"', $viewer);
        assertStringContains('data-role="sqlite-table-preview"', $viewer);
        assertStringContains('role="tablist"', $viewer);
        assertStringContains('data-sqlite-tab="data"', $viewer);
        assertStringContains('data-sqlite-tab="columns"', $viewer);
        assertStringContains('id="sqlite-data-panel"', $viewer);
        assertStringContains('id="sqlite-columns-panel"', $viewer);
        assertStringContains('sqlite_master', $script);
        assertStringContains('LIMIT 20', $script);
        assertStringContains('PRAGMA table_info', $script);
        assertStringContains('explorer.removeAttribute("hidden")', $script);
        assertStringContains('activateTableTab("data")', $script);
        assertStringContains('tablePreview.hidden = tabName !== "data"', $script);
        assertStringContains('sqlite-sort-button', $script);
        assertStringContains('aria-sort', $script);
        assertStringContains('sortDirections', $script);
        assertStringContains('var tablePage = 0', $script);
        assertStringContains('var tableSortColumn = null', $script);
        assertStringContains('ORDER BY " + quoteIdentifier', $script);
        assertStringContains('onSort: function (columnIndex, direction)', $script);
        assertStringContains('renderTable(tableName, 0)', $script);
        assertStringContains('LIMIT " + (maxPreviewRows + 1) + " OFFSET " + (tablePage * maxPreviewRows)', $script);
        assertStringContains('renderTable(tableName, tablePage + 1)', $script);
        assertStringContains('renderTable(tableSelect.value, 0)', $script);
        assertStringContains('aria-sort="ascending"', $css);
        assertStringContains('aria-sort="descending"', $css);
        assertStringContains('content: " ▲"', $css);
        assertStringContains('content: " ▼"', $css);
    }

    public function testSqliteViewerIncludesPresetReadOnlyQueryContract(): void
    {
        $application = new Application(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
        );

        $viewer = $this->render($application, '/tools/sqlite/');
        $script = (string) file_get_contents(dirname(__DIR__) . '/public/assets/sqlite_viewer.js');

        assertStringContains('data-role="sqlite-query-panel"', $viewer);
        assertStringContains('data-role="sqlite-query-select"', $viewer);
        assertStringContains('data-role="sqlite-query-input"', $viewer);
        assertStringContains('data-role="sqlite-query-input" cols="80" rows="24"', $viewer);
        assertStringContains('data-action="run-sqlite-query"', $viewer);
        assertStringContains('data-role="sqlite-effective-query" hidden', $viewer);
        assertStringContains('data-role="sqlite-effective-count"', $viewer);
        assertStringContains('data-role="sqlite-effective-data"', $viewer);
        assertStringContains('class="sqlite-query-bottom"', $viewer);
        assertStringContains('data-role="sqlite-query-pagination"', $viewer);
        assertStringContains('<h2>Run query</h2>', $viewer);
        assertStringContains('Recent posts', $script);
        assertStringContains('Board: Liked + Newest', $script);
        assertStringContains('json_each(threads.board_tags_json)', $script);
        assertStringContains('posts.post_score_total >= 0', $script);
        assertStringContains('author_username_token', $script);
        assertStringContains('isSingleSelectQuery', $script);
        assertStringContains('Only one read-only SELECT statement is allowed.', $script);
        assertStringContains('database.exec(dataSql)', $script);
        assertStringContains('queryPanel.removeAttribute("hidden")', $script);
        assertStringContains('querySelect.value = "0"', $script);
        assertStringContains('queryInput.value = presetQueries[0].sql', $script);
        assertStringContains('queryButton.addEventListener("click", function ()', $script);
        assertStringContains('queryPage = typeof page === "number"', $script);
        assertStringContains('var querySortColumn = null', $script);
        assertStringContains('var querySortDirection = null', $script);
        assertStringContains('function runQuery(page, sortColumn, sortDirection)', $script);
        assertStringContains('if (arguments.length < 2)', $script);
        assertStringContains('var resultSql = "SELECT * FROM (" + normalized + ")"', $script);
        assertStringContains('var resultShape = database.exec(resultSql + " LIMIT 1")[0]', $script);
        assertStringContains('var orderBy = hasSort ? " ORDER BY " + (querySortColumn + 1) + " " + querySortDirection : ""', $script);
        assertStringContains('renderRows(queryResults, result, "The query returned no rows.", maxQueryRows, true', $script);
        assertStringContains('sortColumn: querySortColumn', $script);
        assertStringContains('sortDirection: querySortDirection === "DESC" ? "descending" : "ascending"', $script);
        assertStringContains('runQuery(queryPage + 1, querySortColumn, querySortDirection)', $script);
        assertStringContains('onSort: function (columnIndex, direction)', $script);
        assertStringContains('runQuery(0, columnIndex, direction === "descending" ? "DESC" : "ASC")', $script);
        assertStringContains('Query failed: ', $script);
        assertStringContains('function renderEffectiveQuery(countSql, dataSql)', $script);
        assertStringContains('effectiveCount.textContent = countSql', $script);
        assertStringContains('effectiveData.textContent = dataSql', $script);
        assertStringContains('var queryPagination = root.querySelector', $script);
        assertStringContains('container: queryPagination', $script);
        assertStringContains('var target = pagination.container || node', $script);
        assertStringContains('function clearEffectiveQuery()', $script);
        assertStringContains('No effective query was executed.', $script);
        assertStringContains('effectiveQuery.setAttribute("hidden", "hidden")', $script);
        assertStringContains('var countSql = "SELECT COUNT(*) AS total_rows FROM (" + normalized + ")"', $script);
        assertStringContains('var dataSql = resultSql + orderBy + " LIMIT " + (maxQueryRows + 1)', $script);
        assertStringContains('database.exec(countSql)', $script);
        assertStringContains('database.exec(dataSql)', $script);
        assertStringContains('var queryPage = 0', $script);
        assertStringContains('LIMIT " + (maxQueryRows + 1) + " OFFSET " + (queryPage * maxQueryRows)', $script);
        assertStringContains('runQuery(queryPage + 1, querySortColumn, querySortDirection)', $script);
        assertStringContains("queryInput.value = preset.sql;\n          runQuery();", $script);
        assertStringContains('function renderPagination(node, pagination)', $script);
        assertStringContains('className = "sqlite-pagination"', $script);
        assertStringContains('Page " + (pagination.page + 1)', $script);
        assertStringContains('pagination.pageSize', $script);
        assertStringContains('recordRange', $script);
        assertStringContains('"-" + Math.min', $script);
        assertStringContains('pagination.totalPages', $script);
        assertStringContains('pagination.totalRows', $script);
        assertStringContains('function preserveScroll(callback)', $script);
        assertStringContains('window.scrollTo(scrollX, scrollY)', $script);
        assertStringContains('SELECT COUNT(*) AS total_rows FROM (" + normalized + ")', $script);
        assertStringContains('previous.disabled = !pagination.hasPrevious', $script);
        assertStringContains('next.disabled = !pagination.hasNext', $script);
        assertStringContains('threads.root_post_id,\\n', $script);
        assertStringContains('\\n       threads.root_post_created_at,\\n', $script);
        assertStringContains('\\nFROM threads\\n', $script);
    }

    public function testSqliteViewerPlacesQueryRunnerBeforeTableExplorer(): void
    {
        $application = new Application(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
        );

        $viewer = $this->render($application, '/tools/sqlite/');

        assertTrue(strpos($viewer, 'data-role="sqlite-query-panel"') < strpos($viewer, 'data-role="sqlite-explorer"'));
    }

    public function testSqliteViewerCapsAndScrollsResultSurfaces(): void
    {
        $application = new Application(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
        );

        $viewer = $this->render($application, '/tools/sqlite/');
        $script = (string) file_get_contents(dirname(__DIR__) . '/public/assets/sqlite_viewer.js');
        $css = (string) file_get_contents(dirname(__DIR__) . '/public/assets/sqlite.css');

        assertStringContains('sqlite-result-scroll', $viewer);
        assertStringContains('maxPreviewRows = 25', $script);
        assertStringContains('maxQueryRows = 25', $script);
        assertStringContains('LIMIT " + (maxQueryRows + 1)', $script);
        assertStringContains('.sqlite-result-scroll', $css);
        assertStringContains('[data-role="sqlite-explorer"]', $css);
        assertStringContains('[data-role="sqlite-query-panel"]', $css);
        assertStringContains('width: calc(100vw - 2rem)', $css);
        assertStringContains('margin-left: calc(50% - 50vw + 1rem)', $css);
        assertStringContains('[data-role="sqlite-table-details"] table', $css);
        assertStringContains('min-width: 0', $css);
        assertStringContains('width: max-content', $css);
        assertStringContains('overflow-x: auto', $css);
        assertStringContains('.sqlite-pagination', $css);
        assertStringContains('flex: 0 0 100%', $css);
        assertStringContains('[data-role="sqlite-effective-query"] summary', $css);
        assertStringContains('text-align: right', $css);
        assertStringContains('.sqlite-pagination button', $css);
        assertStringContains('width: auto', $css);
        assertStringContains('.sqlite-cell-toggle', $css);
        assertStringContains('aria-expanded', $script);
        assertStringContains('Click to expand this value', $script);
        assertStringContains('aria-live', $script);
    }

    public function testRepositoryArchiveDownloadFilenamesIncludeReadableTimestamp(): void
    {
        // repositoryArchiveDownloadFilename() lives on InstancePageController
        // now (Phase 2 route extraction), not Application - see
        // docs/plans/codebase_cleanup_audit_findings_v1.md.
        $routeServices = new RouteServices(
            $this->databasePath,
            new TemplateRenderer(dirname(__DIR__) . '/templates'),
            'php-fallback',
            false,
            static fn (): ?array => null,
            $this->repositoryRoot,
            dirname(__DIR__),
            null,
            null,
            FeatureFlagEvaluator::forApplication($this->repositoryRoot, dirname(__DIR__)),
        );
        $controller = new InstancePageController(
            $routeServices,
            $this->repositoryRoot,
            $this->databasePath,
            dirname(__DIR__),
            static fn (): array => ['items' => []],
        );
        $method = new ReflectionMethod(InstancePageController::class, 'repositoryArchiveDownloadFilename');
        $method->setAccessible(true);

        $filename = $method->invoke($controller, 'tar.gz');

        assertStringMatches(
            '/^zenmemes-repository-\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}Z-[a-f0-9]+\.tar\.gz$/',
            $filename
        );
    }

    public function testIdentityHintRouteSetsCookieValue(): void
    {
        @unlink($this->databasePath);
        $application = new Application(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
        );

        $_COOKIE = [];
        $response = $this->renderMethod($application, 'POST', '/api/set_identity_hint?identity_hint=OpenPGP-Example');
        $account = $this->render($application, '/account/key/');

        assertStringContains('identity_hint=openpgp-example', $response);
        assertSame('openpgp-example', $_COOKIE['identity_hint'] ?? null);
        assertStringContains('openpgp-example', $account);
    }

    public function testRequestDataParsesRawFormEncodedBodyWhenPostIsEmpty(): void
    {
        // mergeRequestBodyData() lives on RouteServices now (Phase 2 write-flow
        // slice) - see docs/plans/codebase_cleanup_audit_findings_v1.md.
        $routeServices = new RouteServices(
            $this->databasePath,
            new TemplateRenderer(dirname(__DIR__) . '/templates'),
            'php-fallback',
            false,
            static fn (): ?array => null,
            $this->repositoryRoot,
            dirname(__DIR__),
            null,
            null,
            FeatureFlagEvaluator::forApplication($this->repositoryRoot, dirname(__DIR__)),
        );
        $method = new ReflectionMethod(RouteServices::class, 'mergeRequestBodyData');
        $method->setAccessible(true);

        $result = $method->invoke(
            $routeServices,
            ['thread_id' => 'query-thread'],
            'application/x-www-form-urlencoded; charset=UTF-8',
            'thread_id=body-thread&public_key=' . rawurlencode("-----BEGIN PGP PUBLIC KEY BLOCK-----\nfixture\n-----END PGP PUBLIC KEY BLOCK-----")
        );

        assertSame('body-thread', $result['thread_id']);
        assertSame(
            "-----BEGIN PGP PUBLIC KEY BLOCK-----\nfixture\n-----END PGP PUBLIC KEY BLOCK-----",
            $result['public_key']
        );
    }

    public function testWriteApisAreDisabledAgainstCommittedFixtures(): void
    {
        @unlink($this->databasePath);
        $application = new Application(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
        );

        $response = $this->renderMethod(
            $application,
            'POST',
            '/api/create_thread?subject=Blocked&body=Blocked'
        );

        assertStringContains('Write APIs are disabled against the committed fixture repository', $response);
    }

    public function testFrontControllerServesStaticArtifactForAnonymousEligibleRoute(): void
    {
        @unlink($this->databasePath);
        ['controller' => $controller, 'staticHtmlRoot' => $staticHtmlRoot, 'publicRoot' => $publicRoot] = $this->buildFrontController();
        mkdir($staticHtmlRoot . '/current', 0777, true);
        file_put_contents($staticHtmlRoot . '/current/index.html', '<!doctype html><html><body><!-- route-source: static-html --><h1>Static Board</h1></body></html>');

        try {
            $response = $this->renderFrontController($controller, 'GET', '/', []);

            assertStringContains('Static Board', $response);
            assertStringContains('route-source: static-html', $response);
        } finally {
            $this->deleteTree($staticHtmlRoot);
            $this->deleteTree($publicRoot);
        }
    }

    public function testFrontControllerServesPublicOfflineSnapshotFromActiveRelease(): void
    {
        ['controller' => $controller, 'staticHtmlRoot' => $staticHtmlRoot, 'publicRoot' => $publicRoot] = $this->buildFrontController();
        mkdir($staticHtmlRoot . '/current/offline', 0777, true);
        file_put_contents($staticHtmlRoot . '/current/offline/snapshot.sqlite3', "SQLite format 3\000offline fixture");

        try {
            $response = $this->renderFrontController($controller, 'GET', '/offline/snapshot.sqlite3', []);
            $bootstrapResponse = $this->renderFrontController($controller, 'GET', '/offline/snapshot.sqlite3?__offline_bootstrap=zenmemes-offline-reader-v10', []);

            assertStringContains('SQLite format 3', $response);
            assertStringContains('offline fixture', $response);
            assertSame($response, $bootstrapResponse);
        } finally {
            $this->deleteTree($staticHtmlRoot);
            $this->deleteTree($publicRoot);
        }
    }

    public function testFrontControllerPrefersIndependentlyPublishedOfflineSnapshot(): void
    {
        ['controller' => $controller, 'staticHtmlRoot' => $staticHtmlRoot, 'publicRoot' => $publicRoot] = $this->buildFrontController();
        mkdir($staticHtmlRoot . '/current/offline', 0777, true);
        mkdir($staticHtmlRoot . '/offline', 0777, true);
        file_put_contents($staticHtmlRoot . '/current/offline/snapshot.sqlite3', "SQLite format 3\000release fixture");
        file_put_contents($staticHtmlRoot . '/offline/snapshot.sqlite3', "SQLite format 3\000fast fixture");

        try {
            $response = $this->renderFrontController($controller, 'GET', '/offline/snapshot.sqlite3', []);

            assertStringContains('fast fixture', $response);
            assertStringNotContains('release fixture', $response);
        } finally {
            $this->deleteTree($staticHtmlRoot);
            $this->deleteTree($publicRoot);
        }
    }

    public function testFrontControllerFallsBackWhenIndependentOfflineSnapshotIsInvalid(): void
    {
        ['controller' => $controller, 'staticHtmlRoot' => $staticHtmlRoot, 'publicRoot' => $publicRoot] = $this->buildFrontController();
        mkdir($staticHtmlRoot . '/current/offline', 0777, true);
        mkdir($staticHtmlRoot . '/offline', 0777, true);
        file_put_contents($staticHtmlRoot . '/current/offline/snapshot.sqlite3', "SQLite format 3\000release fixture");
        file_put_contents($staticHtmlRoot . '/offline/snapshot.sqlite3', 'invalid snapshot');

        try {
            $response = $this->renderFrontController($controller, 'GET', '/offline/snapshot.sqlite3', []);

            assertStringContains('release fixture', $response);
        } finally {
            $this->deleteTree($staticHtmlRoot);
            $this->deleteTree($publicRoot);
        }
    }

    public function testFrontControllerDoesNotServeOfflineSnapshotWhenMembersOnlyIsEnabled(): void
    {
        $previousFlag = getenv('FORUM_APPROVED_MEMBERS_ONLY');
        putenv('FORUM_APPROVED_MEMBERS_ONLY=true');
        ['controller' => $controller, 'staticHtmlRoot' => $staticHtmlRoot, 'publicRoot' => $publicRoot] = $this->buildFrontController();
        mkdir($staticHtmlRoot . '/current/offline', 0777, true);
        file_put_contents($staticHtmlRoot . '/current/offline/snapshot.sqlite3', "SQLite format 3\000offline fixture");

        try {
            $response = $this->renderFrontController($controller, 'GET', '/offline/snapshot.sqlite3', []);

            assertStringNotContains('SQLite format 3', $response);
            assertStringContains('Reconnecting', $response);
        } finally {
            $this->deleteTree($staticHtmlRoot);
            $this->deleteTree($publicRoot);
            if ($previousFlag === false) {
                putenv('FORUM_APPROVED_MEMBERS_ONLY');
            } else {
                putenv('FORUM_APPROVED_MEMBERS_ONLY=' . $previousFlag);
            }
        }
    }

    public function testFrontControllerRevalidatesStaticArtifactByEtag(): void
    {
        $html = '<!doctype html><html><body><h1>Static Board</h1></body></html>';
        ['controller' => $controller, 'staticHtmlRoot' => $staticHtmlRoot, 'publicRoot' => $publicRoot] = $this->buildFrontController();
        mkdir($staticHtmlRoot . '/current', 0777, true);
        file_put_contents($staticHtmlRoot . '/current/index.html', $html);

        $previousEtag = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;

        try {
            $_SERVER['HTTP_IF_NONE_MATCH'] = '"' . hash('sha256', $html) . '"';
            http_response_code(200);
            $response = $this->renderFrontController($controller, 'GET', '/', []);

            assertSame('', $response);
            assertSame(304, http_response_code());
        } finally {
            if ($previousEtag === null) {
                unset($_SERVER['HTTP_IF_NONE_MATCH']);
            } else {
                $_SERVER['HTTP_IF_NONE_MATCH'] = $previousEtag;
            }
            $this->deleteTree($staticHtmlRoot);
            $this->deleteTree($publicRoot);
        }
    }

    public function testFrontControllerRecoversStaleFingerprintedAssetRequests(): void
    {
        ['controller' => $controller, 'staticHtmlRoot' => $staticHtmlRoot, 'publicRoot' => $publicRoot] = $this->buildFrontController();
        mkdir($publicRoot . '/assets', 0777, true);
        file_put_contents($publicRoot . '/assets/example.css', 'body { color: red; }');

        try {
            http_response_code(200);
            $response = $this->renderFrontController($controller, 'GET', '/assets/example.000000000000.css', []);

            assertSame('', $response);
            assertSame(302, http_response_code());
        } finally {
            $this->deleteTree($staticHtmlRoot);
            $this->deleteTree($publicRoot);
        }
    }

    public function testFrontControllerServesRawPublicAssetFallback(): void
    {
        ['controller' => $controller, 'staticHtmlRoot' => $staticHtmlRoot, 'publicRoot' => $publicRoot] = $this->buildFrontController();
        mkdir($publicRoot . '/assets', 0777, true);
        file_put_contents($publicRoot . '/assets/openpgp.v5.11.3.min.js', 'window.openpgp = {};');
        file_put_contents($publicRoot . '/assets/openpgp.min.js', 'window.openpgpV6 = {};');
        file_put_contents($publicRoot . '/private.txt', 'must not be served');

        try {
            foreach ([
                '/assets/openpgp.v5.11.3.min.js' => 'window.openpgp = {};',
                '/assets/openpgp.min.js' => 'window.openpgpV6 = {};',
            ] as $requestUri => $expectedBody) {
                http_response_code(200);
                $response = $this->renderFrontController($controller, 'GET', $requestUri, []);

                assertSame($expectedBody, $response);
                assertSame(200, http_response_code());
            }

            $traversalResponse = $this->renderFrontController($controller, 'GET', '/assets/../private.txt', []);
            assertStringNotContains('must not be served', $traversalResponse);
        } finally {
            $this->deleteTree($staticHtmlRoot);
            $this->deleteTree($publicRoot);
        }
    }

    public function testFrontControllerServesStaticArtifactForBackupAlias(): void
    {
        @unlink($this->databasePath);
        ['controller' => $controller, 'staticHtmlRoot' => $staticHtmlRoot, 'publicRoot' => $publicRoot] = $this->buildFrontController();
        mkdir($staticHtmlRoot . '/current/instance', 0777, true);
        file_put_contents($staticHtmlRoot . '/current/instance/index.html', '<!doctype html><html><body><!-- route-source: static-html --><h1>Static Backup</h1></body></html>');

        try {
            $response = $this->renderFrontController($controller, 'GET', '/backup/', []);

            assertStringContains('Static Backup', $response);
            assertStringContains('route-source: static-html', $response);
        } finally {
            $this->deleteTree($staticHtmlRoot);
            $this->deleteTree($publicRoot);
        }
    }

    public function testFrontControllerBypassesStaticArtifactWhenCookieIsPresent(): void
    {
        @unlink($this->databasePath);
        ['controller' => $controller, 'staticHtmlRoot' => $staticHtmlRoot, 'publicRoot' => $publicRoot] = $this->buildFrontController(withPublicRoot: false);
        mkdir($staticHtmlRoot . '/current', 0777, true);
        file_put_contents($staticHtmlRoot . '/current/index.html', '<!doctype html><html><body><!-- route-source: static-html --><h1>Static Board</h1></body></html>');

        try {
            $response = $this->renderFrontController($controller, 'GET', '/', ['identity_hint' => 'guest']);

            assertStringContains('Board', $response);
            assertStringContains('route-source: php-fallback', $response);
        } finally {
            $this->deleteTree($staticHtmlRoot);
            $this->deleteTree($publicRoot);
        }
    }

    public function testFrontControllerShowsConfigurationErrorForMissingRepository(): void
    {
        @unlink($this->databasePath);
        $controller = new FrontController(
            dirname(__DIR__),
            sys_get_temp_dir() . '/forum-rewrite-missing-' . bin2hex(random_bytes(6)),
            $this->databasePath,
            sys_get_temp_dir() . '/forum-rewrite-static-' . bin2hex(random_bytes(6)),
        );

        $response = $this->renderFrontController($controller, 'GET', '/', []);

        assertStringContains('Configuration Error', $response);
        assertStringContains('Repository root does not exist', $response);
    }

    public function testFrontControllerQueuesMissingVoteCountRecoveryWithoutLeakingSql(): void
    {
        $queuePath = sys_get_temp_dir() . '/forum-rewrite-recovery-queue-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $previousQueuePath = getenv('FORUM_TASK_QUEUE_DATABASE_PATH');
        putenv('FORUM_TASK_QUEUE_DATABASE_PATH=' . $queuePath);
        @unlink($this->databasePath);
        (new ReadModelBuilder($this->repositoryRoot, $this->databasePath, new CanonicalRecordRepository($this->repositoryRoot)))->rebuild();
        (new PDO('sqlite:' . $this->databasePath))->exec('ALTER TABLE threads DROP COLUMN vote_count');
        ['controller' => $controller, 'staticHtmlRoot' => $staticHtmlRoot, 'publicRoot' => $publicRoot] = $this->buildFrontController();

        try {
            $store = new SqliteTaskQueueStore(new PDO('sqlite:' . $queuePath));
            $run = $store->startExecutorRun();
            $store->completeExecutorRun($run['id'], [
                'recovered' => 0,
                'claimed' => 0,
                'completed' => 0,
                'continued' => 0,
                'retried' => 0,
                'failed' => 0,
            ], []);

            http_response_code(200);
            $first = $this->renderFrontController($controller, 'GET', '/', []);
            $second = $this->renderFrontController($controller, 'GET', '/', []);

            assertSame(503, http_response_code());
            assertStringContains('Site Update', $first);
            assertStringContains('The site will be back soon', $first);
            assertStringNotContains('SQLSTATE', $first);
            assertStringNotContains('threads.vote_count', $first);
            assertStringNotContains('Configuration Error', $first);
            assertSame($first, $second);
            assertSame(1, $store->counts()['queued']);
        } finally {
            $previousQueuePath === false ? putenv('FORUM_TASK_QUEUE_DATABASE_PATH') : putenv('FORUM_TASK_QUEUE_DATABASE_PATH=' . $previousQueuePath);
            @unlink($queuePath);
            @unlink($this->databasePath);
            $this->deleteTree($staticHtmlRoot);
            $this->deleteTree($publicRoot);
        }
    }

    public function testFrontControllerSanitizesUnexpectedApplicationFailure(): void
    {
        @unlink($this->databasePath);
        (new ReadModelBuilder($this->repositoryRoot, $this->databasePath, new CanonicalRecordRepository($this->repositoryRoot)))->rebuild();
        (new PDO('sqlite:' . $this->databasePath))->exec('DROP TABLE threads');
        ['controller' => $controller, 'staticHtmlRoot' => $staticHtmlRoot, 'publicRoot' => $publicRoot] = $this->buildFrontController();

        try {
            $response = $this->renderFrontController($controller, 'GET', '/', []);

            assertStringContains('Temporarily Unavailable', $response);
            assertStringNotContains('SQLSTATE', $response);
            assertStringNotContains('no such table', $response);
            assertStringNotContains('Configuration Error', $response);
        } finally {
            @unlink($this->databasePath);
            $this->deleteTree($staticHtmlRoot);
            $this->deleteTree($publicRoot);
        }
    }

    public function testFrontControllerShowsBusyErrorForExecutionLockContention(): void
    {
        [$repositoryRoot, $databasePath] = $this->createGitBackedEnvironment();
        @unlink($databasePath);
        $staticHtmlRoot = sys_get_temp_dir() . '/forum-rewrite-static-' . bin2hex(random_bytes(6));
        $publicRoot = sys_get_temp_dir() . '/forum-rewrite-public-root-' . bin2hex(random_bytes(6));
        mkdir($staticHtmlRoot, 0777, true);
        mkdir($publicRoot, 0777, true);
        $controller = new FrontController(
            dirname(__DIR__),
            $repositoryRoot,
            $databasePath,
            $staticHtmlRoot,
            $publicRoot,
        );

        putenv('FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS=0');
        try {
            $lock = new ExecutionLock(dirname($databasePath) . '/forum-rewrite.lock', 0);
            $response = $lock->withExclusiveLock(fn () => $this->renderFrontController($controller, 'GET', '/', []));
        } finally {
            putenv('FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS');
            @rmdir($publicRoot);
        }

        assertStringContains('Meme Oven Is Busy', $response);
        assertStringContains('The next batch of zenmemes is still baking. Try again in a moment.', $response);
        assertStringNotContains('Service Busy', $response);
        assertStringNotContains('Timed out waiting for execution lock', $response);
        assertStringNotContains('forum-rewrite.lock', $response);
        assertStringNotContains('/home/', $response);
        assertStringNotContains('read-model', $response);
        assertStringNotContains('rebuild', $response);
        assertStringNotContains('Configuration Error', $response);
    }

    public function testStaticArtifactBuilderWritesApacheFriendlyArtifactLayout(): void
    {
        @unlink($this->databasePath);
        $artifactRoot = sys_get_temp_dir() . '/forum-rewrite-public-' . bin2hex(random_bytes(6));
        mkdir($artifactRoot, 0777, true);

        $builder = new StaticArtifactBuilder(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
            $artifactRoot,
        );
        $builder->build();

        assertTrue(is_file($artifactRoot . '/index.html'));
        assertTrue(is_file($artifactRoot . '/threads.html'));
        assertTrue(is_file($artifactRoot . '/threads/index.html'));
        assertTrue(is_file($artifactRoot . '/about.html'));
        assertTrue(is_file($artifactRoot . '/about/index.html'));
        assertTrue(is_file($artifactRoot . '/instance.html'));
        assertTrue(is_file($artifactRoot . '/activity.html'));
        assertTrue(is_file($artifactRoot . '/users.html'));
        assertTrue(is_file($artifactRoot . '/tools.html'));
        assertTrue(is_file($artifactRoot . '/tools/index.html'));
        assertTrue(is_file($artifactRoot . '/tools/bookmarklets.html'));
        assertTrue(is_file($artifactRoot . '/tools/feature-flags.html'));
        assertTrue(is_file($artifactRoot . '/tags.html'));
        assertTrue(is_file($artifactRoot . '/tags/index.html'));
        assertTrue(is_file($artifactRoot . '/tags/general.html'));
        assertTrue(is_file($artifactRoot . '/tags/bug.html'));
        assertTrue(is_file($artifactRoot . '/threads/root-001.html'));
        assertTrue(is_file($artifactRoot . '/threads/thread-zenmemes-rules.html'));
        assertTrue(is_file($artifactRoot . '/posts/root-001.html'));
        assertTrue(is_file($artifactRoot . '/posts/thread-zenmemes-rules.html'));
        assertTrue(is_file($artifactRoot . '/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954.html'));
        $indexArtifact = (string) file_get_contents($artifactRoot . '/index.html');
        assertTrue(preg_match_all('#/assets/[A-Za-z0-9_./-]+\.[a-f0-9]{12}\.[A-Za-z0-9]+#', $indexArtifact, $assetMatches) !== false);
        foreach (array_unique($assetMatches[0]) as $assetPath) {
            assertTrue(is_file($artifactRoot . $assetPath));
        }
        assertTrue(is_file($artifactRoot . AssetFingerprint::fingerprintedPath(dirname(__DIR__) . '/public', '/assets/openpgp.min.js')));
        assertTrue(is_file($artifactRoot . AssetFingerprint::fingerprintedPath(dirname(__DIR__) . '/public', '/assets/openpgp.v5.11.3.min.js')));
        assertStringContains(
            '"openpgpV6":"' . AssetFingerprint::fingerprintedPath(dirname(__DIR__) . '/public', '/assets/openpgp.min.js') . '"',
            $indexArtifact,
        );
        assertStringContains(
            '"openpgpV5":"' . AssetFingerprint::fingerprintedPath(dirname(__DIR__) . '/public', '/assets/openpgp.v5.11.3.min.js') . '"',
            $indexArtifact,
        );
        assertTrue(is_file($artifactRoot . '/offline/snapshot.sqlite3'));
        assertStringContains('SQLite format 3', (string) file_get_contents($artifactRoot . '/offline/snapshot.sqlite3'));
        assertSame((string) file_get_contents(dirname(__DIR__) . '/public/service_worker.js'), (string) file_get_contents($artifactRoot . '/service_worker.js'));
        foreach (\ForumRewrite\View\ThemeRegistry::stylesheetPaths() as $path) {
            $fingerprintedPath = AssetFingerprint::fingerprintedPath(dirname(__DIR__) . '/public', $path);
            assertStringContains($fingerprintedPath, $indexArtifact);
            assertTrue(is_file($artifactRoot . $fingerprintedPath));
        }
        assertStringContains('route-source: static-html', (string) file_get_contents($artifactRoot . '/index.html'));
        assertStringContains('route-source: static-html', (string) file_get_contents($artifactRoot . '/threads.html'));
        assertStringContains('route-source: static-html', (string) file_get_contents($artifactRoot . '/threads/index.html'));
        assertStringContains('route-source: static-html', (string) file_get_contents($artifactRoot . '/about.html'));
        assertStringContains('route-source: static-html', (string) file_get_contents($artifactRoot . '/about/index.html'));
        assertStringContains('route-source: static-html', (string) file_get_contents($artifactRoot . '/tools.html'));
        assertStringContains('route-source: static-html', (string) file_get_contents($artifactRoot . '/tools/index.html'));
        assertStringContains('route-source: static-html', (string) file_get_contents($artifactRoot . '/tools/bookmarklets.html'));
        assertStringContains('route-source: static-html', (string) file_get_contents($artifactRoot . '/tags.html'));
        assertStringContains('route-source: static-html', (string) file_get_contents($artifactRoot . '/tags/index.html'));
        assertStringContains('route-source: static-html', (string) file_get_contents($artifactRoot . '/tags/general.html'));
        assertStringContains('route-source: static-html', (string) file_get_contents($artifactRoot . '/tags/bug.html'));
        $rootThreadArtifact = (string) file_get_contents($artifactRoot . '/threads/root-001.html');
        assertStringContains('route-source: static-html', $rootThreadArtifact);
        $inlineReplyAssetPath = fingerprintedAssetPath($rootThreadArtifact, 'inline_reply_form.js');
        assertTrue(is_file($artifactRoot . $inlineReplyAssetPath));
        assertStringContains('inline-reply-composer', $rootThreadArtifact);
        assertStringContains('route-source: static-html', (string) file_get_contents($artifactRoot . '/threads/thread-zenmemes-rules.html'));

        $threadsArtifact = (string) file_get_contents($artifactRoot . '/threads.html');
        assertSame((string) file_get_contents($artifactRoot . '/index.html'), $threadsArtifact);

        $pdo = new PDO('sqlite:' . $this->databasePath);
        assertSame(
            '["pinned"]',
            $pdo->query("SELECT thread_labels_json FROM threads WHERE root_post_id = 'thread-zenmemes-rules'")->fetchColumn()
        );

    }

    public function testStaticArtifactReleasePublisherActivatesCompleteReleaseForFrontController(): void
    {
        @unlink($this->databasePath);
        $staticHtmlRoot = sys_get_temp_dir() . '/forum-rewrite-static-release-' . bin2hex(random_bytes(6));
        $publicRoot = sys_get_temp_dir() . '/forum-rewrite-public-root-' . bin2hex(random_bytes(6));
        mkdir($staticHtmlRoot, 0777, true);
        mkdir($publicRoot, 0777, true);
        file_put_contents($publicRoot . '/index.html', '<!doctype html><p>old public artifact</p>');

        try {
            (new ReadModelBuilder(
                $this->repositoryRoot,
                $this->databasePath,
                new CanonicalRecordRepository($this->repositoryRoot),
            ))->rebuild();
            $publisher = new StaticArtifactReleasePublisher(dirname(__DIR__), $this->repositoryRoot, $staticHtmlRoot);
            $releasePath = $publisher->build($this->databasePath);
            $publisher->activate($releasePath);

            $controller = new FrontController(
                dirname(__DIR__),
                $this->repositoryRoot,
                $this->databasePath,
                $staticHtmlRoot,
                $publicRoot,
            );
            $response = $this->renderFrontController($controller, 'GET', '/', []);

            assertTrue(is_link($staticHtmlRoot . '/current'));
            assertTrue(is_file($staticHtmlRoot . '/current/index.html'));
            assertStringContains('route-source: static-html', $response);
            assertStringNotContains('old public artifact', $response);
        } finally {
            $this->deleteTree($staticHtmlRoot);
            $this->deleteTree($publicRoot);
        }
    }

    public function testStaticArtifactHealthCheckDetectsMissingFingerprint(): void
    {
        $artifactRoot = sys_get_temp_dir() . '/forum-rewrite-assets-' . bin2hex(random_bytes(6));
        mkdir($artifactRoot . '/assets', 0777, true);
        file_put_contents($artifactRoot . '/assets/example.000000000000.css', 'body { color: red; }');
        file_put_contents($artifactRoot . '/index.html', '<link rel="stylesheet" href="/assets/example.000000000000.css">');

        $command = sprintf(
            'php %s %s',
            escapeshellarg(__DIR__ . '/../scripts/check_static_artifacts.php'),
            escapeshellarg($artifactRoot),
        );

        try {
            exec($command, $output, $exitCode);
            assertSame(0, $exitCode);
            unlink($artifactRoot . '/assets/example.000000000000.css');
            exec($command, $outputAfterRemoval, $exitCodeAfterRemoval);
            assertSame(1, $exitCodeAfterRemoval);
        } finally {
            $this->deleteTree($artifactRoot);
        }
    }

    public function testFrontControllerFallsBackDynamicallyUntilAReleaseIsActivated(): void
    {
        @unlink($this->databasePath);
        ['controller' => $controller, 'staticHtmlRoot' => $staticHtmlRoot, 'publicRoot' => $publicRoot] = $this->buildFrontController();
        mkdir($publicRoot . '/threads', 0777, true);
        file_put_contents($publicRoot . '/threads/root-001.html', '<!doctype html><p>old public artifact</p>');

        try {
            $firstResponse = $this->renderFrontController($controller, 'GET', '/threads/root-001', []);
            assertStringContains('Hello world', $firstResponse);
            assertStringContains('route-source: php-fallback', $firstResponse);
            assertStringNotContains('old public artifact', $firstResponse);

            $secondResponse = $this->renderFrontController($controller, 'GET', '/threads/root-001', []);
            assertStringContains('Hello world', $secondResponse);
            assertStringContains('route-source: php-fallback', $secondResponse);
        } finally {
            $this->deleteTree($staticHtmlRoot);
            $this->deleteTree($publicRoot);
        }
    }

    public function testFrontControllerDoesNotBuildArtifactForCookieBearingFallback(): void
    {
        @unlink($this->databasePath);
        ['controller' => $controller, 'staticHtmlRoot' => $staticHtmlRoot, 'publicRoot' => $publicRoot] = $this->buildFrontController();

        try {
            $response = $this->renderFrontController($controller, 'GET', '/threads/root-001', ['identity_hint' => 'guest']);
            assertStringContains('Hello world', $response);
            assertStringContains('route-source: php-fallback', $response);
            assertFalse(is_file($publicRoot . '/threads/root-001.html'));
        } finally {
            $this->deleteTree($staticHtmlRoot);
            $this->deleteTree($publicRoot);
        }
    }

    public function testFrontControllerDoesNotBuildArtifactForQueryFallback(): void
    {
        @unlink($this->databasePath);
        ['controller' => $controller, 'staticHtmlRoot' => $staticHtmlRoot, 'publicRoot' => $publicRoot] = $this->buildFrontController();

        try {
            $response = $this->renderFrontController($controller, 'GET', '/activity/?view=all', []);
            assertStringContains('Activity', $response);
            assertStringContains('route-source: php-fallback', $response);
            assertFalse(is_file($publicRoot . '/activity.html'));
        } finally {
            $this->deleteTree($staticHtmlRoot);
            $this->deleteTree($publicRoot);
        }
    }

    public function testFeatureFlagWriteInvalidatesAlternateStaticActivityArtifact(): void
    {
        [$repositoryRoot, $databasePath] = $this->createGitBackedEnvironment();
        $projectRoot = dirname(__DIR__);
        $staticHtmlRoot = sys_get_temp_dir() . '/forum-rewrite-static-' . bin2hex(random_bytes(6));
        $publicRoot = sys_get_temp_dir() . '/forum-rewrite-public-root-' . bin2hex(random_bytes(6));
        mkdir($staticHtmlRoot . '/releases/test-release/activity', 0777, true);
        mkdir($publicRoot, 0777, true);
        file_put_contents($staticHtmlRoot . '/releases/test-release/activity/index.html', '<!doctype html><title>stale activity</title><p>stale activity</p>');
        symlink('releases/test-release', $staticHtmlRoot . '/current');

        $controller = new FrontController(
            $projectRoot,
            $repositoryRoot,
            $databasePath,
            $staticHtmlRoot,
            $publicRoot,
        );

        $_COOKIE = ['identity_hint' => 'guest'];
        try {
            $writeResponse = $this->renderFrontController(
                $controller,
                'POST',
                '/api/set_feature_flag?key=FORUM_APP_VERSION_NOTIFICATION&value=false',
                ['identity_hint' => 'guest']
            );
        } finally {
            $_COOKIE = [];
        }
        $activityResponse = $this->renderFrontController($controller, 'GET', '/activity/', []);

        assertStringContains('status=ok', $writeResponse);
        assertFalse(is_link($staticHtmlRoot . '/current'));
        assertStringContains('site_feature_flag', $activityResponse);
        assertStringContains('Set feature flag FORUM_APP_VERSION_NOTIFICATION=false', $activityResponse);
        assertStringNotContains('stale activity', $activityResponse);
    }

    public function testUsersStaticArtifactsAreInvalidatedByDirectoryAffectingWrites(): void
    {
        $publicRoot = sys_get_temp_dir() . '/forum-rewrite-public-root-' . bin2hex(random_bytes(6));
        $staticHtmlRoot = sys_get_temp_dir() . '/forum-rewrite-static-' . bin2hex(random_bytes(6));
        mkdir($publicRoot . '/users', 0777, true);
        mkdir($staticHtmlRoot . '/users', 0777, true);

        $invalidations = [
            static function (StaticArtifactInvalidator $invalidator): void {
                $invalidator->invalidateBoardThread('thread-001');
            },
            static function (StaticArtifactInvalidator $invalidator): void {
                $invalidator->invalidateReply('thread-001', 'reply-001');
            },
            static function (StaticArtifactInvalidator $invalidator): void {
                $invalidator->invalidateProfile('openpgp-example');
            },
            static function (StaticArtifactInvalidator $invalidator): void {
                $invalidator->invalidateIdentityLink('openpgp-example', 'thread-001', 'post-001');
            },
            static function (StaticArtifactInvalidator $invalidator): void {
                $invalidator->invalidateApproval('openpgp-example', 'thread-001', 'post-001', 'approval-001');
            },
        ];

        try {
            foreach ($invalidations as $invalidate) {
                file_put_contents($publicRoot . '/users.html', 'stale public users');
                file_put_contents($publicRoot . '/users/index.html', 'stale public users index');
                file_put_contents($staticHtmlRoot . '/users.html', 'stale alternate users');
                file_put_contents($staticHtmlRoot . '/users/index.html', 'stale alternate users index');

                $invalidate(new StaticArtifactInvalidator($publicRoot, $staticHtmlRoot));

                assertFalse(is_file($publicRoot . '/users.html'));
                assertFalse(is_file($publicRoot . '/users/index.html'));
                assertFalse(is_file($staticHtmlRoot . '/users.html'));
                assertFalse(is_file($staticHtmlRoot . '/users/index.html'));
            }
        } finally {
            $this->deleteTree($publicRoot);
            $this->deleteTree($staticHtmlRoot);
        }
    }

    public function testBackupStaticArtifactsAreInvalidatedByContentWrites(): void
    {
        $publicRoot = sys_get_temp_dir() . '/forum-rewrite-public-root-' . bin2hex(random_bytes(6));
        $staticHtmlRoot = sys_get_temp_dir() . '/forum-rewrite-static-' . bin2hex(random_bytes(6));
        mkdir($staticHtmlRoot . '/instance', 0777, true);

        try {
            foreach ([
                static function (StaticArtifactInvalidator $invalidator): void {
                    $invalidator->invalidateBoardThread('thread-001');
                },
                static function (StaticArtifactInvalidator $invalidator): void {
                    $invalidator->invalidateReply('thread-001', 'reply-001');
                },
                static function (StaticArtifactInvalidator $invalidator): void {
                    $invalidator->invalidateIdentityLink('openpgp-example', 'thread-001', 'post-001');
                },
                static function (StaticArtifactInvalidator $invalidator): void {
                    $invalidator->invalidateApproval('openpgp-example', 'thread-001', 'post-001', 'approval-001');
                },
            ] as $invalidate) {
                mkdir($publicRoot, 0777, true);
                file_put_contents($publicRoot . '/instance.html', 'stale public backup');
                file_put_contents($publicRoot . '/activity.html', 'stale public activity');
                file_put_contents($staticHtmlRoot . '/instance/index.html', 'stale alternate backup');
                file_put_contents($staticHtmlRoot . '/activity.html', 'stale alternate activity');

                $invalidate(new StaticArtifactInvalidator($publicRoot, $staticHtmlRoot));

                assertFalse(is_file($publicRoot . '/instance.html'));
                assertFalse(is_file($publicRoot . '/activity.html'));
                assertFalse(is_file($staticHtmlRoot . '/instance/index.html'));
                assertFalse(is_file($staticHtmlRoot . '/activity.html'));
                rmdir($publicRoot);
            }
        } finally {
            $this->deleteTree($publicRoot);
            $this->deleteTree($staticHtmlRoot);
        }
    }

    public function testDefaultRepositoryBootstrapCreatesWritableLocalRepository(): void
    {
        $projectRoot = sys_get_temp_dir() . '/forum-rewrite-project-' . bin2hex(random_bytes(6));
        mkdir($projectRoot . '/tests/fixtures/parity_minimal_v1', 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $projectRoot . '/tests/fixtures/parity_minimal_v1');

        $repositoryRoot = LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot);

        assertSame($projectRoot . '/state/local_repository', $repositoryRoot);
        assertTrue(is_dir($repositoryRoot . '/records'));
        assertTrue(is_dir($repositoryRoot . '/.git'));
        assertTrue(is_file($repositoryRoot . '/records/posts/root-001.txt'));
    }

    public function testDefaultRepositoryBootstrapRepairsExistingLocalRepositoryWithoutGit(): void
    {
        $projectRoot = sys_get_temp_dir() . '/forum-rewrite-project-' . bin2hex(random_bytes(6));
        mkdir($projectRoot . '/tests/fixtures/parity_minimal_v1', 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $projectRoot . '/tests/fixtures/parity_minimal_v1');
        mkdir($projectRoot . '/state/local_repository', 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $projectRoot . '/state/local_repository');

        $repositoryRoot = LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot);

        assertSame($projectRoot . '/state/local_repository', $repositoryRoot);
        assertTrue(is_dir($repositoryRoot . '/records'));
        assertTrue(is_dir($repositoryRoot . '/.git'));
        assertTrue(is_file($repositoryRoot . '/records/posts/root-001.txt'));
    }

    public function testDefaultInstanceStateIsIndependentOfSiteProfile(): void
    {
        $projectRoot = sys_get_temp_dir() . '/forum-rewrite-project-' . bin2hex(random_bytes(6));
        mkdir($projectRoot . '/tests/fixtures/parity_minimal_v1', 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $projectRoot . '/tests/fixtures/parity_minimal_v1');

        $repositoryRoot = LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot);

        assertSame($projectRoot . '/state/local_repository', $repositoryRoot);
        assertSame($projectRoot . '/state/cache/post_index.sqlite3', LocalRepositoryBootstrap::defaultDatabasePath($projectRoot));
        assertTrue(is_dir($repositoryRoot . '/records'));
        assertTrue(is_dir($repositoryRoot . '/.git'));
        assertTrue(is_file($repositoryRoot . '/records/posts/root-001.txt'));
        assertFalse(is_dir($projectRoot . '/state/local_repository_chouse'));
        assertFalse(is_file($projectRoot . '/state/cache/post_index_chouse.sqlite3'));
    }

    public function testBoardPageRendersActiveSiteProfilePerFormSiteId(): void
    {
        [$repositoryRoot, $databasePath] = $this->createGitBackedEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);

        putenv('FORUM_SITE_ID');
        $zenmemesBoard = $this->render($application, '/');

        putenv('FORUM_SITE_ID=chouse');
        try {
            $chouseBoard = $this->render($application, '/');
            $chouseAbout = $this->render($application, '/about/');
        } finally {
            putenv('FORUM_SITE_ID');
        }

        assertStringContains('<p class="eyebrow">zenmemes</p>', $zenmemesBoard);
        assertStringContains('<p class="eyebrow">chouse</p>', $chouseBoard);
        assertStringContains('data-default-theme="auto"', $zenmemesBoard);
        assertStringContains('data-default-theme="chouse"', $chouseBoard);
        assertStringContains("if (allowed.indexOf(theme) === -1) {\n        theme = document.documentElement.getAttribute('data-default-theme');", $chouseBoard);
        assertStringContains('Hackable by design', $chouseAbout);
        assertStringContains('easy to build tools, experiments, and new ways of participating', $chouseAbout);
    }

    public function testApplicationRebuildsWhenRepositoryHeadMetadataIsStale(): void
    {
        [$repositoryRoot, $databasePath] = $this->createGitBackedEnvironment();
        $application = new Application(
            dirname(__DIR__),
            $repositoryRoot,
            $databasePath,
        );

        $this->render($application, '/');

        $pdo = new PDO('sqlite:' . $databasePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->prepare('UPDATE metadata SET value = :value WHERE key = :key');
        $stmt->execute([
            'key' => 'repository_head',
            'value' => 'stale-head',
        ]);

        $status = $this->render($application, '/api/read_model_status');

        assertStringContains('status=ready', $status);
        assertStringContains('rebuild_reason=repository_head_mismatch', $status);
        assertStringNotContains('repository_head=stale-head', $status);
    }

    public function testReadModelStatusReportsLockedWhenExecutionLockIsHeld(): void
    {
        [$repositoryRoot, $databasePath] = $this->createGitBackedEnvironment();
        $application = new Application(
            dirname(__DIR__),
            $repositoryRoot,
            $databasePath,
        );

        $this->render($application, '/');
        $lock = new ExecutionLock(dirname($databasePath) . '/forum-rewrite.lock', 0);
        $status = $lock->withExclusiveLock(fn () => $this->render($application, '/api/read_model_status'));
        $codebase = $lock->withExclusiveLock(fn () => $this->render($application, '/tools/codebase/'));

        assertStringContains('status=ready', $status);
        assertStringContains('lock_status=locked', $status);
        assertStringContains('System State', $codebase);
        assertStringContains('locked', $codebase);
        assertStringContains('Lock status', $codebase);
    }

    public function testApplicationClearsStaleMarkerOnNextSuccessfulRead(): void
    {
        [$repositoryRoot, $databasePath] = $this->createGitBackedEnvironment();
        $application = new Application(
            dirname(__DIR__),
            $repositoryRoot,
            $databasePath,
        );

        $this->render($application, '/');
        file_put_contents(
            dirname($databasePath) . '/read_model_stale.json',
            json_encode(['reason' => 'write_refresh_failed', 'commit_sha' => 'abc123'], JSON_THROW_ON_ERROR)
        );

        $status = $this->render($application, '/api/read_model_status');
        $codebase = $this->render($application, '/tools/codebase/');

        assertStringContains('status=ready', $status);
        assertStringContains('stale_marker=absent', $status);
        assertStringContains('rebuild_reason=stale_marker', $status);
        assertStringContains('System State', $codebase);
        assertStringContains('Stale marker', $codebase);
        assertStringContains('absent', $codebase);
    }

    public function testExecutionLockTimesOutWhenAlreadyHeld(): void
    {
        $lockPath = sys_get_temp_dir() . '/forum-rewrite-lock-' . bin2hex(random_bytes(6)) . '.lock';
        $primaryLock = new ExecutionLock($lockPath, 0);
        $contendedLock = new ExecutionLock($lockPath, 0);

        $primaryLock->withExclusiveLock(function () use ($contendedLock): void {
            try {
                $contendedLock->withExclusiveLock(static fn () => null);
                throw new RuntimeException('Expected lock contention.');
            } catch (RuntimeException $exception) {
                assertStringContains('Timed out waiting for execution lock', $exception->getMessage());
            }
        });
    }

    public function testExecutionLockTimedResultIncludesLockWait(): void
    {
        $lockPath = sys_get_temp_dir() . '/forum-rewrite-lock-' . bin2hex(random_bytes(6)) . '.lock';
        $lock = new ExecutionLock($lockPath, 0);

        $result = $lock->withExclusiveLockTimed(static fn (): string => 'locked');

        assertSame('locked', $result['result']);
        assertTrue(isset($result['timings']['lock_wait']));
        assertTrue(is_float($result['timings']['lock_wait']));
        assertTrue($result['timings']['lock_wait'] >= 0.0);
    }

    private function render(Application $application, string $path): string
    {
        return $this->renderMethod($application, 'GET', $path);
    }

    private function renderMethod(Application $application, string $method, string $path): string
    {
        ob_start();
        $application->handle($method, $path);
        return (string) ob_get_clean();
    }

    /**
     * @param array<string, string> $cookies
     */
    private function renderFrontController(FrontController $controller, string $method, string $path, array $cookies): string
    {
        ob_start();
        $controller->handle($method, $path, $cookies);
        return (string) ob_get_clean();
    }

    /**
     * Creates a bare staticHtmlRoot/publicRoot temp-directory pair and a
     * FrontController wired to them - the setup shared by every
     * testFrontController* test, which then adds its own fixture files
     * (an index.html, an asset, a stale public artifact) into the
     * returned paths before exercising the controller. Callers are
     * responsible for cleaning up both paths (e.g. via deleteTree() in a
     * finally block) once done.
     *
     * @return array{controller: FrontController, staticHtmlRoot: string, publicRoot: string}
     */
    private function buildFrontController(bool $withPublicRoot = true): array
    {
        $staticHtmlRoot = sys_get_temp_dir() . '/forum-rewrite-static-' . bin2hex(random_bytes(6));
        $publicRoot = sys_get_temp_dir() . '/forum-rewrite-public-root-' . bin2hex(random_bytes(6));
        mkdir($staticHtmlRoot, 0777, true);
        mkdir($publicRoot, 0777, true);

        $controller = new FrontController(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
            $staticHtmlRoot,
            $withPublicRoot ? $publicRoot : null,
        );

        return ['controller' => $controller, 'staticHtmlRoot' => $staticHtmlRoot, 'publicRoot' => $publicRoot];
    }

    private function copyDirectory(string $source, string $destination): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $targetPath = $destination . '/' . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0777, true);
                }

                continue;
            }

            copy($item->getPathname(), $targetPath);
        }
    }

    /**
     * @return array{string,string}
     */
    private function createGitBackedEnvironment(): array
    {
        $projectRoot = sys_get_temp_dir() . '/forum-rewrite-project-' . bin2hex(random_bytes(6));
        $repositoryRoot = $projectRoot . '/state/local_repository';
        mkdir($projectRoot . '/tests/fixtures/parity_minimal_v1', 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $projectRoot . '/tests/fixtures/parity_minimal_v1');
        LocalRepositoryBootstrap::initializeLocalRepository($projectRoot, $repositoryRoot);

        return [
            $repositoryRoot,
            $projectRoot . '/state/cache/post_index.sqlite3',
        ];
    }

    /**
     * @return array{string,string,string,string}
     */
    private function createGitBackedEnvironmentWithArtifacts(): array
    {
        $projectRoot = sys_get_temp_dir() . '/forum-rewrite-project-' . bin2hex(random_bytes(6));
        $repositoryRoot = $projectRoot . '/state/local_repository';
        $artifactRoot = $projectRoot . '/public';
        mkdir($projectRoot . '/tests/fixtures/parity_minimal_v1', 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $projectRoot . '/tests/fixtures/parity_minimal_v1');
        LocalRepositoryBootstrap::initializeLocalRepository($projectRoot, $repositoryRoot);
        mkdir($artifactRoot, 0777, true);

        return [
            $projectRoot,
            $repositoryRoot,
            $projectRoot . '/state/cache/post_index.sqlite3',
            $artifactRoot,
        ];
    }

    private function deleteDirectoryContents(string $directory): void
    {
        foreach (glob($directory . '/*') ?: [] as $path) {
            @unlink($path);
        }
    }

    private function generatePublicKey(string $username): string
    {
        $home = sys_get_temp_dir() . '/forum-rewrite-gpg-home-' . bin2hex(random_bytes(6));
        mkdir($home, 0700, true);
        $homedir = escapeshellarg($home);
        $this->runCommand(
            $home,
            'gpg --batch --no-tty --pinentry-mode loopback --passphrase "" --homedir '
            . $homedir . ' --quick-generate-key ' . escapeshellarg($username) . ' ed25519 sign 0'
        );

        $publicKey = $this->runCommand(
            $home,
            'gpg --batch --no-tty --homedir ' . $homedir . ' --armor --export'
        );

        $this->deleteTree($home);

        return trim($publicKey) . "\n";
    }

    private function extractResponseValue(string $response, string $key): string
    {
        foreach (explode("\n", trim($response)) as $line) {
            if (str_starts_with($line, $key . '=')) {
                return substr($line, strlen($key) + 1);
            }
        }

        throw new RuntimeException('Missing response key: ' . $key);
    }

    private function runCommand(string $workdir, string $command): string
    {
        $output = [];
        $exitCode = 0;
        exec('cd ' . escapeshellarg($workdir) . ' && ' . $command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Command failed: ' . $command . "\n" . implode("\n", $output));
        }

        return implode("\n", $output);
    }

    private function deleteTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
                continue;
            }

            @unlink($item->getPathname());
        }

        @rmdir($path);
    }
}

if (!function_exists('assertStringContains')) {
    function assertStringContains(string $needle, string $haystack): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException('Failed asserting that output contains: ' . $needle);
        }
    }
}

if (!function_exists('assertOrdered')) {
    function assertOrdered(string $haystack, string $first, string $second): void
    {
        $firstPos = strpos($haystack, $first);
        $secondPos = strpos($haystack, $second);
        if ($firstPos === false || $secondPos === false || $firstPos >= $secondPos) {
            throw new RuntimeException('Failed asserting that output ordering is correct.');
        }
    }
}

if (!function_exists('assertStringNotContains')) {
    function assertStringNotContains(string $needle, string $haystack): void
    {
        if (str_contains($haystack, $needle)) {
            throw new RuntimeException('Failed asserting that output does not contain: ' . $needle);
        }
    }
}

if (!function_exists('assertStringMatches')) {
    function assertStringMatches(string $pattern, string $value): void
    {
        if (preg_match($pattern, $value) !== 1) {
            throw new RuntimeException('Failed asserting that output matches: ' . $pattern);
        }
    }
}

if (!function_exists('assertFingerprintedAsset')) {
    function assertFingerprintedAsset(string $haystack, string $assetName): void
    {
        fingerprintedAssetPath($haystack, $assetName);
    }
}

if (!function_exists('fingerprintedAssetPath')) {
    function fingerprintedAssetPath(string $haystack, string $assetName): string
    {
        $extensionOffset = strrpos($assetName, '.');
        if ($extensionOffset === false) {
            throw new RuntimeException('Asset name must include an extension: ' . $assetName);
        }

        $base = preg_quote(substr($assetName, 0, $extensionOffset), '#');
        $extension = preg_quote(substr($assetName, $extensionOffset), '#');
        $pattern = '#/assets/' . $base . '\.[a-f0-9]{12}' . $extension . '#';
        if (preg_match($pattern, $haystack, $matches) !== 1) {
            throw new RuntimeException('Failed asserting that output contains fingerprinted asset: ' . $assetName);
        }

        return $matches[0];
    }
}
