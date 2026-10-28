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

## Stage 2 - Dependents follow the visible bar

**Original estimate: 0.10**

**Retrospective: 0.08**

- Changes:
  - `public/assets/site.css`: new `--app-tabbar-offset`, equal to `--app-tabbar-height` while the bar is shown and to the safe-area inset while `data-tabbar="hidden"`. The post-send reply box (`.reply-panel-reading`) uses it.
  - `public/assets/private_message_conversation.css`: the sticky private message composer uses it.
  - `--app-tabbar-height` still sizes the bar, the `.main` padding and the account menu popovers (the bar never hides while that menu is open).
- Verification:
  - `tab_bar_scroll.mjs` extended: with the bar hidden the offset is `0px` (no inset in Chromium) and the reply box sits 8px from the screen bottom; shown, it sits at 64px (56px bar + 8px). Passes.
  - Browser tests pass: `reply_viewport`, `reply_viewport_flow`, `private_message_chat_layout`.
- Notes:
  - Deviation: the plan proposed transitioning the dependents with the bar. They move instantly instead, because `reply_viewport.js` repositions the reply box from the visual viewport and its tests measure positions right after state changes; a transition would lag both. While the bar slides away the box drops ahead of it for 0.2s; when the bar returns the box rises first and the bar slides in beneath it.
  - The private message composer was checked through the shared value only, not on a rendered conversation with the bar hidden; Stage 3 screenshots cover it.
- Estimate details: as expected.

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*

## Stage 3 - Themes, acceptance and closeout

**Original estimate: 0.13**

**Retrospective: 0.12**

- Changes:
  - No theme fixes were needed.
  - `docs/plans/ui_copy_audit_checklist.md`: entry "Phone tab bar scroll-away (ZenMemes)" (no copy change).
  - `docs/plans/ui_redesign/implementation_checklist.md`: follow-up note under Cycle 1.
  - No navigation destination changed, so `public/assets/theme-nav-icons.css` is untouched.
- Verification:
  - 13 permitted themes plus Auto at 390px with the bar shown and hidden, on the board with a mock private message conversation: hidden, the composer's bottom edge sits at the screen bottom (700px of 700px) in all 14; shown, it sits on the bar. No page errors.
  - Desktop and other sites: the script loads only with the `app` navigation slot (`AppNavigationPlacementTest` asserts it is absent with `forum`), and every new rule is scoped to `[data-shell="app"]` inside the phone media query. Other sites' pages still carry the new rules in their inlined critical CSS, where they match nothing.
  - Full suite once: 1595 run, 1589 passed, 6 failed. Five also fail on `main` (`testRepositoryArchiveDownloadFilenamesIncludeReadableTimestamp`, `PlatformDocsStaticTest`, `ReadModelBuilderTimingTest`, two `WriteApiSmokeTest` approval tests). The sixth, `WriteApiSmokeTest::testVerifiedProfileNamesWriteOneOrderedActivityRowEach`, failed on a `gpg` signing command and passed when rerun alone.
- Notes:
  - Observed, not changed: the private message composer is see-through in Vapor and Arena, whether or not the bar is shown. Candidate follow-up, together with making that composer non-sticky.
- Estimate details: as expected.

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*

## Closeout

- All three stages are complete. Whole-cycle projection **0.45** held throughout; separate **0.40 retrospective scope assessment** (stage retrospectives 0.20 / 0.08 / 0.12, not summed).
- Pending:
  - ~~The user's real-iPhone check~~ Done 2026-10-28 on production after merge (`1f44958db`): iOS Safari hides the bar as intended; iOS Firefox does not fully hide it (see [Known issue](#known-issue-ios-firefox)). Threshold and end-distance feel not yet reported.
  - Deployment and merge (only when the user asks).
  - The reply dock cycle revision (remove the docked reply box) is separate and still pending.
- Dependency delta: zero.

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*

## Known issue: iOS Firefox

Reported by the user 2026-10-28 on production. Left as is for now at the user's request; this records the proposed fix.

**Observed.** In iOS Safari the bar slides off screen. In iOS Firefox it slides down but stays visible, parked at the bottom of the visible page under Firefox's bottom address bar. It looks deliberate and is kind of nice, but it still covers content and catches taps near the address bar.

**Likely cause.** The hidden state moves the bar by its own height (`translateY(100%)`), which is only enough when the bottom of the layout viewport (where `position: fixed; bottom: 0` anchors) is at or above the bottom of the screen. Firefox for iOS draws its toolbar over the web view, and the web view, and with it the layout viewport, extends below the visible area while the toolbar is shown or collapsing. One bar height then lands the bar under the toolbar instead of off screen. Not yet confirmed: Firefox may instead report a large `env(safe-area-inset-bottom)`, which enlarges the bar and its 100% move together.

**Diagnosis first (about 0.05).**
- Add a temporary query-flag overlay (for example `?tabbar-debug=1`, never on by default) that prints `innerHeight`, `visualViewport.height` and `offsetTop`, `document.documentElement.clientHeight`, the computed `env(safe-area-inset-bottom)` and the bar's `getBoundingClientRect()` in both states, while the Firefox toolbar is expanded and collapsed.
- Remote Web Inspector works only if Firefox's web view is inspectable, so the overlay is the dependable route.
- Gives the numbers that decide between the two fixes below and serves as the regression check on a real phone.

**Fix (about 0.10 to 0.15), two layers:**
1. **Make the hidden bar invisible, whatever the geometry.** Pure CSS: add `visibility: hidden` to the hidden state with a delayed transition (`transition: transform 0.2s ease, visibility 0s linear 0.2s`, and `visibility 0s` with no delay when shown). The bar still slides, then disappears and stops taking taps; under reduced motion both happen at once. Cost: a hidden bar is skipped by Tab, so the "show on focus" path only applies while it is visible; keyboard users get it back by scrolling up or reaching a page end, as everyone else does. This layer alone removes the bar from under Firefox's toolbar.
2. **Make the slide end off screen.** In `tab_bar_scroll.js`, when hiding, measure how far the bar's top sits above the bottom of the visible area (`visualViewport.offsetTop + visualViewport.height - bar.getBoundingClientRect().top`) and set it as `--app-tabbar-hide-distance` on `.shell`; the CSS becomes `translateY(max(100%, var(--app-tabbar-hide-distance, 100%)))`. Re-measure on `visualViewport` `resize` while hidden. Without this the slide visibly stops short before layer 1 hides the bar.
   - Dependents: `--app-tabbar-offset` while hidden stays at the safe-area inset; if diagnosis shows Firefox's inset is the toolbar height, use `0px` there instead so the reply box and message composer sit right above the toolbar.

**Tests.** Extend `tests/browser/tab_bar_scroll.mjs`: computed `visibility` is `hidden` after the transition and `visible` when shown; with the hidden bar, `document.elementFromPoint` at the bar's last position does not return the bar; a stubbed `visualViewport` shorter than `innerHeight` produces a hide distance larger than the bar height. Emulation cannot reproduce Firefox's toolbar, so acceptance stays the user's check in iOS Firefox and iOS Safari.

**Recommendation.** Ship layer 1 on its own first: it is small, CSS-only, and fixes the visible symptom in every browser. Add layer 2 only if the slide stopping short is noticeable.

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*
