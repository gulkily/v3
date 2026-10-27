> **Feature plan:** [Step 1](./archive_export_contention_step1_solution_assessment.md) · [Step 2](./archive_export_contention_step2_feature_description.md) · [Step 3](./archive_export_contention_step3_development_plan.md) · [Step 4](./archive_export_contention_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/archive_export_contention; [ledger](../effort_estimation_trial.md#cycle-v3archive_export_contention); standing P2 enrollment
Extended estimates: enabled
Cycle estimate: 0.90
Status: Step 1 Option B approved; implementation unapproved

## Original Query

Write step 1 for a fix.

## Understood Intent

Fix the reported `Timed out waiting for execution lock: /home/qdb/v3/state/cache/image-export.lock` when downloading `https://qdb.us/downloads/repository.tar.gz`, including safe retry and diagnosis if an export remains stuck.

Problem: All repository/image archive formats share a zero-timeout export lock, so overlapping requests fail almost immediately and expose an internal path instead of useful recovery.

Evidence: Local lock reproduction failed after 0.1 seconds and succeeded after release with the file still present; the controller returns this exception as HTTP 409. Production holder/duration remain unknown; the live probe received no response within 15 seconds, which does not establish its cause.

## Option A — Bounded waiting and actionable recovery

**Estimate: 0.45**

Allow a short, bounded wait for an active export, then return a temporary-busy response with retry guidance and no internal paths. Preserve snapshot serialization and revision checks; document how operators identify and recover a stalled export safely.

- Pros: Small complete download/retry slice across tar, ZIP and images; preserves existing archive integrity.
- Cons: Repeated requests still rebuild archives; long exports can exhaust the wait. Concurrency and worker-budget verification drive effort; production export duration is uncertain.

## Option B — Reuse completed archive builds

**Estimate: 0.90**

Coordinate requests around reusable, validated snapshots so compatible downloads share a completed build, with bounded waiting and safe retry while construction is underway.

- Pros: Reduces repeated compression and contention; better for frequent downloads.
- Cons: Adds storage, eviction, invalidation and crash recovery. Correct freshness includes image/content changes, retractions and requested revisions, not just a Git-head cache key; this is the main complexity and uncertainty.

## Option C — Immediate busy response only

**Estimate: 0.25**

Keep immediate refusal during another export, replacing the raw lock exception with a temporary-busy response and concise retry guidance, plus operator recovery instructions.

- Pros: Least operational change; no waiting web workers.
- Cons: Ordinary overlapping downloads still fail; error/retry verification drives effort. Appropriate only if immediate retry is an acceptable outcome.

## Recommendation

Choose B, as requested: direct downloads reuse complete, fresh archives; concurrent compatible requests share a build, with bounded waiting and safe retry. Cover tar, ZIP and images as one releasable slice, including freshness/revision validation, bounded storage, safe eviction during downloads, failed/interrupted-build cleanup and operator recovery. Never serve partial or invalidated archives. Verify cache hits, same/different-format concurrency, content/image changes and retractions, revision mismatch, interrupted builds, disk exhaustion and successful retry through normal routes. Check visible recovery on desktop and phone and record copy verification in the audit checklist. Production ownership remains unconfirmed; diagnose a persistent stall before claiming host recovery. No automatic lock deletion or worker termination, new dependencies, schema changes or export-format changes proposed.

Request: “Use Option B.”
Disposition: Select B and supersede the v1 recommendation of A; reusable-build lifecycle is now in scope. Step selection does not approve advancement.

## Study checkpoints and approval

Original scope: v1 / Option A; starting revision 28530e1b59b6b5d98d314bc080dda94588cc4217; outcome/recovery as Option A, including investigation, planning, implementation, verification and documentation. Study-ledger bookkeeping is included.
Reference: R1; all three frozen host-local source hashes verified; 1.0 means the largest comfortably approvable releasable cycle. Option scores are alternative whole-cycle judgments, not additive or hours.
Intake: 2026-10-25T22:41:57Z; 0.45; Codex single exposed rater after prior-turn source inspection/reproduction and reference review; no pristine pre-inspection estimate claimed.
Step 1 handoff: 2026-10-25T22:41:57Z; 0.45 reaffirmed; same v1/R1/P2 and exposure; no measured labor or accepted outcome.
Current scope: v2 / Option B; same starting revision; reusable validated archives and lifecycle/recovery as recommended above, including prior investigation/planning and study bookkeeping.
Step 1 revision: 2026-10-26T00:28:58Z; 0.90 projected total, replacing v1 0.45 because the user selected reusable-build lifecycle scope. Original intake/handoff preserved; B 0.90 reaffirmed, A 0.45 and C 0.25 unchanged. R1 hashes reverified; same exposed single Codex rater/P2; no measured labor or accepted outcome.
Approval history: Awaited Approved Step 1 at the preceding handoffs; no implementation, commit or deployment performed.
Approval: User explicitly approved Step 1 Option B; recorded 2026-10-26T00:35:42Z; proceed to Step 2 only.

*— Codex · 2026-10-25 UTC*

*— Revised by Codex · 2026-10-26 UTC*
