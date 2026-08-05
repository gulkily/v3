# Agent Reply Request Button Step 4 Implementation Summary

## Stage 1 - Durable Request State
- Changes:
  - Added generated-response store APIs to record one durable `requested` row per target post/content hash.
  - Added row claiming that atomically moves queued requests from `requested` to `pending`.
  - Added `skipped` terminal state support while preserving stored request context.
  - Extended focused agent reply generation tests for request creation, duplicate requests, posted-row preservation, claiming, and skipped rows.
- Verification:
  - `php -l src/ForumRewrite/Agent/SqliteAgentReplyGenerationStore.php`
  - `php -l src/ForumRewrite/Agent/AgentReplyGenerationStore.php`
  - `php -l tests/AgentReplyGenerationTest.php`
  - Focused `AgentReplyGenerationTest` run via `php -r ...`: all test methods passed.
- Notes:
  - `php tests/run.php 2>&1 | rg "AgentReplyGenerationTest|All tests passed|FAIL"` showed all `AgentReplyGenerationTest` methods passing, then surfaced an existing-looking `LocalAppSmokeTest::testFrontControllerShowsBusyErrorForExecutionLockContention` failure outside this stage's touched code.

## Stage 2 - Request Recording Endpoint
- Changes:
  - Changed `POST /api/generate_agent_reply` to require an approved viewer and record a durable request instead of publishing inline.
  - Added request-result mapping for `requested`, in-progress, skipped, failed, and already-posted generated-response rows.
  - Updated smoke coverage for approved-viewer request creation, disabled automatic work with manual request recording, agent-authored rejection, and existing pending rows.
- Verification:
  - `php -l src/ForumRewrite/Application.php`
  - `php -l tests/WriteApiSmokeTest.php`
  - Focused `WriteApiSmokeTest` run via `php -r ...`: `testGenerateAgentReplyRequiresApprovedViewerAndRecordsRequest`, `testAutomaticAgentReplyWorkCanBeDisabledWithoutDisablingApi`, `testGenerateAgentReplyRejectsAgentAuthoredTarget`, and `testGenerateAgentReplyDoesNotStartWhenGenerationIsAlreadyPending` passed.
- Notes:
  - Older smoke tests that expected `/api/generate_agent_reply` to publish inline will be moved to the fulfillment service/command stages.

## Stage 3 - Request Button Rendering
- Changes:
  - Added the request button to post cards and thread root cards for approved viewers when no generated-response row exists for current content.
  - Hid the button for anonymous/unapproved viewers, `reply-agent` posts, and posts with existing request/generated-response state.
  - Rendered existing generated-response states in the existing agent-reply feedback node, including requested, in-progress, skipped, failed, and posted states.
  - Added render smoke coverage for the approved-viewer button lifecycle.
- Verification:
  - `php -l templates/partials/post_card.php`
  - `php -l templates/partials/thread_root_card.php`
  - `php -l tests/WriteApiSmokeTest.php`
  - Focused `WriteApiSmokeTest` run via `php -r ...`: Stage 2 endpoint tests plus `testApprovedViewerSeesAgentReplyRequestButtonUntilRequestExists` passed.
- Notes:
  - The static rendered page shows request state after refresh/revisit; live fulfillment updates remain deferred until later UX work.

## Stage 4 - Request Button Browser Behavior
- Changes:
  - Extended `post_analysis.js` to bind `Request agent response` buttons.
  - Added async request submission, duplicate-click prevention, busy text, success hiding, and same-card feedback.
  - Added handling for the new `requested` result and visible in-progress/failed request states.
  - Extended script smoke assertions for the new request-button behavior.
- Verification:
  - `node --check public/assets/post_analysis.js`
  - `php -l tests/WriteApiSmokeTest.php`
  - Focused `WriteApiSmokeTest` run via `php -r ...`: `testPostAnalysisScriptDoesNotExposeInProgressReplyGeneration`, `testGenerateAgentReplyRequiresApprovedViewerAndRecordsRequest`, and `testApprovedViewerSeesAgentReplyRequestButtonUntilRequestExists` passed.
- Notes:
  - The client does not poll for cron fulfillment completion; users see fulfillment results on refresh/revisit.

## Stage 5 - Publish Path Service
- Changes:
  - Added `AgentReplyFulfillmentService` for publish-from-completed-analysis behavior.
  - Moved existing gate/generate/post/idempotency behavior behind the service while keeping result payloads stable.
  - Changed `Application::agentReplyResultForPost()` to delegate to the service after config gating.
- Verification:
  - `php -l src/ForumRewrite/Agent/AgentReplyFulfillmentService.php`
  - `php -l src/ForumRewrite/Application.php`
  - Focused `WriteApiSmokeTest` run via `php -r ...`: `testPostAnalysisEndpointStoresStubResultIdempotently` and `testAnalyzePostGateFailureUsesCompactVisibilityRules` passed.
- Notes:
  - The service currently receives context/gate callbacks from `Application`; Stage 6 will add claimed-request fulfillment on top of the extracted publish path.

## Stage 6 - Claimed Request Fulfillment
- Changes:
  - Added `AgentReplyFulfillmentService::fulfillRequest()` for claimed generated-response request rows.
  - Added missing-analysis execution, target-missing/content-changed safeguards, gate skips, and publish handoff for claimed rows.
  - Treated claimed request rows as worker-owned `pending` rows so fulfillment can proceed without colliding with duplicate in-progress detection.
  - Added focused smoke coverage for queued request analysis/publish and durable gate skip.
- Verification:
  - `php -l src/ForumRewrite/Agent/AgentReplyFulfillmentService.php`
  - `php -l src/ForumRewrite/Application.php`
  - `php -l tests/WriteApiSmokeTest.php`
  - Focused `WriteApiSmokeTest` run via `php -r ...`: `testClaimedAgentReplyRequestAnalyzesAndPublishes` and `testClaimedAgentReplyRequestStoresGateSkip` passed.
- Notes:
  - Claimed rows currently use the existing `pending` status; recovery of stale pending rows remains a minimal V1 policy for the command stage.

## Stage 7 - Periodic Fulfillment Command
- Changes:
  - Added `scripts/run_agent_reply_requests.php` with `--limit`, `--dry-run`, `--post-id`, `--repository-root`, and `--database-path`.
  - Added `Application::fulfillAgentReplyRequest()` as the non-HTTP entry point for claimed rows.
  - Added targeted generated-response claiming for the command's `--post-id` option.
  - Added command smoke coverage for dry run, one queued fulfillment, and duplicate-free repeat execution.
- Verification:
  - `php -l scripts/run_agent_reply_requests.php`
  - `php -l src/ForumRewrite/Agent/SqliteAgentReplyGenerationStore.php`
  - `php -l src/ForumRewrite/Agent/AgentReplyGenerationStore.php`
  - `php -l src/ForumRewrite/Application.php`
  - `php -l tests/WriteApiSmokeTest.php`
  - `php -l tests/AgentReplyGenerationTest.php`
  - Focused `WriteApiSmokeTest` run via `php -r ...`: `testAgentReplyRequestCommandProcessesQueuedRequestOnce`, `testClaimedAgentReplyRequestAnalyzesAndPublishes`, and `testClaimedAgentReplyRequestStoresGateSkip` passed.
  - Focused `AgentReplyGenerationTest` run via `php -r ...`: `testStoreClaimsRequestedRowsOnce` and `testStoreClaimsRequestedRowForSpecificPost` passed.
- Notes:
  - The command exits successfully when the process lock is already held; row-level claims still provide the correctness boundary for duplicate prevention.

## Stage 8 - Documentation And Regression
- Changes:
  - Updated production documentation with the request/fulfillment split, non-blocking button behavior, one-minute cron example, manual worker commands, and overlap-safety expectations.
  - Updated production examples to show the cron invocation and separate automatic-agent-reply defaults from manual request fulfillment.
  - Revised legacy write API smoke tests so `/api/generate_agent_reply` assertions cover request creation, while posting, skip, unicode, context, idempotency, and posting-failure assertions run through claimed request fulfillment.
- Verification:
  - `php -l tests/WriteApiSmokeTest.php`
  - `php -l scripts/run_agent_reply_requests.php`
  - `php -l src/ForumRewrite/Agent/AgentReplyFulfillmentService.php && php -l src/ForumRewrite/Agent/SqliteAgentReplyGenerationStore.php && php -l src/ForumRewrite/Application.php`
  - Direct affected-method run for the updated `WriteApiSmokeTest` agent reply cases passed.
  - `php tests/run.php` passed all updated agent reply request/button/worker tests and failed only `LocalAppSmokeTest::testFrontControllerShowsBusyErrorForExecutionLockContention`.
- Notes:
  - The full suite failure was already present earlier in Step 4; the run also emitted pre-existing asset fingerprint filename-length warnings.
