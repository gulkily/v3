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
  - `site.css`: from 640px the app header spans up to 1100px centred on the 760px column, links sit tighter, and New post and You stay on the first row when the nav wraps.
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
