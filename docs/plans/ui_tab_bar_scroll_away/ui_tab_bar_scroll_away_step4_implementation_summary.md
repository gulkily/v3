> **Feature plan:** [Step 1](./ui_tab_bar_scroll_away_step1_solution_assessment.md) · [Step 2](./ui_tab_bar_scroll_away_step2_feature_description.md) · [Step 3](./ui_tab_bar_scroll_away_step3_development_plan.md) · [Step 4](./ui_tab_bar_scroll_away_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/ui_tab_bar_scroll_away` ([ledger](../effort_estimation_trial.md#cycle-v3ui_tab_bar_scroll_away)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.45**

## Step 4 entry

- Step 3 approved 2026-10-28 ("Approved Step 3"). Branch `feat/ui-tab-bar-scroll-away` from `main` at `7ef735dd2`; planning commit `5357102ec` (Steps 1–3 moved into this folder, README index entry).
- Whole-cycle projection **0.45 reaffirmed** at entry; scope v1 / R1 / P2 unchanged. Claude, single exposed rater. Stage originals are in the [Step 3 plan](./ui_tab_bar_scroll_away_step3_development_plan.md); copied below without re-rating.
- The ledger on this branch lacks the unmerged `v3/ui_thread_reply_dock` section; it was not copied here to avoid duplicating it before that branch merges.

## Stage 1 - Bar hides on scroll down and returns

**Original estimate: 0.22**

**Retrospective: 0.20**

- Changes:
  - New `public/assets/tab_bar_scroll.js`, loaded with `defer` only when the navigation slot is `app` (`layout.php`, `TemplateRenderer::tabBarScrollScriptPath`). At phone width it sets `data-tabbar="hidden"` on `.shell` after a scroll down of 8px or more and removes it on scroll up, within 48px of either page end (scroll position clamped to the page range, so iOS bounce counts as an end), when focus enters the bar, and while the account menu is open. On wider screens and without the script the attribute is never set.
  - `public/assets/site.css` (phone media query, critical CSS): the bar slides with `transform: translateY(100%)` and a 0.2s `transform` transition; no transition under `prefers-reduced-motion: reduce`. The shown state keeps `transform: none`, because any transform would make the account menu's fixed theme popovers position against the bar.
  - `.main` keeps its bottom padding, so hiding causes no layout shift and the last content is uncovered when the bar returns at the end.
- Verification:
  - New `tests/browser/tab_bar_scroll.mjs` (390px touch, 1280px, JavaScript off): hide on scroll down, small jitter ignored, show on scroll up, simulated bounce past both ends, page end, focus, account menu open, reduced motion, sliding by default, desktop never hides, no-JavaScript bar stays. Passes.
  - `php tests/run.php AppNavigationPlacementTest`: 7 run, 7 passed (now asserts the script loads in the app shell and not with the `forum` slot).
  - Browser tests pass: `nav_active_theme_styles`, `lcd_palettes` (account menu popovers), `reply_viewport`.
- Notes:
  - The test's bounce simulation stubs `window.scrollY`; deleting the stub left Chromium reporting the stubbed value, so the test restores an explicit getter instead.
  - Threshold (8px) and end distance (48px) are first guesses for the user's iPhone check.
- Estimate details: smaller than planned because the account menu needed no script changes; iOS uncertainty unchanged.

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*
