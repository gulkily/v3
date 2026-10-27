> **Feature plan:** [Step 1](./high_traffic_toggle_step1_solution_assessment.md) · [Step 2](./high_traffic_toggle_step2_feature_description.md) · [Step 3](./high_traffic_toggle_step3_development_plan.md) · [Step 4](./high_traffic_toggle_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/high_traffic_toggle`; [ledger](../effort_estimation_trial.md#cycle-v3high_traffic_toggle). Standing P2 enrollment.
Extended estimates: enabled. Approval: Step 2 (scope v3, Option A) approved; Step 3 drafted.

**Cycle estimate: 0.20**

## Completion Contract

- Normal entry: Tools → Feature Flags as a flag manager; one high-traffic row directly beneath the heading, matching flag rows, with the shared switch and on/off text.
- End-to-end outcome: signed enable and disable through the existing prepare/finalize flow; switch matches `./v3 high-traffic status`.
- Required recovery: short error with reload and `./v3 high-traffic restore`; switch stays retryable, including by keyboard.
- Deployment/external: none; no production mode change, Apache or schema change.
- Release condition: both stages verified in a real browser, toolbar behaviour unchanged, non-managers see no row, phone/desktop, light/dark and Word97/Chicago/Midnight checked.

## Key Risks

- **Flag-script coupling:** reusing `data-feature-flag-*` attributes would put the row into filters/counts/saving. Validate in Stage 1 by asserting toolbar counts; mitigation: layout classes only, own `data-high-traffic-*` hooks.
- **Theme rendering:** the switch outside a flag group may misalign in icon themes. Validate in Stage 2 screenshots; mitigation: reuse existing row/switch classes, minimal CSS.
- **Browser test drift:** `tests/browser/high_traffic_control.mjs` keys on current markup and "CLI recovery" text. Update in Stage 1 with stable hooks.

## Stage 1

**Estimate: 0.10**

- Goal: replace the card with a flag-style row and drive the shared switch from the existing control script.
- Dependencies: approved Step 2.
- Expected changes:
  - `templates/pages/feature_flags.php`: remove the card; render one row beneath the `h1` (label, staleness line, `.switch` button with `role="switch"`/`aria-checked`, on/off state text, hidden status), manager-only as now.
  - `public/assets/high_traffic_control.js`: update `aria-checked` and state text instead of button text; shorter failure message keeping reload and CLI restore.
  - `tool-details.css`: only if needed to space the standalone row; no new switch style.
  - `tests/browser/high_traffic_control.mjs`: target the new hooks; assert toolbar "N of N flags shown" excludes the row.
- Verification approach: browser test (signed on/off, failure, keyboard retry); `php tests/run.php HighTrafficTest` and existing Feature Flags tests; PHP lint.
- Estimate details: driver: markup and script adjustments plus test update; uncertainty: browser test harness setup time.
- Risks or open questions:
  - Impact: row captured by `feature_flags.js`.
  - Early warning / validation: toolbar count assertion.
  - Mitigation: no `data-feature-flag-*` attributes on the row.
- Canonical components/API contracts touched: Feature Flags template/row layout, `.switch`, `high_traffic_control.js`; `/api/prepare_high_traffic` and `/api/finalize_high_traffic` unchanged.

## Stage 2

**Estimate: 0.07**

- Goal: verify the release condition visually and record the outcome.
- Dependencies: Stage 1.
- Expected changes:
  - Small CSS fixes only if a theme or phone width misrenders.
  - `docs/plans/ui_copy_audit_checklist.md`: entry for the toggle cleanup.
  - This cycle's Step 4 summary and ledger closeout.
- Verification approach: real browser at 390px and 1280px, light/dark, Word97/Chicago/Midnight screenshots inspected; non-manager view shows no row; no horizontal overflow or console errors.
- Estimate details: driver: theme matrix; uncertainty: theme-specific switch styling.
- Risks or open questions:
  - Impact: misaligned or invisible switch in a theme.
  - Early warning / validation: screenshot per theme.
  - Mitigation: theme-scoped CSS adjustment reusing existing tokens.
- Canonical components/API contracts touched: theme styles, UI copy audit checklist.

## Study checkpoint

Step 3 handoff, 2026-10-27 UTC: scope v3 (Option A), baseline `6e32cd093e76da88820388159de08c95e7bd9a0d`, R1/P2, Claude Code single exposed rater. **0.20 projected total reaffirmed**, independently judged (not the stage sum). Stage-work originals S1 **0.10**, S2 **0.07**; annotations, not summed or fractions. Additionally inspected the browser test and flag-row CSS. No implementation, measurement or accepted outcome. Awaiting `Approved Step 3`; planning and ledger remain uncommitted.

*— Claude Code · 2026-10-27 UTC · Model: claude-opus-5-5*
