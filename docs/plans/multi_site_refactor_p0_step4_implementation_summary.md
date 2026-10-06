# Multi-Site Refactor P0 — Step 4: Implementation Summary

> **Feature plan:** [Step 1](./multi_site_refactor_p0_step1_solution_assessment.md) · [Step 2](./multi_site_refactor_p0_step2_feature_description.md) · [Step 3](./multi_site_refactor_p0_step3_development_plan.md) · [Step 4](./multi_site_refactor_p0_step4_implementation_summary.md)

## Stage 1 - Validated profile contract

- Changes:
  - Extended each profile with display identity, permitted themes, browser/offline namespace, editorial-content key, and enabled experience keys.
  - Added descriptor validation for required values, browser-safe/unique identifiers, permitted default themes, and the Zenmemes fallback.
  - Kept repository, database, identity, approval, and content state out of profile metadata.
- Verification:
  - `php -l src/ForumRewrite/SiteProfileRegistry.php`
  - `php -l tests/SiteProfileRegistryTest.php`
  - `php tests/run.php SiteProfileRegistryTest` — 6 passed.
- Notes:
  - Browser/storage consumers remain unchanged until P2; P0 establishes their canonical metadata only.

## Stage 2 - Shared presentation-path resolver

- Changes:
  - Added `PresentationPathResolver` as the sole owner of the profile-derived static-output-root rule.
  - Preserved `state/static_html` for Zenmemes and assigned `_chouse`/`_qdb` roots from validated browser namespaces.
- Verification:
  - `php -l src/ForumRewrite/PresentationPathResolver.php`
  - `php -l tests/PresentationPathResolverTest.php`
  - `php tests/run.php PresentationPathResolverTest` — 1 passed.
- Notes:
  - The resolver owns presentation paths only; it does not resolve repository, database, or other instance paths.

## Stage 3 - Web and task-queue path migration

- Changes:
  - Replaced the web entry point's local Zenmemes suffix branch with `PresentationPathResolver`.
  - Replaced the task queue's duplicated default-root branch without changing explicit option or environment precedence.
- Verification:
  - `php -l public/index.php`
  - `php -l scripts/task_queue.php`
  - `php tests/run.php PresentationPathResolverTest TaskQueueCommandTest` — 13 passed.
  - Confirmed no hand-written profile-suffix branch remains in these two consumers.
- Notes:
  - Existing task-queue explicit-root coverage remains green, preserving operator-supplied root behavior.

## Stage 4 - Offline command migration and release verification

- Changes:
  - Migrated offline snapshot publication and diagnosis fallback roots to `PresentationPathResolver`.
  - Added command coverage for a selected Chouse profile with explicit CLI and environment static-root overrides.
- Verification:
  - `php -l scripts/publish_offline_snapshot.php`
  - `php -l scripts/diagnose_offline_reading.php`
  - `php -l tests/OfflineSnapshotPublishCommandTest.php`
  - `php -l tests/OfflineReadingDiagnosticCommandTest.php`
  - `php tests/run.php OfflineSnapshotPublishCommandTest OfflineReadingDiagnosticCommandTest PresentationPathResolverTest SiteProfileRegistryTest TaskQueueCommandTest` — 25 passed.
  - `php tests/run.php` — the only three failures reproduce on pre-P0 commit `803948d`: `BrowserSigningNormalizationTest::testThreadSubmitRendersPendingShellBeforeApiResponseAndNavigatesOnSuccess`, `BrowserSigningNormalizationTest::testInlineReplySubmitRendersPendingCardBeforeApiResponseAndNavigatesOnSuccess`, and `LocalAppSmokeTest::testAnonymousPublicBoardDoesNotStartViewerSession`.
- Notes:
  - Deployment verification remains for the operator: confirm an existing Zenmemes deployment reads `state/static_html`, then verify Chouse and QDB default roots are isolated before rollout.
