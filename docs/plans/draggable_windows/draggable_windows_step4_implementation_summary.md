> **Feature plan:** [Step 1](./draggable_windows_step1_solution_assessment.md) · [Step 2](./draggable_windows_step2_feature_description.md) · [Step 3](./draggable_windows_step3_development_plan.md) · [Step 4](./draggable_windows_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./draggable_windows_debug_log.md)), inherited from Step 3.

Effort study: `railspike-effort` / `v3/draggable_windows`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled, inherited from Step 3.

Status: Step 4 in progress on `feat/draggable_windows`, branched from `origin/main` at `4a6c8bd`. Planning commit `44dd8b3`.

**Cycle estimate: 0.70**

# Step 4: Implementation Summary — Draggable windows

Step 4 entry projection: 0.70, Step 3 reaffirmed. Main moved from `c98b5221` to `4a6c8bd` between Step 1 and Step 4; none touched Bubblegum, window cards or the Preferences rows we extend. Scope v2, R1, P2; Claude single exposed rater, 2026-11-17 UTC.

Stage estimates below are copied from Step 3 (originals rated 2026-11-17 by Claude); copying them is not a new rating.

Ledger note: Step 3 said this cycle's ledger row would go into the planning commit, but Step 4's gate keeps the ledger out of it. The row goes into the Stage 1 commit instead.

## Stage 1 - Flag, stored collapses and before-paint restore

**Original estimate: 0.12**
**Retrospective stage work: 0.12**

- Status: complete.
- Changes:
  - `FORUM_DRAGGABLE_WINDOWS_ENABLED` in `FeatureFlagRegistry.php`: off by default, site-mutable, in the Experience group.
  - `windowControlsStorageKey` and `collapsedWindowsStorageKey` in `BrowserRuntimeProfile.php`, namespaced per site like reading margins.
  - `TemplateRenderer.php` passes `draggableWindowsEnabled` to the layout. `layout.php`'s pre-paint script sets `data-window-controls="on"` unless this browser turned controls off, and writes a style that collapses saved post IDs. IDs must match `[A-Za-z0-9_.:-]+`, so a stored value can't inject CSS.
  - `theme-bubblegum.css`: the `[data-window-collapsed]` look (title bar only, contents kept in the page).
  - Fix found on the way: `content-interactions.css` hides `.meta-deferred::before` (the hover time) on every post that starts a run. In Bubblegum that pseudo-element is the title bar, so run-starting posts, including every thread's root post, had no title bar on main. Bubblegum now keeps `opacity: 1` on it. Confirmed on unmodified `4a6c8bd` (root title bar opacity 0) before fixing.
  - `tests/FeatureFlagEvaluatorTest.php`: the new flag in both expected flag lists.
  - New `tests/browser/draggable_windows.mjs`.
  - Ledger: this cycle's row in `effort_estimation_trial.md`.
- Verification:
  - `node tests/browser/draggable_windows.mjs`: passes. A saved root collapse is 30px tall at first parse and after load (no jump); other posts stay open; contents stay in the page; every Bubblegum title bar has opacity 1; Gloss, controls Off, and flag off all ignore the saved collapse; an invalid stored ID is dropped; no page errors.
  - `./v3 test --smoke FeatureFlagsBehaviorTest FeatureFlagEvaluatorTest SiteProfileRegistryTest PreferencesPageTest ThemeRegistryTest`: 491 run, 491 passed. The first run failed `FeatureFlagEvaluatorTest::testRegistryListsCurrentPublicFlags` because the new flag wasn't in its expected lists; fixed in this stage.
  - Screenshot of the collapsed root post at 1280px checked by eye.
- Notes:
  - The pre-paint style repeats the two collapsed rules from `theme-bubblegum.css`; both places say so. Stage 2's script removes the style once it marks posts itself.
  - The hover time on run-starting posts was already lost in Bubblegum (the theme replaces its text with the title bar), so the opacity fix takes nothing away.
- Estimate details: original scope as Step 3; uncertainty was the before-paint check, which held. No revisions.

## Stage 2 - Bubblegum theme script and working collapse

**Original estimate: 0.18**

- Status: not started.

## Stage 3 - Active window and pop

**Original estimate: 0.07**

- Status: not started.

## Stage 4 - Float mode, dragging and Reset

**Original estimate: 0.20**

- Status: not started.

## Stage 5 - Preferences row

**Original estimate: 0.06**

- Status: not started.

## Stage 6 - Docs and final review

**Original estimate: 0.07**

- Status: not started.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
