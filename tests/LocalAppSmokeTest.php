<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Host\FrontController;
use ForumRewrite\Host\StaticArtifactBuilder;
use ForumRewrite\Support\ExecutionLock;
use ForumRewrite\Support\LocalRepositoryBootstrap;

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

        assertSame(0, $exitCode);
        assertTrue(is_file($this->databasePath));
    }

    public function testInjectApprovalScriptSeedsIdentity(): void
    {
        [$projectRoot, $repositoryRoot, $databasePath, $artifactRoot] = $this->createGitBackedEnvironmentWithArtifacts();
        $this->deleteDirectoryContents($repositoryRoot . '/records/approval-seeds');

        $command = sprintf(
            '%s approval seed %s %s %s %s',
            escapeshellarg(__DIR__ . '/../v3'),
            escapeshellarg('openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954'),
            escapeshellarg('script seeded approval'),
            escapeshellarg($repositoryRoot),
            escapeshellarg($databasePath),
        );
        exec($command, $output, $exitCode);

        $application = new Application($projectRoot, $repositoryRoot, $databasePath, $artifactRoot);
        $profile = $this->render($application, '/api/get_profile?profile_slug=openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954');

        assertSame(0, $exitCode);
        assertStringContains('Seeded approval for openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954', implode("\n", $output));
        assertTrue(is_file($repositoryRoot . '/records/approval-seeds/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954.txt'));
        assertStringContains('Approved: yes', $profile);
    }

    public function testInjectApprovalScriptApprovesExistingUser(): void
    {
        [$projectRoot, $repositoryRoot, $databasePath, $artifactRoot] = $this->createGitBackedEnvironmentWithArtifacts();
        $application = new Application($projectRoot, $repositoryRoot, $databasePath, $artifactRoot);

        $_POST = [
            'public_key' => $this->generatePublicKey('alice'),
        ];
        $response = $this->renderMethod($application, 'POST', '/api/link_identity');
        $_POST = [];
        $targetIdentityId = $this->extractResponseValue($response, 'identity_id');
        $targetProfileSlug = $this->extractResponseValue($response, 'profile_slug');

        $command = sprintf(
            '%s approval approve %s %s %s %s %s',
            escapeshellarg(__DIR__ . '/../v3'),
            escapeshellarg('openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954'),
            escapeshellarg($targetIdentityId),
            escapeshellarg($repositoryRoot),
            escapeshellarg($databasePath),
            escapeshellarg($artifactRoot),
        );
        exec($command, $output, $exitCode);

        $profile = $this->render($application, '/api/get_profile?profile_slug=' . rawurlencode($targetProfileSlug));

        assertSame(0, $exitCode);
        assertStringContains('Approved ' . $targetIdentityId, implode("\n", $output));
        assertStringContains('Approved: yes', $profile);
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

    public function testApplicationRendersCoreRoutes(): void
    {
        @unlink($this->databasePath);
        $application = new Application(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
        );

        $board = $this->render($application, '/');
        $thread = $this->render($application, '/threads/root-001');
        $post = $this->render($application, '/posts/root-001');
        $instance = $this->render($application, '/instance/');
        $backup = $this->render($application, '/backup/');
        $profile = $this->render($application, '/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954');
        $username = $this->render($application, '/user/guest');
        $_COOKIE = ['identity_hint' => 'guest'];
        $users = $this->render($application, '/users/');
        $tags = $this->render($application, '/tags/');
        $tagPage = $this->render($application, '/tags/bug');
        $pendingUsers = $this->render($application, '/users/pending/');
        $_COOKIE = [];
        $composeThread = $this->render($application, '/compose/thread');
        $composeReply = $this->render($application, '/compose/reply?thread_id=root-001&parent_id=root-001');
        $account = $this->render($application, '/account/key/');
        $activity = $this->render($application, '/activity/?view=content');
        $llms = $this->render($application, '/llms.txt');

        assertStringContains('Board', $board);
        assertStringContains('New Post', $board);
        assertStringContains('href="/compose/thread"', $board);
        assertStringContains('>Tags</a>', $board);
        assertStringContains('href="/tags/"', $board);
        assertStringNotContains('href="/tags/board/', $board);
        assertStringNotContains('href="/tags/label/', $board);
        assertStringContains('Labels: bug, needs-review', $board);
        assertStringContains('Hello world', $thread);
        assertStringContains('Labels: bug, needs-review', $thread);
        assertStringContains('/user/guest', $thread);
        assertStringContains('by <a href="/user/guest">guest</a> on <time datetime="2026-04-10T12:00:00Z">Apr 10, 2026 at 12:00 UTC</time>', $thread);
        assertStringNotContains('Last activity <time datetime=', $thread);
        assertOrdered($thread, 'Post <a href="/posts/root-001">root-001</a>', 'Post <a href="/posts/reply-001">reply-001</a>');
        assertStringContains('/compose/reply?thread_id=root-001&amp;parent_id=root-001', $thread);
        assertStringContains('/compose/reply?thread_id=root-001&amp;parent_id=reply-001', $thread);
        assertStringContains('First line preview.', $post);
        assertStringContains('by <a href="/user/guest">guest</a> on <time datetime="2026-04-10T12:00:00Z">Apr 10, 2026 at 12:00 UTC</time>', $post);
        assertStringContains('/compose/reply?thread_id=root-001&amp;parent_id=root-001', $post);
        assertStringContains('zenmemes', $instance);
        assertStringContains('Backup', $backup);
        assertStringContains('/backup/', $backup);
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
        assertStringContains('Identity ID', $profile);
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
        assertStringContains('All Tags', $tags);
        assertStringContains('/tags/general', $tags);
        assertStringContains('/tags/bug', $tags);
        assertStringContains('Tag', $tagPage);
        assertStringContains('#bug', $tagPage);
        assertStringContains('/threads/root-001', $tagPage);
        assertStringContains('Users Awaiting Approval', $pendingUsers);
        assertStringContains('/assets/pending_approvals.js', $pendingUsers);
        assertStringContains('meta name="app-version" content="no-git"', $board);
        assertStringContains('/assets/site.css?v=no-git', $board);
        assertStringContains('/assets/theme_toggle.js?v=no-git', $board);
        assertStringContains('/assets/version_check.js?v=no-git', $board);
        assertStringContains('data-role="app-version-banner"', $board);
        assertStringContains('Compose Thread', $composeThread);
        assertStringContains('browser_signing.js', $composeThread);
        assertStringContains('Ready.', $composeThread);
        assertStringContains('Thread ID:', $composeReply);
        assertStringContains('browser_signing.js', $composeReply);
        assertStringContains('Ready.', $composeReply);
        assertStringContains('Advanced / technical details', $account);
        assertStringContains('Set up this browser', $account);
        assertStringContains('Link identity', $account);
        assertStringContains('Saved browser identity:', $account);
        assertStringContains('/assets/openpgp.min.js', $account);
        assertStringContains('/assets/browser_signing.js', $account);
        assertStringNotContains('Bootstrap post ID', $account);
        assertStringContains('View: content', $activity);
        assertStringContains('by guest on <time datetime="2026-04-10T12:05:00Z">Apr 10, 2026 at 12:05 UTC</time>', $activity);
        assertStringContains('thread_label_add', $activity);
        assertStringContains('Labels added: bug, needs-review', $activity);
        assertStringContains('/threads/root-001', $activity);
        assertStringContains('GET /api/list_index', $llms);
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
        assertStringContains("root-001\tHello world\t1", $listIndex);
        assertStringContains('Created-At: 2026-04-10T12:00:00Z', $thread);
        assertStringContains('Last-Activity-At: 2026-04-10T12:05:00Z', $thread);
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
        assertStringContains('schema_version=7', $readModelStatus);
        assertStringContains('<rss version="2.0">', $boardRss);
        assertStringContains('<title>Hello world</title>', $threadRss);
        assertStringContains('<pubDate>Fri, 10 Apr 2026 12:05:00 +0000</pubDate>', $threadRss);
        assertStringContains('<title>Activity all</title>', $activityRss);
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

        assertStringContains('Tools', $tools);
        assertStringContains('Clip', $tools);
        assertStringContains('/assets/tools_bookmarklets.js', $tools);
        assertStringContains('data-bookmarklet-kind="clip"', $tools);
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
        assertStringContains('compose-normalization-inline', $reply);
        assertStringContains('data-role="compose-normalization-status"', $reply);
        assertStringContains('data-role="compose-normalization-message"', $reply);
        assertStringContains('data-compose-field-status-for="board_tags"', $reply);
        assertStringContains('data-compose-field-status-for="body"', $reply);
        assertStringContains('data-compose-field-label="Body"', $reply);
        assertStringContains('data-action="remove-unsupported-compose-characters"', $reply);
        assertStringContains('data-compose-field-remove-for="board_tags"', $reply);
        assertStringContains('data-compose-field-remove-for="body"', $reply);
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

        assertSame("\x1f\x8b", substr($repoArchive, 0, 2));
        assertSame("PK", substr($repoZipArchive, 0, 2));
        assertStringContains('SQLite format 3', substr($sqliteDatabase, 0, 32));
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
        $staticHtmlRoot = sys_get_temp_dir() . '/forum-rewrite-static-' . bin2hex(random_bytes(6));
        $publicRoot = sys_get_temp_dir() . '/forum-rewrite-public-root-' . bin2hex(random_bytes(6));
        mkdir($staticHtmlRoot, 0777, true);
        mkdir($publicRoot, 0777, true);
        file_put_contents($staticHtmlRoot . '/index.html', '<!doctype html><html><body><!-- route-source: static-html --><h1>Static Board</h1></body></html>');

        $controller = new FrontController(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
            $staticHtmlRoot,
            $publicRoot,
        );

        $response = $this->renderFrontController($controller, 'GET', '/', []);

        assertStringContains('Static Board', $response);
        assertStringContains('route-source: static-html', $response);
    }

    public function testFrontControllerServesStaticArtifactForBackupAlias(): void
    {
        @unlink($this->databasePath);
        $staticHtmlRoot = sys_get_temp_dir() . '/forum-rewrite-static-' . bin2hex(random_bytes(6));
        $publicRoot = sys_get_temp_dir() . '/forum-rewrite-public-root-' . bin2hex(random_bytes(6));
        mkdir($staticHtmlRoot, 0777, true);
        mkdir($publicRoot, 0777, true);
        mkdir($staticHtmlRoot . '/instance', 0777, true);
        file_put_contents($staticHtmlRoot . '/instance/index.html', '<!doctype html><html><body><!-- route-source: static-html --><h1>Static Backup</h1></body></html>');

        $controller = new FrontController(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
            $staticHtmlRoot,
            $publicRoot,
        );

        $response = $this->renderFrontController($controller, 'GET', '/backup/', []);

        assertStringContains('Static Backup', $response);
        assertStringContains('route-source: static-html', $response);
    }

    public function testFrontControllerBypassesStaticArtifactWhenCookieIsPresent(): void
    {
        @unlink($this->databasePath);
        $staticHtmlRoot = sys_get_temp_dir() . '/forum-rewrite-static-' . bin2hex(random_bytes(6));
        mkdir($staticHtmlRoot, 0777, true);
        file_put_contents($staticHtmlRoot . '/index.html', '<!doctype html><html><body><!-- route-source: static-html --><h1>Static Board</h1></body></html>');

        $controller = new FrontController(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
            $staticHtmlRoot,
        );

        $response = $this->renderFrontController($controller, 'GET', '/', ['identity_hint' => 'guest']);

        assertStringContains('Board', $response);
        assertStringContains('route-source: php-fallback', $response);
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
        assertTrue(is_file($artifactRoot . '/instance.html'));
        assertTrue(is_file($artifactRoot . '/activity.html'));
        assertTrue(is_file($artifactRoot . '/users.html'));
        assertTrue(is_file($artifactRoot . '/tags.html'));
        assertTrue(is_file($artifactRoot . '/tags/general.html'));
        assertTrue(is_file($artifactRoot . '/tags/bug.html'));
        assertTrue(is_file($artifactRoot . '/threads/root-001.html'));
        assertTrue(is_file($artifactRoot . '/posts/root-001.html'));
        assertTrue(is_file($artifactRoot . '/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954.html'));
        assertStringContains('route-source: static-html', (string) file_get_contents($artifactRoot . '/index.html'));
        assertStringContains('route-source: static-html', (string) file_get_contents($artifactRoot . '/tags.html'));
        assertStringContains('route-source: static-html', (string) file_get_contents($artifactRoot . '/tags/general.html'));
        assertStringContains('route-source: static-html', (string) file_get_contents($artifactRoot . '/tags/bug.html'));
        assertStringContains('route-source: static-html', (string) file_get_contents($artifactRoot . '/threads/root-001.html'));

        $controller = new FrontController(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
            sys_get_temp_dir() . '/forum-rewrite-unused-static-' . bin2hex(random_bytes(6)),
            $artifactRoot,
        );

        $response = $this->renderFrontController($controller, 'GET', '/threads/root-001', []);
        assertStringContains('Hello world', $response);
        assertStringContains('route-source: static-html', $response);

        $usersResponse = $this->renderFrontController($controller, 'GET', '/users/', []);
        assertStringContains('Users', $usersResponse);
        assertStringContains('route-source: static-html', $usersResponse);
    }

    public function testFrontControllerBuildsMissingArtifactAfterEligibleAnonymousFallback(): void
    {
        @unlink($this->databasePath);
        $staticHtmlRoot = sys_get_temp_dir() . '/forum-rewrite-static-' . bin2hex(random_bytes(6));
        $publicRoot = sys_get_temp_dir() . '/forum-rewrite-public-root-' . bin2hex(random_bytes(6));
        mkdir($staticHtmlRoot, 0777, true);
        mkdir($publicRoot, 0777, true);

        $controller = new FrontController(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
            $staticHtmlRoot,
            $publicRoot,
        );

        $firstResponse = $this->renderFrontController($controller, 'GET', '/threads/root-001', []);
        assertStringContains('Hello world', $firstResponse);
        assertStringContains('route-source: php-fallback', $firstResponse);
        assertTrue(is_file($publicRoot . '/threads/root-001.html'));
        assertStringContains('route-source: static-html', (string) file_get_contents($publicRoot . '/threads/root-001.html'));

        $secondResponse = $this->renderFrontController($controller, 'GET', '/threads/root-001', []);
        assertStringContains('Hello world', $secondResponse);
        assertStringContains('route-source: static-html', $secondResponse);
    }

    public function testFrontControllerDoesNotBuildArtifactForCookieBearingFallback(): void
    {
        @unlink($this->databasePath);
        $staticHtmlRoot = sys_get_temp_dir() . '/forum-rewrite-static-' . bin2hex(random_bytes(6));
        $publicRoot = sys_get_temp_dir() . '/forum-rewrite-public-root-' . bin2hex(random_bytes(6));
        mkdir($staticHtmlRoot, 0777, true);
        mkdir($publicRoot, 0777, true);

        $controller = new FrontController(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
            $staticHtmlRoot,
            $publicRoot,
        );

        $response = $this->renderFrontController($controller, 'GET', '/threads/root-001', ['identity_hint' => 'guest']);
        assertStringContains('Hello world', $response);
        assertStringContains('route-source: php-fallback', $response);
        assertFalse(is_file($publicRoot . '/threads/root-001.html'));
    }

    public function testFrontControllerDoesNotBuildArtifactForQueryFallback(): void
    {
        @unlink($this->databasePath);
        $staticHtmlRoot = sys_get_temp_dir() . '/forum-rewrite-static-' . bin2hex(random_bytes(6));
        $publicRoot = sys_get_temp_dir() . '/forum-rewrite-public-root-' . bin2hex(random_bytes(6));
        mkdir($staticHtmlRoot, 0777, true);
        mkdir($publicRoot, 0777, true);

        $controller = new FrontController(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
            $staticHtmlRoot,
            $publicRoot,
        );

        $response = $this->renderFrontController($controller, 'GET', '/activity/?view=all', []);
        assertStringContains('Activity', $response);
        assertStringContains('route-source: php-fallback', $response);
        assertFalse(is_file($publicRoot . '/activity.html'));
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

        assertStringContains('status=ready', $status);
        assertStringContains('lock_status=locked', $status);
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

        assertStringContains('status=ready', $status);
        assertStringContains('stale_marker=absent', $status);
        assertStringContains('rebuild_reason=stale_marker', $status);
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
