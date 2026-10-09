> **Feature plan:** [Step 1](./event_feature_gating_step1_solution_assessment.md) · [Step 2](./event_feature_gating_step2_feature_description.md) · [Step 3](./event_feature_gating_step3_development_plan.md) · [Step 4](./event_feature_gating_step4_implementation_summary.md)

## Stage 1 - Register event support

- Changes:
  - Added the site-mutable, default-off `FORUM_EVENT_SUPPORT_ENABLED` flag in the Authored content group.
  - Exposed its evaluated value to the existing template-rendering data contract.
  - Added evaluator coverage for default and site-enabled behavior.
- Verification:
  - `./v3 test FeatureFlagEvaluatorTest` — 14 run, 14 passed.
  - Deployment and external verification: not applicable; this stage changes only the local flag registry and rendering context.
- Notes:
  - The Feature Flags tool enumerates the registry, so the new flag requires no separate settings UI.

## Stage 2 - Gate event UI

- Changes:
  - Hid the shared non-compact composer event inputs unless event support is enabled.
  - Suppressed the shared event block on board cards and thread pages unless event support is enabled.
  - Added smoke coverage for default-disabled and enabled compose, board, thread, and Forte surfaces.
- Verification:
  - `./v3 test LocalAppSmokeTest::testEventSupportUiIsHiddenByDefaultAndShownWhenEnabled` — 1 run, 1 passed.
  - Deployment and external verification: not applicable; UI behavior is covered by local application renders.
- Notes:
  - The compact QDB composer remains unchanged and event-free.

## Stage 3 - Enforce event support on writes

- Changes:
  - Added a pre-write thread-creation guard that rejects non-empty event fields when the site flag is disabled.
  - Added direct write coverage proving disabled event input writes no record, ordinary threads still work, and enabled event input retains all three headers.
- Verification:
  - `php -l src/ForumRewrite/Write/LocalWriteService.php` and `php -l tests/WriteApiSmokeTest.php` — no syntax errors.
  - `./v3 test WriteApiSmokeTest::testEventFieldsRequireEventSupportBeforeWriting` — 1 run, 1 passed.
  - Deployment and external verification: not applicable; the server-side write contract is covered locally.
- Notes:
  - The compose controller already routes write errors through its standard 400 re-render path, so no new error surface was introduced.

## Stage 4 - Verify safe flag transitions

- Changes:
  - Added a site-record toggle regression test for default-disabled → enabled → disabled → re-enabled event rendering.
  - Asserted that both the canonical event record and its read-model values remain unchanged across transitions.
- Verification:
  - `php -l tests/LocalAppSmokeTest.php` — no syntax errors.
  - `./v3 test LocalAppSmokeTest::testEventSupportTogglePreservesExistingEventData` — 1 run, 1 passed.
  - `./v3 test` — 882 run, 880 passed, 2 failed: the known unrelated `WriteApiSmokeTest::testTaskQueueProcessesQueuedAgentReplyOnce` and `::testQdbPermalinkShowsViewersExistingUpvoteAsPressedAndDisabled` failures.
  - Reran those two failures directly: 2 run, 0 passed; test history classifies them as long-standing since 2026-10-08 and 2026-10-09 respectively.
  - Deployment and external verification: not applicable; this feature has no deployment or external-service dependency.
- Notes:
  - The test changes only the temporary site's feature-flags record, mirroring an operator toggle without mutating event data.
