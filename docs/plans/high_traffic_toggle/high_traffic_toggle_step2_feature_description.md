> **Feature plan:** [Step 1](./high_traffic_toggle_step1_solution_assessment.md) · [Step 2](./high_traffic_toggle_step2_feature_description.md) · [Step 3](./high_traffic_toggle_step3_development_plan.md) · [Step 4](./high_traffic_toggle_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/high_traffic_toggle`; [ledger](../effort_estimation_trial.md#cycle-v3high_traffic_toggle). Standing P2 enrollment.
Extended estimates: enabled. Approval: Step 1 Option A (scope v3) approved; Step 2 approved 2026-10-27.

**Cycle estimate: 0.20**

## Problem

On Feature Flags, high-traffic mode sits in a separate card above the page heading as an unstyled text button that looks unlike the page's other switches, and its failure message is long.

## User stories

- As an operator who manages flags, I want high-traffic mode to look like the other switches so its state is obvious at a glance.
- As an operator whose switch fails, I want a short error with visible recovery so I can retry or recover from the CLI.

## Core requirements

- Remove the separate card; show high-traffic mode as one row directly beneath the Feature Flags heading, matching flag-row layout: label, one-line staleness note, shared switch and on/off state text. Shown only to flag managers, as today.
- The row stays outside the flag list: not searched, filtered or counted, and no flag key, badges or reset.
- Switching keeps the existing signed prepare/finalize flow; no routine success notice; keyboard operable.
- Failure shows a short error with reload and `./v3 high-traffic restore` recovery visible, leaving the switch retryable.

## Delivery scope and completion boundary

Application change: `feature_flags.php`, `high_traffic_control.js`, shared CSS reuse, the existing browser test, the UI copy audit checklist and this cycle's planning/study ledger. No API, permission, route, navigation, icon, CLI, schema or dependency changes; no production mode change. The runbook path "Tools → Feature Flags → High-traffic mode" stays valid.

Normal entry: Tools → Feature Flags → high-traffic row → toggle. Recovery: short error, reload shows true mode, CLI restore remains. Release condition: real-browser signed on/off and simulated failure, non-manager absence, toolbar counts unaffected, phone/desktop, light/dark and Word97/Chicago/Midnight rendering verified.

## Risks

- **Coupling with flag scripts:** `feature_flags.js` binds rows and forms by `data-feature-flag-*` attributes; reusing those would pull the row into filters and flag saving. Validate in Step 3 by checking toolbar counts; mitigation: reuse layout classes only, not flag data attributes.
- **Theme rendering of the reused switch** outside a flag row in Word97/Chicago/Midnight. Validate early with screenshots in each theme.
- **Browser test selectors:** `tests/browser/high_traffic_control.mjs` relies on current markup and the "CLI recovery" text. Keep stable hooks; update the test with the copy.

## Shared component inventory

- `templates/pages/feature_flags.php` flag-row markup and `.feature-flag-*` styles: reuse layout for the new row; remove the card.
- `.switch` in `tool-details.css`: reuse; no new switch style.
- `public/assets/high_traffic_control.js`: extend to drive the switch/state text; keep its own submit handler.
- `ToolsPageController` `highTraffic` status data, `/api/prepare_high_traffic`, `/api/finalize_high_traffic`, `browser_signing.js`: reuse unchanged.
- `public/assets/feature_flags.js` toolbar: unchanged; must not see the row.

## User flow

1. Operator opens Tools → Feature Flags.
2. The high-traffic row under the heading shows the switch in its current state.
3. Operator flips it, signs, and the switch and state text update.
4. On failure, the short error and recovery appear; operator retries, reloads or uses the CLI.

## Success criteria

- The separate card is gone; the row aligns with flag rows and uses the same switch.
- Signed enable and disable succeed; the switch state matches `./v3 high-traffic status` afterwards.
- A simulated failure shows the short error with recovery; retry works, including by keyboard.
- Toolbar counts, search and filters are unchanged with the row present; non-managers see no row.
- No horizontal overflow at phone width; readable in light/dark and the three icon themes.

## Study snapshot and approval

History: an earlier Step 2 draft for Option C (scope v2, 0.50) was superseded when the user returned to Step 1; its content is summarized in the [ledger](../effort_estimation_trial.md#cycle-v3high_traffic_toggle) and this file now describes Option A.

Step 2 handoff, 2026-10-27 UTC: scope v3 (Option A), baseline `6e32cd093e76da88820388159de08c95e7bd9a0d`, R1/P2, Claude Code single exposed rater. **0.20 projected total reaffirmed**; `feature_flags.js` attribute coupling is new understanding that fits within it. Earlier snapshots retained. No implementation, measurement or accepted outcome. No incidental enhancement requests. Awaiting `Approved Step 2`; planning and ledger remain uncommitted.

*— Claude Code · 2026-10-27 UTC · Model: claude-opus-5-5*

Approval recorded, 2026-10-27 UTC: user explicitly sent “Approved Step 2” (after an earlier “Approved Step 3” that was clarified, not applied).

*— Revised by Claude Code · 2026-10-27 UTC · Model: claude-opus-5-5*
