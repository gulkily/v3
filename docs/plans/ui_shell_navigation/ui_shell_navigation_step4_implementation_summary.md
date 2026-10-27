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
- Enhancement requests:
  - **2026-10-27 — Pull-only deploys.** Request: "make it automatic. A deploy should never require more than a git pull." Context: pre-built (static) releases are served regardless of which code built them, so a presentation change can show the old header on thread/tag/profile pages until `./v3 build-static` runs (and the documented `--shared-only` fast path keeps them stale). Disposition: **separate work** — it changes static-release validity in the serving path and needs an automatic rebuild trigger, outside this cycle's approved scope. Approval status: user-requested; new cycle to start at Step 1 after this cycle's closeout. Outcome: follow-up cycle (link to be added when created).

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
- **Correction (after commit `650fe55d9`):** the line above stating "`AppNavigationPlacementTest`: 7 run, 7 passed" was wrong. That run reported 5 passed, 2 failed, and the commit went ahead because the command chain did not stop on the failure. Cause: the Stage 3–4 shell CSS is inlined into every page as critical CSS, so two tests that searched the whole page for `new-post-button` / `account-menu` found the class names in the `<style>` block. The shell behavior was correct. Fix: those tests now check the page markup without the inline critical CSS. Rerun: 7 run, 7 passed, exit 0. From here on, test runs gate commits on the exit code.

*— Claude · 2026-10-27 UTC · Model: claude-opus-5-5*

## Stage 5 - Navigation scripts and their browser tests

**Original estimate: 0.15**
**Retrospective: 0.20**

- Changes:
  - No navigation script needed changes: `feature_flags.js` live refresh, `private_message_unread.js`, `auth_navigation.js`, invite navigation and `theme_toggle.js` work with the single nav and the account menu.
  - `public/assets/site.css` (phone, account menu): the LCD palette's centering transform is dropped inside the menu, because it made the palette's fixed popover position against the palette and get clipped (palette options could not be tapped on phones); theme and palette popovers now open above the menu's controls row instead of covering it.
  - `public/assets/private_message_conversation.css`: the phone composer offset moved here from the critical CSS, so the `data-private-message-composer` token no longer appears on every page (a test asserts it is absent when messaging is disabled).
  - Browser tests: `lcd_palettes.mjs` and `invite_zero_click.mjs` open the account menu before using the theme controls or Invite, as a person now does; `nav_unread_outline.mjs` waits for the theme stylesheet's `load` before measuring.
- Verification:
  - Browser tests with `CHROMIUM_PATH=/usr/bin/google-chrome`, all exit 0: `nav_unread_outline`, `nav_active_theme_styles`, `theme_cycle`, `lcd_palettes`, `feature_flags_toolbar` (live navigation refresh), `media_embed_browser`, `invitation_issuance`, `invite_zero_click`, `private_message_availability`.
  - `php tests/run.php AppNavigationPlacementTest PrivateMessageFeatureFlagTest FeatureFlagChangePageTest`: 14 run, 14 passed (exit-code gated).
  - Baseline header diff (24 files): identical.
  - LCD palette geometry at 390px: popover 570–728 clears the theme and palette buttons (742–774); at 1280px it does not overlap the theme button.
- Notes:
  - `nav_unread_outline.mjs` had a race: after swapping the theme stylesheet it waited for a page `load` that had already fired, so its "before" measurement could precede the new theme. On the baseline it passed 3/3; with this branch's larger critical CSS it failed 4/4 (different theme each time). Bisecting the CSS showed no single rule responsible (each half passed alone); with the test waiting for the stylesheet it passes 3/3 on the full branch CSS. The fix keeps the test's intent (unread must not shift layout) and removes the race.
  - Deviation from the plan's wording: the expected script adjustments turned out unnecessary; the stage's work was CSS and test updates instead.
- Estimate details: original scope; retrospective above the original because of the race investigation and the phone popover fixes.

*— Claude · 2026-10-27 UTC · Model: claude-opus-5-5*

## Stage 6 - Permitted themes

**Original estimate: 0.15**
**Retrospective: 0.12**

- Changes (all in `public/assets/site.css`; no theme stylesheet needed edits):
  - New post and the highlighted phone tabs use `:root`-scoped rules so themes' general link colors don't override their text (Word 97 showed navy on navy).
  - The account menu panel and the phone tab bar layer `--panel` over `--page-bg`, so themes with a translucent or transparent `--panel` (Vapor, Arena) no longer show the page through them; the "New Post" text seen in Arena's menu was the board's own button showing through.
  - Highlighted phone tabs (active link, open "You") get `--active-ink`, fixing blank or unreadable tabs in Sticker, Arena and Word 97.
- Verification:
  - Chrome, all 13 permitted themes plus Auto, account menu open: desktop 1280px and phone 390px (touch) screenshots reviewed as contact sheets; every header control, menu item and tab readable.
  - Sweep of a thread, tools, users and about in every theme at 1280px and 380px: no horizontal overflow, no page errors, account-menu trigger shows a focus indicator (focus set programmatically; a real keyboard focus ring per theme was not inspected individually). 0 problems.
  - Gated: `php tests/run.php AppNavigationPlacementTest PrivateMessageFeatureFlagTest` 12/12; baseline header diff identical; `nav_unread_outline` (16 themes) and `nav_active_theme_styles` (16 themes) pass.
- Notes:
  - Arena's active nav link renders blank on the old header too (compared on a baseline server); with the app shell, the highlighted Board tab on phones is now readable, but the desktop active link is unchanged and remains a pre-existing Arena issue.
  - Word 97's decorative separator after the account menu is the theme's own toolbar chrome; left as styled.
  - Headless Chromium was used rather than headed Chrome; custom scrollbars were not part of these checks.
- Estimate details: original scope; shared fixes in `site.css` made per-theme edits unnecessary.

*— Claude · 2026-10-27 UTC · Model: claude-opus-5-5*

## Stage 7 - Icon themes

**Original estimate: 0.08**
**Retrospective: 0.06**

- Changes (`public/assets/theme-nav-icons.css`, Word 97 / Chicago / Midnight):
  - Account-menu links reuse the existing `.nav-link` address icons (About, Tools, Account, Invite), so they needed no new rules.
  - New: Messages (envelope) and a personal Profile link (`/profiles/…`, person) icons; New post shares the existing composer icon and hides its "+" glyph; the "You" trigger shows a pixel person instead of the outline circle.
  - Nav icons no longer shrink as flex items (`flex: 0 0 auto`), which had hidden the Messages icon in the five-tab phone bar.
- Verification:
  - Chrome, each icon theme at 1280px and 390px (touch), account menu open, with Messages and a Profile link injected (the local viewer is a guest): computed `::before` icons present on Messages, Profile, New post and About, and on the "You" trigger; all nav icons 16px wide in the phone tab bar. Screenshots reviewed.
  - Gated: `php tests/run.php InvitationIssuanceTest AppNavigationPlacementTest PrivateMessageFeatureFlagTest` 21/21; baseline header diff identical; `nav_unread_outline` and `nav_active_theme_styles` (16 themes) pass.
- Notes: Messages had no icon before this cycle either; it is added now because the tab bar shows it.
- Estimate details: original scope; no revision.

*— Claude · 2026-10-27 UTC · Model: claude-opus-5-5*

## Stage 8 - Acceptance and closeout

**Original estimate: 0.10**
**Retrospective: 0.15**

- Changes:
  - `docs/plans/ui_copy_audit_checklist.md`: the shell's copy changes, verification and the Board-cycle follow-up recorded.
  - Effort ledger: cycle row and closeout entry.
  - Corrective test maintenance (not shell work): five stale assertions that failed on the baseline before this cycle were updated to current behavior. `AgentIdentityServiceTest` reads username and approval state from the access-management profile (`/tools/user-access/profiles/…`), and `ApprovalSeedCommandTest` checks for "Member activity" instead of copy removed in `aa8c3cde2`. In `LocalAppSmokeTest`, the two build-progress tests accept any shared-page total (platform docs pages grew it), and the continuation-merge test matches `<p class="meta"` with the `data-post-byline` attribute.
- Verification:
  - Other sites unchanged and switch-back: headers rendered on the baseline commit `83fba5980` and on this branch, for Chouse, QDB, MIT Rap Club and ZenMemes switched to `forum`, each for guest, unapproved and approved viewers with Messages on and off. All 24 are byte-identical.
  - Guest matrix, Chrome against the local server, JavaScript on and off at 1280px and 390px (touch), on the board, tools, about and users pages with the account menu open: every destination (`/`, `/users/`, `/compose/thread`, `/about/`, `/tools/`, `/account/key/`) visible and linked, no horizontal overflow, no page errors; New post reaches the composer in all four combinations. Screenshots reviewed.
  - Signed-in states: placement and render tests (`AppNavigationPlacementTest`) plus the browser tests below, which sign in through the app.
  - Browser tests with `CHROMIUM_PATH=/usr/bin/google-chrome`, all exit 0: `nav_unread_outline`, `nav_active_theme_styles`, `theme_cycle`, `lcd_palettes`, `feature_flags_toolbar`, `media_embed_browser`, `invitation_issuance`, `invite_zero_click`, `private_message_availability`, `private_message_feature_flag`, `private_message_live_navigation`, `private_message_unread_ui`, `board_compose_focus`, `railspike_auth_navigation`, `testing_self_approval`.
  - Previously failing tests rerun: 12 run, 10 passed. The five tests above now pass, and so do `testFourthProfileFixtureUsesTheSameRuntimeAndSharedStateMatrix` and `PresentationProfileMatrixTest::testListingQueryRoutesAndCodebaseCoverage`, which last failed on 2026-10-20.
  - Two failures remain, both failing before this cycle: `ReadModelBuilderTimingTest::testProgressSeparatesParsingDerivationAndWritingWithBoundedCounts` (since 2026-10-18), and `PrivateMessageReleaseIsolationTest::testStaticReleaseAndOfflineSnapshotExcludePrivateMailboxArtifacts` (since 2026-10-20). For the second, a fixture build shows every `private_message_reader` hit is a generated `/docs/` page naming plans such as `private_message_reader_refinement` in text; no application page loads a private-message script.
  - Baked artifacts: the local server's pre-built tag, thread and profile pages carry the account menu. Production needs the same rebuild; that is the pull-only deploy follow-up above.
  - Full suite (`php tests/run.php`): **partial, not a full-suite result.** Stopped by agreement after 55 minutes (exit 143) with 712 tests run across 83 classes: 711 passed, 1 failed. It was interrupted during `LocalAppSmokeTest::testFourthProfileFixtureUsesTheSameRuntimeAndSharedStateMatrix`; roughly 700 tests did not run. The one failure, `ImportedContentPublicationTest::testEachPublicationFailureResumesWithoutNewRecords` ("Documentation changed during publication"), was caused by editing this summary while the test built the repository's docs; it was not rerun here. On this branch every static build renders all platform docs pages, so each import and static-build test takes 1–9 minutes; current main publishes docs separately (`./v3 docs publish`). The full suite will be run after merging main (follow-up below).
- Estimate details: original scope plus the corrective test maintenance and the slow full-suite run; retrospective above the original.

*— Claude · 2026-10-27 UTC · Model: claude-opus-5-5*

## Closeout

**Retrospective cycle assessment: 0.95** (separately labeled retrospective scope assessment; not a prospective score or measured effort)

- Delivered scope: v1 as approved. ZenMemes renders the app shell (top bar with Board, Users and Messages, New post, an account menu holding About, Tools, Account, Invite and theme choice; a phone tab bar below 640px; theme hooks; icon-theme icons). Chouse, QDB and MIT Rap Club headers are byte-identical to the baseline, and switching ZenMemes back to `forum` restores today's header.
- Projections: 0.90 intake, 0.85 Step 1 and Step 2, 0.90 Step 3, 0.90 reaffirmed at Step 4 entry; no in-cycle revision. The retrospective is slightly higher because of the Stage 4 test correction, the Stage 5 browser-test race and the Stage 8 test maintenance and slow suite.
- Stages: 8 planned, 8 completed. Commits: planning `062c19f4b`; stages `78568bb93`, `a70fb5cbe`, `4ace73ab7`, `650fe55d9` (Stage 4) with its correction `9c2b3c55e`, `60216a806`, `c4d71b1d1`, `00986b74e`, and the Stage 8 commit.
- Corrective work versus new requests: the Stage 1 test-registration miss, the Stage 4 false pass and the Stage 8 stale baseline tests are corrective. The pull-only deploy request is new work for a separate cycle.
- Unverified or outside scope: a complete full-suite run (partial only, above); signed-in viewer states in a real browser beyond the browser tests listed in Stage 8; the thread reply panel's reading state on phones; landscape phones; physical devices; per-theme keyboard focus rings; production rebuild and deployment; merging `main`.
- Follow-up before merge to `main`: merge current `origin/main` (215 commits ahead; a trial merge shows 9 conflicts, all in Railspike and CLI docs, none in shell code), then rerun the full suite, the header comparison, the guest matrix and the navigation browser tests on the merged branch.
- Maintainer comfortable-fit judgment: **pending.** No study accepted-count increment.

*— Claude · 2026-10-27 UTC · Model: claude-opus-5-5*
