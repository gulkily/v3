> **Feature plan:** [Step 2](./site_operations_page_step2_feature_description.md) · [Step 3](./site_operations_page_step3_development_plan.md) · [Step 4](./site_operations_page_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/site_operations_page`; [ledger](../effort_estimation_trial.md#cycle-v3site_operations_page). Standing P2 enrollment.
Extended estimates: enabled

**Cycle estimate: 0.40**

Branch: `feat/operations-page`, created from unmerged `feat/high-traffic-toggle` at `c462cc92` (branch reuse was not requested). Step 3 approved 2026-10-27 by explicit “Approved Step 3”.

## Step 4 entry

2026-10-27 UTC: **0.40 projected total reaffirmed** from Step 3, scope v1, R1/P2, Claude Code single exposed rater. Stage-work originals copied: S1 0.18, S2 0.07, S3 0.05 (copies, not new ratings). No implementation yet.

## Stage 1 - Operations page with high-traffic mode

**Original estimate: 0.18**
**Retrospective: 0.16**

- Changes:
  - `ToolsPageSupport`: `operations` registry entry (“Operations”, `/tools/operations/`, “Control how the site is served, including high-traffic mode.”).
  - `Application`: GET route and application-route classification for `/tools/operations[/]`. `TemplateRenderer`: `tool-details.css` for `operations.php`.
  - `ToolsPageController::operations()`: managers get the signed switch, status/error and control scripts; non-managers get a disabled switch with on/off and the existing read-only hint, no error text or scripts.
  - `templates/pages/operations.php`: new page reusing flag-row and `.switch` markup. `feature_flags.php`/`featureFlags()`: removed the high-traffic row, data and script; removed its spacing CSS.
  - Tests: `high_traffic_control.mjs` now targets Operations, asserts active nav, Feature Flags has no row, and non-manager disabled switch without status; `LocalAppSmokeTest` asserts launcher entry/description, Operations render, read-only hint and no manager hooks; `HighTrafficTest` asserts no refresh hint for `/tools/operations/`.
- Verification:
  - Passed: `node tests/browser/high_traffic_control.mjs` (signing on/off, pending, failure/keyboard retry, 403, 13 themes at 375/1150px); `node tests/browser/feature_flags_toolbar.mjs`; `php tests/run.php HighTrafficTest FeatureFlagEvaluatorTest LocalAppSmokeTest WebServerRoutingTest` 176 passed; `TerminalOperatorUiDashboardTest PlatformDocsCatalogTest LlmsTxtRendererTest` 17 passed; PHP lint.
  - Failed, pre-existing baseline: the same two `LocalAppSmokeTest` failures reproduced at `6e32cd09` in the predecessor cycle.
  - Desktop Midnight screenshot inspected; icon absent pending Stage 2.
- Notes: status is now computed for every Operations viewer, but only the mode reaches non-managers.
- Estimate details: original scope unchanged; no revisions.

*— Claude Code · 2026-10-27 UTC · Model: claude-opus-5-5*

## Stage 2 - Operations icon and visual verification

**Original estimate: 0.07**
**Retrospective: 0.07**

- Changes: `theme-nav-icons.css` adds a 16px teal gear for `/tools/operations/` on the Tools launcher button and subnavigation, and includes the subnav link in the shared icon sizing list. No layout CSS needed.
- Verification:
  - Passed: scratch copy of the browser test (outside the repository) captured the launcher item, Tools subnav and the Operations row in light, dark, Word97, Chicago and Midnight at 375/1150px (30 images), with no horizontal overflow on `/tools/`. Icon inspected at 3× zoom in each icon theme. Light/dark show no icon, as with other destinations.
  - Correction: the first grey gear (`#404040`) was nearly invisible in Midnight; changed to teal `#008080` and re-verified.
  - Passed: `node tests/browser/high_traffic_control.mjs` after the icon change.
- Notes: none.
- Estimate details: original scope unchanged; the icon colour correction is ordinary correction within the stage.

*— Claude Code · 2026-10-27 UTC · Model: claude-opus-5-5*

## Stage 3 - Documentation and closeout

**Original estimate: 0.05**
**Retrospective: 0.03**

- Changes: `docs/runbooks/high_traffic_mode.md` UI path now **Tools → Operations → High-traffic mode**; UI copy audit checklist entry; ledger closeout. `docs/reference/v3_cli.md` does not name the UI location, so it is unchanged.
- Verification: repository grep for stale Feature Flags/high-traffic UI references outside plans found only the updated runbook line; `git diff --check` passed; plan and checklist links resolve to existing files.
- Inapplicable: deployment, Apache, migration — out of scope.
- Estimate details: original scope unchanged.

## Closeout

- Retrospective scope assessment (not a projection or measured effort): **0.30**, below the 0.40 projection; route/registry wiring was simpler than expected and no capability filter was needed.
- Commits: planning `aab96984`, Stage 1 `d160c1ab`, Stage 2 `b364f71f`, Stage 3 (this commit). Branch `feat/operations-page` from unmerged `feat/high-traffic-toggle`.
- Not verified: physical devices, production. Pre-existing baseline failures: two `LocalAppSmokeTest` cases.
- Maintainer comfortable-fit judgment: pending. Study acceptance: pending; no accepted-count increment.

*— Claude Code · 2026-10-27 UTC · Model: claude-opus-5-5*
