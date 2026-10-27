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
