<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Agent\AgentIdentityService;
use ForumRewrite\Agent\SqliteAgentReplyGenerationStore;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Analysis\SqlitePostAnalysisStore;
use ForumRewrite\Host\StaticArtifactBuilder;
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

    public function testPostAnalysisEndpointStoresStubResultIdempotently(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        putenv('DEDALUS_ANALYSIS_MODE=stub');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $threadResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=Analyzed&body=Thoughtful%20body%3F'
            );
            $postId = $this->extractValue($threadResponse, 'post_id');

            $_COOKIE = [];
            $first = json_decode($this->renderMethod($application, 'POST', '/api/analyze_post?post_id=' . rawurlencode($postId)), true);
            $postCountAfterFirstAnalyze = count(glob($repositoryRoot . '/records/posts/*.txt') ?: []);
            $_COOKIE = ['identity_hint' => 'guest'];
            $second = json_decode($this->renderMethod($application, 'POST', '/api/analyze_post?post_id=' . rawurlencode($postId)), true);
            $postCountAfterSecondAnalyze = count(glob($repositoryRoot . '/records/posts/*.txt') ?: []);
            $_COOKIE = [];
            $threadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId) . '?created_post_id=' . rawurlencode($postId));
            $_COOKIE = [];
            $anonymousThreadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId));
            $_COOKIE = ['identity_hint' => 'guest'];
            $approvedThreadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId));
            $_COOKIE = [];
            $pdo = new PDO('sqlite:' . $databasePath);
            $count = (int) $pdo->query('SELECT COUNT(*) FROM post_analyses')->fetchColumn();
            $unicodeRiskCount = (int) $pdo->query('SELECT COUNT(*) FROM post_unicode_risks')->fetchColumn();
            $generatedCount = (int) $pdo->query('SELECT COUNT(*) FROM post_generated_responses')->fetchColumn();
            $generatedRow = $pdo->query('SELECT provider, provider_model, raw_response_json FROM post_generated_responses')->fetch();
            $rawResponse = json_decode((string) $generatedRow['raw_response_json'], true);
            $agentPostId = (string) $first['agent_reply_post_id'];
            $replyRecord = (string) file_get_contents($repositoryRoot . '/records/posts/' . $agentPostId . '.txt');

            assertSame('ok', $first['status']);
            assertSame('complete', $first['analysis_status']);
            assertSame(false, $first['cached']);
            assertSame(false, $first['viewer_can_see_analysis']);
            assertSame(false, isset($first['moderation']));
            assertSame(false, isset($first['unicode_risk']));
            assertSame(true, $first['agent_reply_generation_allowed']);
            assertSame('generated', $first['agent_reply_generation_status']);
            assertSame(true, $first['agent_reply_posted']);
            assertSame('/posts/' . $agentPostId, $first['agent_reply_post_url']);
            assertSame(null, $first['agent_reply_reason']);
            assertSame(null, $first['agent_reply_failure_code']);
            assertStringContains('Parent-ID: ' . $postId, $replyRecord);
            assertStringContains('Author-Identity-ID: openpgp:', $replyRecord);
            assertSame('ok', $second['status']);
            assertSame('complete', $second['analysis_status']);
            assertSame(true, $second['cached']);
            assertSame(true, $second['viewer_can_see_analysis']);
            assertSame(true, $second['agent_reply_generation_allowed']);
            assertSame('already_posted', $second['agent_reply_generation_status']);
            assertSame(true, $second['agent_reply_posted']);
            assertSame($agentPostId, $second['agent_reply_post_id']);
            assertSame($postCountAfterFirstAnalyze, $postCountAfterSecondAnalyze);
            assertSame('stub', $second['provider']);
            assertSame('The post says: Thoughtful body?', $second['post_summary']);
            assertSame('none', $second['moderation']['severity']);
            assertSame([], $second['unicode_risk']['deterministic_facts']['fields']['subject']['risk_labels']);
            assertSame([], $second['unicode_risk']['llm_review']);
            assertSame(true, $second['respondability']['asks_question']);
            assertSame('opinion', $second['respondability']['question_type']);
            assertSame(true, $second['respondability']['should_generate_response']);
            assertSame(1, $count);
            assertSame(1, $unicodeRiskCount);
            assertSame(1, $generatedCount);
            assertSame('stub', $generatedRow['provider']);
            assertSame('stub/post-analysis', $generatedRow['provider_model']);
            assertSame('analysis_suggested_response', $rawResponse['source']);
            assertStringContains('data-created-post-id="' . $postId . '"', $threadPage);
            assertStringContains('data-agent-reply-posted-id="' . $agentPostId . '"', $threadPage);
            assertStringNotContains('data-agent-reply-work=', $threadPage);
            assertStringNotContains('Post analysis', $anonymousThreadPage);
            assertStringContains('Post analysis', $approvedThreadPage);
            assertStringContains('Provider: stub / stub/post-analysis', $approvedThreadPage);
            assertStringContains('Post summary:', $approvedThreadPage);
            assertStringContains('Moderation summary:', $approvedThreadPage);
            assertStringContains('Respondability:', $approvedThreadPage);
            assertStringContains('Question:', $approvedThreadPage);
            assertStringContains('Response value:', $approvedThreadPage);
            assertStringContains('Unicode risk:', $approvedThreadPage);
            assertStringContains('Unicode scripts:', $approvedThreadPage);
            assertStringContains('Suggested response:', $approvedThreadPage);
            assertFalse(is_dir($repositoryRoot . '/records/post-analyses'));
        } finally {
            putenv('DEDALUS_ANALYSIS_MODE');
            $_COOKIE = [];
        }
    }

    public function testPostAnalysisEndpointReportsMissingConfigWithoutStoringAnalysis(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        putenv('DEDALUS_ANALYSIS_MODE');
        putenv('DEDALUS_API_KEY');
        putenv('FORUM_SECRETS_PATH=' . sys_get_temp_dir() . '/forum-rewrite-missing-secrets-' . bin2hex(random_bytes(6)) . '.php');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $response = json_decode($this->renderMethod($application, 'POST', '/api/analyze_post?post_id=root-001'), true);

            assertSame('ok', $response['status']);
            assertSame('config_missing', $response['analysis_status']);
            assertSame('Dedalus API key is not configured.', $response['failure_message']);
        } finally {
            putenv('FORUM_SECRETS_PATH');
        }
    }

    public function testUnicodeRiskAnalysisFlagsMixedScriptIdentifierForApprovedViewersOnly(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        putenv('DEDALUS_ANALYSIS_MODE=stub');
        putenv('FORUM_UNICODE_AUTHORED_TEXT=true');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $threadResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=' . rawurlencode('Look at раypal.com') . '&body=' . rawurlencode('Plain body')
            );
            $postId = $this->extractValue($threadResponse, 'post_id');

            $_COOKIE = [];
            $anonymous = json_decode($this->renderMethod($application, 'POST', '/api/analyze_post?post_id=' . rawurlencode($postId)), true);
            $_COOKIE = ['identity_hint' => 'guest'];
            $approved = json_decode($this->renderMethod($application, 'POST', '/api/analyze_post?post_id=' . rawurlencode($postId)), true);
            $approvedPostPage = $this->renderMethod($application, 'GET', '/posts/' . rawurlencode($postId));
            $_COOKIE = [];
            $anonymousPostPage = $this->renderMethod($application, 'GET', '/posts/' . rawurlencode($postId));
            $pdo = new PDO('sqlite:' . $databasePath);
            $riskRow = $pdo->query('SELECT status, llm_review_json FROM post_unicode_risks WHERE post_id = ' . $pdo->quote($postId))->fetch();
            $llmReview = json_decode((string) $riskRow['llm_review_json'], true);

            assertSame(false, isset($anonymous['unicode_risk']));
            assertSame(true, in_array('mixed_script', $approved['unicode_risk']['deterministic_facts']['fields']['subject']['risk_labels'], true));
            assertSame(true, in_array('confusable_identifier_like_text', $approved['unicode_risk']['deterministic_facts']['fields']['subject']['risk_labels'], true));
            assertSame('complete', $riskRow['status']);
            assertSame('low', $llmReview['review_priority']);
            assertStringContains('Unicode risk:', $approvedPostPage);
            assertStringContains('confusable_identifier_like_text', $approvedPostPage);
            assertStringNotContains('Unicode risk:', $anonymousPostPage);
        } finally {
            putenv('DEDALUS_ANALYSIS_MODE');
            putenv('FORUM_UNICODE_AUTHORED_TEXT');
            $_COOKIE = [];
        }
    }

    public function testPostAnalysisContextIncludesRelatedCrossThreadContent(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        putenv('DEDALUS_ANALYSIS_MODE=stub');
        putenv('DEDALUS_AGENT_REPLIES_ENABLED=false');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $relatedResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=' . rawurlencode('Reply agent context')
                    . '&body=' . rawurlencode('The reply agent should use prior context before answering repeated questions.')
            );
            $relatedPostId = $this->extractValue($relatedResponse, 'post_id');
            $targetResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=' . rawurlencode('Repeated reply agent question')
                    . '&body=' . rawurlencode('Could the reply agent use prior context when a repeated question appears?')
            );
            $targetPostId = $this->extractValue($targetResponse, 'post_id');

            $response = json_decode($this->renderMethod($application, 'POST', '/api/analyze_post?post_id=' . rawurlencode($targetPostId)), true);
            $pdo = new PDO('sqlite:' . $databasePath);
            $rawResponseJson = (string) $pdo->query('SELECT raw_response_json FROM post_analyses WHERE post_id = ' . $pdo->quote($targetPostId))->fetchColumn();
            $engagementJson = (string) $pdo->query('SELECT engagement_json FROM post_analyses WHERE post_id = ' . $pdo->quote($targetPostId))->fetchColumn();
            $relatedContentJson = (string) $pdo->query('SELECT related_content_json FROM post_analyses WHERE post_id = ' . $pdo->quote($targetPostId))->fetchColumn();
            $relatedContentAssessmentJson = (string) $pdo->query('SELECT related_content_assessment_json FROM post_analyses WHERE post_id = ' . $pdo->quote($targetPostId))->fetchColumn();
            $rawResponse = json_decode($rawResponseJson, true);
            $engagement = json_decode($engagementJson, true);
            $storedRelatedContent = json_decode($relatedContentJson, true);
            $storedRelatedContentAssessment = json_decode($relatedContentAssessmentJson, true);
            $relatedContent = $rawResponse['related_content'] ?? [];
            $_COOKIE = [];
            $anonymousThreadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($targetPostId));
            $anonymousPostPage = $this->renderMethod($application, 'GET', '/posts/' . rawurlencode($targetPostId));
            $_COOKIE = ['identity_hint' => 'guest'];
            $approvedThreadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($targetPostId));
            $approvedPostPage = $this->renderMethod($application, 'GET', '/posts/' . rawurlencode($targetPostId));
            $approvedResponse = json_decode($this->renderMethod($application, 'POST', '/api/analyze_post?post_id=' . rawurlencode($targetPostId)), true);

            assertSame('ok', $response['status']);
            assertSame('complete', $response['analysis_status']);
            assertSame($relatedPostId, $relatedContent[0]['post_id'] ?? null);
            assertSame($relatedPostId, $storedRelatedContent[0]['post_id'] ?? null);
            assertSame(true, $storedRelatedContentAssessment['related_results_appropriate'] ?? null);
            assertSame(true, $approvedResponse['related_content_assessment']['related_results_appropriate'] ?? null);
            assertSame('/posts/' . $relatedPostId, $relatedContent[0]['post_url'] ?? null);
            assertStringContains('/posts/' . $relatedPostId, (string) ($engagement['suggested_response'] ?? ''));
            assertStringContains('/posts/' . $relatedPostId, $approvedResponse['related_content'][0]['post_url'] ?? '');
            assertStringNotContains('Possibly related', $anonymousThreadPage);
            assertStringNotContains('Post analysis', $anonymousPostPage);
            assertStringContains('Possibly related', $approvedThreadPage);
            assertStringContains('/posts/' . $relatedPostId, $approvedThreadPage);
            assertStringContains('Post analysis', $approvedPostPage);
            assertStringContains('/posts/' . $relatedPostId, $approvedPostPage);
            assertSame(false, in_array($targetPostId, array_column($relatedContent, 'post_id'), true));
        } finally {
            putenv('DEDALUS_ANALYSIS_MODE');
            putenv('DEDALUS_AGENT_REPLIES_ENABLED');
            $_COOKIE = [];
        }
    }

    public function testGenerateAgentReplyRequiresCompletedCurrentAnalysis(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();

        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $threadResponse = $this->renderMethod(
            $application,
            'POST',
            '/api/create_thread?board_tags=general&subject=Needs%20Analysis&body=Should%20this%20be%20answered%3F'
        );
        $postId = $this->extractValue($threadResponse, 'post_id');
        $threadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId) . '?created_post_id=' . rawurlencode($postId));
        $response = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($postId)), true);

        assertStringContains('data-agent-reply-work="analyze"', $threadPage);
        assertSame('ok', $response['status']);
        assertSame('analysis_required', $response['generation_status']);
        assertSame('missing_analysis', $response['reason']);
    }

    public function testGenerateAgentReplyReportsFailedAnalysisAsRequired(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        putenv('DEDALUS_ANALYSIS_MODE=stub');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $postId = $this->createAnalyzedThread($application, $databasePath);
            $contentHash = $this->contentHashForAnalysis($databasePath, $postId);
            (new SqlitePostAnalysisStore(new PDO('sqlite:' . $databasePath)))->saveFailed($postId, $contentHash, 'provider_error', 'analysis failed');

            $response = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($postId)), true);

            assertSame('ok', $response['status']);
            assertSame('analysis_required', $response['generation_status']);
            assertSame('analysis_not_complete', $response['reason']);
            assertSame('failed', $response['analysis_status']);
        } finally {
            putenv('DEDALUS_ANALYSIS_MODE');
        }
    }

    public function testGenerateAgentReplyRejectsLowRespondabilityHighRiskHighModerationAndPrivateResponse(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        putenv('DEDALUS_ANALYSIS_MODE=stub');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

            $lowPostId = $this->createAnalyzedThread($application, $databasePath, [
                'respondability' => ['overall_score' => 0.4],
            ]);
            $riskPostId = $this->createAnalyzedThread($application, $databasePath, [
                'respondability' => ['response_risk' => 'high'],
            ]);
            $moderationPostId = $this->createAnalyzedThread($application, $databasePath, [
                'moderation' => ['severity' => 'critical'],
            ]);
            $privatePostId = $this->createAnalyzedThread($application, $databasePath, [
                'engagement' => ['response_should_be_public' => false],
            ]);

            $low = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($lowPostId)), true);
            $risk = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($riskPostId)), true);
            $moderation = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($moderationPostId)), true);
            $private = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($privatePostId)), true);

            assertSame('not_recommended', $low['generation_status']);
            assertSame('respondability_score_low', $low['reason']);
            assertSame('not_recommended', $risk['generation_status']);
            assertSame('response_risk_high', $risk['reason']);
            assertSame('not_recommended', $moderation['generation_status']);
            assertSame('moderation_severity_high', $moderation['reason']);
            assertSame('not_recommended', $private['generation_status']);
            assertSame('response_not_public', $private['reason']);
        } finally {
            putenv('DEDALUS_ANALYSIS_MODE');
        }
    }

    public function testAnalyzePostGateFailureUsesCompactVisibilityRules(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        putenv('DEDALUS_ANALYSIS_MODE=stub');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $postId = $this->createAnalyzedThread($application, $databasePath, [
                'respondability' => ['overall_score' => 0.4],
            ]);
            $postCountBefore = count(glob($repositoryRoot . '/records/posts/*.txt') ?: []);

            $_COOKIE = [];
            $anonymous = json_decode($this->renderMethod($application, 'POST', '/api/analyze_post?post_id=' . rawurlencode($postId)), true);
            $_COOKIE = ['identity_hint' => 'guest'];
            $approved = json_decode($this->renderMethod($application, 'POST', '/api/analyze_post?post_id=' . rawurlencode($postId)), true);
            $_COOKIE = [];
            $postCountAfter = count(glob($repositoryRoot . '/records/posts/*.txt') ?: []);
            $pdo = new PDO('sqlite:' . $databasePath);

            assertSame(false, $anonymous['agent_reply_generation_allowed']);
            assertSame('not_recommended', $anonymous['agent_reply_generation_status']);
            assertSame(false, $anonymous['agent_reply_posted']);
            assertSame(null, $anonymous['agent_reply_post_id']);
            assertSame(null, $anonymous['agent_reply_post_url']);
            assertSame('not_recommended', $anonymous['agent_reply_reason']);
            assertSame(null, $anonymous['agent_reply_failure_code']);
            assertSame(false, isset($anonymous['response_text']));
            assertSame(false, isset($anonymous['provider_model']));
            assertSame(false, isset($anonymous['raw_response']));
            assertSame(false, $approved['agent_reply_generation_allowed']);
            assertSame('not_recommended', $approved['agent_reply_generation_status']);
            assertSame('respondability_score_low', $approved['agent_reply_reason']);
            assertSame($postCountBefore, $postCountAfter);
            assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM sqlite_master WHERE type = "table" AND name = "post_generated_responses"')->fetchColumn());
        } finally {
            putenv('DEDALUS_ANALYSIS_MODE');
            $_COOKIE = [];
        }
    }

    public function testGenerateAgentReplyRejectsAgentAuthoredTarget(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        putenv('DEDALUS_ANALYSIS_MODE=stub');

        try {
            $identity = (new AgentIdentityService(
                $repositoryRoot,
                $databasePath,
                $artifactRoot,
                sys_get_temp_dir() . '/forum-rewrite-agent-private-' . bin2hex(random_bytes(6)),
                new CanonicalRecordRepository($repositoryRoot),
            ))->ensureReplyAgentIdentity();
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $threadResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=Agent%20Thread&body=Agent%20body%3F&author_identity_id=' . rawurlencode((string) $identity['identity_id'])
            );
            $postId = $this->extractValue($threadResponse, 'post_id');
            $this->renderMethod($application, 'POST', '/api/analyze_post?post_id=' . rawurlencode($postId));

            $response = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($postId)), true);

            assertSame('not_recommended', $response['generation_status']);
            assertSame('agent_loop_prevention', $response['reason']);
        } finally {
            putenv('DEDALUS_ANALYSIS_MODE');
        }
    }

    public function testGenerateAgentReplyCreatesCanonicalReplyAndIsIdempotent(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        putenv('DEDALUS_ANALYSIS_MODE=stub');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $postId = $this->createAnalyzedThread($application, $databasePath);

            $first = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($postId)), true);
            $agentPostId = (string) $first['agent_post_id'];
            $postCountAfterFirst = count(glob($repositoryRoot . '/records/posts/*.txt') ?: []);
            $second = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($postId)), true);
            $postCountAfter = count(glob($repositoryRoot . '/records/posts/*.txt') ?: []);
            $pdo = new PDO('sqlite:' . $databasePath);
            $row = $pdo->query('SELECT status, provider, response_text, agent_post_id, agent_identity_id, agent_profile_slug, posted_at FROM post_generated_responses')->fetch();
            $replyRecord = (string) file_get_contents($repositoryRoot . '/records/posts/' . $agentPostId . '.txt');
            $threadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId));
            $createdThreadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId) . '?created_post_id=' . rawurlencode($postId));
            $agentProfile = $this->renderMethod($application, 'GET', '/profiles/' . rawurlencode((string) $row['agent_profile_slug']));
            $users = $this->renderMethod($application, 'GET', '/users/');
            $activity = $this->renderMethod($application, 'GET', '/activity/?view=content');

            assertSame('generated', $first['generation_status']);
            assertSame(false, $first['cached']);
            assertSame('stub', $first['provider']);
            assertSame('stub/post-analysis', $first['provider_model']);
            assertStringContains('strongest reason', $first['response_text']);
            assertSame(true, $first['posted']);
            assertSame('/posts/' . $agentPostId, $first['agent_post_url']);
            assertStringContains('Parent-ID: ' . $postId, $replyRecord);
            assertStringContains('Author-Identity-ID: openpgp:', $replyRecord);
            assertStringContains('strongest reason', $replyRecord);
            assertSame('already_posted', $second['generation_status']);
            assertSame($agentPostId, $second['agent_post_id']);
            assertSame($postCountAfterFirst, $postCountAfter);
            assertSame('posted', $row['status']);
            assertSame('stub', $row['provider']);
            assertStringContains('strongest reason', $row['response_text']);
            assertSame($agentPostId, $row['agent_post_id']);
            assertStringContains('openpgp:', $row['agent_identity_id']);
            assertStringContains('openpgp-', $row['agent_profile_slug']);
            assertSame(true, is_string($row['posted_at']));
            assertStringContains('reply-agent', $threadPage);
            assertStringContains('Agent-authored reply', $threadPage);
            assertStringContains('data-agent-authored="reply-agent"', $threadPage);
            assertStringContains('strongest reason', $threadPage);
            assertStringContains('data-created-post-id="' . $postId . '"', $createdThreadPage);
            assertStringContains('data-post-id="' . $postId . '"', $createdThreadPage);
            assertStringContains('data-agent-reply-posted-id="' . $agentPostId . '"', $createdThreadPage);
            assertStringNotContains('data-agent-reply-work=', $createdThreadPage);
            assertStringContains('data-role="agent-reply-feedback"', $createdThreadPage);
            assertStringContains('Automated reply agent', $agentProfile);
            assertStringContains('Account type:</strong> automated reply agent', $agentProfile);
            assertStringContains('reply-agent', $users);
            assertStringContains('Automated reply agent', $users);
            assertStringContains('Author: reply-agent', $activity);
            assertStringContains('automated reply agent', $activity);
        } finally {
            putenv('DEDALUS_ANALYSIS_MODE');
        }
    }

    public function testGenerateAgentReplyPreservesVisibleUnicodeWhenAuthoredTextFlagIsEnabled(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        putenv('DEDALUS_ANALYSIS_MODE=stub');
        putenv('FORUM_UNICODE_AUTHORED_TEXT=true');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $postId = $this->createAnalyzedThread($application, $databasePath, [
                'engagement' => [
                    'suggested_response' => "Unicode is supported here; the Cyrillic 'Хорошо' renders cleanly.",
                ],
            ]);

            $response = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($postId)), true);
            $pdo = new PDO('sqlite:' . $databasePath);
            $statement = $pdo->prepare('SELECT response_text, agent_post_id FROM post_generated_responses WHERE target_post_id = :post_id');
            $statement->execute(['post_id' => $postId]);
            $row = $statement->fetch();
            $replyRecord = (string) file_get_contents($repositoryRoot . '/records/posts/' . $row['agent_post_id'] . '.txt');
            $postPage = $this->renderMethod($application, 'GET', '/posts/' . rawurlencode((string) $row['agent_post_id']));

            assertSame('generated', $response['generation_status']);
            assertStringContains("'Хорошо'", (string) $row['response_text']);
            assertStringContains("'Хорошо'", $replyRecord);
            assertStringContains('Хорошо', $postPage);
        } finally {
            putenv('DEDALUS_ANALYSIS_MODE');
            putenv('FORUM_UNICODE_AUTHORED_TEXT');
        }
    }

    public function testGenerateAgentReplyPersistsThreadCommentContext(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        putenv('DEDALUS_ANALYSIS_MODE=stub');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $threadResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=Context&body=' . rawurlencode('Root body?')
            );
            $threadId = $this->extractValue($threadResponse, 'post_id');
            $this->analyzePostWithoutAgentReply($application, $threadId);
            $replyResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_reply?thread_id=' . rawurlencode($threadId)
                    . '&parent_id=' . rawurlencode($threadId)
                    . '&board_tags=general&body=' . rawurlencode('Reply body?')
            );
            $replyId = $this->extractValue($replyResponse, 'post_id');
            $this->analyzePostWithoutAgentReply($application, $replyId);

            $response = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($replyId)), true);
            $pdo = new PDO('sqlite:' . $databasePath);
            $contextJson = (string) $pdo->query('SELECT request_context_json FROM post_generated_responses')->fetchColumn();
            $context = json_decode($contextJson, true);

            assertSame('generated', $response['generation_status']);
            assertSame($replyId, $context['post_id']);
            assertSame($threadId, $context['thread_comments'][0]['post_id']);
            assertSame("Root body?\n", $context['thread_comments'][0]['body']);
            assertSame('The post says: Root body?', $context['thread_comments'][0]['post_summary']);
            assertSame(false, $context['thread_comments'][0]['is_target']);
            assertSame($replyId, $context['thread_comments'][1]['post_id']);
            assertSame("Reply body?\n", $context['thread_comments'][1]['body']);
            assertSame('The post says: Reply body?', $context['thread_comments'][1]['post_summary']);
            assertSame(true, $context['thread_comments'][1]['is_target']);
            assertSame(false, $context['thread_comments'][1]['is_parent']);
        } finally {
            putenv('DEDALUS_ANALYSIS_MODE');
        }
    }

    public function testGenerateAgentReplyDoesNotStartWhenGenerationIsAlreadyPending(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        putenv('DEDALUS_ANALYSIS_MODE=stub');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $postId = $this->createAnalyzedThread($application, $databasePath);
            $contentHash = $this->contentHashForAnalysis($databasePath, $postId);
            $store = new SqliteAgentReplyGenerationStore(new PDO('sqlite:' . $databasePath));
            $store->reserveGeneration([
                'post_id' => $postId,
                'content_hash' => $contentHash,
                'analysis_hash' => 'already-running',
            ]);
            $postCountBefore = count(glob($repositoryRoot . '/records/posts/*.txt') ?: []);

            $response = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($postId)), true);
            $postCountAfter = count(glob($repositoryRoot . '/records/posts/*.txt') ?: []);
            $row = $store->findByTarget($postId, $contentHash);

            assertSame('ok', $response['status']);
            assertSame('in_progress', $response['generation_status']);
            assertSame($postCountBefore, $postCountAfter);
            assertSame('pending', $row['status']);
            assertSame(null, $row['agent_post_id']);
        } finally {
            putenv('DEDALUS_ANALYSIS_MODE');
        }
    }

    public function testPostAnalysisScriptDoesNotExposeInProgressReplyGeneration(): void
    {
        $script = (string) file_get_contents(dirname(__DIR__) . '/public/assets/post_analysis.js');

        assertStringContains('__forumAgentReplyGenerationStartedPostIds', $script);
        assertStringContains('data-agent-reply-work', $script);
        assertStringContains('function agentReplyResultFromAnalysis(analysis)', $script);
        assertStringContains('function setFeedbackLink(node, text, href, label)', $script);
        assertStringContains('function agentReplyAnchorUrl(agentPostId)', $script);
        assertStringContains('work === "analyze"', $script);
        assertStringContains('work !== "analyze" && work !== "publish"', $script);
        assertStringContains('const result = agentReplyResultFromAnalysis(analysis);', $script);
        assertStringContains('Agent analysis and reply added below this post.', $script);
        assertStringContains('View agent reply.', $script);
        assertStringContains('result.reason === "config_disabled"', $script);
        assertStringContains('url.searchParams.set("created_post_id", agentPostId);', $script);
        assertStringContains('url.hash = "post-" + agentPostId;', $script);
        assertStringContains('generation_status === "in_progress"', $script);
        $analyzeBranchStart = strpos($script, 'if (work === "analyze")');
        $publishBranchStart = strpos($script, 'const result = await generateAgentReply(postId);');
        assertFalse($analyzeBranchStart === false);
        assertFalse($publishBranchStart === false);
        $analyzeBranch = substr($script, (int) $analyzeBranchStart, (int) $publishBranchStart - (int) $analyzeBranchStart);
        assertStringNotContains('generateAgentReply(postId)', $analyzeBranch);
        assertStringNotContains('Generating agent reply...', $script);
        assertStringNotContains('Agent reply failed', $script);
        assertStringNotContains('Agent reply posted', $script);
    }

    public function testGenerateAgentReplyRespectsDisabledConfig(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        putenv('DEDALUS_ANALYSIS_MODE=stub');
        putenv('DEDALUS_AGENT_REPLIES_ENABLED=false');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $postId = $this->createAnalyzedThread($application, $databasePath);
            $postCountBefore = count(glob($repositoryRoot . '/records/posts/*.txt') ?: []);

            $analysis = json_decode($this->renderMethod($application, 'POST', '/api/analyze_post?post_id=' . rawurlencode($postId)), true);
            $response = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($postId)), true);
            $postCountAfter = count(glob($repositoryRoot . '/records/posts/*.txt') ?: []);
            $pdo = new PDO('sqlite:' . $databasePath);

            assertSame(false, $analysis['agent_reply_generation_allowed']);
            assertSame('not_recommended', $analysis['agent_reply_generation_status']);
            assertSame(false, $analysis['agent_reply_posted']);
            assertSame(null, $analysis['agent_reply_post_id']);
            assertSame(null, $analysis['agent_reply_post_url']);
            assertSame('config_disabled', $analysis['agent_reply_reason']);
            assertSame(null, $analysis['agent_reply_failure_code']);
            assertSame('not_recommended', $response['generation_status']);
            assertSame('config_disabled', $response['reason']);
            assertSame($postCountBefore, $postCountAfter);
            assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM sqlite_master WHERE type = "table" AND name = "post_generated_responses"')->fetchColumn());
        } finally {
            putenv('DEDALUS_ANALYSIS_MODE');
            putenv('DEDALUS_AGENT_REPLIES_ENABLED');
        }
    }

    public function testGenerateAgentReplyRecordsPostingFailureAfterGeneration(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        putenv('DEDALUS_ANALYSIS_MODE=stub');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $postId = $this->createAnalyzedThread($application, $databasePath);
            $this->deleteTree($repositoryRoot . '/.git');

            $response = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($postId)), true);
            $pdo = new PDO('sqlite:' . $databasePath);
            $row = $pdo->query('SELECT status, failure_code, failure_message, agent_post_id FROM post_generated_responses')->fetch();

            assertSame('failed', $response['generation_status']);
            assertSame('posting_error', $response['failure_code']);
            assertSame('failed', $row['status']);
            assertSame('posting_error', $row['failure_code']);
            assertStringContains('Writable repository must be a git checkout', $row['failure_message']);
            assertSame(null, $row['agent_post_id']);
        } finally {
            putenv('DEDALUS_ANALYSIS_MODE');
        }
    }

    public function testLinkIdentityUsesPublicKeyUserIdForUsernameAndInvalidatesProfileArtifact(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->deleteDirectoryContents($repositoryRoot . '/records/identity');
        $this->deleteDirectoryContents($repositoryRoot . '/records/public-keys');
        $this->seedArtifacts($artifactRoot, [
            '/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954.html',
            '/threads/root-001.html',
            '/posts/root-001.html',
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
        assertFalse(is_file($artifactRoot . '/threads/root-001.html'));
        assertFalse(is_file($artifactRoot . '/posts/root-001.html'));
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
        $identityActivity = $this->renderMethod($application, 'GET', '/activity/?view=identity');
        $bootstrapActivity = $this->renderMethod($application, 'GET', '/activity/?view=bootstrap');
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
        assertStringContains($bootstrapPostId, $identityActivity);
        assertStringContains($bootstrapPostId, $bootstrapActivity);
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
        assertStringContains('created_post_id=', $threadResponse);
        assertStringContains('__v=', $threadResponse);
        assertStringContains('Commit ', $threadResponse);
        assertStringContains('Redirecting', $replyResponse);
        assertStringContains('Created reply', $replyResponse);
        assertStringContains('/threads/' . $threadId, $replyResponse);
        assertStringContains('created_post_id=', $replyResponse);
        assertStringContains('__v=', $replyResponse);
        assertStringContains('#post-', $replyResponse);
        assertStringContains('Commit ', $replyResponse);
        assertStringContains('Redirecting', $accountResponse);
        assertStringContains('Linked identity', $accountResponse);
        assertStringContains('/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954', $accountResponse);
        assertStringContains('Commit ', $accountResponse);
    }

    public function testAccountPageLinksLoggedInApprovedUserToUsernameRoute(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $_COOKIE = ['identity_hint' => 'guest'];
        $account = $this->renderMethod($application, 'GET', '/account/key/');
        $_COOKIE = [];

        assertStringContains('View user page', $account);
        assertStringContains('href="/user/guest"', $account);
    }

    public function testAccountPageLinksLoggedInUnapprovedUserToProfileRoute(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $identity = $this->linkGeneratedIdentity($application, 'pending-user');

        $_COOKIE = ['identity_hint' => $identity['identity_id']];
        $account = $this->renderMethod($application, 'GET', '/account/key/');
        $_COOKIE = [];

        assertStringContains('View profile', $account);
        assertStringContains('href="/profiles/' . $identity['profile_slug'] . '"', $account);
        assertStringNotContains('href="/user/pending-user"', $account);
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

    public function testUnicodeAuthoredTextFlagDoesNotWidenMachineFields(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        putenv('FORUM_UNICODE_AUTHORED_TEXT=true');

        try {
            $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
            $threadResult = $service->createThread([
                'board_tags' => 'тест bug',
                'subject' => 'Привет',
                'body' => 'Привет мир',
            ]);
            $record = (string) file_get_contents($repositoryRoot . '/records/posts/' . $threadResult['thread_id'] . '.txt');

            assertStringContains('Board-Tags: bug', $record);
            assertStringNotContains('тест', $record);

            $rejected = false;
            try {
                $service->createReply([
                    'thread_id' => 'ветка',
                    'parent_id' => $threadResult['thread_id'],
                    'body' => 'Привет',
                ]);
            } catch (RuntimeException $exception) {
                assertStringContains('thread_id is required and must be an ASCII token.', $exception->getMessage());
                $rejected = true;
            }
            if (!$rejected) {
                throw new RuntimeException('Expected Unicode thread_id rejection.');
            }

            $rejected = false;
            try {
                $service->applyThreadTag([
                    'thread_id' => $threadResult['thread_id'],
                    'tag' => 'лайк',
                    'author_identity_id' => 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954',
                ]);
            } catch (RuntimeException $exception) {
                assertStringContains('tag must be one of:', $exception->getMessage());
                $rejected = true;
            }
            if (!$rejected) {
                throw new RuntimeException('Expected Unicode reaction tag rejection.');
            }

            $rejected = false;
            try {
                $service->applyThreadTag([
                    'thread_id' => $threadResult['thread_id'],
                    'tag' => 'like',
                    'author_identity_id' => 'openpgp:кириллица',
                ]);
            } catch (RuntimeException $exception) {
                assertStringContains('ASCII token', $exception->getMessage());
                $rejected = true;
            }
            if (!$rejected) {
                throw new RuntimeException('Expected Unicode identity ID rejection.');
            }
        } finally {
            putenv('FORUM_UNICODE_AUTHORED_TEXT');
        }
    }

    public function testUnicodeAuthoredTextSurvivesWriteRenderRssAndStaticArtifacts(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        putenv('FORUM_UNICODE_AUTHORED_TEXT=true');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $response = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=' . rawurlencode('Привет') . '&body=' . rawurlencode('Привет мир')
            );
            $postId = $this->extractValue($response, 'post_id');
            $record = (string) file_get_contents($repositoryRoot . '/records/posts/' . $postId . '.txt');
            $threadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId));
            $postPage = $this->renderMethod($application, 'GET', '/posts/' . rawurlencode($postId));
            $activity = $this->renderMethod($application, 'GET', '/activity/');
            $rss = $this->renderMethod($application, 'GET', '/activity/?format=rss');

            $builder = new StaticArtifactBuilder(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $builder->build();
            $threadArtifact = (string) file_get_contents($artifactRoot . '/threads/' . $postId . '.html');
            $postArtifact = (string) file_get_contents($artifactRoot . '/posts/' . $postId . '.html');

            assertStringContains('status=ok', $response);
            assertStringContains('Subject: Привет', $record);
            assertStringContains('Привет мир', $record);
            assertStringContains('Привет', $threadPage);
            assertStringContains('Привет мир', $threadPage);
            assertStringContains('Привет', $postPage);
            assertStringContains('Привет', $activity);
            assertStringContains('Привет', $rss);
            assertStringContains('Привет мир', $threadArtifact);
            assertStringContains('Привет мир', $postArtifact);
            assertTrue(is_file($artifactRoot . '/threads/' . $postId . '.html'));
            assertTrue(is_file($artifactRoot . '/posts/' . $postId . '.html'));
        } finally {
            putenv('FORUM_UNICODE_AUTHORED_TEXT');
        }
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

    public function testLinkIdentityUsesIncrementalReadModelUpdateWhenDatabaseIsWarm(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->deleteDirectoryContents($repositoryRoot . '/records/identity');
        $this->deleteDirectoryContents($repositoryRoot . '/records/public-keys');
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
        $result = $service->linkIdentity([
            'public_key' => $this->readFixturePublicKey(),
            'bootstrap_post_id' => 'root-001',
        ]);

        $profilePage = $this->renderMethod($application, 'GET', '/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954');
        $threadPage = $this->renderMethod($application, 'GET', '/threads/root-001');

        assertSame(true, isset($result['timings']['read_model_incremental_update']));
        assertSame(false, isset($result['timings']['read_model_rebuild']));
        assertStringContains('Visible username:</strong> forum-user', $profilePage);
        assertStringContains('forum-user', $threadPage);
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
        assertStringNotContains('Score: 1', $threadPage);
        assertStringContains('Score-Total: 1', $threadApi);
        assertStringNotContains('Score: 1', $board);
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
        assertStringNotContains('Score: 0', $threadPage);
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
        $createdPostId = $this->extractValue($response, 'post_id');
        $postPage = $this->renderMethod($application, 'GET', '/posts/' . rawurlencode($createdPostId));

        assertStringContains('status=ok', $response);
        assertStringNotContains('Score: -100', $threadPage);
        assertStringContains('Post ' . $createdPostId, $postPage);
        assertStringContains('Score-Total: -100', $threadApi);
    }

    public function testBoardSupportsLikedViewAndNewestOldestTopSorts(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $newThreadResponse = $this->renderMethod(
            $application,
            'POST',
            '/api/create_thread?board_tags=general&subject=Brand%20New%20Thread&body=Thread%20body'
        );
        $newThreadId = $this->extractValue($newThreadResponse, 'thread_id');
        $this->renderMethod(
            $application,
            'POST',
            '/api/create_thread?board_tags=general&subject=Unliked%20Thread&body=Thread%20body'
        );

        $_COOKIE = ['identity_hint' => 'guest'];
        $this->renderMethod($application, 'POST', '/api/apply_thread_tag?thread_id=root-001&tag=like');
        $this->renderMethod($application, 'POST', '/api/apply_thread_tag?thread_id=' . rawurlencode($newThreadId) . '&tag=like');

        $alice = $this->linkGeneratedIdentity($application, 'alice');
        $this->renderMethod($application, 'POST', '/profiles/' . $alice['profile_slug'] . '/approve');
        $_COOKIE = ['identity_hint' => 'alice'];
        $this->renderMethod($application, 'POST', '/api/apply_thread_tag?thread_id=root-001&tag=like');
        $_COOKIE = [];

        $boardDefault = $this->renderMethod($application, 'GET', '/');
        $boardLikedNewest = $this->renderMethod($application, 'GET', '/?view=liked&sort=newest');
        $boardLikedOldest = $this->renderMethod($application, 'GET', '/?view=liked&sort=oldest');
        $boardLikedTop = $this->renderMethod($application, 'GET', '/?view=liked&sort=top');

        assertStringNotContains('View: All', $boardDefault);
        assertStringNotContains('Sort: Newest', $boardDefault);
        assertStringNotContains('View: Liked', $boardLikedNewest);
        assertStringNotContains('Sort: Newest', $boardLikedNewest);
        assertOrdered($boardLikedNewest, 'Brand New Thread', 'Hello world');
        assertOrdered($boardLikedOldest, 'Hello world', 'Brand New Thread');
        assertOrdered($boardLikedTop, 'Hello world', 'Brand New Thread');
        assertStringNotContains('Unliked Thread', $boardLikedNewest);
    }

    public function testPinnedThreadsSortBeforeNormalThreadsForBoardSorts(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $this->renderMethod(
            $application,
            'POST',
            '/api/create_thread?board_tags=general&subject=Newer%20Normal%20Thread&body=Thread%20body'
        );

        $alice = $this->linkGeneratedIdentity($application, 'alice');
        $this->renderMethod($application, 'POST', '/profiles/' . $alice['profile_slug'] . '/approve');
        $_COOKIE = ['identity_hint' => 'alice'];
        $this->renderMethod($application, 'POST', '/api/apply_thread_tag?thread_id=root-001&tag=like');
        $_COOKIE = [];

        $boardNewest = $this->renderMethod($application, 'GET', '/threads/?view=all&sort=newest');
        $boardOldest = $this->renderMethod($application, 'GET', '/threads/?view=all&sort=oldest');
        $boardTop = $this->renderMethod($application, 'GET', '/threads/?view=all&sort=top');

        assertOrdered($boardNewest, 'The Rules of ZenMemes.com', 'Newer Normal Thread');
        assertOrdered($boardOldest, 'The Rules of ZenMemes.com', 'Hello world');
        assertOrdered($boardTop, 'The Rules of ZenMemes.com', 'Hello world');
    }

    public function testLikedViewIncludesUnapprovedLikesBecauseItFiltersLabelsNotScore(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $identity = $this->linkGeneratedIdentity($application, 'pending-user');
        $threadResponse = $this->renderMethod(
            $application,
            'POST',
            '/api/create_thread?board_tags=general&subject=Pending%20Liked%20Thread&body=Thread%20body'
        );
        $threadId = $this->extractValue($threadResponse, 'thread_id');

        $_COOKIE = ['identity_hint' => $identity['identity_id']];
        $this->renderMethod($application, 'POST', '/api/apply_thread_tag?thread_id=' . rawurlencode($threadId) . '&tag=like');
        $_COOKIE = [];

        $boardLiked = $this->renderMethod($application, 'GET', '/?view=liked&sort=newest');

        assertStringContains('Pending Liked Thread', $boardLiked);
    }

    public function testApplyThreadTagUsesIncrementalReadModelUpdateWhenDatabaseIsWarm(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
        $result = $service->applyThreadTag([
            'thread_id' => 'root-001',
            'tag' => 'like',
            'author_identity_id' => 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954',
        ]);

        $threadApi = $this->renderMethod($application, 'GET', '/api/get_thread?thread_id=root-001');

        assertSame(true, isset($result['timings']['read_model_incremental_update']));
        assertSame(false, isset($result['timings']['read_model_rebuild']));
        assertStringContains('Score-Total: 1', $threadApi);
    }

    public function testApplyPostTagUsesIncrementalReadModelUpdateWhenDatabaseIsWarm(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
        $result = $service->applyPostTag([
            'post_id' => 'reply-001',
            'tag' => 'flag',
            'author_identity_id' => 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954',
        ]);

        $pdo = new PDO('sqlite:' . $databasePath);
        $row = $pdo->query("SELECT post_tags_json, post_score_total, approved_flag_count, is_hidden FROM posts WHERE post_id = 'reply-001'")->fetch();

        assertSame(true, isset($result['timings']['read_model_incremental_update']));
        assertSame(false, isset($result['timings']['read_model_rebuild']));
        assertSame('reply-001', $result['post_id']);
        assertSame('root-001', $result['thread_id']);
        assertSame('-100', $result['post_score_total']);
        assertSame('1', $result['approved_flag_count']);
        assertSame('no', $result['is_hidden']);
        assertSame('["flag"]', $row['post_tags_json']);
        assertSame('-100', (string) $row['post_score_total']);
        assertSame('1', (string) $row['approved_flag_count']);
        assertSame('0', (string) $row['is_hidden']);
    }

    public function testApplyThreadTagApiWritesApprovedLikeAndReportsUpdatedScore(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $recordsBefore = count(glob($repositoryRoot . '/records/thread-labels/*.txt') ?: []);

        $_COOKIE = ['identity_hint' => 'guest'];
        $response = $this->renderMethod($application, 'POST', '/api/apply_thread_tag?thread_id=root-001&tag=like');
        $threadPage = $this->renderMethod($application, 'GET', '/threads/root-001');
        $_COOKIE = [];

        $recordsAfter = count(glob($repositoryRoot . '/records/thread-labels/*.txt') ?: []);

        assertStringContains('status=ok', $response);
        assertStringContains('thread_id=root-001', $response);
        assertStringContains('tag=like', $response);
        assertStringContains('score_total=1', $response);
        assertStringContains('viewer_is_approved=yes', $response);
        assertStringContains('wrote_record=yes', $response);
        assertTrue(strlen($this->extractValue($response, 'commit_sha')) === 40);
        assertSame($recordsBefore + 1, $recordsAfter);
        assertStringNotContains('Score: 1', $threadPage);
        assertStringContains('>Liked</button>', $threadPage);
        assertStringContains('aria-pressed="true"', $threadPage);
        assertStringNotContains('Set up or choose an identity', $threadPage);
    }

    public function testApplyPostTagApiWritesApprovedFlagAndReportsPostState(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $recordsBefore = count(glob($repositoryRoot . '/records/post-reactions/*.txt') ?: []);

        $_COOKIE = ['identity_hint' => 'guest'];
        $response = $this->renderMethod($application, 'POST', '/api/apply_post_tag?post_id=reply-001&tag=flag');
        $threadPage = $this->renderMethod($application, 'GET', '/threads/root-001');
        $_COOKIE = [];

        $recordsAfter = count(glob($repositoryRoot . '/records/post-reactions/*.txt') ?: []);

        assertStringContains('status=ok', $response);
        assertStringContains('post_id=reply-001', $response);
        assertStringContains('thread_id=root-001', $response);
        assertStringContains('tag=flag', $response);
        assertStringContains('post_score_total=-100', $response);
        assertStringContains('approved_flag_count=1', $response);
        assertStringContains('is_hidden=no', $response);
        assertStringContains('viewer_is_approved=yes', $response);
        assertStringContains('wrote_record=yes', $response);
        assertTrue(strlen($this->extractValue($response, 'commit_sha')) === 40);
        assertSame($recordsBefore + 1, $recordsAfter);
        assertStringContains('>Flagged</button>', $threadPage);
        assertStringContains('data-action="apply-post-tag"', $threadPage);
    }

    public function testApplyPostTagApiWritesApprovedCommentLikeAndRendersLikedButton(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $recordsBefore = count(glob($repositoryRoot . '/records/post-reactions/*.txt') ?: []);

        $_COOKIE = ['identity_hint' => 'guest'];
        $response = $this->renderMethod($application, 'POST', '/api/apply_post_tag?post_id=reply-001&tag=like');
        $threadPage = $this->renderMethod($application, 'GET', '/threads/root-001');
        $_COOKIE = [];

        $recordsAfter = count(glob($repositoryRoot . '/records/post-reactions/*.txt') ?: []);

        assertStringContains('status=ok', $response);
        assertStringContains('post_id=reply-001', $response);
        assertStringContains('thread_id=root-001', $response);
        assertStringContains('tag=like', $response);
        assertStringContains('post_score_total=1', $response);
        assertStringContains('approved_flag_count=0', $response);
        assertStringContains('is_hidden=no', $response);
        assertStringContains('viewer_is_approved=yes', $response);
        assertStringContains('wrote_record=yes', $response);
        assertTrue(strlen($this->extractValue($response, 'commit_sha')) === 40);
        assertSame($recordsBefore + 1, $recordsAfter);
        assertStringContains('data-post-id="reply-001"', $threadPage);
        assertStringContains('data-tag="like"', $threadPage);
        assertStringContains('>Liked</button>', $threadPage);
        assertStringContains('aria-pressed="true"', $threadPage);
    }

    public function testApplyPostTagApiDuplicateShortCircuitsWithoutNewRecord(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $_COOKIE = ['identity_hint' => 'guest'];
        $first = $this->renderMethod($application, 'POST', '/api/apply_post_tag?post_id=reply-001&tag=flag');
        $recordsAfterFirst = count(glob($repositoryRoot . '/records/post-reactions/*.txt') ?: []);
        $second = $this->renderMethod($application, 'POST', '/api/apply_post_tag?post_id=reply-001&tag=flag');
        $_COOKIE = [];

        $recordsAfterSecond = count(glob($repositoryRoot . '/records/post-reactions/*.txt') ?: []);

        assertStringContains('wrote_record=yes', $first);
        assertStringContains('wrote_record=no', $second);
        assertStringNotContains('commit_sha=', $second);
        assertSame($recordsAfterFirst, $recordsAfterSecond);
    }

    public function testApplyPostTagApiRejectsAnonymousViewerAndUnknownTag(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $anonymous = $this->renderMethod($application, 'POST', '/api/apply_post_tag?post_id=reply-001&tag=flag');
        $_COOKIE = ['identity_hint' => 'guest'];
        $unknownTag = $this->renderMethod($application, 'POST', '/api/apply_post_tag?post_id=reply-001&tag=bug');
        $_COOKIE = [];

        assertStringContains('error=You must set an identity hint before applying a tag.', $anonymous);
        assertStringContains('error=tag must be one of: like, flag', $unknownTag);
    }

    public function testApprovedFlagOnReplyAgentPostHidesPublicSurfaces(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        putenv('DEDALUS_ANALYSIS_MODE=stub');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $postId = $this->createAnalyzedThread($application, $databasePath);
            $reply = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($postId)), true);
            $agentPostId = (string) $reply['agent_post_id'];

            $_COOKIE = ['identity_hint' => 'guest'];
            $flagResponse = $this->renderMethod($application, 'POST', '/api/apply_post_tag?post_id=' . rawurlencode($agentPostId) . '&tag=flag');
            $_COOKIE = [];

            $threadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId));
            $postPage = $this->renderMethod($application, 'GET', '/posts/' . rawurlencode($agentPostId));
            $threadApi = $this->renderMethod($application, 'GET', '/api/get_thread?thread_id=' . rawurlencode($postId));
            $postApi = $this->renderMethod($application, 'GET', '/api/get_post?post_id=' . rawurlencode($agentPostId));
            $activity = $this->renderMethod($application, 'GET', '/activity/?view=content');

            assertStringContains('is_hidden=yes', $flagResponse);
            assertStringNotContains('strongest reason', $threadPage);
            assertStringContains('This post has been hidden.', $postPage);
            assertStringNotContains($agentPostId, $threadApi);
            assertStringContains('post not found', $postApi);
            assertStringNotContains($agentPostId, $activity);
        } finally {
            putenv('DEDALUS_ANALYSIS_MODE');
            $_COOKIE = [];
        }
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
        $threadPage = $this->renderMethod($application, 'GET', '/threads/root-001');
        $_COOKIE = [];

        $recordsAfter = count(glob($repositoryRoot . '/records/thread-labels/*.txt') ?: []);

        assertStringContains('status=ok', $response);
        assertStringContains('viewer_identity_id=' . $identity['identity_id'], $response);
        assertStringContains('viewer_is_approved=no', $response);
        assertStringContains('score_total=0', $response);
        assertStringContains('wrote_record=yes', $response);
        assertSame($recordsBefore + 1, $recordsAfter);
        assertStringContains('>Liked</button>', $threadPage);
        assertStringNotContains('start affecting score once you are approved.', $threadPage);
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

    public function testIdentityIncrementalFailureFallsBackToFullRebuildAndKeepsReadModelHealthy(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->deleteDirectoryContents($repositoryRoot . '/records/identity');
        $this->deleteDirectoryContents($repositoryRoot . '/records/public-keys');
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $service = new class($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot)) extends LocalWriteService {
            protected function incrementalReadModelUpdater(): IncrementalReadModelUpdater
            {
                return new class($this->databasePath(), $this->repositoryRoot()) extends IncrementalReadModelUpdater {
                    public function applyIdentityLink(\ForumRewrite\Canonical\IdentityBootstrapRecord $record, string $commitSha): array
                    {
                        throw new RuntimeException('simulated identity incremental failure');
                    }
                };
            }
        };

        $result = $service->linkIdentity([
            'public_key' => $this->readFixturePublicKey(),
            'bootstrap_post_id' => 'root-001',
        ]);

        $profilePage = $this->renderMethod($application, 'GET', '/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954');
        $staleMarkerPath = dirname($databasePath) . '/read_model_stale.json';

        assertSame(true, isset($result['timings']['read_model_incremental_fallback']));
        assertSame(true, isset($result['timings']['read_model_rebuild_fallback']));
        assertStringContains('Visible username:</strong> forum-user', $profilePage);
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

    public function testIncrementalIdentityLinkMatchesFreshRebuildView(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->deleteDirectoryContents($repositoryRoot . '/records/identity');
        $this->deleteDirectoryContents($repositoryRoot . '/records/public-keys');
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $identity = $this->linkGeneratedIdentity($application, 'compare-user');
        $freshDatabasePath = sys_get_temp_dir() . '/forum-rewrite-write-db-identity-parity-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $freshApplication = new Application(dirname(__DIR__), $repositoryRoot, $freshDatabasePath, $artifactRoot);

        $incrementalProfile = $this->renderMethod($application, 'GET', '/profiles/' . $identity['profile_slug']);
        $freshProfile = $this->renderMethod($freshApplication, 'GET', '/profiles/' . $identity['profile_slug']);
        $incrementalUser = $this->renderMethod($application, 'GET', '/user/compare-user');
        $freshUser = $this->renderMethod($freshApplication, 'GET', '/user/compare-user');
        $incrementalPost = $this->renderMethod($application, 'GET', '/posts/' . $identity['bootstrap_post_id']);
        $freshPost = $this->renderMethod($freshApplication, 'GET', '/posts/' . $identity['bootstrap_post_id']);
        $incrementalActivity = $this->renderMethod($application, 'GET', '/activity/?view=bootstrap');
        $freshActivity = $this->renderMethod($freshApplication, 'GET', '/activity/?view=bootstrap');

        assertSame($this->normalizeRenderedPage($freshProfile), $this->normalizeRenderedPage($incrementalProfile));
        assertSame($this->normalizeRenderedPage($freshUser), $this->normalizeRenderedPage($incrementalUser));
        assertSame($this->normalizeRenderedPage($freshPost), $this->normalizeRenderedPage($incrementalPost));
        assertSame($this->normalizeRenderedPage($freshActivity), $this->normalizeRenderedPage($incrementalActivity));
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

    public function testIdentityWriteMarksDerivedStateStaleWhenIncrementalAndFallbackRefreshFail(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->deleteDirectoryContents($repositoryRoot . '/records/identity');
        $this->deleteDirectoryContents($repositoryRoot . '/records/public-keys');
        $service = new class($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot)) extends LocalWriteService {
            protected function incrementalReadModelUpdater(): IncrementalReadModelUpdater
            {
                return new class($this->databasePath(), $this->repositoryRoot()) extends IncrementalReadModelUpdater {
                    public function applyIdentityLink(\ForumRewrite\Canonical\IdentityBootstrapRecord $record, string $commitSha): array
                    {
                        throw new RuntimeException('simulated identity incremental failure');
                    }
                };
            }

            protected function rebuildReadModel(): array
            {
                throw new RuntimeException('simulated identity rebuild failure');
            }
        };

        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        try {
            $service->linkIdentity([
                'public_key' => $this->readFixturePublicKey(),
                'bootstrap_post_id' => 'root-001',
            ]);
            throw new RuntimeException('Expected identity refresh failure.');
        } catch (RuntimeException $exception) {
            assertStringContains('Derived state marked stale', $exception->getMessage());
        }

        $staleMarkerPath = dirname($databasePath) . '/read_model_stale.json';
        assertTrue(is_file($staleMarkerPath));
        $staleMarker = json_decode((string) file_get_contents($staleMarkerPath), true, 512, JSON_THROW_ON_ERROR);
        assertSame('write_refresh_failed', $staleMarker['reason'] ?? null);
        assertTrue(strlen((string) ($staleMarker['commit_sha'] ?? '')) === 40);
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
        $identityActivity = $this->renderMethod($application, 'GET', '/activity/?view=identity');
        $approvalActivity = $this->renderMethod($application, 'GET', '/activity/?view=approval');
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
        assertStringContains($approvalPostId, $identityActivity);
        assertStringContains($approvalPostId, $approvalActivity);
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
        assertStringContains('title="' . $pendingTarget['profile_slug'] . '"', $pendingUsers);
        assertStringContains('aria-label="' . $pendingTarget['profile_slug'] . '"', $pendingUsers);
        assertStringContains(
            substr($pendingTarget['profile_slug'], 0, 18) . '...' . substr($pendingTarget['profile_slug'], -10),
            $pendingUsers
        );
        assertStringNotContains('>' . $pendingTarget['profile_slug'] . '</a>', $pendingUsers);
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

    public function testApproveUserUsesIncrementalReadModelUpdateWhenDatabaseIsWarm(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $target = $this->linkGeneratedIdentity($application, 'alice');
        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
        $result = $this->approveIdentity($service, $target);
        $profilePage = $this->renderMethod($application, 'GET', '/profiles/' . $target['profile_slug']);

        assertSame(true, isset($result['timings']['read_model_approval_incremental_update']));
        assertSame(false, isset($result['timings']['read_model_approval_rebuild']));
        assertStringContains('Approved:</strong> yes', $profilePage);
        assertStringContains('>guest</a>', $profilePage);
    }

    public function testIncrementalApprovalMatchesFreshRebuildForProfileThreadAndActivity(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $target = $this->linkGeneratedIdentity($application, 'alice');
        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
        $this->approveIdentity($service, $target);

        $freshDatabasePath = sys_get_temp_dir() . '/forum-rewrite-write-db-approval-parity-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $freshApplication = new Application(dirname(__DIR__), $repositoryRoot, $freshDatabasePath, $artifactRoot);

        $incrementalProfile = $this->renderMethod($application, 'GET', '/profiles/' . $target['profile_slug']);
        $freshProfile = $this->renderMethod($freshApplication, 'GET', '/profiles/' . $target['profile_slug']);
        $incrementalBootstrapThread = $this->renderMethod($application, 'GET', '/threads/' . $target['bootstrap_thread_id']);
        $freshBootstrapThread = $this->renderMethod($freshApplication, 'GET', '/threads/' . $target['bootstrap_thread_id']);
        $incrementalApprovalActivity = $this->renderMethod($application, 'GET', '/activity/?view=approval');
        $freshApprovalActivity = $this->renderMethod($freshApplication, 'GET', '/activity/?view=approval');

        assertSame($this->normalizeRenderedPage($freshProfile), $this->normalizeRenderedPage($incrementalProfile));
        assertSame($this->normalizeRenderedPage($freshBootstrapThread), $this->normalizeRenderedPage($incrementalBootstrapThread));
        assertSame($this->normalizeRenderedPage($freshApprovalActivity), $this->normalizeRenderedPage($incrementalApprovalActivity));
    }

    public function testIncrementalApprovalMatchesFreshRebuildForTransitiveApprovalAndScoreRefresh(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $alice = $this->linkGeneratedIdentity($application, 'alice');
        $bob = $this->linkGeneratedIdentity($application, 'bob');
        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));

        $likeResult = $service->applyThreadTag([
            'thread_id' => 'root-001',
            'tag' => 'like',
            'author_identity_id' => $alice['identity_id'],
        ]);
        $approveAlice = $this->approveIdentity($service, $alice);
        $approveBob = $this->approveIdentity($service, $bob, $alice['identity_id']);

        $freshDatabasePath = sys_get_temp_dir() . '/forum-rewrite-write-db-approval-transitive-parity-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $freshApplication = new Application(dirname(__DIR__), $repositoryRoot, $freshDatabasePath, $artifactRoot);

        $incrementalRootThread = $this->renderMethod($application, 'GET', '/threads/root-001');
        $freshRootThread = $this->renderMethod($freshApplication, 'GET', '/threads/root-001');
        $incrementalBobProfile = $this->renderMethod($application, 'GET', '/profiles/' . $bob['profile_slug']);
        $freshBobProfile = $this->renderMethod($freshApplication, 'GET', '/profiles/' . $bob['profile_slug']);
        $incrementalApprovalActivity = $this->renderMethod($application, 'GET', '/activity/?view=approval');
        $freshApprovalActivity = $this->renderMethod($freshApplication, 'GET', '/activity/?view=approval');
        $incrementalThreadApi = $this->renderMethod($application, 'GET', '/api/get_thread?thread_id=root-001');
        $freshThreadApi = $this->renderMethod($freshApplication, 'GET', '/api/get_thread?thread_id=root-001');

        assertSame('no', $likeResult['viewer_is_approved']);
        assertSame(true, isset($approveAlice['timings']['read_model_approval_incremental_update']));
        assertSame(true, isset($approveBob['timings']['read_model_approval_incremental_update']));
        assertStringContains('Score-Total: 1', $incrementalThreadApi);
        assertSame($this->normalizeRenderedPage($freshRootThread), $this->normalizeRenderedPage($incrementalRootThread));
        assertSame($this->normalizeRenderedPage($freshBobProfile), $this->normalizeRenderedPage($incrementalBobProfile));
        assertSame($this->normalizeRenderedPage($freshApprovalActivity), $this->normalizeRenderedPage($incrementalApprovalActivity));
        assertSame($freshThreadApi, $incrementalThreadApi);
    }

    public function testApprovalIncrementalFailureFallsBackToFullRebuildAndKeepsReadModelHealthy(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $target = $this->linkGeneratedIdentity($application, 'alice');
        $service = new class($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot)) extends LocalWriteService {
            protected function incrementalReadModelUpdater(): IncrementalReadModelUpdater
            {
                return new class($this->databasePath(), $this->repositoryRoot()) extends IncrementalReadModelUpdater {
                    public function applyApprovalWrite(\ForumRewrite\Canonical\PostRecord $record, string $commitSha): array
                    {
                        throw new RuntimeException('simulated approval incremental failure');
                    }
                };
            }
        };

        $result = $this->approveIdentity($service, $target);
        $profilePage = $this->renderMethod($application, 'GET', '/profiles/' . $target['profile_slug']);
        $staleMarkerPath = dirname($databasePath) . '/read_model_stale.json';

        assertSame(true, isset($result['timings']['read_model_approval_incremental_fallback']));
        assertSame(true, isset($result['timings']['read_model_approval_rebuild_fallback']));
        assertStringContains('Approved:</strong> yes', $profilePage);
        assertFalse(is_file($staleMarkerPath));
    }

    public function testApprovalWriteMarksDerivedStateStaleWhenIncrementalAndFallbackRefreshFail(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $target = $this->linkGeneratedIdentity($application, 'alice');
        $service = new class($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot)) extends LocalWriteService {
            protected function incrementalReadModelUpdater(): IncrementalReadModelUpdater
            {
                return new class($this->databasePath(), $this->repositoryRoot()) extends IncrementalReadModelUpdater {
                    public function applyApprovalWrite(\ForumRewrite\Canonical\PostRecord $record, string $commitSha): array
                    {
                        throw new RuntimeException('simulated approval incremental failure');
                    }
                };
            }

            protected function rebuildReadModel(): array
            {
                throw new RuntimeException('simulated approval rebuild failure');
            }
        };

        try {
            $this->approveIdentity($service, $target);
            throw new RuntimeException('Expected approval refresh failure.');
        } catch (RuntimeException $exception) {
            assertStringContains('Derived state marked stale', $exception->getMessage());
        }

        $staleMarkerPath = dirname($databasePath) . '/read_model_stale.json';
        assertTrue(is_file($staleMarkerPath));
        $staleMarker = json_decode((string) file_get_contents($staleMarkerPath), true, 512, JSON_THROW_ON_ERROR);
        assertSame('write_refresh_failed', $staleMarker['reason'] ?? null);
        assertTrue(strlen((string) ($staleMarker['commit_sha'] ?? '')) === 40);
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

        return strtok($matches[1], '?') ?: $matches[1];
    }

    /**
     * @param array<string, mixed> $analysisOverrides
     */
    private function createAnalyzedThread(Application $application, string $databasePath, array $analysisOverrides = []): string
    {
        $response = $this->renderMethod(
            $application,
            'POST',
            '/api/create_thread?board_tags=general&subject=Respondable%20Question&body=' . rawurlencode('What should we consider next?')
        );
        $postId = $this->extractValue($response, 'post_id');
        $this->analyzePostWithoutAgentReply($application, $postId);

        if ($analysisOverrides !== []) {
            $contentHash = $this->contentHashForAnalysis($databasePath, $postId);
            (new SqlitePostAnalysisStore(new PDO('sqlite:' . $databasePath)))->saveComplete(
                $postId,
                $contentHash,
                array_replace_recursive($this->baseCompletedAnalysis(), $analysisOverrides)
            );
        }

        return $postId;
    }

    /**
     * @return array<string, mixed>
     */
    private function analyzePostWithoutAgentReply(Application $application, string $postId): array
    {
        $previous = getenv('DEDALUS_AGENT_REPLIES_ENABLED');
        putenv('DEDALUS_AGENT_REPLIES_ENABLED=false');

        try {
            return json_decode(
                $this->renderMethod($application, 'POST', '/api/analyze_post?post_id=' . rawurlencode($postId)),
                true
            );
        } finally {
            if ($previous === false) {
                putenv('DEDALUS_AGENT_REPLIES_ENABLED');
            } else {
                putenv('DEDALUS_AGENT_REPLIES_ENABLED=' . $previous);
            }
        }
    }

    private function contentHashForAnalysis(string $databasePath, string $postId): string
    {
        $pdo = new PDO('sqlite:' . $databasePath);
        $stmt = $pdo->prepare('SELECT content_hash FROM post_analyses WHERE post_id = :post_id');
        $stmt->execute(['post_id' => $postId]);
        $value = $stmt->fetchColumn();
        if ($value === false) {
            throw new RuntimeException('Missing analysis content hash for ' . $postId);
        }

        return (string) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function baseCompletedAnalysis(): array
    {
        return [
            'provider' => 'stub',
            'provider_model' => 'stub/post-analysis',
            'provider_request_id' => 'stub-analysis',
            'post_summary' => 'The post asks what should be considered next.',
            'moderation' => [
                'severity' => 'none',
                'labels' => [],
                'confidence' => 0.9,
                'summary' => 'No moderation concern.',
                'recommended_action' => 'none',
            ],
            'engagement' => [
                'suggested_response' => 'Consider naming the main tradeoff.',
                'response_style' => 'curious',
                'response_should_be_public' => true,
            ],
            'quality' => [
                'discussion_value' => 'medium',
                'good_faith_likelihood' => 0.9,
                'needs_human_review' => false,
            ],
            'respondability' => [
                'overall_score' => 0.9,
                'asks_question' => true,
                'question_type' => 'opinion',
                'invites_response' => true,
                'author_benefit' => 'medium',
                'audience_benefit' => 'medium',
                'response_effort_required' => 'low',
                'response_risk' => 'low',
                'best_response_mode' => 'answer',
                'should_generate_response' => true,
                'reason' => 'Respondable test post.',
            ],
            'raw_response' => ['stub' => true],
        ];
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

    /**
     * @param array{identity_id:string,profile_slug:string,bootstrap_post_id:string,bootstrap_thread_id:string} $target
     * @return array<string, mixed>
     */
    private function approveIdentity(LocalWriteService $service, array $target, string $approverIdentityId = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954'): array
    {
        return $service->approveUser([
            'approver_identity_id' => $approverIdentityId,
            'target_identity_id' => $target['identity_id'],
            'target_profile_slug' => $target['profile_slug'],
            'thread_id' => $target['bootstrap_thread_id'],
            'parent_id' => $target['bootstrap_post_id'],
        ]);
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
