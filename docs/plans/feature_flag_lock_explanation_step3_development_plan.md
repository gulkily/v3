# Step 3: Development Plan — Feature Flag Lock Explanation

## Stage 1
- Goal: Expose whether the current viewer can manage feature flags to the read-time page render (currently only checked on submit).
- Dependencies: none
- Expected changes:
  - In `ToolsPageController::featureFlags()`, resolve the viewer profile via the existing `resolveViewerProfileFromIdentityHint` closure and compute `canManageFeatureFlags` using the existing private `viewerCanManageFeatureFlags()` method.
  - Pass `canManageFeatureFlags` (bool) as a new template variable alongside `flags`/`registry`/`toolNavOptions`.
- Verification approach: Load `/tools/feature-flags/` as a root-approved identity and confirm the page renders unchanged (no visible diff yet, this stage only wires data through). Confirm no errors/exceptions from resolving the viewer profile on a GET request.
- Risks or open questions:
  - Confirm `resolveViewerProfileFromIdentityHint` has no side effects/perf cost inappropriate for a read path (it's already invoked on the two POST paths, so expected to be safe).
  - Need a way to exercise the "approved but non-root" case for later verification — check whether a second dev/test identity fixture already exists.
- Canonical components/API contracts touched: `ToolsPageController::featureFlags()`; reuses existing `viewerCanManageFeatureFlags(?array $viewerProfile): bool` — no new public contract.

## Stage 2
- Goal: Make the lock reason visible as page text instead of a hover-only tooltip.
- Dependencies: none
- Expected changes:
  - In `templates/pages/feature_flags.php`, next to the existing `badge-locked` badge, add visible text rendering `$flag->lockReason()`.
  - Keep existing badge/tooltip in place or fold its content into the new visible text (avoid duplicating the same string twice in the DOM).
- Verification approach: Manually trigger all three lock reasons (set an env var override, use a private-config-backed flag, and inspect a `siteMutable: false` flag) and confirm each shows its correct visible reason text matching `lockReason()`.
- Risks or open questions: none beyond minor styling (reuse existing `.meta` text class, no new CSS component).
- Canonical components/API contracts touched: `templates/pages/feature_flags.php` only; reuses `FeatureFlagState::lockReason()` — no API change.

## Stage 3
- Goal: Render mutable flags as read-only with explanatory text when the viewer lacks permission, instead of showing a live-looking toggle that later 403s.
- Dependencies: Stage 1 (needs `canManageFeatureFlags` in the template)
- Expected changes:
  - In `templates/pages/feature_flags.php`, extend the control block's condition to a three-way branch: (a) `canChangeFromSite() && canManageFeatureFlags` → live toggle, current behavior; (b) `canChangeFromSite() && !canManageFeatureFlags` → disabled toggle plus visible text ("Read-only — requires root-approved identity"); (c) `!canChangeFromSite()` → existing disabled/locked rendering, unchanged.
  - Apply the same `canManageFeatureFlags` guard to the "Reset to default" inline form so it isn't rendered as clickable when it would also 403.
- Verification approach:
  - As a root-approved viewer: confirm toggle and reset-form behavior is unchanged (regression check).
  - As a non-root approved viewer (or a temporary forced `canManageFeatureFlags = false` during manual testing, reverted after): confirm mutable flags show a disabled toggle with the new explanatory text and no live form is rendered.
- Risks or open questions:
  - Same open question as Stage 1: need a concrete non-root-approved test identity to verify this without guesswork.
- Canonical components/API contracts touched: `templates/pages/feature_flags.php` (control block and reset-form block); no controller/backend contract changes beyond the Stage 1 template variable.

## Stage 4
- Goal: Add automated test coverage for the three render states so the fix doesn't regress silently.
- Dependencies: Stages 1-3
- Expected changes: Extend or add tests covering: locked flag shows visible reason text; mutable flag with `canManageFeatureFlags = false` renders disabled with explanatory text and no active form; mutable flag with `canManageFeatureFlags = true` renders the live toggle (regression case).
- Verification approach: Run the project's existing test suite and confirm the new/updated tests pass alongside the full suite.
- Risks or open questions:
  - Existing test harness/conventions for controller or template-level rendering need to be identified during Step 4 before writing tests (file location, fixture pattern for viewer identity).
- Canonical components/API contracts touched: test suite only; exact file(s) TBD during implementation, no production contract changes.
