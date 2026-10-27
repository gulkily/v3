> **Feature plan:** [Step 1](./ui_shell_navigation_step1_solution_assessment.md) · [Step 2](./ui_shell_navigation_step2_feature_description.md) · [Step 3](./ui_shell_navigation_step3_development_plan.md) · [Step 4](./ui_shell_navigation_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/ui_shell_navigation` ([ledger](../effort_estimation_trial.md#cycle-v3ui_shell_navigation)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.90**

Branch `feat/ui-shell-navigation`, cut from `ui-redesign-mockups` at `83fba5980`. Planning commit `062c19f4b`.

## Step 4 entry

- Step 3 approved 2026-10-27 ("Approved Step 3").
- Entry projection: **0.90 reaffirmed** from the Step 3 handoff; scope v1 / R1 / P2, baseline `83fba59802ce`; Claude single exposed rater; no new inspection since the Step 3 snapshot.
- Original stage-work estimates copied from the [approved plan](./ui_shell_navigation_step3_development_plan.md) (copying is not a new rating): Stage 1 0.15, Stage 2 0.12, Stage 3 0.10, Stage 4 0.20, Stage 5 0.15, Stage 6 0.15, Stage 7 0.08, Stage 8 0.10.
- Enhancement requests: none recorded.

*— Claude · 2026-10-27 UTC · Model: claude-opus-5-5*

## Stage 1 - Shell switch and item placement

**Original estimate: 0.15**
**Retrospective: 0.12**

- Changes:
  - `PresentationSlotRegistry`: `navigation` slot accepts `app`.
  - `SiteProfileRegistry`: ZenMemes selects `navigation => app`; Chouse, QDB and MIT Rap Club unchanged.
  - `TemplateRenderer::navigationItems()` (new, public): wraps `navItems()` and, for `app` only, gives each item a placement: `main` (Board, Users, Messages, Lobby) or `menu` (About, Tools, Account, Invite, a members-only Profile link). Other slots return items unchanged.
  - `tests/AppNavigationPlacementTest.php`: slot selection per site; placements for guest, unapproved and approved viewers with Messages on and off; members-only placements; no placements on other sites; ZenMemes header byte-identical between `app` and `forum`.
- Verification:
  - `php tests/run.php AppNavigationPlacementTest PresentationSlotRegistryTest PrivateMessageFeatureFlagTest FeatureFlagChangePageTest InvitationIssuanceTest QuoteCardDisplayNumberTest ProfilePresentationContentTest`: 46 run, 46 passed.
  - UI checks not applicable: rendered output is unchanged by design in this stage.
- Notes:
  - Sequencing adjustment within the approved plan: the layout does not yet pick a shell partial by slot. Switching the rendering before the account menu exists (Stage 2) would hide About, Tools and Account, so the partial switch moves into Stage 2 with the menu. No scope change.
  - The Tools-hidden case cannot occur with `app` today (only MIT Rap Club hides Tools, and it uses `forum`); covered by the existing `forum` behavior.
  - Existing constraint kept for later stages: `PrivateMessageFeatureFlagTest` requires the page's `<nav class="nav">` to equal the live-refresh HTML.
- Estimate details: original scope as planned; uncertainty was low; no revisions. Retrospective 0.12 because the rendering switch moved to Stage 2.

*— Claude · 2026-10-27 UTC · Model: claude-opus-5-5*

## Stage 2 - Shell markup and account menu

**Original estimate: 0.12**
**Retrospective: 0.12**

- Changes:
  - `templates/layout.php`: renders `partials/app_header.php` when the `navigation` slot is `app`, adds `data-shell="app"` and an empty decorative sky layer (`.shell-sky`, theme hook) to the shell, and loads `account_menu.js`; the `forum`/`qdb` header is unchanged.
  - `templates/partials/app_header.php` (new): wordmark with an empty `.site-brand__mark` slot (theme hook); the single main nav; New post (`/compose/thread`, only when Board is a destination, so not in members-only mode); account menu on a native `<details>` with the `menu` destinations, the density toggle where shown, and the existing theme menu.
  - `templates/partials/nav.php`: skips `menu` items and adds `data-tab` (theme hook) only when placements exist, so other sites' output is unchanged.
  - `TemplateRenderer`: layout and `navigationState()` both use `navigationItems()`, keeping the page nav equal to the live-refresh HTML; passes the slot and the new script path.
  - `public/assets/account_menu.js` (new): Escape and outside click close the account menu; Escape handled by a nested popover is left alone.
  - Tests: the Stage 1 "header unchanged" test replaced by checks of the bar/menu split, the theme hooks, the members-only case (no New post) and the `forum` switch-back.
- Verification:
  - Baseline diff: 24 headers (Chouse, QDB, MIT Rap Club, and ZenMemes switched to `forum`; guest, unapproved, approved; Messages on and off) captured before the change are byte-identical after it.
  - Browser (Chrome, local server, desktop 1280px): with JavaScript off, opening the menu exposes every destination (`/`, `/users/`, `/compose/thread`, `/about/`, `/tools/`, `/account/key/`) as plain links; with JavaScript on, Enter opens the menu, Tab reaches its first link, Escape closes it and returns focus to the trigger, the theme popover opens inside the menu within the viewport, Escape closes only the popover first, an outside click closes the menu; no page errors.
  - `php tests/run.php AppNavigationPlacementTest`: 7 run, 7 passed.
  - Markup-sensitive smoke tests (`testInviteNavigationHighlightsOnlyInvite`, `testClearingIdentityRevokesApprovedPrivateSession`, `testPendingPrivateSessionNavigationOnlyShowsLobbyOwnProfileAndAccount`, `testApplicationRendersCoreRoutes`, `testApprovedPublicSessionIsResumedForBoardAndInvite`) plus `PrivateMessageFeatureFlagTest`: 10 run, 10 passed, with no test edits.
  - Visual styling not checked: unstyled by design until Stage 3.
  - Baked artifacts: `php scripts/build_static_artifacts.php` on the fixture repository succeeds on this branch and on the baseline (exit 0); 2,036 baked pages carry the account menu and `account_menu.<hash>.js` is copied. `check_static_artifacts.php` reports the same 11 missing fingerprints on both builds (documentation pages; none involve this change).
  - Pre-existing failures, identical on the baseline worktree at `062c19f4b`: `testBuildStaticCommandReportsProgressAndArtifactSummary`, `testSharedStaticRefreshCommandKeepsDetailArtifactsWithoutRebuildingTheReadModel` (progress-text assertions) and `testThreadMergesSameAuthorQuickRepliesIntoContinuations`. Not caused by this cycle; left for separate follow-up.
- Notes:
  - **Correction to Stage 1:** `tests/AppNavigationPlacementTest.php` was not registered in `tests/run.php`, so Stage 1's "46 run, 46 passed" did not include it and its tests never ran in Stage 1. It is registered in this stage; the current 7 tests pass. Stage 1's committed code was not changed by this correction.
  - `data-tab` is written as the first attribute of each link. Existing tests assert exact strings such as `class="nav-link" href="/tools/">Tools</a>`, including negative assertions; keeping the original attribute order unchanged keeps all of them meaningful without edits.
  - Members-only mode shows the personal Profile link in the account menu (it was in the header row before).
  - Removed a stale `public/index.html` before browser checks, per the theme guide; it regenerates on page load.
- Estimate details: original scope plus the layout switch moved from Stage 1; no revision.

*— Claude · 2026-10-27 UTC · Model: claude-opus-5-5*

## Stage 3 - Desktop top bar styles

**Original estimate: 0.10**
**Retrospective: 0.10**

- Changes:
  - `public/assets/site.css` (critical section, before `critical-css-end`): one-row app header (wordmark, main nav, New post pushed right, account menu); New post styled with the active-state variables; account menu trigger with a person mark; dropdown panel (right-aligned, `z-index: 30`, theme `--panel`/`--line` variables) with full-width menu links and a controls row for the theme menu. All rules scoped to `[data-shell="app"]` or new class names, so other sites are unaffected.
- Verification:
  - Chrome, local server, 1280px, account menu open, all 13 permitted themes plus Auto: every header control visible (8 per page) in each theme; header height 59–68px (one row) in all 14; no page errors. Screenshots reviewed.
  - Light, Dark and Chicago match the mockup's arrangement; theme popover and LCD palette render inside the panel.
  - Baseline header diff (other sites) unchanged: new CSS selectors do not match `forum`/`qdb` markup.
- Notes (early theme check, fixes deferred to Stage 6 as planned):
  - Word97: New post label is navy on navy; the theme draws a separator after the account menu.
  - Arena: the active Board link renders blank (the board's own active Liked/Newest buttons do too, so likely pre-existing); the theme adds a decorative "New Post" caption that lands inside the menu panel.
  - Vapor and Arena: the menu panel lets page content show through.
- Estimate details: original scope; no revision.

*— Claude · 2026-10-27 UTC · Model: claude-opus-5-5*

## Stage 4 - Phone tab bar

**Original estimate: 0.20**
**Retrospective: 0.15**

- Changes:
  - `public/assets/site.css` (critical section), below 640px with the app shell: the header's action row becomes a fixed bottom tab bar (`--app-tabbar-height`, safe-area padding) holding the same nav links, New post and the account menu in document order; the nav uses `display: contents` so its links are tabs; page content reserves the bar height; the account panel opens upward and scrolls; the theme popover opens upward within the screen.
  - Bottom-pinned parts move above the bar: the private-message composer (sticky) and, on touch phones, the thread reply panel's reading state; the decorative Word 97 / Midnight status bars are hidden on phones in the app shell.
- Verification:
  - Chrome, 390×844, touch emulation (`isMobile`, `hasTouch`), guest: board, a thread, compose, account and tools all show the bar at the bottom (top 781, height 63), no fixed page element under the bar, no horizontal overflow, no page errors. Screenshots reviewed, including the open menu and the theme popover (spans 249–783 of 844, fully on screen).
  - Word 97 and Midnight at 390px: status bar `display: none`, bar flush with the bottom edge.
  - `php tests/run.php AppNavigationPlacementTest`: 7 run, 7 passed.
  - Not verified: the thread reply panel's reading state (it appears only after a reply is started, which needs a signed-in browser identity); the offset rule is in place but unexercised. Landscape phone not checked.
- Notes (deviations from the mockup, within the approved requirements):
  - Tab order is document order (Board, Users, Messages, New post, You) rather than New post in the middle, so keyboard and screen-reader order match what is shown.
  - Guests and unapproved members see four tabs; About stays in the account menu instead of replacing Messages in the bar. Every destination stays reachable; revisit with the Board cycle if a fifth tab is wanted.
  - The Board page still shows its own "New Post" control in the board toolbar, next to the shell's New post; removing it belongs to cycle 2 (Board).
- Estimate details: original scope; the single-nav design made the bar mostly CSS, so the retrospective is below the original.

*— Claude · 2026-10-27 UTC · Model: claude-opus-5-5*
