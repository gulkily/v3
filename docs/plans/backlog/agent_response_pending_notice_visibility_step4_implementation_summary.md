> **Feature plan:** [Step 1](./agent_response_pending_notice_visibility_step1_solution_assessment.md) · [Step 2](./agent_response_pending_notice_visibility_step2_feature_description.md) · [Step 3](./agent_response_pending_notice_visibility_step3_development_plan.md) · [Step 4](./agent_response_pending_notice_visibility_step4_implementation_summary.md)

# Agent Response Pending Notice Visibility Step 4 Implementation Summary

## Stage 1 - Server-rendered pending notice visibility

- Changes:
  - Moved unfinished agent-response feedback before the collapsible action footer on both root and reply cards.
  - Kept published, skipped, failed, and empty feedback in the existing footer location.
  - Added root- and reply-continuation regression coverage for the requested state.
- Verification:
  - `php -l templates/partials/thread_root_card.php`, `php -l templates/partials/post_card.php`, and `php -l tests/WriteApiSmokeTest.php` passed.
  - `php tests/run.php WriteApiSmokeTest::testApprovedViewerSeesAgentReplyRequestButtonUntilRequestExists WriteApiSmokeTest::testRequestedAgentReplyNoticePrecedesCollapsedReplyActions` passed: 2 run, 2 passed.
  - Started the local server and loaded `/threads/root-001`; its root card rendered as a continuation head (`meta-deferred`) with the canonical agent-feedback surface present. Focused tests verify pending feedback precedes collapsed actions for actual requested root and reply cards.
  - `git diff --check` passed.
- Notes:
  - No API, persistence, lifecycle wording, or continuation rules changed.

## Stage 2 - In-page status synchronization

- Changes:
  - Added a shared browser-controller helper that moves agent-response feedback before the action footer for requesting, requested, and in-progress states.
  - Restored terminal, skipped, and failed feedback to its existing position in the action footer.
  - Added a browser-controller regression test covering requested-to-failed placement and wording.
- Verification:
  - `node --check public/assets/post_analysis.js` and `php -l tests/WriteApiSmokeTest.php` passed.
  - `php tests/run.php WriteApiSmokeTest::testPostAnalysisScriptDoesNotExposeInProgressReplyGeneration WriteApiSmokeTest::testPostAnalysisScriptKeepsOnlyUnfinishedAgentFeedbackOutsideActions WriteApiSmokeTest::testApprovedViewerSeesAgentReplyRequestButtonUntilRequestExists WriteApiSmokeTest::testRequestedAgentReplyNoticePrecedesCollapsedReplyActions` passed: 4 run, 4 passed.
  - `php tests/run.php WriteApiSmokeTest LocalAppSmokeTest` ran 239 tests: 234 passed; the 5 failures are tracked long-standing failures unrelated to this feature.
  - Full `php tests/run.php` ran 728 tests: 720 passed, 8 failed. Seven are tracked long-standing unrelated failures; the approval read-model parity failure was also present in the earlier focused run and does not touch this feature’s templates or browser controller.
  - In a disposable local repository, real API requests for a collapsed root and reply card both returned `generation_status: requested`; Chromium rendered each `Logic analysis requested.` notice directly before its `post-card-actions` footer.
  - `git diff --check` passed.
- Notes:
  - No endpoint, request payload, database, response lifecycle, or terminal-status wording changed.
