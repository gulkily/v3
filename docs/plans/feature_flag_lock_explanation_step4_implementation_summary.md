# Step 4: Implementation Summary — Feature Flag Lock Explanation

## Stage 1 - Expose viewer capability to the read path
- Changes:
  - `src/ForumRewrite/Http/ToolsPageController.php`: `featureFlags()` now resolves the viewer profile via `resolveViewerProfileFromIdentityHint` and passes `canManageFeatureFlags` (bool, from the existing `viewerCanManageFeatureFlags()`) into the `feature_flags.php` template render array.
- Verification:
  - `php -l src/ForumRewrite/Http/ToolsPageController.php` — no syntax errors.
  - `./v3 test FeatureFlagEvaluatorTest` — 13/13 passed.
  - `./v3 test LocalAppSmokeTest WriteApiSmokeTest` — 202/207 passed; the 5 failures are pre-existing/long-standing (activity manifest tests, failing since 2026-09-25T12:05:43-04:00, unrelated to feature flags) and were already failing before this change.
  - Confirmed no template yet consumes `canManageFeatureFlags`, so this stage has no rendering effect (data wiring only).
- Notes:
  - `resolveViewerProfileFromIdentityHint()` returns `null` when no session/cookie identity is present, so `canManageFeatureFlags` defaults to `false` for anonymous viewers — matches Step 2's intended behavior for Stage 3.
  - Confirmed via `Application::resolveViewerProfileFromIdentityHint()` (src/ForumRewrite/Application.php:1307-1351) that this is a cheap read (DB lookup only when a cookie/session hint is present), safe to call on the GET path.

## Stage 2 - Visible lock-reason text
- Changes:
  - `templates/pages/feature_flags.php`: added a visible `<span class="feature-flag-lock-reason">` showing `$flag->lockReason()` in the flag's meta row, alongside (not replacing) the existing `badge-locked` tooltip.
- Verification:
  - `php -l templates/pages/feature_flags.php` — no syntax errors.
  - `./v3 test LocalAppSmokeTest` — 94/99 passed; same 5 pre-existing/long-standing failures as Stage 1 baseline, no new regressions.
  - Manual render check (scratch script booting `Application` against the `parity_minimal_v1` fixture, GET `/tools/feature-flags/`): confirmed all three lock reasons ("Set via environment variable...", "Set via private config file...", "Not configurable from the site.") now appear as visible text on their respective rows (e.g. `DEDALUS_AGENT_REPLIES_ENABLED`, `LLM_CONVERSATION_RECORDING_ENABLED`, `FAST_SCORING_ENABLED`).
- Notes:
  - Kept the existing tooltip/badge markup untouched (still has `title="..."`) so the pre-existing `testFeatureFlagsPageShowsLockedBadgeWithReasonForNonMutableFlags` assertion on the `title` attribute still holds; the new span is additive.

## Stage 3 - Read-only rendering for permission-blocked mutable flags
- Changes:
  - `templates/pages/feature_flags.php`: computed `$canEditFlag = $flag->canChangeFromSite() && $canManageFeatureFlags` and `$isReadOnlyForViewer = $flag->canChangeFromSite() && !$canManageFeatureFlags` per row.
    - Control block now branches on `$canEditFlag` (live toggle) vs. disabled toggle, instead of only `canChangeFromSite()`.
    - "Reset to default" form now guarded by `$canEditFlag` instead of `canChangeFromSite()`.
    - Added a visible `<span class="feature-flag-permission-note">Read-only — requires a root-approved identity to change.</span>` when `$isReadOnlyForViewer` is true.
  - `tests/WriteApiSmokeTest.php` (`testFeatureFlagFormSubmitRequiresRootApprovedIdentityAndRedirectsAfterCommit`): updated to fetch the page anonymously first and assert the new read-only state (no `data-feature-flag-toggle`, permission note present), then fetch again as the root-approved `guest` identity and assert the live form is present — this test previously fetched the form anonymously and asserted the live toggle was present, which was the exact bug being fixed.
- Verification:
  - `php -l` on both changed files — no syntax errors.
  - Manual render check (scratch script, `Application::handle('GET', '/tools/feature-flags/')` against `parity_minimal_v1`): anonymous viewer sees 0 `data-feature-flag-toggle` forms and the read-only note on all 5 mutable flags; `identity_hint=guest` (root-approved in this fixture) sees all 5 live toggle forms and no read-only note — confirms root-approved viewers see unchanged behavior.
  - `./v3 test LocalAppSmokeTest WriteApiSmokeTest` — 202/207 passed. Same 5 pre-existing failures as the Stage 1/2 baseline (activity manifest tests, failing since 2026-09-25).
- Notes:
  - Investigated one additional failure that appeared during iteration, `WriteApiSmokeTest::testIncrementalApprovalMatchesFreshRebuildForTransitiveApprovalAndScoreRefresh`. Confirmed via `git stash` (reverting all Stage 3 changes back to the committed Stage 2 code) that this test still fails intermittently and passes on other runs with zero changes applied — it's a pre-existing flake in the suite, unrelated to this feature. Not fixed here (out of scope); flagged for awareness.

## Stage 4 - Automated test coverage for the three render states
- Changes:
  - `tests/LocalAppSmokeTest.php` (`testFeatureFlagsPageShowsLockedBadgeWithReasonForNonMutableFlags`): added an assertion that the visible `<span class="feature-flag-lock-reason">Not configurable from the site.</span>` renders, closing the coverage gap for Stage 2's change (previously only manually verified).
  - Mutable-flag permission states (no-permission read-only vs. root-approved live toggle) were already covered as part of Stage 3's required fix to `testFeatureFlagFormSubmitRequiresRootApprovedIdentityAndRedirectsAfterCommit` in `tests/WriteApiSmokeTest.php` (see Stage 3 notes) — no further test needed for those two states.
- Verification:
  - `php -l tests/LocalAppSmokeTest.php` — no syntax errors.
  - `./v3 test LocalAppSmokeTest WriteApiSmokeTest` — 202/207 passed; same 5 pre-existing failures as every prior stage's baseline, no new regressions. The known-flaky transitive-approval test (see Stage 3 notes) passed on this run.
- Notes:
  - All three render states identified in Step 2/3 now have automated coverage: (1) locked flag → visible lock-reason text, (2) mutable + non-root viewer → disabled toggle + read-only note, (3) mutable + root-approved viewer → live toggle, unchanged from prior behavior.
  - This is the final planned stage; all 4 Step 3 stages are implemented and committed.
