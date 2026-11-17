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
**Retrospective stage work: 0.20**

- Status: complete.
- Changes:
  - New `public/assets/theme-bubblegum.js`, registered with `forumThemeScripts` like `theme-engagify.js`. Start runs only when `data-window-controls` is set. It appends a title bar (label and collapse button) as each `.card`'s last child, so no first-child spacing moves. Clicks and double-clicks on the title bar toggle collapse. A post's collapse is saved by `data-post-id`, newest 500 kept. A `MutationObserver` decorates windows added later. Stop removes every title bar, mark, listener and the observer. `window.forumWindowControls.restart()` is for Stage 5.
  - `ThemeRegistry.php`: Bubblegum gets `'script' => true`. `TemplateRenderer.php` leaves Bubblegum's script out of `forumThemeScriptPaths` when the flag is off, so flag-off pages load nothing new.
  - `theme-bubblegum.css`: the drawn three boxes give way to the real button on decorated windows; title bar strip, label (shown when collapsed: a post's author, otherwise the first heading), and a pixel underscore or window glyph with a 44px touch target. The visitor intro keeps its close box at the end of the bar. The collapsed rule spares the title bar and the intro's close box; `layout.php`'s pre-paint copy matches.
  - `tests/browser/draggable_windows.mjs` extended.
- Verification:
  - `node tests/browser/draggable_windows.mjs`: passes. One title bar per window on the thread, Tools, Preferences and About; click, Enter, Space, and double-click on the title bar; `aria-expanded` and the "Window: <label>" name; saved after reload with the pre-paint style removed; label equals `data-author`; a composer keeps "draft text"; Gloss removes every title bar and mark, and Bubblegum restores the saved collapse; tap at 380px collapses with no sideways scroll; flag off offers no Bubblegum script and adds no title bars.
  - `node tests/browser/theme_scripts.cjs`: passes.
  - `./v3 test --smoke ThemeRegistryTest FeatureFlagEvaluatorTest`: 475 run, 475 passed.
  - Screenshots at 1280px and 380px checked by eye. The first phone shot showed Bubblegum's general hover and press styles on the tapped button; the button's selector now outranks them.
- Notes:
  - The board (`/`) shows "Temporarily Unavailable" with the `parity_minimal_v1` fixture on unmodified `4a6c8bd` too, so the test checks Tools, Preferences and About instead. The visitor intro only shows on the board, so its title bar gets a screenshot check in Stage 6 on a fuller fixture.
- Estimate details: original scope as Step 3. Retrospective 0.20, a little over: the title bar CSS fight with the general button rules and the fixture's board took an extra pass. No revision was recorded before the work.

## Stage 3 - Active window and pop

**Original estimate: 0.07**
**Retrospective stage work: 0.08**

- Status: complete.
- Changes:
  - `theme-bubblegum.js`: pointerdown or focusin inside a window marks it `data-window-active` and sets `data-window-has-active` on the root. Expanding a collapsed window sets `data-window-pop`, cleared on `animationend`. Stop clears all three.
  - `theme-bubblegum.css`: once a window is active, the other title bars fade to `#f6b4d3`; a collapsed, inactive window's label turns berry. A 180ms `bubblegum-window-pop` scale on expand, off under `prefers-reduced-motion: reduce`.
  - `tests/browser/draggable_windows.mjs` extended.
- Verification:
  - `node tests/browser/draggable_windows.mjs`: passes. Clicking a window activates only it; the active bar stays `rgb(228, 91, 156)` and the others fade; tabbing in activates; a faded label keeps at least 4.5:1 contrast; expanding starts `bubblegum-window-pop` once, and reduced motion starts none.
  - Screenshot of active and faded title bars at 1280px checked by eye.
- Notes:
  - Found while testing: a post that starts a run shows its byline while something inside it has focus (`content-interactions.css`). Moving focus out of it by pointer shrinks it, so a button just below can move between pointerdown and pointerup and the click lands on the card instead. This applies to every button below such a post, not only ours. Not changed here, since it's outside this cycle's scope; the test uses the keyboard for those steps. Worth its own fix.
- Estimate details: original scope as Step 3; retrospective 0.08, a little over for tracking down the lost click. No revisions.

## Stage 4 - Float mode, dragging and Reset

**Original estimate: 0.20**
**Retrospective stage work: 0.20**

- Status: complete.
- Changes:
  - `theme-bubblegum.js`: with `(pointer: fine) and (min-width: 640px)`, start adds Float and Reset buttons in a fixed corner bar at the bottom left. Float sets `data-window-float` on the root. A primary-button mouse or pen press on a title bar (not its button) drags the window with pointer capture, moving it with the CSS `translate` property so the pop's `transform` doesn't fight it. Arrow keys on a window's title bar button move it 10px, or 50px with Shift. Reset clears every position; Float off clears them too. Positions live only in the page. Stop removes the corner bar, positions and Float mode.
  - `theme-bubblegum.css`: grab and grabbing cursors, the active window on top in Float mode, and the corner bar's look.
  - `tests/browser/draggable_windows.mjs` extended.
- Verification:
  - `node tests/browser/draggable_windows.mjs`: passes. No drag before Float; a drag moves the reply by (150, 100) within 2px; dragging across text inside a window selects it and moves nothing; ArrowRight and Shift+ArrowDown move by (10, 50); Reset restores both windows and keeps Float on; Float off restores them; a link inside a window is followed in Float mode; a reload starts with Float off; switching to Gloss removes the corner bar, positions and Float mode; no Float toggle at 380px with touch.
  - `node tests/browser/theme_scripts.cjs`: passes.
  - Screenshots at 1280px with Float on and a window dragged, checked by eye.
- Notes:
  - Placement (left open in Step 3 for screenshot review): beside the wordmark, Float and Reset ran into the Board tab at 1280px. They moved to a fixed corner bar at the bottom left, styled like a taskbar's start area.
  - Small deviation from Step 3's wording "Reset returns every window and turns Float mode off": Reset keeps Float on, since turning Float off already puts windows back. Two buttons doing the same thing would add nothing. Flagged for the maintainer.
- Estimate details: original scope as Step 3; retrospective 0.20, on estimate. No revisions.

## Stage 5 - Preferences row

**Original estimate: 0.06**
**Retrospective stage work: 0.06**

- Status: complete.
- Changes:
  - `ToolsPageController.php` passes `draggableWindowsEnabled`. `preferences.php` shows a "Window controls" On/Off select under Reading margins when the flag is on; its inline script shows the saved value before paint.
  - `preferences.js`: Off saves `'off'` and removes `data-window-controls`; On removes the key and sets the attribute. Either way it calls `forumWindowControls.restart()`, so Bubblegum's buttons go or return without a reload. "Reset all preferences" also clears the window controls and collapsed-posts keys.
  - Tests: `PreferencesPageTest` (row shown with the flag on, left out with it off), `tests/browser/preferences.mjs` (default On, saved Off shown before the page script, On removes the key, Off survives reload, Reset all clears both keys), `tests/browser/draggable_windows.mjs` (Off removes every title bar and the Float corner at once and after reload; On brings them back).
- Verification:
  - `node tests/browser/draggable_windows.mjs`: passes.
  - `node tests/browser/preferences.mjs`: passes, including the no-overflow check in seven themes at 390px and 1280px.
  - `./v3 test --smoke PreferencesPageTest FeatureFlagEvaluatorTest`: 477 run, 477 passed.
  - Preferences in Bubblegum at 1280px checked by eye.
- Notes: none.
- Estimate details: original scope as Step 3; retrospective 0.06, on estimate. No revisions.

## Stage 6 - Docs and final review

**Original estimate: 0.07**

- Status: not started.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
