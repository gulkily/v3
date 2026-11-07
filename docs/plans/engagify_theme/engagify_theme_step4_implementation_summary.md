> **Feature plan:** [Step 1](./engagify_theme_step1_solution_assessment.md) · [Step 2](./engagify_theme_step2_feature_description.md) · [Step 3](./engagify_theme_step3_development_plan.md) · [Step 4](./engagify_theme_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/engagify_theme`; [host ledger](../effort_estimation_trial.md#cycle-v3engagify_theme). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

Cycle estimate: 0.80

## Step 4 entry

2026-11-07T12:03:35Z. Step 3 approved by the maintainer ("approved step 3"). Branch `feat/engagify_theme` from `main` at `f343b626`; planning commit `fe15dec`. Whole-cycle projection **0.80** reaffirmed from Step 3, same scope v5, R1/P2, single exposed Claude rater. Stage-work originals copied from Step 3, not re-rated: 0.12, 0.10, 0.14, 0.05, 0.10, 0.12, 0.12, 0.10. They do not add up.

Baseline: the smoke set on `main` has one failure, `BoardListTest::testPinnedThreadsComeFirstAndLatestThreadsArePaged` (a `rel="prev"` pagination assertion). It is unrelated to themes and is reported as a baseline failure below.

The `v3/theme_directive` plans and earlier drafts stay on branch `plan/engagify_theme`. Links to them became plain text in the planning commit.

## Stage 1 - Theme scripts

**Original estimate: 0.12**
**Retrospective: 0.10**

- Changes:
  - `ThemeRegistry.php`: entries may set `script`; new `scriptPaths()` beside `stylesheetPaths()`.
  - `TemplateRenderer.php` passes fingerprinted theme script paths; `layout.php` exposes them as `window.forumThemeScriptPaths` before paint.
  - `theme_toggle.js`: a small `window.forumThemeScripts` registry. A theme script calls `register(name, { start, stop })`. Every resolved theme change stops the old script and loads or starts the new one. A thrown start or stop is logged and does not block switching.
  - New `tests/browser/theme_scripts.cjs` and `ThemeToggleScriptTest`, which also wires the existing unwired `theme_cycle.cjs` into the PHP runner.
- Verification:
  - Passed: `./v3 test --smoke ThemeRegistryTest ThemeToggleScriptTest`, 436 run, 435 passed; the one failure is the baseline `BoardListTest` failure.
  - Passed: `node tests/browser/theme_scripts.cjs` (load once, start only while active, stop on switch, restart without reload, failing start) and `theme_cycle.cjs`.
  - Pending to Stage 2: a real-browser start/stop check, because no theme declares a script until Engagify exists.
- Notes: reuse decisions follow Step 3. The registry lives in `theme_toggle.js`, not the inline pre-paint script, so baked pages gain one variable only. The script starts loading when `theme_toggle.js` runs, after first paint; Engagify's nags are delayed on purpose, so this costs nothing visible.
- Estimate details: original scope as Step 3; uncertainty was low to medium; no revision. Retrospective is a scope assessment, not measured time.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Stage 2 - Engagify and Exit

**Original estimate: 0.10**
**Retrospective: 0.12**

- Changes:
  - `ThemeRegistry.php`: `engagify` entry with `script`; `SiteProfileRegistry.php`: permitted on `zenmemes` only.
  - `theme-engagify.css`: glossy startup palette, swatch, a "NEW" badge on its menu row, rounded cards, and shared nag styles scoped under the theme.
  - `theme-engagify.js`: registers with the Stage 1 registry; each nag is a function given a context that tracks its elements and timers, so stop removes everything. This stage adds the always-visible Exit pill.
  - `theme_toggle.js`: remembers the theme before each change (`<storage key>-previous`) and exposes `window.forumExitTheme()`, which returns to it.
  - `LocalAppSmokeTest`: allow-list and menu option assertions include `engagify`.
- Verification:
  - Passed: `./v3 test --smoke ThemeRegistryTest ThemeToggleScriptTest SiteProfileRegistryTest PostActionThemeTest`, 446 run, 445 passed; the one failure is the baseline `BoardListTest` failure.
  - Passed: `python3 -I tests/theme_contrast_audit.py`, Engagify OK after raising `--line` from 1.31:1 to 3.5:1.
  - Passed: headless Chrome check against `./v3 start` at 1280px and 380px, from a saved Word 97 theme. Engagify from the menu shows Exit; Exit returns Word 97 without a reload and leaves no Engagify element; reload keeps Word 97; reload while on Engagify restarts the script; choosing Light afterwards removes every Engagify element; no page errors. Screenshots inspected.
  - This stage also completes Stage 1's pending real-browser start/stop check.
- Notes:
  - First check found the Exit pill covering the phone tab bar. The tab bar offset is a custom property scoped to the app shell, so nags now mount inside `[data-shell]` and sit at z-index 24, under the tab bar and account menu (25).
  - Base button rules outranked the nag classes, so nag styles are scoped under `:root[data-theme="engagify"]`.
- Estimate details: original scope as Step 3; no revision. Retrospective is slightly higher because of the two layout fixes; it is a scope assessment, not measured time.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*
