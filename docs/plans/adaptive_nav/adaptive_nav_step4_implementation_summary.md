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
