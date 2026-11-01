> **Feature plan:** [Step 1](./ui_tools_launcher_step1_solution_assessment.md) · [Step 2](./ui_tools_launcher_step2_feature_description.md) · [Step 3](./ui_tools_launcher_step3_development_plan.md) · [Step 4](./ui_tools_launcher_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/ui_tools_launcher` — [ledger](../effort_estimation_trial.md#cycle-v3ui_tools_launcher) (standing P2 enrollment)
Extended estimates: enabled

**Cycle estimate: 0.45**

Branch `feat/ui-tools-launcher` from `main` `41a6dbe9c`; planning commit `58f8906cc` (Steps 1–3 and debug log, folder organization, plan index entry).

## Step 4 entry checkpoint

- 2026-10-31: Step 3 approved ("approved step 3"). Whole-cycle projection **0.45 reaffirmed** before implementation. Stage originals copied from Step 3 (not new ratings): S1 0.10, S2 0.15, S3 0.15, S4 0.05, not additive.
- Same rater (Claude, claude-opus-5-5, single exposed), R1 (hashes verified 2026-10-31), P2, scope v1 / Option A.
- Early validation from Key Risks: Bookmarklets baseline captured before any change (the Bookmarklets card, excluding its Tools bar) in default, Word97, Chicago and Midnight at 390px and 1280px; a second capture was byte-identical, so later comparisons are exact.

## Enhancement requests

None.

## Stage 1 - Tool list and Tools bar

**Original estimate: 0.10**
**Retrospective stage work: 0.10**

- Changes:
  - `ToolsPageSupport`: new `groups()` (device, reading, community, operator, in order; keys double as `data-shape`); `registry()` reordered by group, each tool with `group`, a stable `tone` and a one-line description (mockup wording); Account removed; Operations under Operator; "System State" → "System status".
  - `navOptions()` returns tools in registry order with `tone` and `group_start` (first tool of each later group); the old in-page/standalone split is gone.
  - `partials/tools_nav.php`: a divider at each group boundary (three), `data-tone` on each link; ↗ markers on full-page tools kept.
  - System status page title and heading renamed (`CodebaseStateController`, `codebase_state.php`).
  - Tests: `LocalAppSmokeTest` assertions moved to the new descriptions, "System status", no Account entry, and the bar's group order and three dividers; new `ToolsRegistryTest` (15 tools, unique keys/links/tones, groups in order, one-line descriptions, a divider per group boundary, current tool marked), registered in `tests/run.php`.
- Verification:
  - `php tests/run.php ToolsRegistryTest`: 2/2 passed.
  - `php tests/run.php LocalAppSmokeTest AppNavigationPlacementTest HighTrafficTest BackupFeatureFlagTest InvitationIssuanceTest UserAccessTest PlatformDocsPageTest`: 206/209. The three failures also fail without this stage: `testRepositoryArchiveDownloadFilenamesIncludeReadableTimestamp` (long-standing), and `testPrivateLobbyOnlyExposesLobbyAccountAndAuthenticationSurfaces` / `testPrivateForteRoutesRecoverExpiredSessionsInsteadOfReturningFalseNotFound`, which fail on GitHub's `main` `93cea640e` and pass on `05e43912e`, so they came with the newer upstream commits.
  - Bookmarklets card: byte-identical to its baseline in default, Word97, Chicago and Midnight at 390px and 1280px. The comparison script removes the Tools bar card and hides fixed bars first; without that, the phone captures differed only by a sub-pixel shift because the bar above changed height.
  - Browser checks of the Tools bar layout belong to Stage 3.
- Notes:
  - "System status" follows the approved Step 2 wording; other labels keep their current Title Case. Whether to move every label to sentence case (as the mockup shows) is left for review at Stage 3.
  - Account's icon rules in `theme-nav-icons.css` stay; they are now unused by Tools but harmless, and the plan keeps that file unchanged.
- Estimate details: original scope as planned; no revision. Retrospective unchanged: test updates were as expected; the two upstream Lobby failures cost a bisect but are not this stage's work.

*— Claude · 2026-10-31 UTC · Model: claude-opus-5-5*

## Stage 2 - The grouped /tools/ page

**Original estimate: 0.15**
**Retrospective stage work: 0.15**

- Changes:
  - `pages/tools.php`: one card with the page heading (`<h1>Tools</h1>`), then each group as a labelled section with an `<h2 data-shape="{group}">` heading and its tools; each item carries `data-tone`, the existing `tool-launcher-button` class and its unchanged link, and the one-line description. The Tools bar card is gone from /tools/. Backup still drops out when both backups are disabled; an empty group would be skipped.
  - `ToolsPageController::index` passes the registry and `groups()` directly.
  - `tools.css`: rules scoped to `.tools-launcher-page` (group spacing, group headings in the UI font). On phone, the list fits as many tools to a row as have room without breaking a word, and buttons no longer stretch to match a taller neighbour.
  - Tests: `LocalAppSmokeTest` checks /tools/ has no Tools bar, lists every tool once with its tone, and shows the four headings with `data-shape` in order. `backup_feature_flags.mjs` expects the bar only on tool pages and checks the bar icon on /tools/sqlite/. `docs_discovery.mjs` checks the bar's Docs icon on /tools/bookmarklets/.
- Verification:
  - Screenshots of /tools/ in default, Word97, Chicago and Midnight (ZenMemes) and QDB, at 390px and 1280px, reviewed: groups, headings and descriptions present; icons in all three icon themes; no overflow.
  - `php tests/run.php ToolsRegistryTest LocalAppSmokeTest`: 156/159; the same three failures that also fail on main (see Stage 1).
  - `backup_feature_flags.mjs`: passed (240 theme/viewport/page states).
  - `bookmarklet_copy_ui.mjs` (against its fixture server): passed.
  - `docs_discovery.mjs`, dynamic half (`DOCS_ONLY_MODE=dynamic`, fixture server): passed, 8 width/theme cases. The static half needs a full built release and was not run.
  - Bookmarklets card: byte-identical to its baseline in all eight captures.
  - `high_traffic_control.mjs` fails waiting for the auth-resume marker on /tools/operations/; it fails the same way on main, so it was already broken. `access_history.mjs` and `account_key_approval.mjs` already fail on main (recorded in earlier cycles); their Tools selectors use the bar only on tool pages and the launcher on /tools/, which this stage keeps working.
- Notes:
  - Deviation from the Step 3 line "phone: two-up": at 390px Midnight's bold buttons split "Bookmarklets" mid-word in two columns. The list now uses two columns only where labels fit whole, so 390px phones get one column. In-scope layout refinement; no requirement changed.
- Estimate details: original scope as planned; no revision. Retrospective unchanged: the phone layout needed one extra pass.

*— Claude · 2026-10-31 UTC · Model: claude-opus-5-5*

## Review findings after Stage 2 (2026-10-31)

User review of the Stage 2 page, fixed in the same cycle before Stage 3's matrix:

- **Not every tool was in view.** The grouped list was one tool to a row with the description beside it, so at 1280×900 the last four to six tools (the tail of Operator) sat below the fold in every theme, and at 1280×720 eight did. The old page had kept every tool in view through the Tools bar card, which Stage 2 removed. Fix in `tools.css` (rules still scoped to `.tools-launcher-page`): the list is a tile grid at every width (`repeat(auto-fill, minmax(min(100%, 10.5rem), 1fr))`, the phone rule promoted), group spacing and heading margins tightened. The desktop shell is a fixed 760px with no wider variant, so three columns is the ceiling there; Midnight's wider window gets four.
- **Descriptions removed from /tools/** (user request: "get rid of the text bits under the buttons"). `pages/tools.php` renders the button only; the description rule in `tools.css` is gone. With descriptions, mono-font themes wrapped most of them to two lines and the last Operator tile still fell below a 720px-high window; without them every tool is in view at 1280×720 in every permitted theme (last button bottom 511–663px). The one-line descriptions stay in `ToolsPageSupport::registry()` (still covered by `ToolsRegistryTest`) but are no longer shown anywhere; Step 2's "each tool has a one-line description" requirement is superseded by this review, and the descriptions can be dropped from the registry in a later cleanup if nothing else wants them.
- **Group heading icons in the icon themes** (user request). `theme-nav-icons.css`: a 16px pixel-art glyph before each `.tool-launcher-group-heading[data-shape]` in Word97, Chicago and Midnight: a monitor (device), an open book (reading), the existing Users glyph (community) and a gear (operator). The gear was first drawn in the dark grey of the Tools wrench and was invisible on Midnight's panel; it is mid-grey with a white ring so it reads on both light and dark panels.
- Tests: `LocalAppSmokeTest` no longer expects description text on /tools/ and asserts there is none; `tools_launcher.mjs` asserts no description element per tool and a non-empty heading icon per group in the three icon themes.
- Verification:
  - Fold measurement (every permitted ZenMemes theme, 1280×900 and 1280×720, scratch script, buttons whose bottom edge is past the viewport): before the fix 4–6 of 15 below at 900 and 8 at 720; tiles with descriptions 0 at 900, 1–4 at 720; buttons only 0 at both.
  - `php tests/run.php ToolsRegistryTest LocalAppSmokeTest`: only the same three long-standing failures as Stage 1 (two Lobby tests that fail on upstream `main`, the archive filename test).
  - `tools_launcher.mjs` (all permitted themes, 390px and 1280px): passed on ZenMemes (28 cases) and QDB (30 cases) with the tile layout and descriptions, and again without descriptions; passed in Word97/Chicago/Midnight with the heading icons; the full run with the final icons passed on ZenMemes (28 cases) and QDB (30 cases).
  - Screenshots reviewed at 1280×900: default, Console, Word97, Chicago, Midnight (and Midnight at 390px): every tool in view, icons on headings and tools, no clipped labels.
  - Bookmarklets: every changed selector is scoped to `.tools-launcher-page` or `.tool-launcher-group-heading`, neither of which Bookmarklets renders; its baseline comparison was not re-run.
- Estimate details: no score revision recorded here; this pass was made by a different model (claude-fable-5-1) than the cycle's single rater, and the Stage 2 retrospective above is left as rated. The work is in-scope layout refinement plus two review-requested changes (descriptions removed, heading icons), not new scope for the study.

*— Claude · 2026-10-31 UTC · Model: claude-fable-5-1*

## Stage 3 - Every site and theme (retrospective record, 2026-11-01)

**Original estimate: 0.15**
**Retrospective stage work: 0.15** (rated at closeout by claude-fable-5-1, not the cycle's original rater)

- Recorded at closeout, not at the time: the stage's work shipped in commit `429f66ea3` ("stage 3") on 2026-10-31 together with the review fixes above, and its verification was written under "Review findings after Stage 2" rather than as a stage section. This record collects it; it adds no new rating of the original scope.
- Changes: `tests/browser/tools_launcher.mjs` (site via `FORUM_SITE_ID`, every permitted theme, 390px and 1280px): 15 tools once, no Account, four headings with `data-shape` in order, `data-tone` on every tool, each tool page's bar in registry order with the current tool marked and a divider per group boundary, no description text, whole labels, no overflow or page errors, non-empty icons on tools and group headings in Word97, Chicago and Midnight. The fixes the matrix and the user's review revealed (tile grid at every width, descriptions off the page, group heading icons) are recorded above.
- Verification: as recorded above; the matrix passed on ZenMemes (28 cases) and QDB (30 cases) on 2026-10-31. Chouse and MIT Rap Club were not run at the time; they are run at closeout (Stage 4). The plan's "full PHP suite once" was not run at the time; it is run at closeout. The plan's Bookmarklets list comparison was not re-run after Stage 2's byte-identical result because every later selector is scoped away from Bookmarklets.
- Estimate details: original scope as planned; no in-cycle revision. The retrospective is unchanged from the original: the matrix found no theme needing its own fix beyond the review findings.

*— Claude · 2026-11-01 UTC · Model: claude-fable-5-1*

## Stage 4 - Records and closeout

**Original estimate: 0.05**
**Retrospective stage work: 0.05** (rated by claude-fable-5-1, not the cycle's original rater)

- Done on branch `chore/ui-tools-launcher-stage4` from `main` `32918c910` (the merge of `feat/ui-tools-launcher`), because the user merged the feature branch after Stage 3 and this stage is records only.
- Changes:
  - Registry cleanup left open by the Stage 2 review: the unused one-line `description` left `ToolsPageSupport::registry()` and the return type; `ToolsRegistryTest` no longer asserts on it. Nothing rendered it (the launcher shows labels, the bar builds from `navOptions()`), and `LocalAppSmokeTest` and `tools_launcher.mjs` keep asserting that no description text appears.
  - Concept entries created: [Tools launcher](../../concepts/tools_launcher.md) and [theme navigation icons](../../concepts/theme_navigation_icons.md); paths checked against the code (icons key on each link's `href` and the heading's `data-shape`).
  - [UI copy checklist](../ui_copy_audit_checklist.md#tools-launcher--october-31-2026): removed descriptions and Account entry, renamed System status, the four headings added.
  - [Implementation checklist](../ui_redesign/implementation_checklist.md#cycle-9-ui_tools_launcher): Cycle 9 marked built and merged; Cycle 5's stale "not merged" corrected (merged at `b8afb7711`).
  - Study ledger closeout below and in [effort_estimation_trial.md](../effort_estimation_trial.md#cycle-v3ui_tools_launcher).
- Verification (2026-11-01):
  - `php tests/run.php ToolsRegistryTest LocalAppSmokeTest`: 156/159; the same three pre-existing failures as `main` (two Lobby tests, the archive filename test).
  - `tests/browser/tools_launcher.mjs` with `/usr/bin/google-chrome` on all four sites, every permitted theme at 390px and 1280px: ZenMemes 28 cases, QDB 30, Chouse 30, MIT Rap Club 30, all passed (groups and hooks, each tool once, no Account, no description text, whole labels, no overflow, icons on tools and group headings in Word97/Chicago/Midnight; 8 tool bars in order with the current tool marked). Midnight 1280px ZenMemes screenshot inspected: four groups, 15 buttons with icons, every tool in view.
  - Full PHP suite once: 1740 run, 1734 passed, 6 failed. Five are long-standing on `main` (the three above, `PlatformDocsStaticTest` static release inventory, `ReadModelBuilderTimingTest` progress counts). `ApprovalSeedCommandTest::testSeedRetiresCachedPagesAndReleaseBeforeServingUpdatedState` is marked new by the runner (first failed 2026-11-01T02:16 local, before this stage); it fails alone and fails identically with this stage's code change stashed, so it is not caused by this work and is left for its own investigation.
  - Concept and checklist links resolve (script over the relative paths).
- Not applicable: deployment, release, external systems, roles, migrations (none in scope). Not checked: physical phones.
- Estimate details: original scope as planned; the retrospective is unchanged. The extra work over the plan was the four-site matrix and the full suite that Stage 3 had deferred, plus the registry cleanup.

*— Claude · 2026-11-01 UTC · Model: claude-fable-5-1*

## Closeout

- Stages: four of four complete, one commit each after the planning commit `58f8906cc`: Stage 1 `162623aa2`, Stage 2 `8804bffb7`, Stage 3 `429f66ea3` (also carrying the review fixes after Stage 2), and this stage's commit on `chore/ui-tools-launcher-stage4`. Debug report in a separate log-only commit.
- Completion Contract: Tools from the main navigation opens one grouped launcher with the 15 tools once and no Account; every tool's bar lists the same tools in the same order with the current one marked; `data-tone` and `data-shape` hooks present; same server HTML for every viewer; Backup hidden when both backups are off. Checked on all four sites in every permitted theme at 390px and 1280px, with icons in Word97, Chicago and Midnight. One deviation from Step 2, approved in review: no description text on the page (and none in the registry).
- Not checked: physical phones, deployment. Baseline failures recorded above, not fixed.
- Follow-ups: none proposed. Account's Tools bar icon rule stays in `theme-nav-icons.css` unused; harmless.
- Effort study: entry 0.45 reaffirmed, no in-cycle revision. **Retrospective cycle scope: 0.45** — the work matched the plan; the review after Stage 2 (tile grid, descriptions removed, heading icons) and the closeout matrix fit the original rating. Rated at closeout by claude-fable-5-1; Steps 1–3 and Stages 1–2 were rated by claude-opus-5-5, the cycle's single exposed rater, and those ratings are untouched. Stage originals/retrospectives 0.10/0.10, 0.15/0.15, 0.15/0.15, 0.05/0.05 (not summed). No measured labour. Maintainer comfortable-fit judgment: pending.

*— Claude · 2026-11-01 UTC · Model: claude-fable-5-1*

