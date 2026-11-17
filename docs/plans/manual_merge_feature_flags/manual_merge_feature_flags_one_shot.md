Cycle: v3/manual_merge_feature_flags; Mode: one-shot; Index: [plans](../README.md)
Debug mode: enabled
Effort study: railspike-effort; [host ledger](../effort_estimation_trial.md); standing P2 enrollment
Extended estimates: enabled
**Cycle estimate: 0.06**
Status: Live outage repaired and verified; completion commit and PR handoff follow.

Original request: i seem to have messed up a manual merge and now we're getting a 500, please look for the error in ~/logs/, find it, figure out the cause, and fix it, and report back
Authorization: 2026-11-14 request authorizes diagnosing and repairing the live checkout's merge failure. No unrelated production changes.
Outcome: Restore ordinary site requests and preserve both merged feature flags.
Starting revision: 393a64d5224f308438edbff96931fcdc7d836b6e; fresh origin/main.
Branch: fix/manual-merge-feature-flags
Predecessor: PR 217; merge e6ce2d95 combined pending-chip approval with usage reports incorrectly.
Feedback: Searched docs/feedback/items for 500, fatal, syntax and manual merge. No matching item; PF-20261030-B17 concerns a different posting/firewall failure.
Evidence: HTTPS error.log reports unexpected RENDERING at FeatureFlagRegistry.php:69 since 12:18:25 local time. PHP lint reproduces it; GET https://zenmemes.com/ returns 500.
Reuse decisions: Repair src/ForumRewrite/Support/FeatureFlags/FeatureFlagRegistry.php in place. Preserve PRESENTATION_ORDER and both existing definitions. Use existing FeatureFlagEvaluatorTest, FeatureFlagsBehaviorTest and FeatureFlagChangePageTest; inspect existing order and pending-chip checks. No new component, payload, dependency or schema.
Cause: Conflict resolution kept both EXPERIENCE array tails, closing the containing array early. It also dropped pending-chip siteMutable and group arguments.
Approach and risks: Combine both new flags once in EXPERIENCE and restore the pending-chip metadata from its parent. Compare merge parents before editing. This is the live checkout; use one atomic file replacement and immediately lint and replay HTTP.
Completion conditions: Homepage and dynamic ordinary pages return successful responses; flag completeness, mutability and grouping checks pass. Retry requests after correction without clearing user data.
Verification plan: PHP lint, smoke plus affected feature-flag and pending-chip tests, live HTTP replay and log follow-up, diff check. No full suite. No UI copy, theme, navigation or schema changes requiring separate visual or migration work.
Recovery: Preserve original Git revision; if verification reveals further merge defects, correct only those in scope. Do not roll back unrelated merged features.
Concepts touched: Existing feature-flag configuration contract used unchanged; add a concise missing concept entry. Usage reports and approval behavior remain unchanged.
Documentation scope: This record and debug log, plans index, effort ledger and feature-flag concept entry.
Planning commit: 757179c8; confirmed before source edits.
Study history:
- 2026-11-14 UTC; intake and execution entry: 0.06, scope v1, R1/P2, Codex single exposed rater. Intake follows log/source inspection; no pristine estimate. R1 hashes verified. Execution entry reaffirms 0.06. No measured effort or independent rating.
Attribution: Codex, 2026-11-14 UTC.

Execution history:
- 2026-11-14 20:20 UTC: Atomically repaired the existing registry. PHP lint passed and the live homepage changed from HTTP 500 to 200.
- Live GET /tools/, /recent/ and /tools/feature-flags/ each returned 200. The rendered flags page includes both repaired feature keys.
- Direct evaluator checks confirmed both flags are site-mutable, belong to EXPERIENCE, and honor false and true site overrides.
- BoardListTest and UsageReportsPageTest passed 15/15, including pending-chip flag-off rendering and usage route access controls.
- Smoke verification in the live checkout encountered startup recovery failures. Application::ensureReadModel uses the project's active worker state, which differs from a disposable checkout. Repeating the same selection in an isolated worktree.
- Before-merge revision 66b44b70 passed the selected startup recovery and BoardSearch checks in isolation. It reproduced FeatureFlagsBehaviorTest::testRenderedOrderIgnoresRegistryInputOrder failing in the toolbar fixture because tools_nav_card receives no partial callable. This predates the repair.
Test boundary: Existing checks cover registry completeness and grouping. No new implementation-mirroring test was added. No signing mutation or user-data change was needed for HTTP recovery.

Follow-up question: The user asked whether the feature-flag test relates to recent code cleanup. Git history identifies cd72c983 (2026-11-09), which extracted tools_nav_card.php and introduced nested partial rendering. The fixture closure only captures e and toolNavOptions; it cannot provide partial or indent to nested templates. Production TemplateRenderer supports this contract, and the live flags page returns 200. This older fixture issue is recorded separately from the merge repair.

Closeout: 2026-11-14 UTC.
Delivered: Fixed the malformed EXPERIENCE list and restored pending-chip site mutability and group. No new feature behavior.
Live verification: HTTP and HTTPS homepage returned 200 after the repair; HTTPS Tools, Recent and Feature Flags returned 200. Both merged flags appear in the live page. No later PHP errors appeared after the last pre-repair error at 12:20:21 local time.
Checks passed: PHP lint; registry completeness and group assertions; evaluator metadata and false/true site overrides; signed feature-flag change tests; BoardListTest and UsageReportsPageTest (15/15); git diff --check.
Smoke selection: ./v3 test --smoke FeatureFlagEvaluatorTest FeatureFlagsBehaviorTest FeatureFlagChangePageTest.
Live-checkout result: 470 run, 444 passed, 26 failed. Twenty-four startup/read-model failures depend on live worker state; all passed with the repaired source in an isolated worktree.
Isolated result: 470 run, 468 passed, 2 failed. Both remaining failures reproduce on pre-merge revision 66b44b70: the outdated feature-flags toolbar fixture and LocalAppSmokeTest::testThreadCardWrapsMediaEmbedPreviewInDiv. They remain unresolved and outside this outage repair.
Baseline commands: ./v3 test ActivityProjectionTest::testPreviousSchemaStartupRecoversCountersBeforePaging FeatureFlagsBehaviorTest BoardSearchTest (11/12 passed; old toolbar fixture failed); ./v3 test LocalAppSmokeTest::testThreadCardWrapsMediaEmbedPreviewInDiv (same failure).
Limitations: Full suite was not run. No new browser-interaction or theme checks because no markup, styling or interaction changed. This is verified HTTP recovery, not a claim that every unrelated feature works.
Logs: /tmp/manual-merge-tests.log; /tmp/manual-merge-isolated-tests.log; /tmp/manual-merge-consumers.log; /tmp/manual-merge-baseline-tests.log; /tmp/manual-merge-parent-media.log.
Recovery for user: Reload the site; no cache clear, data migration, configuration change or process restart is required.
Study closeout: v3/manual_merge_feature_flags; scope v1; R1/P2; Codex single exposed rater; retrospective 0.10. The original 0.06 projection is retained. Additional effort came from isolating existing test failures and tracing cleanup history. Maintainer fit and acceptance pending; no measured labor or accepted-outcome increment.
Final review: Compared the two repaired registry regions with merge parents. Existing shared registry and evaluator contracts are reused. Reviewed bounded source/documentation diff and concept paths; unrelated untracked files and todo.txt are untouched.
Commit status: Planning commit 757179c8 precedes implementation. Completion commit pending at this write. Open a fresh PR under the repository follow-up rule; do not merge it. The repaired source is already active in this live checkout.

*— Codex · 2026-11-14 UTC*
