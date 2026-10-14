> **Feature plan:** [Step 1](./private_messaging_live_navigation_step1_solution_assessment.md) · [Step 2](./private_messaging_live_navigation_step2_feature_description.md) · [Step 3](./private_messaging_live_navigation_step3_development_plan.md) · [Step 4](./private_messaging_live_navigation_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

- Feature Flags toggle/reset → confirmed eligible Messages navigation in place → open Messages or toggle back; preserve focus, scroll, other settings, and messaging data. Recover saved-setting/refresh failures through explicit reload without resubmitting writes.
- Release: both initial states, repeated toggles, eligibility, overrides, obsolete responses, unread/sync restart, and recovery verified in isolated PHP/browser fixtures. No migration, external service, production deployment, or cross-tab broadcast.
- After approval: branch, organize/commit approved plans and debug log, then verify/commit each stage with its summary. Target ≤1 hour per stage; split oversized work before implementation.

## Key Risks

- **High risk:** Wrong-user navigation exposes misleading access. Renderer owns eligibility; validate guest/member/operator and members-only cases before Stage 1 integration; reuse canonical rendering.
- **High risk:** Re-enable remains stuck or duplicates work. Scripts currently load conditionally, bind at page startup, and retain disabled state. Validate lifecycle boundaries before Stages 2–3; use repeatable start/stop and generation checks.
- **High risk:** Save/refresh races misreport settings. Save currently updates only its row. Stage 1 separates confirmation from refresh failure; Stages 2–3 reject stale results without losing pending-send recovery.

## Stage 1

- Goal: Confirmed disable immediately removes Messages on the settings page.
- Dependencies: Planning commit; eligibility matrix reviewed.
- Expected changes: Reuse canonical navigation through a no-store, read-only `GET /api/feature_flags/navigation` response containing navigation HTML, messaging eligibility/viewer, and required asset URLs. Wire signed toggle/reset success to refresh navigation; stop known-disabled activity immediately; show saved-but-stale feedback with reload recovery.
- Verification approach: PHP eligibility/override/session tests; browser disable, rejected save, and refresh-failure checks; preserve focus/scroll.
- Risks or open questions: Stale/cached identity misrenders access; validate session resolution and no-store behavior before dependents.
- Canonical components/API contracts touched: Application/RouteServices, ToolsPageController, TemplateRenderer/shared nav, feature_flags.js; planned renderer `navigationState(?array $viewerProfile, string $activeSection): array`.

## Stage 2

- Goal: Enable restores one usable Messages link and unread state without reload.
- Dependencies: Stage 1 verified and committed.
- Expected changes: Initialize missing messaging dependencies once on this settings page; support availability reset and unread `start(navElement)`/`stop()`; remove obsolete notice/observer state after confirmed eligibility. Preserve normal startup elsewhere.
- Verification approach: Initially-disabled enable and off/on browser journeys; correct unread count or existing unavailable feedback; no duplicate listeners/links; successful Messages entry.
- Risks or open questions: Delayed disable responses undo enable; validate generation guards and failed asset loading before sync integration.
- Canonical components/API contracts touched: Shared availability, unread module, canonical asset resolution, settings-page coordinator; extend availability `setEnabled(enabled)` for confirmed settings-page transitions.

## Stage 3

- Goal: Repeated toggles safely stop/resume bounded history sync.
- Dependencies: Stage 2 verified and committed.
- Expected changes: Add sync `start(navElement)`/`stop()`; cancel queued work and ignore obsolete completions across transitions. Keep existing budgets, durable progress, and conversation-page recovery.
- Verification approach: Scheduling/availability tests with delayed responses, rapid toggles, and identity changes; browser traffic stops disabled and resumes once enabled; retained private state.
- Risks or open questions: Old tasks restart or overwrite current state; exercise cancellation/generation ordering before release checks.
- Canonical components/API contracts touched: History-sync lifecycle, shared availability, settings coordinator; existing messaging endpoints unchanged.

## Stage 4

- Goal: Verify and document the complete operator journey.
- Dependencies: Stages 1–3 verified and committed.
- Expected changes: Extend signed operator browser journey and operator/messaging documentation, including reload fallback.
- Verification approach: Run focused flag/messaging tests, both messaging browser runners, and `php tests/run.php`; verify override locks, reset, two initial states, stale responses, refresh failure, theme navigation, and unchanged conversation recovery. Check links/diff; compare unrelated failures with baseline.
- Risks or open questions: Missing browser prerequisites or new regressions block completion; resolve before handoff.
- Canonical components/API contracts touched: Existing feature-flag/messaging fixtures and tests, README, messaging architecture/API help.
