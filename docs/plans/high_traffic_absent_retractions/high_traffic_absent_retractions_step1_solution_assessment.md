> **Feature plan:** [Step 1](./high_traffic_absent_retractions_step1_solution_assessment.md) · [Step 2](./high_traffic_absent_retractions_step2_feature_description.md) · [Step 3](./high_traffic_absent_retractions_step3_development_plan.md) · [Step 4](./high_traffic_absent_retractions_step4_implementation_summary.md)

Debug mode: enabled (project marker).
Effort study: `railspike-effort`, cycle `v3/high_traffic_absent_retractions`; [host ledger](../effort_estimation_trial.md#cycle-v3high_traffic_absent_retractions), standing enrollment R1/P2.

## Original Query

We need to engineer a fix as soon as possible, as this is a production issue. Can you do that?

## Understood Intent

Restore safe high-traffic homepage serving on zenmemes after retained retractions for two physically deleted replies blocked cache generation. User supplied [production diagnosis](https://zenmemes.com/threads/thread-20261024044151-755007ca) and confirmed disabling high-traffic mode restored the homepage, with slower rendering. Latest instruction: “OK, it's off, and the home page loads. It's slow now though... Please continue with your fix.”

## Problem

Confirmed absent retraction targets prevent every cold shared-cache publication, even when ordinary rendering works; the generic busy response hides this persistent condition.

## Option A — Safe publication with absent-target exclusions

Extend the existing absent-target snapshot approach to high-traffic HTML publication, verifying that candidate content excludes missing targets and their stale generated content, while retaining strict validation of other evidence and concurrent changes.

- Pros: fixes the normal cached homepage without canonical repair, restored deleted prose, or unlimited live fallback; reuses existing project machinery.
- Cons: requires stale-read-model, surviving-reply, target-reappearance and concurrent-publication verification; relaxing a shared guard indiscriminately is unsafe.

## Option B — Repair accepted deletion evidence

Use the existing trusted source-removal process to reconcile the two deleted replies and retain strict high-traffic publication rules.

- Pros: preserves the existing guard contract and can repair attributed effects where trusted historical evidence is sufficient.
- Cons: requires production data changes and per-record authority review; does not make future valid absent-target publication resilient and may be blocked by incomplete provenance.

## Recommendation

**Option A**, a focused follow-up to [completed snapshot work](../absent_retraction_snapshot/absent_retraction_snapshot_step4_implementation_summary.md). Deliver successful cold generation, refresh and warm Apache serving with removed content absent and surviving content preserved. Keep malformed/unreadable/unsafe evidence failures, private-instance restrictions and publication fencing; safely reject/retry candidates whose evidence or source state changes. Verify the direct/queued offline snapshot fix remains intact. Include actionable operator recovery and the missing refresh-cron setup in release instructions. No broad performance rewrite, automatic production enablement, canonical repair, new dependencies or schema change proposed. Address the offline-bootstrap uncaught-error path only if required for the approved recovery contract; otherwise record separately.

## Study snapshots and approval

2026-10-24T04:47:13+00:00: intake and Step 1 handoff, scope v1, baseline `bce2c29e37be4a06a25e2a3c6e022249e8d90101`, deployed failure `527a64c46`, Codex single exposed rater. **0.65 projected total**, including prior diagnosis, planning, implementation, verification and recovery documentation; not remaining work or hours. Earlier local reproduction, production logs/report, guard/cache source and completed snapshot work were visible before enrollment; no pristine intake is claimed. R1 report/diff/feedback hashes match the frozen manifest. Compared with 0.35 toolbar integration, 0.60 composer integration and 1.00 startup recovery: concentrated publication correctness and concurrency verification make this larger than a narrow routing fix, while reusing snapshot evidence classification limits scope. No measured labor, independent rating or accepted outcome. New cycle related to `v3/absent_retraction_import`; no duplicate outcome claimed. Dependency inventory: zero additions/updates. Await **Approved Step 1**; implementation is unapproved.

2026-10-24T05:06:56+00:00: User explicitly supplied **Approved Step 1**; Option A continues in [Step 2](./high_traffic_absent_retractions_step2_feature_description.md). Original scope/score and historical pending status above are preserved.
