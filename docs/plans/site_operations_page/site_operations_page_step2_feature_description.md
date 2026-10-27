> **Feature plan:** [Step 2](./site_operations_page_step2_feature_description.md) · [Step 3](./site_operations_page_step3_development_plan.md) · [Step 4](./site_operations_page_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/site_operations_page`; [ledger](../effort_estimation_trial.md#cycle-v3site_operations_page). Standing P2 enrollment.
Extended estimates: enabled
Follow-up to [`v3/high_traffic_toggle`](../high_traffic_toggle/high_traffic_toggle_step4_implementation_summary.md#enhancement-requests-after-closeout); intended branch reuse: `feat/high-traffic-toggle` (unmerged; reuse to be confirmed at Step 4 — not requested, so Step 4 uses new branch `feat/operations-page` from it). Step 1 skipped: the user chose a dedicated page and delegated the name.

**Cycle estimate: 0.40**

## Request

“Move high traffic mode setting to its own page, but don't call the whole page high traffic mode, pick a name for it that would include other things in the same category.”

Chosen name: **Operations** (`/tools/operations/`). It covers how the site is served and kept running — high-traffic mode now, and later things like static rebuilds, cache refresh/purge and task-queue status — without overlapping System State (version and read-model health) or Feature Flags (product behaviour).

## Problem

High-traffic mode is a serving/operations switch living on Feature Flags, where it's mixed in with product settings and is the most prominent row on an already dense page.

## User stories

- As an operator, I want an Operations tool for site-serving controls so I can find high-traffic mode without scanning feature flags.
- As an operator whose switch fails, I want a short error with visible recovery so I can retry or use the CLI.
- As a non-manager, I want to see whether high-traffic mode is on without being able to change it, as with read-only feature flags.

## Core requirements

- New Tools destination **Operations** at `/tools/operations/`, in the Tools launcher and subnavigation for everyone, like other tools.
- The page shows a High-traffic mode row reusing the row/switch layout and the existing signed prepare/finalize flow, staleness line and short failure recovery. Managers get a working switch; others a disabled switch with the read-only hint and no error details.
- The high-traffic row is removed from Feature Flags.
- Icon mappings for the launcher button and subnavigation in Word97, Chicago and Midnight.
- The runbook's UI path and CLI reference point to Tools → Operations.

## Delivery scope and completion boundary

Application change: tools registry, route/access classification, a new Operations template and controller action, Feature Flags template, the existing control script, nav icon CSS, browser/PHP tests, runbook/CLI reference, UI copy audit checklist and this cycle's planning/ledger. No API, permission, CLI, Apache, schema or dependency changes; no production mode change; no other operations controls added yet.

Normal entry: Tools → Operations → toggle. Recovery: short error, reload, `./v3 high-traffic restore`. Release condition: real-browser signed on/off and failure on the new page, non-manager read-only view, Feature Flags without the row, page never served from the high-traffic cache, phone/desktop and the three icon themes verified, including icons.

## Risks

- **Caching:** the new route must stay out of static/high-traffic artifacts. Validate early with the route allowlist tests; mitigation: classify it with the other private tools routes.
- **Status exposure to non-managers:** the status call can return internal error text. Validate the read-only render first; mitigation: show only on/off to non-managers.
- **Test coupling:** `high_traffic_control.mjs` and smoke tests target Feature Flags. Move them with stable hooks.

## Shared component inventory

- `ToolsPageSupport` registry, `tools.php` launcher, `partials/tools_nav.php`: extend with Operations.
- `ToolsPageController` and `RouteServices::highTrafficStatus()`: reuse; move high-traffic data from Feature Flags to an Operations action.
- Feature Flags row markup/`.feature-flag-*` and `.switch` styles: reuse for the Operations row.
- `high_traffic_control.js`, `browser_signing.js`, prepare/finalize APIs: reuse unchanged.
- `theme-nav-icons.css`: extend for `/tools/operations/`.

## User flow

1. Open Tools → Operations.
2. See High-traffic mode with its current state.
3. A manager flips it and signs; the switch updates.
4. On failure, read the short error and retry, reload or use the CLI.

## Success criteria

- Operations appears in the launcher and subnav with an icon in all three icon themes; Feature Flags no longer shows the row.
- Signed enable/disable from Operations matches `./v3 high-traffic status`.
- Failure shows recovery and is retryable by keyboard.
- Non-managers see a disabled switch with state only; prepare API still returns 403.
- No overflow at phone width; readable in light/dark and the icon themes.

## Study snapshots and approval

Intake and Step 2 handoff, 2026-10-27 UTC: scope v1, starting revision `c462cc92` on `feat/high-traffic-toggle`, R1/P2, Claude Code single exposed rater. **0.40 projected total**. Not pristine: the toggle code, tools registry and the predecessor's superseded Option C (0.50, including manager-only nav) were inspected or estimated earlier. Lower than that Option C because nav is listed for everyone, avoiding a registry capability filter. Step 1 not applicable. No implementation, measurement or accepted outcome. No other enhancement requests. Awaiting `Approved Step 2`; planning remains uncommitted.

*— Claude Code · 2026-10-27 UTC · Model: claude-opus-5-5*

Approval recorded, 2026-10-27 UTC: user explicitly sent “Approved Step 2.”

*— Revised by Claude Code · 2026-10-27 UTC · Model: claude-opus-5-5*
