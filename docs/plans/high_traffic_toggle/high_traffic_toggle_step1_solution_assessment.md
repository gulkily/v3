> **Feature plan:** Step 1 · [Step 2](./high_traffic_toggle_step2_feature_description.md) · [Step 3](./high_traffic_toggle_step3_development_plan.md) · [Step 4](./high_traffic_toggle_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/high_traffic_toggle`; [ledger](../effort_estimation_trial.md#cycle-v3high_traffic_toggle). Standing P2 enrollment.
Extended estimates: enabled. Selection: Option A (re-selected after returning from Step 2); Step 1 re-approved 2026-10-27.

**Cycle estimate: 0.20**

## Original Query

The high traffic mode toggle is kind of an eyesore. Write Step 1 for improving on it.

## Problem

On Tools › Feature Flags, high-traffic mode sits in its own card above the page heading as an unstyled text button ("Enable high-traffic mode") that claims `role="switch"` but looks unlike every other switch on the page, and its failure message appends a long CLI recovery sentence.

## Option A — Restyle as a switch row under the Feature Flags heading (selected)

**Estimate: 0.20**

Drop the separate card and render high-traffic mode as one row directly beneath the Feature Flags heading, reusing the existing flag-row layout: label, one-line consequence ("Public pages may be about two minutes old"), shared `.switch` control and on/off state text. It stays outside the searchable/filterable flag list and keeps its own signed prepare/finalize flow.

- Visually consistent with neighbouring switches; removes a card and the duplicated verb-in-button text.
- Smallest change: template, a little CSS reuse and the existing control script; no API, permission or navigation change.
- Keeps the failure state and recovery action visible, shortened.
- Con: the row looks like a flag but has no key, badges or reset; must not be counted by the toolbar filters.
- Driver: matching row markup without coupling to `feature_flags.js`; uncertainty: theme rendering (Word97/Chicago/Midnight) of the reused switch.

## Option B — Present it as a pseudo-flag inside the flag list

**Estimate: 0.35**

Insert high-traffic mode into the flag list as a row in an "Operations" group, so it participates in search and the Enabled filter.

- Fully uniform with flags; discoverable via search.
- Con: it is instance-local state with a different save path, so the flag toolbar, counts, badges and save handlers need special cases; misleads users into expecting flag semantics (overridden/default/reset).

## Option C — Move it to a compact site-status control elsewhere (previously selected; superseded)

**Estimate: 0.50**

Remove it from Feature Flags and show a small status indicator with the switch on a separate Tools destination or the Tools overview.

- Separates site operation from feature configuration.
- Con: new or changed navigation destination (theme icon mappings, verification in three icon themes), more copy and relocation risk; larger than the problem warrants.

## Recommendation (original)

Option A. It is a complete vertical slice: an operator opens Feature Flags, sees one consistent switch, toggles it through the existing signed flow, and on failure sees a short error with the reload/CLI recovery still available. Verification: real-browser signed toggle on and off, simulated failure, non-manager absence, phone/desktop widths, light/dark and Word97/Chicago/Midnight themes, flag toolbar counts unaffected. No new dependencies, API, schema or navigation changes. Record the result in the [UI copy audit checklist](../ui_copy_audit_checklist.md).

*— Claude Code · 2026-10-27 UTC · Model: claude-opus-5-5*

## Option selection — 2026-10-27 UTC

Request: “Let's go with Option C, please.” Adopted as scope v2, refined to a concrete destination: a dedicated **Tools › High traffic** page (`/tools/high-traffic/`) for flag managers, removing the card from Feature Flags.

- Page: current mode, the shared `.switch` control with on/off state, one line on staleness, and the existing signed prepare/finalize flow; failure shows a short error with reload/CLI recovery visible.
- Navigation: Tools launcher button and Tools subnavigation entry, shown only to viewers who can manage it, with icon mappings in `theme-nav-icons.css` for Word97, Chicago and Midnight.
- Unchanged: API, permissions, CLI, schema; no new dependencies.
- Vertical slice: an operator opens Tools, follows High traffic, toggles it and sees the result or recovery. Verification adds the A checks plus launcher/subnav visibility for managers vs. others and icons verified in each icon theme.
- Not chosen: placing the control on the Tools overview itself, which would crowd the launcher.

*— Revised by Claude Code · 2026-10-27 UTC · Model: claude-opus-5-5*

## Study snapshots

Intake, 2026-10-27 UTC: scope v1, baseline `6e32cd093e76da88820388159de08c95e7bd9a0d`, R1/P2, Claude Code single exposed rater. **0.25 projected total** for a visual cleanup of the toggle, recorded after reading Railspike/ledger guidance but before inspecting the toggle source. Not independent; R1 comparisons and prior scores visible.

Step 1 handoff, 2026-10-27T02:06:46Z: scope v1 unchanged, **0.20 projected total** for Option A, lowered from intake on new understanding (template plus existing switch styles; no API work). Option estimates A **0.20**, B **0.35**, C **0.50** are whole-cycle judgments, not additive or measured effort. Exact R1 hashes verified. Inspected: `templates/pages/feature_flags.php`, `public/assets/high_traffic_control.js`, `.switch` CSS, prior high-traffic plans and UI copy checklist. No implementation, accepted outcome or approval. Planning and ledger remain uncommitted.

*— Claude Code · 2026-10-27 UTC · Model: claude-opus-5-5*

Selection revision, 2026-10-27 UTC: scope v2 (Option C, dedicated Tools page) at the user's request; **0.50 projected total**, adopting the C/v1 estimate unchanged after naming the concrete destination. Intake 0.25 and Step 1 0.20 retained as history. Same baseline, R1/P2 and single exposed rater; additionally inspected `tools_nav.php` and `theme-nav-icons.css`. No implementation or approval.

*— Revised by Claude Code · 2026-10-27 UTC · Model: claude-opus-5-5*

Approval recorded, 2026-10-27 UTC: user explicitly sent “Approved Step 1.” Option C/scope v2 approved for Step 2.

*— Revised by Claude Code · 2026-10-27 UTC · Model: claude-opus-5-5*

## Option reselection — 2026-10-27 UTC

Request: “Let us return to Step 1, and choose Option A, please.” Adopted as scope v3: Option A as originally described and recommended — a switch row directly beneath the Feature Flags heading, reusing flag-row layout and the shared `.switch`, outside the flag search/filter list, with the existing signed flow and a shortened failure message that keeps recovery visible. Option C/scope v2 and its approval are superseded; the [Step 2 draft](./high_traffic_toggle_step2_feature_description.md) for C is retained as history only and will be rewritten after renewed approval. No new destination, navigation or icon changes.

Study revision: **0.20 projected total**, adopting the A/v1 estimate unchanged. Intake 0.25, Step 1 0.20, C selection 0.50 and Step 2 0.50 retained as history. Same baseline, R1/P2 and single exposed rater; no new inspection. No implementation or approval.

*— Revised by Claude Code · 2026-10-27 UTC · Model: claude-opus-5-5*

Approval recorded, 2026-10-27 UTC: user explicitly sent “Approved Step 1” for Option A/scope v3.

*— Revised by Claude Code · 2026-10-27 UTC · Model: claude-opus-5-5*
