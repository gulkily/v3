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
