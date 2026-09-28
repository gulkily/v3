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
