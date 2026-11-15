> **Feature plan:** [Step 1](./usage_reports_ui_step1_solution_assessment.md) · [Step 2](./usage_reports_ui_step2_feature_description.md) · [Step 3](./usage_reports_ui_step3_development_plan.md) · [Step 4](./usage_reports_ui_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 3; [debug log](./usage_reports_ui_debug_log.md))

Effort study: `railspike-effort` / `v3/usage_reports_ui`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.45** (Step 4 entry, reaffirmed from Step 3)

Branch `plan/usage_reports_ui` with main `8314fae9` merged in. Stage originals below are copied from the approved Step 3 (R1, Claude single exposed rater); copying is not a new rating.

## Stage 1 - Readable names table

**Original estimate: 0.08**
**Retrospective stage work: 0.08**

- Changes:
  - `UsageNames` (`src/ForumRewrite/UsageReports/`): `page()` maps page kinds to names (`/threads/*` → "Thread", `/` → "Board"); `control()` maps `tab:` navbar keys (`tab:account` → "You menu"), `tool:` tones and `action:` hooks to labels, and passes free labels through. Unknown kinds and hooks fall back to their raw or humanized form. `covers()` reports whether a kind has a name or is raw on purpose (`/docs`, `/forte`, `/source`, whose segments are content). `forBrowser()` returns the pages, tabs and tools tables for the Usage Log page.
  - Tool page names and tool tones come from `ToolsPageSupport::registry()`, so new tools are named without touching the table.
- Verification:
  - New `UsageNamesTest` (registered in `tests/run.php`): 4 passed; covers known names, fallbacks, every route kind (48 sample paths plus every registry tool) named or raw on purpose, and the browser table.
  - `./v3 test` (smoke set, full output checked): 464 run, 464 passed.
- Notes:
  - The menu toggle is recorded by its `aria-label`, "Menu", because the `data-tab="account"` hook sits on the `<details>`, not the clicked `<summary>`; the table names `tab:account` anyway for links that carry it.
- Estimate details: original scope as approved; uncertainty low; no revisions.

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*

## Stage 2 - Usage Reports page

**Original estimate: 0.15**

- Status: pending.

## Stage 3 - Usage Log page

**Original estimate: 0.14**

- Status: pending.

## Stage 4 - Checks, docs and closeout

**Original estimate: 0.06**

- Status: pending.
