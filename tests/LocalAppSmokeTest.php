<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Host\AssetFingerprint;
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

    public function testAssetFingerprintPathsUseContentHashFilenames(): void
    {
        $publicRoot = dirname(__DIR__) . '/public';

        $siteCssPath = AssetFingerprint::fingerprintedPath($publicRoot, '/assets/site.css');
        assertStringMatches('#^/assets/site\.[a-f0-9]{12}\.css$#', $siteCssPath);
        assertSame($publicRoot . '/assets/site.css', AssetFingerprint::sourcePathForFingerprint($publicRoot, $siteCssPath));
        assertSame(null, AssetFingerprint::sourcePathForFingerprint($publicRoot, '/assets/site.000000000000.css'));
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
        $threadsIndex = $this->render($application, '/threads/');
        $threadsIndexNoSlash = $this->render($application, '/threads');
        $about = $this->render($application, '/about/');
        $thread = $this->render($application, '/threads/root-001');
        $post = $this->render($application, '/posts/root-001');
        $instance = $this->render($application, '/instance/');
        $backup = $this->render($application, '/backup/');
        $toolsBackup = $this->render($application, '/tools/backup/');
        $tools = $this->render($application, '/tools/');
        $codebase = $this->render($application, '/tools/codebase/');
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
        assertFingerprintedAsset($thread, 'openpgp_loader.js');
        assertFingerprintedAsset($thread, 'browser_signing.js');
        assertFingerprintedAsset($thread, 'inline_reply_form.js');
        assertFingerprintedAsset($thread, 'thread_reactions.js');
        assertFingerprintedAsset($thread, 'post_analysis.js');
        assertStringContains('data-thread-reactions-root', $thread);
        assertStringContains('data-action="apply-thread-tag"', $thread);
        assertStringContains('class="card post-card thread-root-card"', $thread);
        assertStringContains('data-thread-id="root-001" data-post-id="root-001"', $thread);
        assertSame(1, substr_count($thread, 'by <a href="/user/guest">guest</a> on <time datetime="2026-04-10T12:00:00Z">Apr 10, 2026 at 12:00 UTC</time>'));
        assertOrdered($thread, '<h1>Hello world</h1>', 'First line preview.');
        assertOrdered($thread, 'First line preview.', 'id="post-reply-001"');
        assertStringContains('inline-reply-composer', $thread);
        assertStringContains('data-inline-reply-details', $thread);
        assertStringContains('class="inline-reply-prompt"', $thread);
        assertStringContains('placeholder="Write a reply..."', $thread);
        $inlineReplyScript = (string) file_get_contents(__DIR__ . '/../public/assets/inline_reply_form.js');
        assertStringContains('function scrollFullyIntoView(node)', $inlineReplyScript);
        assertStringContains('node.scrollIntoView({', $inlineReplyScript);
        assertStringContains('inline-reply-identity-status', $thread);
        assertStringContains('method="post" action="/compose/reply" class="stack" data-compose-form data-compose-kind="reply"', $thread);
        assertStringContains('method="post" action="/compose/reply" class="stack" data-compose-form data-compose-kind="reply"', $composeReply);
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
        assertStringContains('/user/guest', $thread);
        assertStringContains('by <a href="/user/guest">guest</a> on <time datetime="2026-04-10T12:00:00Z">Apr 10, 2026 at 12:00 UTC</time>', $thread);
        assertStringNotContains('Last activity <time datetime=', $thread);
        assertStringContains('id="post-root-001"', $thread);
        assertStringContains('id="post-reply-001"', $thread);
        assertOrdered($thread, 'href="/posts/root-001" title="Post root-001" aria-label="Post root-001">#</a>', 'href="/posts/reply-001" title="Post reply-001" aria-label="Post reply-001">#</a>');
        assertStringNotContains('Post <a href="/posts/root-001">root-001</a>', $thread);
        assertStringContains('/compose/reply?thread_id=root-001&amp;parent_id=root-001', $thread);
        assertStringContains('/compose/reply?thread_id=root-001&amp;parent_id=reply-001', $thread);
        assertStringContains('First line preview.', $post);
        assertStringContains('by <a href="/user/guest">guest</a> on <time datetime="2026-04-10T12:00:00Z">Apr 10, 2026 at 12:00 UTC</time>', $post);
        assertStringContains('/compose/reply?thread_id=root-001&amp;parent_id=root-001', $post);
        assertStringContains('zenmemes', $instance);
        assertStringContains('Backup', $backup);
        assertStringContains('Backup', $toolsBackup);
        assertStringContains('class="nav-link is-active" href="/tools/backup/"', $toolsBackup);
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
        assertStringContains('/tools/bookmarklets/', $tools);
        assertStringContains('/tools/backup/', $tools);
        assertStringContains('/tools/codebase/', $tools);
        assertStringContains('Current application version, repository head, and read-model health.', $tools);
        assertStringContains('/account/key/', $tools);
        assertStringContains('System State', $codebase);
        assertStringContains('class="nav-link is-active" href="/tools/codebase/"', $codebase);
        assertStringContains('Repository head', $codebase);
        assertStringContains('Read model', $codebase);
        assertStringContains('Schema version', $codebase);
        assertStringContains('Lock status', $codebase);
        assertStringContains('Read-model rows', $codebase);
        assertStringContains('git -C REPOSITORY rev-parse HEAD', $codebase);
        assertStringContains("SELECT value FROM metadata WHERE key = &#039;schema_version&#039;", $codebase);
        assertStringContains('flock DATABASE_DIR/forum-rewrite.lock', $codebase);
        assertStringContains('SELECT COUNT(*) FROM posts', $codebase);
        assertStringContains('/downloads/repository.tar.gz', $codebase);
        assertStringContains('About zenmemes', $about);
        assertStringContains('extraordinary people', $about);
        assertStringContains('Harvard St Commons', $about);
        assertStringContains('continuous social graph', $about);
        assertStringContains('/tools/backup/', $about);
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
        assertFingerprintedAsset($board, 'site.css');
        assertFingerprintedAsset($board, 'theme_toggle.js');
        assertFingerprintedAsset($board, 'compose_draft_clear.js');
        assertFingerprintedAsset($board, 'version_check.js');
        assertStringContains('data-role="app-version-banner"', $board);
        assertStringContains('Compose Thread', $composeThread);
        assertFingerprintedAsset($composeThread, 'browser_signing.js');
        assertStringContains('Ready.', $composeThread);
        assertStringContains('data-action="submit-anonymous-compose"', $composeThread);
        assertStringContains('Bookmarklets', $bookmarklets);
        assertStringContains('class="nav-link is-active" href="/tools/bookmarklets/"', $bookmarklets);
        assertFingerprintedAsset($bookmarklets, 'tools_bookmarklets.js');
        assertStringContains('data-bookmarklet-kind="clip"', $bookmarklets);
        assertStringContains('Thread ID:', $composeReply);
        assertFingerprintedAsset($composeReply, 'browser_signing.js');
        assertStringNotContains('/assets/inline_reply_form.js', $composeReply);
        assertStringContains('Ready.', $composeReply);
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
        assertStringContains('View: content', $activity);
        assertStringContains('by guest on <time datetime="2026-04-10T12:05:00Z">Apr 10, 2026 at 12:05 UTC</time>', $activity);
        assertStringContains('thread_label_add', $activity);
        assertStringContains('Labels added: bug, needs-review', $activity);
        assertStringContains('/threads/root-001', $activity);
        assertStringContains('GET /about/', $llms);
        assertStringContains('POST /api/analyze_post', $llms);
        assertStringContains('GET /api/list_index', $llms);
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
        assertStringContains('schema_version=9', $readModelStatus);
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
        assertFingerprintedAsset($bookmarklets, 'tools_bookmarklets.js');
        assertStringContains('class="nav-link is-active" href="/tools/bookmarklets/"', $bookmarklets);
        assertStringContains('data-bookmarklet-kind="clip"', $bookmarklets);
        assertStringContains('window.getSelection().toString().trim()', $bookmarkletAsset);
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

    public function testRequestDataParsesRawFormEncodedBodyWhenPostIsEmpty(): void
    {
        $application = new Application(dirname(__DIR__), $this->repositoryRoot, $this->databasePath);
        $method = new ReflectionMethod(Application::class, 'mergeRequestBodyData');
        $method->setAccessible(true);

        $result = $method->invoke(
            $application,
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

    public function testFrontControllerShowsBusyErrorForExecutionLockContention(): void
    {
        [$repositoryRoot, $databasePath] = $this->createGitBackedEnvironment();
        @unlink($databasePath);
        $staticHtmlRoot = sys_get_temp_dir() . '/forum-rewrite-static-' . bin2hex(random_bytes(6));
        mkdir($staticHtmlRoot, 0777, true);
        $controller = new FrontController(
            dirname(__DIR__),
            $repositoryRoot,
            $databasePath,
            $staticHtmlRoot,
        );

        putenv('FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS=0');
        try {
            $lock = new ExecutionLock(dirname($databasePath) . '/forum-rewrite.lock', 0);
            $response = $lock->withExclusiveLock(fn () => $this->renderFrontController($controller, 'GET', '/', []));
        } finally {
            putenv('FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS');
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
        assertTrue(is_file($artifactRoot . '/tags.html'));
        assertTrue(is_file($artifactRoot . '/tags/index.html'));
        assertTrue(is_file($artifactRoot . '/tags/general.html'));
        assertTrue(is_file($artifactRoot . '/tags/bug.html'));
        assertTrue(is_file($artifactRoot . '/threads/root-001.html'));
        assertTrue(is_file($artifactRoot . '/threads/thread-zenmemes-rules.html'));
        assertTrue(is_file($artifactRoot . '/posts/root-001.html'));
        assertTrue(is_file($artifactRoot . '/posts/thread-zenmemes-rules.html'));
        assertTrue(is_file($artifactRoot . '/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954.html'));
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

        $threadsResponse = $this->renderFrontController($controller, 'GET', '/threads/', []);
        assertSame((string) file_get_contents($artifactRoot . '/index.html'), $threadsResponse);
        assertStringContains('route-source: static-html', $threadsResponse);

        $threadsNoSlashResponse = $this->renderFrontController($controller, 'GET', '/threads', []);
        assertSame((string) file_get_contents($artifactRoot . '/index.html'), $threadsNoSlashResponse);
        assertStringContains('route-source: static-html', $threadsNoSlashResponse);

        $usersResponse = $this->renderFrontController($controller, 'GET', '/users/', []);
        assertStringContains('Users', $usersResponse);
        assertStringContains('route-source: static-html', $usersResponse);

        $tagsResponse = $this->renderFrontController($controller, 'GET', '/tags/', []);
        assertStringContains('class="nav-link is-active" href="/tags/"', $tagsResponse);
        assertStringContains('route-source: static-html', $tagsResponse);
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
