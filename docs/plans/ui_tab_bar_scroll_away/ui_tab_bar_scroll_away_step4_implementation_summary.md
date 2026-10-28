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
  - The user's real-iPhone check: bounce and Safari toolbar resizing, and whether the 8px threshold and 48px end distance feel right.
  - Deployment and merge (only when the user asks).
  - The reply dock cycle revision (remove the docked reply box) is separate and still pending.
- Dependency delta: zero.

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*
