> **Feature plan:** [Step 1](./ui_tools_launcher_step1_solution_assessment.md) · [Step 2](./ui_tools_launcher_step2_feature_description.md) · [Step 3](./ui_tools_launcher_step3_development_plan.md) · [Step 4](./ui_tools_launcher_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/ui_tools_launcher` — [ledger](../effort_estimation_trial.md#cycle-v3ui_tools_launcher) (standing P2 enrollment)
Extended estimates: enabled

**Cycle estimate: 0.45**

## Completion Contract

- Normal entry: Tools in the main navigation; the Tools bar inside any tool.
- End-to-end outcome: /tools/ lists the 15 tools once in four groups with one-line descriptions and no Account; every tool's bar lists the same tools in the same order with the current one marked; hooks `data-tone` (tool) and `data-shape` (group heading) present; same server HTML for every viewer.
- Recovery: none new; Backup stays hidden when both backups are off; refusing tools still refuse.
- Deployment/external: none (no schema, dependency or deployment change).
- Release condition: every site and permitted theme at 390px and 1280px without overflow or page errors, icons in Word97/Chicago/Midnight, Bookmarklets' own list unchanged, contact sheets reviewed, affected tests updated and passing.

## Key Risks

- **Bookmarklets shares launcher classes, styles and icon rules.** Impact: its list could change. Validation: baseline screenshots of the Bookmarklets list (not its Tools bar, which reorders by design) in default, Word97, Chicago and Midnight before Stage 1. Mitigation: group styles scoped to the Tools page; compared in Stages 2 and 3.
- **Theme overrides of launcher buttons (Word97, Midnight, QDB).** Impact: broken tiles or overflow. Validation: Stage 2 renders those themes first. Mitigation: keep the button class and links so theme and icon rules still match.
- **Existing tests assume the old list.** Resolved by search before planning: `LocalAppSmokeTest` (Account entry, divider, ↗ markers, "System State"), and `docs_discovery`, `access_history`, `account_key_approval`, `backup_feature_flags`, `high_traffic_control` use launcher/bar selectors. No docs mention Tools → Account. Each is updated in the stage that changes it.

## Stage 1

**Estimate: 0.10**

- Goal: the registry knows each tool's group, tone and one-line description; Account leaves Tools; the Tools bar follows group order.
- Dependencies: Bookmarklets baseline screenshots taken.
- Expected changes: registry entries gain `group`, `tone`, short `description`; Account removed; "System State" → "System status" in the registry, page title and heading; `navOptions()` orders by group and marks group boundaries; the bar shows a divider between groups instead of the single in-page/standalone divider (↗ markers kept).
- Verification approach: `LocalAppSmokeTest`, `AppNavigationPlacementTest`, `HighTrafficTest`, `BackupFeatureFlagTest` updated where they assert the old list; a registry check that every tool has a known group and a unique tone.
- Estimate details: driver: test updates across the smoke suite; uncertainty: low.
- Risks or open questions: bar wraps differently with more dividers. Impact: crowded bar on phone. Validation: Stage 3 matrix. Mitigation: dividers are thin and hidden at phone width if needed.
- Canonical components/API contracts touched: `ToolsPageSupport::registry()` / `navOptions(?string $activeKey)` (return shape gains `group`, `tone`), `partials/tools_nav.php`.

## Stage 2

**Estimate: 0.15**

- Goal: /tools/ shows one grouped launcher with hooks, replacing the bar and the long list.
- Dependencies: Stage 1.
- Expected changes: `pages/tools.php` renders four group headings (`data-shape`) each followed by its tools (`data-tone`, existing button class and links, one-line description); no Tools bar on /tools/; Tools-page-only group styles in `tools.css` (phone: two-up, descriptions kept short).
- Verification approach: render /tools/ in default, Word97, Chicago, Midnight and QDB at 390px and 1280px first; Bookmarklets list matches its baseline; `docs_discovery`, `access_history`, `account_key_approval`, `backup_feature_flags` updated and passing.
- Estimate details: driver: theme overrides of the shared button; uncertainty: low–medium.
- Risks or open questions: Bookmarklets regression. Impact: visible change on another page. Validation: baseline comparison. Mitigation: scope new rules to the Tools page.
- Canonical components/API contracts touched: `pages/tools.php`, `public/assets/tools.css`, theme launcher rules (reused), `theme-nav-icons.css` (reused unchanged).

## Stage 3

**Estimate: 0.15**

- Goal: prove the outcome on every site and theme.
- Dependencies: Stage 2.
- Expected changes: new browser test `tests/browser/tools_launcher.mjs` (sites via `FORUM_SITE_ID`, every permitted theme, 390px and 1280px): 15 tools once, no Account, four headings with hooks, each tool page's bar in the same order with the current tool marked, icons non-empty in Word97/Chicago/Midnight, no overflow or page errors, Bookmarklets list compared; contact sheets reviewed. Fixes found by the matrix.
- Verification approach: the new test; `high_traffic_control` and other updated browser tests rerun; full PHP suite once.
- Estimate details: driver: theme × site matrix and fixes it reveals; uncertainty: medium.
- Risks or open questions: a theme needs its own adjustment. Impact: extra fix. Validation: the matrix. Mitigation: fix inside this stage; record anything deferred.
- Canonical components/API contracts touched: theme CSS only where the matrix requires it.

## Stage 4

**Estimate: 0.05**

- Goal: records and closeout.
- Dependencies: Stage 3.
- Expected changes: UI copy checklist entry (removed/added wording); concept entries `docs/concepts/tools_launcher.md` (created) and theme navigation icons (created, describing current behaviour); implementation checklist Cycle 9 marked built (and the stale Cycle 5 "not merged" fixed); study closeout and debug report.
- Verification approach: links resolve; concept paths match the code.
- Estimate details: driver: records only; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: none.

## Concepts touched

- Tools launcher (created in Stage 4): one registry builds the launcher and every Tools bar.
- Theme navigation icons (created in Stage 4, behaviour unchanged): icons keyed to tool links in Word97, Chicago and Midnight.

## Execution and study checkpoint

| Checkpoint | UTC | Scope | Estimate | Reason |
| --- | --- | --- | --- | --- |
| Step 3 handoff | 2026-10-31 | v1 / Option A | 0.45 | Reaffirmed; the test search found no hidden surfaces beyond Step 2's inventory. Stage work S1 0.10, S2 0.15, S3 0.15, S4 0.05, not additive. |

Four stages planned, none implemented. Same rater (Claude, claude-opus-5-5, single exposed), R1 (hashes verified 2026-10-31), P2, starting revision `41a6dbe9c`. Branch `feat/ui-tools-launcher` after approval.

*— Claude · 2026-10-31 UTC · Model: claude-opus-5-5*
