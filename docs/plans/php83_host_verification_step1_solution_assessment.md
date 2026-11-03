> **Feature plan:** [Step 1](./php83_host_verification_step1_solution_assessment.md) · Step 2 (not started) · Step 3 (not started) · Step 4 (not started)

Debug mode: enabled (project marker)
Effort study: railspike-effort / v3/php83_host_verification; [P2 ledger](effort_estimation_trial.md#cycle-v3php83_host_verification)
Extended estimates: enabled

**Cycle estimate: 0.65**

## Original Query

Please write Step 1 for each.

## Understood Intent

Plan actual-host verification after local memory fixes, using the [capacity study](application_capacity_study/capacity_report.txt) and existing [shared-host work](qdb_shared_host_traffic_capacity/qdb_shared_host_traffic_capacity_step4_implementation_summary.md). This is a new evidence cycle, not a second static-routing implementation.

## Problem

Local PHP 8.1/development-server samples cannot establish PHP 8.3/FastCGI worker capacity or Apache delivery, despite the user's earlier successful production build/activation.

## Option A — Operator-run acceptance packet

**Estimate: 0.40**

Prepare a bounded replay packet for the operator to run on the authorized host, then reconcile returned logs, route checks and recovery evidence into a qualified report.

- Pros: Requires no agent remote access; operator controls timing and account impact.
- Cons: Missing worker/resource observations can limit conclusions; evidence remains operator-reported rather than agent-observed.
- Driver/uncertainty: Completeness and provenance of returned measurements, excluding waiting time.

## Option B — Supervised replay on an approved host staging copy (Recommended)

**Estimate: 0.65**

Use a production-shaped staging copy with the target PHP/Apache/FastCGI configuration to observe normal rebuild/publication, actual static/fallback delivery and a bounded concurrent read/rebuild campaign.

- Pros: Can correlate requests, workers, memory, activation and recovery directly; closes the specific environment gap.
- Cons: Needs an identified target, access and operator/provider limits before execution; staging differences still limit production extrapolation.
- Driver/uncertainty: Host observability and representative staging, not unbounded infrastructure setup.

## Recommendation and boundary

Choose B after local QDB selection/rebuild acceptance; import compatibility is required only for archive replay. Step 2 must name the target, allowed operations, corpus handling and evidence destination; Step 3 fixes request/duration/resource stops and recovery. No connection, deployment, live load, destructive production interruption, server-setting change or tool installation is authorized by this planning request. If access/observability is unavailable, retain blocked evidence or explicitly select A; do not substitute local success.

Reuse: [capacity runbook](../runbooks/qdb_shared_host_capacity.md), `scripts/qdb_capacity_probe.js` workload definitions, existing Apache guards and the study's corrected collector. No k6 installation implied; use available approved tools. Compare static hits, cookie/query fallbacks and shared consumers; require complete activation and isolated failure/retry. Deliver reproducible commands, measured limits and remaining gaps, not runtime fixes.

Concepts touched: [resource measurement](../concepts/resource_measurement.md) changed; [publication](../concepts/static_publication.md) and [rebuild](../concepts/read_model_rebuild.md) used unchanged. Documentation/evidence work only; zero proposed dependencies. Preserve the user's earlier 314.961-second successful activation separately; it is not new concurrency evidence.

## Study checkpoint and approval

2026-11-01 UTC; scope v1/B; baseline 75b5e30eb40b16f6297f69d83214b4e4926499b2; R1/P2 standing enrollment, all frozen hashes verified. First recorded intake after prior study/source exposure: 0.65; Step 1 handoff reaffirms 0.65. Codex single exposed rater; pristine pre-inspection intake unavailable, not reconstructed. Options estimate complete scoped cycles, not hours or additive forecasts. Separate follow-up to v3/application_capacity_study, not a duplicate study outcome. Plan only; no implementation, host execution, commits or accepted outcome. Ledger/debug updates stay uncommitted. Await Approved Step 1 for this cycle; future branch reuse is not assumed.

*— Codex · 2026-11-01 UTC*
