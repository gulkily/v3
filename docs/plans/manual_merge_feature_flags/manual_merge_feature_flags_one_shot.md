Cycle: v3/manual_merge_feature_flags; Mode: one-shot; Index: [plans](../README.md)
Debug mode: enabled
Effort study: railspike-effort; [host ledger](../effort_estimation_trial.md); standing P2 enrollment
Extended estimates: enabled
Cycle estimate: 0.06
Status: Planned; implementation follows planning commit.

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
Planning commit: Pending.
Study history:
- 2026-11-14 UTC; intake and execution entry: 0.06, scope v1, R1/P2, Codex single exposed rater. Intake follows log/source inspection; no pristine estimate. R1 hashes verified. Execution entry reaffirms 0.06. No measured effort or independent rating.
Attribution: Codex, 2026-11-14 UTC.
