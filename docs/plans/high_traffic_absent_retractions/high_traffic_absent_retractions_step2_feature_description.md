> **Feature plan:** [Step 1](./high_traffic_absent_retractions_step1_solution_assessment.md) · [Step 2](./high_traffic_absent_retractions_step2_feature_description.md) · [Step 3](./high_traffic_absent_retractions_step3_development_plan.md) · [Step 4](./high_traffic_absent_retractions_step4_implementation_summary.md)

Debug mode: enabled.
Effort study: `railspike-effort`, cycle `v3/high_traffic_absent_retractions`; [host ledger](../effort_estimation_trial.md#cycle-v3high_traffic_absent_retractions), R1/P2.

## Problem

zenmemes cannot generate shared HTML when valid retained retractions refer to physically absent targets. Disabling high-traffic mode restores availability but makes ordinary visits slower.

## User stories

- As a visitor, I want the ordinary homepage and thread pages to load from a safe shared cache so that deleted records do not cause a permanent busy page.
- As an operator, I want verified generation, refresh and recovery steps so that I can restore cached serving without editing canonical evidence.

## Core requirements

- Permit cold generation and refresh for readable, valid retractions whose targets are confirmed absent, including both production reply IDs; exclude stale target content and attributable generated content, preserving surviving replies and thread structure.
- Reject malformed, missing, unreadable or unsafe evidence and untrusted removal authority; preserve private-instance restrictions and unrelated retraction checks. No canonical repair or restored deleted prose is required.
- Bind publication to validated candidate content and evidence. Concurrent source reappearance, evidence changes or mode changes must not publish an unsafe candidate; a rejected attempt remains retryable, and later attempts validate present sources normally.
- Preserve bounded generation, existing eligible pages on safe refresh failure, and supported disable/restore recovery; do not introduce an unlimited live-render fallback.
- Retain direct/queued offline base/update exclusions and other strict publication consumers. Include deployment verification, rollback and refresh-cron setup; no schema or dependency changes proposed.

## Delivery scope and completion boundary

Application change, focused tests and operator documentation; cycle plans, debug records and study-ledger updates included. Extend existing publication/evidence machinery rather than bypassing shared safety checks. Deliver the normal visitor flow through successful cold generation, refresh and warm Apache serving. Exclude broad performance tuning, canonical repair and automatic production changes. Offline-bootstrap fatal-error handling is a separate follow-up unless a later approved revision includes it.

## Known environment and flow

1. Production currently serves zenmemes through ordinary PHP with high-traffic mode off; retain this recovery state while preparing the release.
2. After operator deployment in the same PHP/CLI environment, generate the cache with the two absent-target conditions and configure the documented once-per-minute refresh job.
3. The operator enables high-traffic mode, requests `/` and representative surviving thread pages, and verifies HTTP 200 plus a subsequent `X-V3-High-Traffic: hit`; refresh must advance generation time while removed content stays absent.
4. On failure, the operator can inspect the reported cause, disable or restore routing, verify ordinary `/` returns 200 and retry after correction.

Local application/Apache acceptance is required preparation; it does not establish DreamHost production success. Release handoff must mark production verification pending until the host checks above are recorded. Deployment and re-enablement require operator action or separate authorization.

## Risks

- **Stale content leakage:** validate stale target/reply/root/summary and attributable-effect fixtures first; block release until exclusions preserve independent content.
- **Races or weakened authority:** exercise reappearance, unrelated evidence changes and mode withdrawal before publication; retain rejection and retry without granting permanent removal authority.
- **Host/runtime mismatch:** verify bounded isolated rendering and actual Apache hits locally; document same-user/configuration and cron checks, keep production rollback available.

## Shared component inventory

Reuse canonical retraction classification, publication fencing and existing high-traffic rendering/controls for `/`, `/threads/`, `/about/` and visible thread pages. Preserve ordinary PHP board/thread rendering, static-release/import publication, trusted source-removal tooling, and offline direct/queued base/update generation and reader exclusions through regression coverage; no new UI or API surface proposed.

## Success criteria and study checkpoint

Cold generation, refresh and warm serving succeed with both production missing-target IDs; removed bytes remain absent and surviving replies visible. Invalid-evidence and race cases reject safely, recovery/retry works, and focused shared-consumer regressions pass. Production remains an explicit host acceptance gate.

2026-10-24T05:06:56+00:00: Step 1 Option A approved by user. Scope v1, original baseline `bce2c29e37be4a06a25e2a3c6e022249e8d90101`, R1/P2, Codex single exposed rater; **0.65 total reaffirmed**, including earlier diagnosis and all planning/verification/recovery work. Prior source/report/reproduction exposure retained; no new implementation inspection, measured labor, independent rating or accepted outcome. Frozen R1 hashes previously verified and unchanged. Zero dependency inventory. Await **Approved Step 2**; no implementation authorized.

2026-10-24T05:09:04+00:00: User supplied **Approved Step 2**. Proceeded to [Step 3](./high_traffic_absent_retractions_step3_development_plan.md); requirements and prior score preserved.
