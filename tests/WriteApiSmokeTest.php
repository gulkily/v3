<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\IncrementalReadModelUpdater;
use ForumRewrite\Write\LocalWriteService;

final class WriteApiSmokeTest
{
    public function testCreateThreadAndReplyApisWriteCanonicalFilesCommitAndInvalidateArtifacts(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->seedArtifacts($artifactRoot, [
            '/index.html',
        ]);
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $threadResponse = $this->renderMethod(
            $application,
            'POST',
            '/api/create_thread?board_tags=general&subject=New%20Thread&body=Thread%20body'
        );
        $threadId = $this->extractValue($threadResponse, 'thread_id');
        $threadCommitSha = $this->extractValue($threadResponse, 'commit_sha');
        $threadPage = $this->renderMethod($application, 'GET', '/threads/' . $threadId);
        $this->seedArtifacts($artifactRoot, [
            '/threads/' . $threadId . '.html',
            '/posts/' . $threadId . '.html',
            '/index.html',
        ]);

        $replyResponse = $this->renderMethod(
            $application,
            'POST',
            '/api/create_reply?thread_id=' . rawurlencode($threadId) . '&parent_id=' . rawurlencode($threadId) . '&body=Reply%20body'
        );
        $replyId = $this->extractValue($replyResponse, 'post_id');
        $replyCommitSha = $this->extractValue($replyResponse, 'commit_sha');
        $postPage = $this->renderMethod($application, 'GET', '/posts/' . $replyId);

        assertStringContains('status=ok', $threadResponse);
        assertTrue(strlen($threadCommitSha) === 40);
        assertTrue(is_file($repositoryRoot . '/records/posts/' . $threadId . '.txt'));
        assertStringContains('Created-At: ', (string) file_get_contents($repositoryRoot . '/records/posts/' . $threadId . '.txt'));
        assertStringContains('New Thread', $threadPage);
        assertFalse(is_file($artifactRoot . '/index.html'));
        assertStringContains('status=ok', $replyResponse);
        assertTrue(strlen($replyCommitSha) === 40);
        assertTrue(is_file($repositoryRoot . '/records/posts/' . $replyId . '.txt'));
        assertStringContains('Created-At: ', (string) file_get_contents($repositoryRoot . '/records/posts/' . $replyId . '.txt'));
        assertStringContains('Reply body', $postPage);
        assertStringContains('by guest on <time datetime="', $postPage);
        assertFalse(is_file($artifactRoot . '/threads/' . $threadId . '.html'));
        assertFalse(is_file($artifactRoot . '/posts/' . $replyId . '.html'));
        assertTrue(is_file($artifactRoot . '/posts/' . $threadId . '.html'));
        assertSame($replyCommitSha, $this->gitOutput($repositoryRoot, 'rev-parse HEAD'));
    }

    public function testLinkIdentityUsesPublicKeyUserIdForUsernameAndInvalidatesProfileArtifact(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->deleteDirectoryContents($repositoryRoot . '/records/identity');
        $this->deleteDirectoryContents($repositoryRoot . '/records/public-keys');
        $this->seedArtifacts($artifactRoot, [
            '/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954.html',
        ]);

        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $_POST = [
            'public_key' => $this->readFixturePublicKey(),
        ];
        $response = $this->renderMethod(
            $application,
            'POST',
            '/api/link_identity?bootstrap_post_id=root-001'
        );
        $_POST = [];
        $commitSha = $this->extractValue($response, 'commit_sha');

        $profile = $this->renderMethod(
            $application,
            'GET',
            '/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954'
        );
        $usernameRoute = $this->renderMethod($application, 'GET', '/user/forum-user');

        assertStringContains('status=ok', $response);
        assertStringContains('username=forum-user', $response);
        assertTrue(strlen($commitSha) === 40);
        assertTrue(is_file($repositoryRoot . '/records/identity/identity-openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954.txt'));
        assertTrue(is_file($repositoryRoot . '/records/public-keys/openpgp-0168FF20EB09C3EA6193BD3C92A73AA7D20A0954.asc'));
        assertStringContains('Visible username:</strong> forum-user', $profile);
        assertStringContains('User forum-user', $usernameRoute);
        assertStringContains('Approved Profiles', $usernameRoute);
        assertStringContains('/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954', $usernameRoute);
        assertFalse(is_file($artifactRoot . '/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954.html'));
        assertSame($commitSha, $this->gitOutput($repositoryRoot, 'rev-parse HEAD'));
    }

    public function testLinkIdentityAutoCreatesHiddenBootstrapPostWhenNoBootstrapPostIdIsProvided(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->deleteDirectoryContents($repositoryRoot . '/records/identity');
        $this->deleteDirectoryContents($repositoryRoot . '/records/public-keys');
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $_POST = [
            'public_key' => $this->readFixturePublicKey(),
        ];
        $response = $this->renderMethod($application, 'POST', '/api/link_identity');
        $_POST = [];

        $bootstrapPostId = $this->extractValue($response, 'bootstrap_post_id');
        $bootstrapThreadId = $this->extractValue($response, 'bootstrap_thread_id');
        $bootstrapRecord = (string) file_get_contents($repositoryRoot . '/records/posts/' . $bootstrapPostId . '.txt');
        $board = $this->renderMethod($application, 'GET', '/');
        $activity = $this->renderMethod($application, 'GET', '/activity/?view=all');
        $bootstrapPostPage = $this->renderMethod($application, 'GET', '/posts/' . $bootstrapPostId);
        $profile = $this->renderMethod(
            $application,
            'GET',
            '/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954'
        );

        assertStringContains('status=ok', $response);
        assertSame($bootstrapPostId, $bootstrapThreadId);
        assertStringContains('Board-Tags: identity internal', $bootstrapRecord);
        assertStringContains('Subject: account bootstrap', $bootstrapRecord);
        assertStringNotContains($bootstrapPostId, $board);
        assertStringNotContains($bootstrapPostId, $activity);
        assertStringContains('Automatic account bootstrap anchor.', $bootstrapPostPage);
        assertStringContains('Threads:</strong> 0', $profile);
        assertStringContains('Posts:</strong> 0', $profile);
    }

    public function testHtmlComposeAndAccountFormsSubmitAgainstWritableRepo(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->deleteDirectoryContents($repositoryRoot . '/records/identity');
        $this->deleteDirectoryContents($repositoryRoot . '/records/public-keys');

        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $_POST = [
            'board_tags' => 'general',
            'subject' => 'Form Thread',
            'body' => 'Form body',
        ];
        $threadResponse = $this->renderMethod($application, 'POST', '/compose/thread');
        $_POST = [];

        $threadId = $this->extractHrefId($threadResponse, '/threads/');

        $_POST = [
            'thread_id' => $threadId,
            'parent_id' => $threadId,
            'board_tags' => 'general',
            'body' => 'Form reply body',
        ];
        $replyResponse = $this->renderMethod($application, 'POST', '/compose/reply');
        $_POST = [];

        $_POST = [
            'public_key' => $this->readFixturePublicKey(),
        ];
        $accountResponse = $this->renderMethod($application, 'POST', '/account/key/');
        $_POST = [];

        assertStringContains('Redirecting', $threadResponse);
        assertStringContains('Created thread', $threadResponse);
        assertStringContains('Commit ', $threadResponse);
        assertStringContains('Redirecting', $replyResponse);
        assertStringContains('Created reply', $replyResponse);
        assertStringContains('/threads/' . $threadId, $replyResponse);
        assertStringContains('Commit ', $replyResponse);
        assertStringContains('Redirecting', $accountResponse);
        assertStringContains('Linked identity', $accountResponse);
        assertStringContains('/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954', $accountResponse);
        assertStringContains('Commit ', $accountResponse);
    }

    public function testCreateThreadUsesAuthorIdentityForRenderedAuthorLabel(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->deleteDirectoryContents($repositoryRoot . '/records/identity');
        $this->deleteDirectoryContents($repositoryRoot . '/records/public-keys');
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $_POST = [
            'public_key' => $this->readFixturePublicKey(),
        ];
        $identityResponse = $this->renderMethod($application, 'POST', '/api/link_identity?bootstrap_post_id=root-001');
        $_POST = [];

        $identityId = $this->extractValue($identityResponse, 'identity_id');
        $threadResponse = $this->renderMethod(
            $application,
            'POST',
            '/api/create_thread?board_tags=general&subject=Signed%20Thread&body=Thread%20body&author_identity_id=' . rawurlencode($identityId)
        );
        $threadId = $this->extractValue($threadResponse, 'thread_id');
        $threadPage = $this->renderMethod($application, 'GET', '/threads/' . $threadId);

        assertStringContains('status=ok', $threadResponse);
        assertStringContains('forum-user', $threadPage);
        assertStringContains('/user/forum-user', $threadPage);
        assertStringContains('by <a href="/user/forum-user">forum-user</a> on <time datetime="', $threadPage);
        assertStringNotContains('(unapproved)', $threadPage);
        assertFalse(str_contains($threadPage, 'by guest'));
    }

    public function testThreadAndReplyWritesUseIncrementalReadModelUpdateWhenDatabaseIsWarm(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
        $threadResult = $service->createThread([
            'board_tags' => 'general',
            'subject' => 'Incremental Thread',
            'body' => 'Incremental body',
        ]);
        $replyResult = $service->createReply([
            'thread_id' => $threadResult['thread_id'],
            'parent_id' => $threadResult['thread_id'],
            'board_tags' => 'general',
            'body' => 'Incremental reply',
        ]);

        $threadPage = $this->renderMethod($application, 'GET', '/threads/' . $threadResult['thread_id']);
        $replyPage = $this->renderMethod($application, 'GET', '/posts/' . $replyResult['post_id']);

        assertSame(true, isset($threadResult['timings']['read_model_incremental_update']));
        assertSame(false, isset($threadResult['timings']['read_model_rebuild']));
        assertSame(true, isset($replyResult['timings']['read_model_incremental_update']));
        assertSame(false, isset($replyResult['timings']['read_model_rebuild']));
        assertStringContains('Incremental Thread', $threadPage);
        assertStringContains('Incremental reply', $replyPage);
        assertStringContains('Incremental reply', $threadPage);
    }

    public function testCreateThreadWithHashtagsWritesThreadLabelRecordAndRendersLabels(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $response = $this->renderMethod(
            $application,
            'POST',
            '/api/create_thread?board_tags=general&subject=Tagged%20Thread&body='
            . rawurlencode("Thread body\n#bug #needs-review\n> #ignored\n#bug\n")
        );
        $threadId = $this->extractValue($response, 'thread_id');
        $threadPage = $this->renderMethod($application, 'GET', '/threads/' . $threadId);
        $records = glob($repositoryRoot . '/records/thread-labels/*.txt');

        assertStringContains('status=ok', $response);
        assertStringContains('Labels: bug, needs-review', $threadPage);
        assertSame(2, count($records));
        assertSame(1, count(array_filter($records, static fn (string $path): bool => str_contains((string) file_get_contents($path), 'Thread-ID: ' . $threadId))));

        $recordPath = array_values(array_filter($records, static fn (string $path): bool => str_contains((string) file_get_contents($path), 'Thread-ID: ' . $threadId)))[0];
        $recordContents = (string) file_get_contents($recordPath);
        assertStringContains('Labels: bug needs-review', $recordContents);
        assertStringNotContains('ignored', $recordContents);
    }

    public function testCreateReplyWithHashtagsWritesThreadLabelRecordForTargetThread(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $response = $this->renderMethod(
            $application,
            'POST',
            '/api/create_reply?thread_id=root-001&parent_id=root-001&body=' . rawurlencode("Reply body\n#answered\n> #ignored\n")
        );
        $threadPage = $this->renderMethod($application, 'GET', '/threads/root-001');
        $records = glob($repositoryRoot . '/records/thread-labels/*.txt');
        $matchingRecords = array_values(array_filter(
            $records,
            static fn (string $path): bool => str_contains((string) file_get_contents($path), "Thread-ID: root-001\n")
                && str_contains((string) file_get_contents($path), 'Labels: answered')
        ));

        assertStringContains('status=ok', $response);
        assertStringContains('Labels: answered, bug, needs-review', $threadPage);
        assertSame(1, count($matchingRecords));
    }

    public function testLabeledWritesRebuildReadModelAndPublishLabelActivityImmediately(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
        $result = $service->createReply([
            'thread_id' => 'root-001',
            'parent_id' => 'root-001',
            'board_tags' => 'general',
            'body' => "Reply body\n#answered\n",
        ]);

        $activity = $this->renderMethod($application, 'GET', '/activity/?view=content');

        assertSame(true, isset($result['timings']['read_model_rebuild']));
        assertSame(false, isset($result['timings']['read_model_incremental_update']));
        assertStringContains('thread_label_add', $activity);
        assertStringContains('Labels added: answered', $activity);
        assertStringContains('/threads/root-001', $activity);
    }

    public function testApprovedLikeTagAddsScoreAndDoesNotDoubleCountForSameIdentity(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $first = $this->renderMethod(
            $application,
            'POST',
            '/api/create_reply?thread_id=root-001&parent_id=root-001&author_identity_id='
            . rawurlencode('openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954')
            . '&body=' . rawurlencode("Reply body\n#like\n")
        );
        $second = $this->renderMethod(
            $application,
            'POST',
            '/api/create_reply?thread_id=root-001&parent_id=root-001&author_identity_id='
            . rawurlencode('openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954')
            . '&body=' . rawurlencode("Another reply\n#like\n")
        );

        $threadPage = $this->renderMethod($application, 'GET', '/threads/root-001');
        $threadApi = $this->renderMethod($application, 'GET', '/api/get_thread?thread_id=root-001');
        $board = $this->renderMethod($application, 'GET', '/');

        assertStringContains('status=ok', $first);
        assertStringContains('status=ok', $second);
        assertStringContains('Score: 1', $threadPage);
        assertStringContains('Score-Total: 1', $threadApi);
        assertStringContains('Score: 1', $board);
    }

    public function testUnapprovedLikeTagDoesNotAffectScore(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $identity = $this->linkGeneratedIdentity($application, 'alice');
        $response = $this->renderMethod(
            $application,
            'POST',
            '/api/create_reply?thread_id=root-001&parent_id=root-001&author_identity_id='
            . rawurlencode($identity['identity_id'])
            . '&body=' . rawurlencode("Reply body\n#like\n")
        );

        $threadPage = $this->renderMethod($application, 'GET', '/threads/root-001');
        $threadApi = $this->renderMethod($application, 'GET', '/api/get_thread?thread_id=root-001');

        assertStringContains('status=ok', $response);
        assertStringContains('Score: 0', $threadPage);
        assertStringContains('Score-Total: 0', $threadApi);
    }

    public function testApprovedFlagTagSubtractsFromScore(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $response = $this->renderMethod(
            $application,
            'POST',
            '/api/create_reply?thread_id=root-001&parent_id=root-001&author_identity_id='
            . rawurlencode('openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954')
            . '&body=' . rawurlencode("Reply body\n#flag\n")
        );

        $threadPage = $this->renderMethod($application, 'GET', '/threads/root-001');
        $threadApi = $this->renderMethod($application, 'GET', '/api/get_thread?thread_id=root-001');

        assertStringContains('status=ok', $response);
        assertStringContains('Score: -100', $threadPage);
        assertStringContains('Score-Total: -100', $threadApi);
    }

    public function testApplyThreadTagApiWritesApprovedLikeAndReportsUpdatedScore(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $recordsBefore = count(glob($repositoryRoot . '/records/thread-labels/*.txt') ?: []);

        $_COOKIE = ['identity_hint' => 'guest'];
        $response = $this->renderMethod($application, 'POST', '/api/apply_thread_tag?thread_id=root-001&tag=like');
        $_COOKIE = [];

        $threadPage = $this->renderMethod($application, 'GET', '/threads/root-001');
        $recordsAfter = count(glob($repositoryRoot . '/records/thread-labels/*.txt') ?: []);

        assertStringContains('status=ok', $response);
        assertStringContains('thread_id=root-001', $response);
        assertStringContains('tag=like', $response);
        assertStringContains('score_total=1', $response);
        assertStringContains('viewer_is_approved=yes', $response);
        assertStringContains('wrote_record=yes', $response);
        assertTrue(strlen($this->extractValue($response, 'commit_sha')) === 40);
        assertSame($recordsBefore + 1, $recordsAfter);
        assertStringContains('Score: 1', $threadPage);
    }

    public function testApplyThreadTagApiDuplicateShortCircuitsWithoutNewRecord(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $_COOKIE = ['identity_hint' => 'guest'];
        $first = $this->renderMethod($application, 'POST', '/api/apply_thread_tag?thread_id=root-001&tag=like');
        $recordsAfterFirst = count(glob($repositoryRoot . '/records/thread-labels/*.txt') ?: []);
        $second = $this->renderMethod($application, 'POST', '/api/apply_thread_tag?thread_id=root-001&tag=like');
        $_COOKIE = [];

        $recordsAfterSecond = count(glob($repositoryRoot . '/records/thread-labels/*.txt') ?: []);
        $threadApi = $this->renderMethod($application, 'GET', '/api/get_thread?thread_id=root-001');

        assertStringContains('wrote_record=yes', $first);
        assertStringContains('score_total=1', $first);
        assertStringContains('wrote_record=no', $second);
        assertStringContains('score_total=1', $second);
        assertStringNotContains('commit_sha=', $second);
        assertSame($recordsAfterFirst, $recordsAfterSecond);
        assertStringContains('Score-Total: 1', $threadApi);
    }

    public function testApplyThreadTagApiAllowsUnapprovedUserButDoesNotChangeScore(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $identity = $this->linkGeneratedIdentity($application, 'alice');
        $recordsBefore = count(glob($repositoryRoot . '/records/thread-labels/*.txt') ?: []);

        $_COOKIE = ['identity_hint' => 'alice'];
        $response = $this->renderMethod($application, 'POST', '/api/apply_thread_tag?thread_id=root-001&tag=like');
        $_COOKIE = [];

        $threadPage = $this->renderMethod($application, 'GET', '/threads/root-001');
        $recordsAfter = count(glob($repositoryRoot . '/records/thread-labels/*.txt') ?: []);

        assertStringContains('status=ok', $response);
        assertStringContains('viewer_identity_id=' . $identity['identity_id'], $response);
        assertStringContains('viewer_is_approved=no', $response);
        assertStringContains('score_total=0', $response);
        assertStringContains('wrote_record=yes', $response);
        assertSame($recordsBefore + 1, $recordsAfter);
        assertStringContains('Score: 0', $threadPage);
    }

    public function testApplyThreadTagApiRejectsAnonymousViewerAndUnknownTag(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $_COOKIE = [];
        $anonymous = $this->renderMethod($application, 'POST', '/api/apply_thread_tag?thread_id=root-001&tag=like');

        $_COOKIE = ['identity_hint' => 'guest'];
        $unknownTag = $this->renderMethod($application, 'POST', '/api/apply_thread_tag?thread_id=root-001&tag=bug');
        $_COOKIE = [];

        assertStringContains('error=You must set an identity hint before applying a tag.', $anonymous);
        assertStringContains('error=tag must be one of: like, flag', $unknownTag);
    }

    public function testIncrementalFailureFallsBackToFullRebuildAndKeepsReadModelHealthy(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $service = new class($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot)) extends LocalWriteService {
            protected function incrementalReadModelUpdater(): IncrementalReadModelUpdater
            {
                return new class($this->databasePath(), $this->repositoryRoot()) extends IncrementalReadModelUpdater {
                    public function applyPostWrite(\ForumRewrite\Canonical\PostRecord $record, string $commitSha): array
                    {
                        throw new RuntimeException('simulated incremental failure');
                    }
                };
            }
        };

        $result = $service->createThread([
            'board_tags' => 'general',
            'subject' => 'Fallback Thread',
            'body' => 'Fallback body',
        ]);

        $threadPage = $this->renderMethod($application, 'GET', '/threads/' . $result['thread_id']);
        $staleMarkerPath = dirname($databasePath) . '/read_model_stale.json';

        assertSame(true, isset($result['timings']['read_model_incremental_fallback']));
        assertSame(true, isset($result['timings']['read_model_rebuild_fallback']));
        assertStringContains('Fallback Thread', $threadPage);
        assertFalse(is_file($staleMarkerPath));
    }

    public function testIncrementalThreadAndReplyMatchFreshRebuildView(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $identity = $this->linkGeneratedIdentity($application, 'compare-user');
        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
        $threadResult = $service->createThread([
            'board_tags' => 'general',
            'subject' => 'Parity Thread',
            'body' => 'Parity body',
            'author_identity_id' => $identity['identity_id'],
        ]);
        $replyResult = $service->createReply([
            'thread_id' => $threadResult['thread_id'],
            'parent_id' => $threadResult['thread_id'],
            'board_tags' => 'general',
            'body' => 'Parity reply',
            'author_identity_id' => $identity['identity_id'],
        ]);

        $freshDatabasePath = sys_get_temp_dir() . '/forum-rewrite-write-db-parity-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $freshApplication = new Application(dirname(__DIR__), $repositoryRoot, $freshDatabasePath, $artifactRoot);

        $incrementalBoard = $this->renderMethod($application, 'GET', '/');
        $freshBoard = $this->renderMethod($freshApplication, 'GET', '/');
        $incrementalThread = $this->renderMethod($application, 'GET', '/threads/' . $threadResult['thread_id']);
        $freshThread = $this->renderMethod($freshApplication, 'GET', '/threads/' . $threadResult['thread_id']);
        $incrementalReply = $this->renderMethod($application, 'GET', '/posts/' . $replyResult['post_id']);
        $freshReply = $this->renderMethod($freshApplication, 'GET', '/posts/' . $replyResult['post_id']);
        $incrementalUser = $this->renderMethod($application, 'GET', '/user/compare-user');
        $freshUser = $this->renderMethod($freshApplication, 'GET', '/user/compare-user');

        assertSame($this->normalizeRenderedPage($freshBoard), $this->normalizeRenderedPage($incrementalBoard));
        assertSame($this->normalizeRenderedPage($freshThread), $this->normalizeRenderedPage($incrementalThread));
        assertSame($this->normalizeRenderedPage($freshReply), $this->normalizeRenderedPage($incrementalReply));
        assertSame($this->normalizeRenderedPage($freshUser), $this->normalizeRenderedPage($incrementalUser));
    }

    public function testWriteApiReportsGitFailureWithoutInvalidatingArtifacts(): void
    {
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-write-repo-' . bin2hex(random_bytes(6));
        mkdir($repositoryRoot, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-write-db-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $artifactRoot = sys_get_temp_dir() . '/forum-rewrite-write-public-' . bin2hex(random_bytes(6));
        mkdir($artifactRoot, 0777, true);
        $this->seedArtifacts($artifactRoot, [
            '/index.html',
        ]);

        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $response = $this->renderMethod(
            $application,
            'POST',
            '/api/create_thread?board_tags=general&subject=New%20Thread&body=Thread%20body'
        );

        assertStringContains('error=Writable repository must be a git checkout before writes are allowed.', $response);
        assertTrue(is_file($artifactRoot . '/index.html'));
    }

    public function testWriteMarksDerivedStateStaleWhenRefreshFailsAfterCommit(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->seedArtifacts($artifactRoot, ['/index.html']);
        $service = new class($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot)) extends LocalWriteService {
            protected function rebuildReadModel(): array
            {
                throw new RuntimeException('simulated refresh failure');
            }
        };

        try {
            $service->createThread([
                'board_tags' => 'general',
                'subject' => 'New Thread',
                'body' => 'Thread body',
            ]);
            throw new RuntimeException('Expected refresh failure.');
        } catch (RuntimeException $exception) {
            assertStringContains('Derived state marked stale', $exception->getMessage());
        }

        $staleMarkerPath = dirname($databasePath) . '/read_model_stale.json';
        assertTrue(is_file($staleMarkerPath));
        $staleMarker = json_decode((string) file_get_contents($staleMarkerPath), true, 512, JSON_THROW_ON_ERROR);
        assertSame('write_refresh_failed', $staleMarker['reason'] ?? null);
        assertTrue(strlen((string) ($staleMarker['commit_sha'] ?? '')) === 40);
        assertTrue(is_file($artifactRoot . '/index.html'));
    }

    public function testLinkIdentityAllowsDuplicateUsernameTokensWithoutBreakingRebuild(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->deleteDirectoryContents($repositoryRoot . '/records/identity');
        $this->deleteDirectoryContents($repositoryRoot . '/records/public-keys');
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $_POST = [
            'public_key' => $this->generatePublicKey('forum-user'),
        ];
        $firstResponse = $this->renderMethod($application, 'POST', '/api/link_identity?bootstrap_post_id=root-001');
        $_POST = [
            'public_key' => $this->generatePublicKey('forum-user'),
        ];
        $secondResponse = $this->renderMethod($application, 'POST', '/api/link_identity?bootstrap_post_id=root-001');
        $_POST = [];

        $status = $this->renderMethod($application, 'GET', '/api/read_model_status');
        $usernameRoute = $this->renderMethod($application, 'GET', '/user/forum-user');

        assertStringContains('status=ok', $firstResponse);
        assertStringContains('status=ok', $secondResponse);
        assertStringContains('status=ready', $status);
        assertStringContains('User forum-user', $usernameRoute);
        assertStringContains('No approved profiles currently use this username.', $usernameRoute);
        assertStringContains('Unapproved Profiles', $usernameRoute);
    }

    public function testUsernameRouteShowsUnapprovedProfilesWhenNoApprovedMatchesExist(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->deleteDirectoryContents($repositoryRoot . '/records/identity');
        $this->deleteDirectoryContents($repositoryRoot . '/records/public-keys');
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $_POST = [
            'public_key' => $this->generatePublicKey('forum-user'),
        ];
        $this->renderMethod($application, 'POST', '/api/link_identity?bootstrap_post_id=root-001');
        $_POST = [];

        $usernameRoute = $this->renderMethod($application, 'GET', '/user/forum-user');

        assertStringContains('User forum-user', $usernameRoute);
        assertStringContains('No approved profiles currently use this username.', $usernameRoute);
        assertStringContains('Unapproved Profiles', $usernameRoute);
        assertStringContains('/profiles/openpgp-', $usernameRoute);
    }

    public function testApprovedUserCanApproveAnotherUserAndApprovalStaysOutOfFeeds(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $target = $this->linkGeneratedIdentity($application, 'alice');
        $targetProfileSlug = $target['profile_slug'];
        $targetBootstrapThreadId = $target['bootstrap_thread_id'];
        $targetBootstrapPostId = $target['bootstrap_post_id'];

        $_COOKIE = ['identity_hint' => 'guest'];
        $profilePage = $this->renderMethod($application, 'GET', '/profiles/' . $targetProfileSlug);

        $this->seedArtifacts($artifactRoot, [
            '/profiles/' . $targetProfileSlug . '.html',
        ]);
        $postIdsBefore = $this->listCanonicalPostIds($repositoryRoot);
        $approvalResponse = $this->renderMethod($application, 'POST', '/profiles/' . $targetProfileSlug . '/approve');
        $postIdsAfter = $this->listCanonicalPostIds($repositoryRoot);
        $approvalPostIds = array_values(array_diff($postIdsAfter, $postIdsBefore));
        sort($approvalPostIds);
        $approvalPostId = $approvalPostIds[0] ?? '';

        $approvalLanding = $this->renderMethod(
            $application,
            'GET',
            '/profiles/' . rawurlencode($targetProfileSlug) . '?approval=success&post_id=' . rawurlencode($approvalPostId)
                . '&commit=' . rawurlencode($this->latestCommitSha($repositoryRoot))
        );
        $targetProfile = $this->renderMethod($application, 'GET', '/profiles/' . $targetProfileSlug);
        $targetProfileApi = $this->renderMethod($application, 'GET', '/api/get_profile?profile_slug=' . rawurlencode($targetProfileSlug));
        $board = $this->renderMethod($application, 'GET', '/');
        $activity = $this->renderMethod($application, 'GET', '/activity/?view=all');
        $bootstrapThread = $this->renderMethod($application, 'GET', '/threads/' . $targetBootstrapThreadId);
        $_COOKIE = [];

        assertStringContains('Approve user', $profilePage);
        assertStringContains('Redirecting', $approvalResponse);
        assertStringContains('Approved user alice.', $approvalResponse);
        assertStringContains('/profiles/' . $targetProfileSlug, $approvalResponse);
        assertStringContains('approval=success', $approvalResponse);
        assertStringContains('post_id=' . $approvalPostId, $approvalResponse);
        assertSame(1, count($approvalPostIds));
        assertStringContains('Approved user alice', $approvalLanding);
        assertStringContains('/posts/' . $approvalPostId, $approvalLanding);
        assertStringContains('Approved: yes', $targetProfileApi);
        assertStringContains('Approved-By: guest', $targetProfileApi);
        assertStringContains('Approved:</strong> yes', $targetProfile);
        assertStringContains('Approved by:</strong>', $targetProfile);
        assertStringContains('/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954', $targetProfile);
        assertStringContains('>guest</a>', $targetProfile);
        assertStringNotContains('Approve user', $approvalLanding);
        assertStringNotContains('Approve user', $targetProfile);
        assertFalse(is_file($artifactRoot . '/profiles/' . $targetProfileSlug . '.html'));
        assertStringNotContains($approvalPostId, $board);
        assertStringNotContains($approvalPostId, $activity);
        assertStringContains($approvalPostId, $bootstrapThread);
        assertStringContains('Approve-Identity-ID: ' . $target['identity_id'], $bootstrapThread);
    }

    public function testUnapprovedUserCannotApproveAndSelfApprovalIsRejected(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $alice = $this->linkGeneratedIdentity($application, 'alice');
        $bob = $this->linkGeneratedIdentity($application, 'bob');

        $_COOKIE = ['identity_hint' => 'alice'];
        $unapprovedResponse = $this->renderMethod($application, 'POST', '/profiles/' . $bob['profile_slug'] . '/approve');

        $_COOKIE = ['identity_hint' => 'guest'];
        $selfResponse = $this->renderMethod(
            $application,
            'POST',
            '/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954/approve'
        );
        $_COOKIE = [];

        assertStringContains('Only approved users can approve other users.', $unapprovedResponse);
        assertStringContains('Self-approval is not allowed.', $selfResponse);
    }

    public function testApprovedViewerResolvesFromIdentityIdAndProfileSlugHints(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $target = $this->linkGeneratedIdentity($application, 'alice');

        $_COOKIE = ['identity_hint' => 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954'];
        $byIdentityId = $this->renderMethod($application, 'GET', '/profiles/' . $target['profile_slug']);

        $_COOKIE = ['identity_hint' => 'openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954'];
        $byProfileSlug = $this->renderMethod($application, 'GET', '/profiles/' . $target['profile_slug']);
        $_COOKIE = [];

        assertStringContains('Approve user', $byIdentityId);
        assertStringContains('Approve user', $byProfileSlug);
    }

    public function testUserDirectoryShowsOnlyApprovedUsersAndPendingDirectoryRequiresApprovedViewer(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $_COOKIE = ['identity_hint' => 'guest'];
        $approvedUsersWithoutPending = $this->renderMethod($application, 'GET', '/users/');

        $approvedTarget = $this->linkGeneratedIdentity($application, 'alice');
        $pendingTarget = $this->linkGeneratedIdentity($application, 'bob');

        $this->renderMethod($application, 'POST', '/profiles/' . $approvedTarget['profile_slug'] . '/approve');
        $approvedUsers = $this->renderMethod($application, 'GET', '/users/');
        $pendingUsers = $this->renderMethod($application, 'GET', '/users/pending/');

        $_COOKIE = ['identity_hint' => 'bob'];
        $pendingUsersForbidden = $this->renderMethod($application, 'GET', '/users/pending/');
        $_COOKIE = [];

        assertStringNotContains('/users/pending/', $approvedUsersWithoutPending);
        assertStringContains('alice', $approvedUsers);
        assertStringNotContains('bob', $approvedUsers);
        assertStringContains('/user/alice', $approvedUsers);
        assertStringNotContains('Profile:', $approvedUsers);
        assertStringNotContains('Username route:', $approvedUsers);
        assertStringContains('/users/pending/', $approvedUsers);
        assertStringContains('Users Awaiting Approval', $pendingUsers);
        assertStringContains('bob', $pendingUsers);
        assertStringNotContains('alice', $pendingUsers);
        assertStringContains('<table ', $pendingUsers);
        assertStringContains('Approve', $pendingUsers);
        assertStringNotContains('Username route', $pendingUsers);
        assertStringNotContains('Threads', $pendingUsers);
        assertStringNotContains('Posts', $pendingUsers);
        assertStringNotContains('Bootstrap thread', $pendingUsers);
        assertStringContains('Only approved users can view the pending approval directory.', $pendingUsersForbidden);
    }

    public function testApproveUserApiApprovesPendingUser(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $target = $this->linkGeneratedIdentity($application, 'alice');

        $_COOKIE = ['identity_hint' => 'guest'];
        $response = $this->renderMethod(
            $application,
            'POST',
            '/api/approve_user?profile_slug=' . rawurlencode($target['profile_slug'])
        );
        $pendingUsersAfter = $this->renderMethod($application, 'GET', '/users/pending/');
        $_COOKIE = [];

        assertStringContains('status=ok', $response);
        assertStringContains('profile_slug=' . $target['profile_slug'], $response);
        assertStringContains('username=alice', $response);
        assertStringContains('No users are awaiting approval.', $pendingUsersAfter);
    }

    public function testAlreadyApprovedUserCannotBeApprovedAgain(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $target = $this->linkGeneratedIdentity($application, 'alice');

        $_COOKIE = ['identity_hint' => 'guest'];
        $firstResponse = $this->renderMethod($application, 'POST', '/profiles/' . $target['profile_slug'] . '/approve');
        $secondResponse = $this->renderMethod($application, 'POST', '/profiles/' . $target['profile_slug'] . '/approve');
        $_COOKIE = [];

        $matchingApprovals = 0;
        foreach (glob($repositoryRoot . '/records/posts/*.txt') ?: [] as $path) {
            $contents = (string) file_get_contents($path);
            if (str_contains($contents, 'Thread-ID: ' . $target['bootstrap_thread_id'])
                && str_contains($contents, 'Parent-ID: ' . $target['bootstrap_post_id'])
                && str_contains($contents, 'Approve-Identity-ID: ' . $target['identity_id'])) {
                $matchingApprovals++;
            }
        }

        $bootstrapThread = $this->renderMethod($application, 'GET', '/threads/' . $target['bootstrap_thread_id']);

        assertStringContains('Redirecting', $firstResponse);
        assertStringContains('User is already approved.', $secondResponse);
        assertSame(1, $matchingApprovals);
        assertStringContains('Approve-Identity-ID: ' . $target['identity_id'], $bootstrapThread);
    }

    public function testTagsIndexShowsFiveNewestThreadsAndTagPageShowsAllThreads(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $subjects = [];
        for ($index = 1; $index <= 6; $index++) {
            $subject = 'Bug thread ' . $index;
            $subjects[] = $subject;
            $response = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=' . rawurlencode($subject) . '&body=' . rawurlencode("Thread body\n#bug\n")
            );
            assertStringContains('status=ok', $response);
        }

        $tags = $this->renderMethod($application, 'GET', '/tags/');
        $tagPage = $this->renderMethod($application, 'GET', '/tags/bug');

        assertStringContains('showing 5 newest', $tags);
        assertStringContains('View all bug', $tags);
        assertSame(5, substr_count($tags, 'data-tag-preview-item="bug"'));
        assertSame(1, preg_match('#<ul class="tag-thread-list" data-tag-preview-for="bug">(.*?)</ul>#s', $tags, $matches));
        $preview = $matches[1];
        assertSame(5, substr_count($preview, 'Bug thread '));

        foreach ($subjects as $subject) {
            assertStringContains($subject, $tagPage);
        }
        assertSame(6, substr_count($tagPage, 'Bug thread '));
    }

    /**
     * @return array{string,string,string}
     */
    private function createTempEnvironment(): array
    {
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-write-repo-' . bin2hex(random_bytes(6));
        mkdir($repositoryRoot, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-write-db-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $artifactRoot = sys_get_temp_dir() . '/forum-rewrite-write-public-' . bin2hex(random_bytes(6));
        mkdir($artifactRoot, 0777, true);
        $this->initializeGitRepository($repositoryRoot);

        return [$repositoryRoot, $databasePath, $artifactRoot];
    }

    private function readFixturePublicKey(): string
    {
        return (string) file_get_contents(__DIR__ . '/fixtures/parity_minimal_v1/records/public-keys/openpgp-0168FF20EB09C3EA6193BD3C92A73AA7D20A0954.asc');
    }

    private function renderMethod(Application $application, string $method, string $path): string
    {
        ob_start();
        $application->handle($method, $path);
        return (string) ob_get_clean();
    }

    private function extractValue(string $response, string $key): string
    {
        foreach (explode("\n", trim($response)) as $line) {
            if (str_starts_with($line, $key . '=')) {
                return substr($line, strlen($key) + 1);
            }
        }

        throw new RuntimeException('Missing response key: ' . $key);
    }

    private function extractHrefId(string $response, string $prefix): string
    {
        if (preg_match('#href="' . preg_quote($prefix, '#') . '([^"]+)"#', $response, $matches) !== 1) {
            throw new RuntimeException('Missing href prefix: ' . $prefix);
        }

        return $matches[1];
    }

    /**
     * @return array{identity_id:string,profile_slug:string,bootstrap_post_id:string,bootstrap_thread_id:string}
     */
    private function linkGeneratedIdentity(Application $application, string $username): array
    {
        $_POST = [
            'public_key' => $this->generatePublicKey($username),
        ];
        $response = $this->renderMethod($application, 'POST', '/api/link_identity');
        $_POST = [];

        return [
            'identity_id' => $this->extractValue($response, 'identity_id'),
            'profile_slug' => $this->extractValue($response, 'profile_slug'),
            'bootstrap_post_id' => $this->extractValue($response, 'bootstrap_post_id'),
            'bootstrap_thread_id' => $this->extractValue($response, 'bootstrap_thread_id'),
        ];
    }

    /**
     * @return list<string>
     */
    private function listCanonicalPostIds(string $repositoryRoot): array
    {
        $ids = [];
        foreach (glob($repositoryRoot . '/records/posts/*.txt') ?: [] as $path) {
            $ids[] = basename($path, '.txt');
        }

        sort($ids);

        return $ids;
    }

    private function latestCommitSha(string $repositoryRoot): string
    {
        $command = sprintf(
            'git -C %s rev-parse HEAD 2>&1',
            escapeshellarg($repositoryRoot)
        );
        exec($command, $output, $exitCode);
        assertSame(0, $exitCode, implode("\n", $output));

        return trim(implode("\n", $output));
    }

    private function normalizeRenderedPage(string $html): string
    {
        return preg_replace('/[a-f0-9]{40}/', 'COMMIT_SHA', $html) ?? $html;
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

    private function deleteDirectoryContents(string $directory): void
    {
        foreach (glob($directory . '/*') ?: [] as $path) {
            @unlink($path);
        }
    }

    /**
     * @param list<string> $relativePaths
     */
    private function seedArtifacts(string $artifactRoot, array $relativePaths): void
    {
        foreach ($relativePaths as $relativePath) {
            $path = $artifactRoot . $relativePath;
            $directory = dirname($path);
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            file_put_contents($path, '<!doctype html><html><body>artifact</body></html>');
        }
    }

    private function initializeGitRepository(string $repositoryRoot): void
    {
        $this->runCommand($repositoryRoot, 'git init');
        $this->runCommand($repositoryRoot, 'git config user.name "Forum Rewrite"');
        $this->runCommand($repositoryRoot, 'git config user.email "forum-rewrite@example.invalid"');
        $this->runCommand($repositoryRoot, 'git add .');
        $this->runCommand($repositoryRoot, 'git commit -m "Initialize test repository"');
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

    private function gitOutput(string $repositoryRoot, string $command): string
    {
        return trim($this->runCommand($repositoryRoot, 'git ' . $command));
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

if (!function_exists('assertFalse')) {
    function assertFalse(bool $condition): void
    {
        if ($condition) {
            throw new RuntimeException('Failed asserting that condition is false.');
        }
    }
}
