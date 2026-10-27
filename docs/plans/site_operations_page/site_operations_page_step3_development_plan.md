> **Feature plan:** [Step 2](./site_operations_page_step2_feature_description.md) · [Step 3](./site_operations_page_step3_development_plan.md) · [Step 4](./site_operations_page_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/site_operations_page`; [ledger](../effort_estimation_trial.md#cycle-v3site_operations_page). Standing P2 enrollment.
Extended estimates: enabled. Approval: Step 2 approved 2026-10-27; Step 3 drafted.
Follow-up to `v3/high_traffic_toggle`; intended branch reuse: `feat/high-traffic-toggle`.

**Cycle estimate: 0.40**

## Completion Contract

- Normal entry: Tools launcher or subnav → **Operations** (`/tools/operations/`).
- End-to-end outcome: a manager signs enable/disable of high-traffic mode from Operations; state matches `./v3 high-traffic status`. Non-managers see on/off read-only.
- Required recovery: short error with reload and `./v3 high-traffic restore`; switch retryable by keyboard.
- Deployment/external: none; no production mode change, Apache, API or schema change.
- Release condition: Feature Flags no longer has the row; Operations never cached by high-traffic/static paths; icons and layout verified in Word97/Chicago/Midnight plus light/dark at phone/desktop; runbook/CLI reference updated.

## Key Risks

- **Caching of the new route:** a private tools page served from high-traffic or static artifacts would leak stale/manager state. Validate in Stage 1 with the existing route allowlist/hint tests; mitigation: classify with the other tools routes, assert `hint('/tools/operations/')` is false.
- **Status exposure:** `highTrafficStatus()` errors can contain internal text. Validate in Stage 1 with a non-manager render; mitigation: pass only the effective mode to non-managers.
- **Test coupling:** browser and smoke tests reference the Feature Flags location. Move in Stage 1.

## Stage 1

**Estimate: 0.18**

- Goal: Operations page with the working high-traffic row, reachable from Tools, and the row removed from Feature Flags.
- Dependencies: approved Step 2; branch reuse confirmed.
- Expected changes:
  - `ToolsPageSupport` registry: `operations` entry (label “Operations”, `/tools/operations/`, one-line description).
  - `Application`: GET route and access/route classification beside other tools.
  - `ToolsPageController::operations(): string` rendering `templates/pages/operations.php` with tools nav, row (manager switch or disabled read-only switch with existing read-only hint), and `high_traffic_control.js` only for managers.
  - `feature_flags.php`/`featureFlags()`: drop high-traffic row, data and script.
  - Tests: `high_traffic_control.mjs` targets Operations and asserts Feature Flags has no row; `LocalAppSmokeTest`/`HighTrafficTest` assert nav entry, active state, read-only render without error text and no cache hint.
- Verification approach: browser test (signing on/off, failure, keyboard retry, unauthorized 403, 13 themes overflow); `php tests/run.php HighTrafficTest LocalAppSmokeTest FeatureFlagEvaluatorTest`; `feature_flags_toolbar.mjs`; PHP lint.
- Estimate details: driver: new route/controller/template plus test moves; uncertainty: route classification lists spread across `Application`.
- Risks or open questions:
  - Impact: page cached or status text leaked.
  - Early warning / validation: hint/allowlist and non-manager render assertions.
  - Mitigation: classify as private tools route; mode-only data for non-managers.
- Canonical components/API contracts touched: tools registry/nav, Feature Flags row/switch styles, `high_traffic_control.js`; prepare/finalize APIs unchanged.

## Stage 2

**Estimate: 0.07**

- Goal: Operations icon in the three icon themes and visual verification.
- Dependencies: Stage 1.
- Expected changes: `theme-nav-icons.css` icon (launcher and subnav selectors, plus the shared `::before` sizing list); small layout CSS only if needed.
- Verification approach: real-browser screenshots of launcher, subnav and Operations row in Word97/Chicago/Midnight, light/dark at 375/1150px; inspect each icon.
- Estimate details: driver: hand-drawn 16px SVG in existing style; uncertainty: icon legibility at small size.
- Risks or open questions:
  - Impact: missing or misaligned icon in a theme.
  - Early warning / validation: per-theme screenshots.
  - Mitigation: follow existing icon pattern exactly.
- Canonical components/API contracts touched: `theme-nav-icons.css`.

## Stage 3

**Estimate: 0.05**

- Goal: documentation and closeout.
- Dependencies: Stage 2.
- Expected changes: `docs/runbooks/high_traffic_mode.md` UI path → Tools → Operations; `docs/reference/v3_cli.md` mention if it names the UI location; UI copy audit checklist entry; Step 4 summary closeout and ledger.
- Verification approach: link/path checks, `git diff --check`, grep for stale “Feature Flags → High-traffic” references.
- Estimate details: driver: doc edits; uncertainty: low.
- Risks or open questions: stale references elsewhere; mitigation: repository grep.
- Canonical components/API contracts touched: runbook, CLI reference.

## Study checkpoint

Step 3 handoff, 2026-10-27 UTC: scope v1, start `c462cc92`, R1/P2, Claude Code single exposed rater. **0.40 projected total reaffirmed**, independently judged. Stage-work originals S1 **0.18**, S2 **0.07**, S3 **0.05**; not summed. Additionally inspected Application tools routes, icon CSS and test references. No implementation or accepted outcome. Awaiting `Approved Step 3`; planning and ledger uncommitted.

*— Claude Code · 2026-10-27 UTC · Model: claude-opus-5-5*
