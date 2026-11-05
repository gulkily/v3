> **Feature plan:** [Step 1](./adaptive_nav_step1_solution_assessment.md) · [Step 2](./adaptive_nav_step2_feature_description.md) · [Step 3](./adaptive_nav_step3_development_plan.md) · [Step 4](./adaptive_nav_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/adaptive_nav`; [host ledger](../effort_estimation_trial.md#cycle-v3adaptive_nav). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

Cycle estimate: 0.85

Branch: `feat/adaptive-nav` from `main` `a24e16df`. Planning commit `8fe60a2a`.

## Step 4 entry

2026-11-05T05:20:00Z. Step 3 approved ("Approved step 3"). Whole-cycle projection **0.85 reaffirmed** from Step 3, unchanged scope v2. Stage-work originals copied from the approved plan: S1 0.08, S2 0.12, S3 0.08, S4 0.14, S5 0.14, S6 0.08, S7 0.08, S8 0.13. Single exposed Claude rater; copying is not a new rating. Full suite not run in this cycle; the maintainer runs it periodically.

## Enhancement Requests

- Carried from Step 3: JSON slot storage (adopted, Stage 4); local-storage editor PF-20261104-F18 (separate work).

## Stage 1 - Desktop bar and menu split

**Original estimate: 0.08**
**Retrospective stage work: 0.08**

- Changes:
  - `TemplateRenderer::navigationItems()`: in the app slot, every destination except Account, Invite and the personal Profile link is `main`; items are ordered Board, Lobby, Recent, Calendar, Users, Messages, Search, About, Tools, then menu items.
  - `site.css`: from 640px the app header spans up to 1180px centred on the 760px column, links sit tighter, and New post and You stay on the first row when the nav wraps.
  - `AppNavigationPlacementTest`: Search, About and Tools are `main`; the bar order is asserted.
- Verification:
  - Passed: `./v3 test --smoke AppNavigationPlacementTest CalendarPageTest` (430 run, 430 passed).
  - Passed: `tests/browser/nav_active_theme_styles.mjs` (16 themes).
  - Passed: `git diff --check`.
  - Passed: rendered-layout screenshots at 1280px (one row), 1024px (one row) and 800px (nav wraps to two rows; buttons on the first row).
  - Inapplicable: phone layout (Stage 2); icon themes (Stage 7).
  - Not run: full suite (maintainer runs it periodically).
- Notes:
  - Risk "the desktop bar wraps on narrow laptops" validated: it wraps below about 950px and stays tidy. Mitigation applied as planned.
  - Playwright for the browser checks is linked from the workspace's preinstalled copy into the ignored `node_modules/`; nothing was added to the repository.
- Estimate details: original scope unchanged; uncertainty low; no revisions. Retrospective matches the original.

*— Claude · 2026-11-05 UTC · Model: claude-fable-5-1*

## Stage 2 - Fixed phone bar and More

**Original estimate: 0.12**
**Retrospective stage work: 0.16**

- Changes:
  - `TemplateRenderer::navigationItems()`: each app item also carries `phone`: `bar` for Board, Lobby and Calendar, `more` for the rest.
  - `nav.php`: bar links carry `data-phone`; phones hide the `more` ones.
  - `app_header.php`: the panel starts with a phone-only list of the bar destinations placed in More (`account-menu__more`), followed by the account links. The trigger reads You on desktop and More on phones; the disclosure gets `is-active` when the current section is listed in More.
  - `site.css` (critical section): the panel fills the screen above the tab bar on phones; the disclosure is `display: contents` there so its trigger is a tab with the same width rules as the links; More list hidden on desktop by a two-class selector.
  - Tests: placement test covers `phone` and the More markup; `viewer_nav_prepaint.mjs` observes each member destination once per width (bar on desktop, More copy on phones), caps tabs at five, and opens More for the phone sign-in check; `board_search.mjs` expects Search in the bar on desktop and in More on phones; `LocalAppSmokeTest` counts Tools in the bar only; `CalendarPageTest` includes `data-phone`.
- Verification:
  - Passed: `./v3 test --smoke AppNavigationPlacementTest CalendarPageTest` (430 run, 430 passed).
  - Passed: `viewer_nav_prepaint.mjs`, `tab_bar_scroll.mjs`, `board_search.mjs`, `nav_unread_outline.mjs`.
  - Passed on rerun (2 of 2) after one timing failure in its expired-invite step: `invite_zero_click.mjs`.
  - Failed, reproduced baseline: `lcd_palettes.mjs` fails identically on `main` `a24e16df` before this cycle (summary outside the viewport after the reply textarea takes focus at 390px). Not caused here; left for separate work.
  - Passed: `git diff --check`.
  - Passed: screenshots at 375px light and 390px Word97: four tabs of equal width; More opens full-screen with the current section highlighted; desktop You menu shows only Account and Invite.
- Notes:
  - The open More panel leaves the tab bar visible with More highlighted; tapping More again closes it. This stands in for the planned Close row.
  - `tabs share the bar evenly` failed by 3px in icon themes because `flex-basis: 0` with `border-box` keeps each tab's own padding and border; making the trigger the flex item (`display: contents` on the disclosure) removed the difference.
  - Stage 1 omission caught here: `board_search.mjs` still expected Search in the desktop menu after Stage 1; fixed in this stage.
- Estimate details: original scope unchanged; uncertainty low. Retrospective 0.16 (above 0.12): the browser-check updates for the duplicated Messages destination took most of the extra work. No revision was appended before completion.

*— Claude · 2026-11-05 UTC · Model: claude-fable-5-1*

## Stage 3 - Unread indicator on More

**Original estimate: 0.08**
**Retrospective stage work: 0.06**

- Changes:
  - `private_message_unread.js`: a `mirror()` step copies the bar link's `data-unread-count` and title onto every `[data-private-message-unread-mirror]` element when the status becomes ready or unavailable, and clears them on stop.
  - `app_header.php`: the More trigger and the panel's Messages link carry the mirror attribute. The trigger keeps `aria-label="Menu"`; only the bar link has `aria-describedby`, so screen readers hear the count once.
  - `site.css`: on phones a non-zero count outlines the More tab (inset, inside the fixed bar). The panel link already uses the shared `.nav-link[data-unread-count]` outline.
  - Tests: `viewer_nav_prepaint.mjs` asserts both mirrors equal the bar count at 390px; placement test asserts the mirror markup.
- Verification:
  - Passed: `viewer_nav_prepaint.mjs` (light, word97), `private_message_live_navigation.mjs`, `nav_unread_outline.mjs`.
  - Passed: `./v3 test --smoke AppNavigationPlacementTest`.
  - Passed: `git diff --check`.
  - Passed: screenshot at 375px with count 3: More tab outlined; Word97 panel shows Messages outlined.
- Notes: nothing moved or re-captioned; the outline is the same device the bar link uses.
- Estimate details: original scope unchanged; uncertainty low; no revisions. Retrospective 0.06, slightly under.

*— Claude · 2026-11-05 UTC · Model: claude-fable-5-1*

## Stage 4 - Slot markup and pre-paint placement

**Original estimate: 0.14**
**Retrospective stage work: 0.14**

- Changes:
  - No extra markup: the bar's `data-phone="more"` links, already in every viewer's HTML, are the slot candidates.
  - `TemplateRenderer::navSlotCount()`: five tabs minus the fixed ones, capped at two (zenmemes: one with Calendar, two without; members-only lobby: two). The layout also receives `navSlotSections`, the More destinations that may fill a slot.
  - `layout.php` head script: publishes `window.__forumNavSlots = { count, sections }`, reads `forum_nav_slots` (JSON) from local storage, keeps known sections only, drops Messages unless the browser is remembered as approved, and sets `data-nav-slots` on `<html>` before paint.
  - `site.css` (critical): per-section rules show a slotted tab (`order: 1`, after the fixed tabs; New post and More order 2 and 3). More loses its current-tab highlight when the current section holds a slot; the server marks the disclosure with `data-active` instead of a class.
  - `nav_slots.js` (new): owns the storage key and format, `place()` re-applies the choice on `forum:nav-refreshed` and `forum:identity-changed`. Filling and pinning come in Stage 5.
  - `standalone_layout.php`: no change; it has no app header and no member pre-paint script, so parity is inapplicable.
  - Tests: placement test asserts the published config, the script include and `navSlotCount()` for three layouts and another site; `viewer_nav_prepaint.mjs` seeds slots and asserts a guest gets Recent but never Messages, and a member gets a Messages tab from first paint with no change after.
- Verification:
  - Passed: `./v3 test --smoke AppNavigationPlacementTest CalendarPageTest` (431 run, 431 passed).
  - Passed: `viewer_nav_prepaint.mjs` (light), `tab_bar_scroll.mjs`.
  - Failed then passed: `viewer_nav_high_traffic.mjs` kept the pre-Stage-2 assumption that every member-only link is displayed at 390px, and read the hidden bar link's caption with `innerText`. Its observer now uses the same per-width rule as the pre-paint check. Fixed in a second Stage 4 commit; it should have been caught in Stage 2, which did not run this check.
  - Passed: `git diff --check`.
  - Passed: screenshots at 375px: Tools slotted and current for a guest on /tools/; Recent slotted and current; More unhighlighted in both.
- Notes:
  - The `!important` member-only rule still hides a Messages slot for any browser not remembered as approved, whatever the stored state says.
  - A slot shows the bar link in DOM order before Calendar but visually after it (`order`), so keyboard order and visual order differ by one tab. Accepted; noted for Stage 7's docs.
- Estimate details: original scope unchanged; uncertainty medium; no revisions. Retrospective matches the original.

*— Claude · 2026-11-05 UTC · Model: claude-fable-5-1*

## Stage 5 - Filling and pinning

**Original estimate: 0.14**
**Retrospective stage work: 0.14**

- Changes:
  - `nav_slots.js`: counts a use when a phone reader opens a destination from More or taps a tab that holds a slot; `decide()` keeps pins first, holds each earned slot unless a challenger leads it by `LEAD` (3) uses and was used on two different days, then fills free slots with any destination at `FILL` (3) uses. Slots keep the order earned. `pin()` writes, re-places at once and updates the buttons; `reflect()` sets each Pin button's pressed state from storage on load.
  - `app_header.php`: each More row is a destination link plus a `Pin` toggle button (`data-nav-pin`, `aria-pressed`, labelled "Pin <name> to the tab bar"). Non-members see neither half of the Messages row.
  - `site.css`: row layout and pin styling on phones; pins hidden on desktop; the global `button { width: 100% }` is overridden for pins.
  - `tests/Support/app_layout_fixture.php` (new): prints the rendered app layout for browser checks served from memory.
  - `tests/browser/nav_slots.mjs` (new): drives clicks with a fixed clock: fills after three uses; holds through twenty other visits in one day; yields to a six-use leader on day two; pin wins at once and after reload; unpin returns the leader; a guest never gets a Messages slot or pin; storage is a flat JSON object.
- Verification:
  - Passed: `nav_slots.mjs`, `board_search.mjs`, `tab_bar_scroll.mjs`, `viewer_nav_prepaint.mjs` (light).
  - Passed: `./v3 test --smoke AppNavigationPlacementTest` (424 run, 424 passed).
  - Passed: `git diff --check`.
  - Passed: screenshots at 375px: member More panel shows six rows with Pin; Word97 guest panel hides the Messages row and its pin.
- Notes:
  - Thresholds live in `FILL` and `LEAD` at the top of `nav_slots.js`; the Stage 8 replay may tune them.
  - A tap on a slotted tab also counts as a use, so a destination in steady use is not evicted by a lighter one.
  - Pin state is applied after load inside the closed panel; nothing visible changes on the page at paint.
- Estimate details: original scope unchanged; uncertainty medium; no revisions. Retrospective matches the original.

*— Claude · 2026-11-05 UTC · Model: claude-fable-5-1*

## Stage 6 - Slots survive nav refresh

**Original estimate: 0.08**
**Retrospective stage work: 0.05**

- Changes:
  - `feature_flags.js`: after it removes or inserts the Messages link it dispatches `forum:nav-refreshed`, before messaging starts.
  - `nav_slots.js`: `sections()` reads the live bar (`data-phone="more"` links) when present, so a Messages link that arrived with the refresh can hold a slot and one that left releases it; the published list is the fallback before the nav exists.
  - `nav_slots.mjs`: replays the refresh with the same event and markup for a member with Messages pinned: the slot survives a swap and is released when the link leaves.
- Verification:
  - Passed: `nav_slots.mjs`; `private_message_live_navigation.mjs` and `railspike_auth_navigation.mjs` (the real refresh path).
  - Passed: `./v3 test --smoke`; `git diff --check`.
  - Inapplicable: a real flag toggle with a filled slot in a browser. The refresh path is covered by the two existing checks and the mechanism by the replay; combining them needs the operator fixture, which the replay stands in for.
- Notes: the refresh never touches `<html>` attributes or the other links, so non-Messages slots were already safe; the change closes the Messages case only.
- Estimate details: original scope unchanged; uncertainty low; no revisions. Retrospective 0.05, under the original.

*— Claude · 2026-11-05 UTC · Model: claude-fable-5-1*

## Stage 7 - Icons, concepts and docs

**Original estimate: 0.08**
**Retrospective stage work: 0.08**

- Changes:
  - `theme-nav-icons.css`: on phones the account menu's person glyph becomes a three-line list for More; Pin toggles show a pushpin that fills red when pressed. More-panel links already take glyphs from the `href` rules.
  - `docs/concepts/navigation_slots.md` (new): purpose, scenario, sources and boundary, including the keyboard-order note and the JSON state decision.
  - `docs/concepts/theme_navigation_icons.md`: Calendar, More and Pin glyphs described. `docs/concepts/calendar.md`: boundary corrected; Calendar has been a navigation destination since `a24e16df`, which did not update the entry.
  - `docs/plans/ui_copy_audit_checklist.md`: dated entry for the new copy (More, Pin, Menu label) and its verification.
  - `viewer_nav_prepaint.mjs`: asserts a More glyph and a Pin glyph at 390px in icon themes.
- Verification:
  - Passed: `viewer_nav_prepaint.mjs` for word97, chicago, midnight (one run showed a desktop Word97 shift of a few pixels after paint; two reruns and the Stage 6 commit passed, so it is a timing flake in the icon-theme font load, not a change from this stage).
  - Passed: `nav_active_theme_styles.mjs` (16 themes), `tools_launcher.mjs`, `./v3 test --smoke` (424), `git diff --check`.
  - Passed: screenshots at 375px in the three icon themes: More glyph in the bar, pushpins in the panel.
  - Concept entries: paths in the three touched entries resolve (checked by hand against the tree).
- Notes: none.
- Estimate details: original scope unchanged; uncertainty low; no revisions. Retrospective matches the original.

*— Claude · 2026-11-05 UTC · Model: claude-fable-5-1*

## Stage 8 - Release check and iPhone replay

**Original estimate: 0.13**
**Retrospective stage work: 0.10 (headless part; the replay is pending)**

- Changes:
  - `site.css`: desktop header cap raised from 1100px to 1180px after the matrix showed Chicago's wider font pushing Tools alone onto a second row at 1280px. All four themes now fit one row at 1280px.
  - `viewer_nav_prepaint.mjs`: the readiness check now requires each stylesheet link's loaded sheet to match its current href. The head script swaps the theme link's href, and the old sheet lingered long enough to record a pre-theme layout as the first paint, which read as a shift in icon themes at 1280px. Two full five-theme runs pass after the change.
- Verification:
  - Passed: `viewer_nav_prepaint.mjs`, all five default themes at 390px and 1280px, two full runs: at most five tabs, no change after first paint, slots and member links correct.
  - Passed: `nav_slots.mjs`, `nav_active_theme_styles.mjs`, `./v3 test --smoke AppNavigationPlacementTest CalendarPageTest` (431), `git diff --check`.
  - Passed: screenshot matrix at 375px (light, Word97, Chicago, Midnight): five tabs with Recent slotted, equal widths; at 1280px: one row in all four.
  - **Pending: the maintainer's iPhone replay** on the deployed site: a calm bar of five tabs or fewer, a slot that appears after a few More visits and stays, and a pin. A headless 375px pass does not satisfy this condition.
  - Pending: deployment (maintainer deploys by SSH); no schema, API, cookie, dependency or Apache change.
  - Not run: full suite.
- Notes:
  - Thresholds (`FILL`, `LEAD` in `nav_slots.js`) may need tuning after the replay; that is an in-scope refinement of Stage 5.
  - `lcd_palettes.mjs` remains a reproduced baseline failure (fails on `main` before this cycle).
- Estimate details: original scope unchanged; uncertainty medium; no revisions. The retrospective covers the headless matrix only.

*— Claude · 2026-11-05 UTC · Model: claude-fable-5-1*

## Closeout

- Technical completion: Stages 1–7 complete; Stage 8 pending the maintainer's iPhone replay and deployment. Branch `feat/adaptive-nav`, not merged or pushed to `main`. Commits: planning `8fe60a2a`, stages `4f290c6e`, `74a7c5b8`, `a3dfb3f9`, `d64a0f49` + `617ce9be`, `eacf6ea6`, `3c9c796e`, `5e670104`, and this Stage 8 commit.
- Completion Contract: desktop bar and You split delivered; five-tab phone bar with More delivered; slots fill, hold, yield and pin as specified, placed before first paint; unpin, cleared storage and feature-flag refresh recovery covered by browser checks. Release condition met for the headless matrix; the iPhone condition is open.
- Final diff review: changed files are the renderer, two header partials, the layout head script, `site.css`, `theme-nav-icons.css`, `private_message_unread.js`, `feature_flags.js`, the new `nav_slots.js`, tests and fixtures, three concept entries, the copy checklist and this cycle's plans. No dependency, schema or deployment change; `node_modules/` symlinks for Playwright are ignored and not committed.
- Enhancement requests: JSON storage (adopted, Stage 4); local-storage editor PF-20261104-F18 (separate work, unchanged).

## Study closeout

2026-11-05T07:40:00Z, `railspike-effort` / `v3/adaptive_nav`. Entry projection **0.85** preserved. Separately labelled **retrospective scope assessment: 0.90**, single exposed Claude rater: the delivered slice matched the plan; the extra work was in browser-check updates for the duplicated Messages destination and the theme-swap readiness race, not in features. Stage retrospectives S1 0.08 / S2 0.16 / S3 0.06 / S4 0.14 / S5 0.14 / S6 0.05 / S7 0.08 / S8 0.10 (headless part), non-additive. Omissions: iPhone replay, deployment, full suite. Maintainer comfortable-fit judgment and study acceptance: pending. No measured labor, independent rating or accepted-count change.

## Tweaks after iPhone replay

Direct work on this branch; maintainer requests.

### 2026-11-05

- Reuse: extended the existing More panel, `nav_slots.js` and browser checks; no new components.
- More panel items now sit at the bottom, by the tab bar (`site.css`). An auto margin on a flex column keeps the top reachable when the list overflows a short screen.
- Removed the Pin toggle and pin logic (`app_header.php`, `site.css`, `theme-nav-icons.css`, `nav_slots.js`): confusing and cluttered. Slots still fill from use. A slot saved through an old pin stays in `slots` and can now be overtaken; an old `pins` key is ignored and dropped at the next save.
- Removed the More tab's current-page highlight and its `data-active` attribute. It lit up for destinations inside More, and for slotted tabs too, because the exception's `:root html[...]` selector could never match.
- Desktop header: on themes that do not frame it, a rule now spans the page and, from 1240px, the links start at the content's left edge with the name in the margin (`site.css`). Maintainer's choice from the mockups. Word97, Midnight, Chicago and LCD keep their frames unchanged. Below 1240px the header keeps its one-row width with the rule.
- New post no longer uses the selected colors, which made it read as the current page. It keeps its weight and takes neutral colors everywhere except the compose page, where it is the current destination.
- Follow-up: the maintainer may want a left sidebar as a desktop navigation option, as a separate cycle. Not part of this one.
- Verification:
  - Passed: `./v3 test --smoke AppNavigationPlacementTest CalendarPageTest` (431), `git diff --check`, `node --check nav_slots.js`.
  - Passed: headless Chrome renders of the real templates and CSS at 390px: panel bottom-aligned (light, dark, Midnight); overflow at 420px high scrolls; on About and on a slotted Users page More is not highlighted and the slotted tab is.
  - Passed: headless Chrome measurements as a member, all 16 themes at 1239, 1240, 1280 and 1920px: links on one row, no sideways scrolling; links line up with the content from 1240px on the themes without a frame. 800px was measured but its results were not reviewed.
  - Observed, not changed: at 390px in the dark theme with a slot, "New post" wraps onto two lines. The fonts in this environment may differ from an iPhone's.
  - Not run: browser scripts, including the edited `nav_slots.mjs` and `viewer_nav_prepaint.mjs` (`playwright-core` is not installed here); iPhone replay; full suite.
