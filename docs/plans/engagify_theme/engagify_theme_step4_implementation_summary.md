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

## Stage 3 - Page themes

**Original estimate: 0.14**
**Retrospective: 0.14**

- Changes:
  - `SiteProfileRegistry.php`: optional `pageThemes` per profile, keyed by a normalized path (no query, no trailing slash). Validation requires a leading slash, the normalized form, and a permitted theme other than `auto`. `zenmemes` maps the cookie notice thread, `/threads/thread-20261102152258-25e82c97`, to `engagify`.
  - `Application.php` records each request path and hands the renderer a closure that reads it. The builder renders many routes with one renderer, so the path is read at render time.
  - `TemplateRenderer.php` and `layout.php`: the page marks `data-page-theme` and `data-page-path`. The pre-paint script shows the page theme unless this browser has a page-exit entry for that path. The page theme's stylesheet is the initial one.
  - `theme_toggle.js`: tracks whether the page theme is showing. Any menu or cycle choice of another theme leaves it and records the page exit. `forumExitTheme()` leaves it and shows the viewer's saved theme. The page theme is never saved, and never recorded as the previous theme.
- Verification:
  - Passed: `./v3 test --smoke ThemeRegistryTest ThemeToggleScriptTest SiteProfileRegistryTest PostActionThemeTest`, 448 run, 447 passed; the one failure is the baseline `BoardListTest` failure. New tests: `SiteProfileRegistryTest::testPageThemesUseNormalizedPathsAndPermittedThemes` and `LocalAppSmokeTest::testPageThemeIsMarkedOnItsOwnPageOnly`.
  - Passed: headless Chrome check against `./v3 start`, with the local thread `/threads/root-001` mapped to Engagify for the check only (mapping removed afterwards). Fresh browser at 1280px and saved Midnight at 380px: the board keeps the viewer's theme; the thread shows Engagify before paint without saving it; Exit returns the viewer's theme without a reload and stays after reload; clearing the exit brings Engagify back; a menu choice of Word 97 leaves it and persists; no page errors. Screenshots inspected.
  - Inapplicable: `standalone_layout.php` serves only Forte pages, which have no page themes; left unchanged.
- Notes:
  - Viewers who already left a page theme fetch its stylesheet once before the pre-paint script swaps it. That costs one cached request on one page.
  - A browser that blocks storage cannot remember a page exit, so the page theme returns on its next visit.
- Estimate details: original scope as Step 3; no revision. Retrospective is a scope assessment, not measured time.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Stage 4 - Baked pages

**Original estimate: 0.05**
**Retrospective: 0.04**

- Changes:
  - No builder change: `StaticArtifactBuilder` renders through `Application::handle`, which now records each route's path.
  - New `LocalAppSmokeTest::testBakedThreadArtifactCarriesItsPageTheme`. It maps a fixture thread to Engagify, builds the release, and checks three things. The baked thread carries `data-page-theme`, the baked board does not, and the release holds the fingerprinted Engagify script and stylesheet.
- Verification:
  - Passed: `./v3 test --smoke LocalAppSmokeTest::testBakedThreadArtifactCarriesItsPageTheme`; 436 run, 435 passed; the one failure is the baseline `BoardListTest` failure.
- Notes: the deploy must rebuild artifacts for the cookie thread to carry its page theme, as the theme guide already requires for new themes. Stage 8 documents this for page themes.
- Estimate details: original scope as Step 3; no revision. Retrospective is a scope assessment, not measured time.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Stage 5 - Cookie banner, app bar, notification prompt

**Original estimate: 0.10**
**Retrospective: 0.10**

- Changes:
  - `theme-engagify.js`: three nag functions and a small toast.
    - "Open in app" bar: inserted at the top of the app shell, so it pushes the page down. "Open" answers with a toast about the app that does not exist; × removes the bar.
    - Cookie banner after 0.8s: a large "Accept all" and a small "Manage preferences", which only says preferences are saved by Accept all.
    - Notification prompt after 3s, drawn like the browser's own; Block and Allow each close it with a toast.
    - Every nag starts with an Exit control, so Exit comes first in its tab order.
  - `theme-engagify.css`: card, banner, prompt, bar and toast styles; on phones the cookie banner sits above the Exit pill.
- Verification:
  - Passed: `./v3 test --smoke ThemeRegistryTest ThemeToggleScriptTest`; the only failure is the baseline `BoardListTest` failure.
  - Passed: headless Chrome check at 1280px and 380px on `/threads/root-001`, mapped to Engagify for the check only. All three nags appear. There is no sideways scroll. Every nag button takes focus and is at least 32px tall, with Exit first. The theme menu stays clickable with nags open. Enter on each dismiss control removes its nag, and the reply box is reachable afterwards. Choosing Light during the delays leaves no Engagify element once the timers would have fired. No page errors. Screenshots inspected.
- Notes:
  - Base button rules stretched nag buttons to full width; nag buttons now set their own width and margin.
  - While open, the cookie banner covers part of the reply box. That is the joke; one click on Accept all or Exit clears it. The check confirms the reply box is reachable after dismissal, and the theme menu while nags are open.
  - Dismissals are not remembered, so nags return on the next page load. That is intended; Exit is the way out.
- Estimate details: original scope as Step 3; no revision. Retrospective is a scope assessment, not measured time.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*
