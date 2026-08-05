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
