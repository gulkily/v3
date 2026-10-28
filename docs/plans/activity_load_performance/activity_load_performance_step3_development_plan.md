> **Feature plan:** [Step 1](./activity_load_performance_step1_solution_assessment.md) · [Step 2](./activity_load_performance_step2_feature_description.md) · [Step 3](./activity_load_performance_step3_development_plan.md) · Step 4

Debug mode: enabled
Effort study: railspike-effort / v3/activity_load_performance; [ledger](../effort_estimation_trial.md#cycle-v3activity_load_performance)
Extended estimates: enabled
Status: Step 3 approved; implementation authorized.
**Cycle estimate: 0.95**

## Completion Contract

Both zenmemes.com Activity views read indexed evidence/counts through normal opening, filtering, paging, sorting and selection; preserve access, source links and recovery without read-time reconstruction. Deliver application/tests/spec/recovery guidance and cycle/study documentation, with zero dependencies. Verify previous-schema startup, interrupted updates and normal rebuild. Production resolution requires the user's ordinary cookies/account/phone/desktop flow after separately authorized rollout; local results alone do not close it. No deployment, broad startup redesign, full-commit/source redesign or retraction-policy changes.

## Key Risks

- **High risk:** Stale evidence/counts or deleted prose. The [writer audit](./activity_load_performance_investigation.md#step-3-writer-audit--2026-10-27) identifies direct inserts and rebuild paths; transaction/parity tests must cover them before switching readers.
- **High risk:** Bulk commits/shared keys amplify indexing. Reuse stored signature results; group work by affected source/commit/key and measure before adoption. No per-event full manifests.
- Intermittent production delay remains unisolated. Stage 1 separates startup/locks from Activity work; broader changes require returning to Step 2, not silently widening this plan.

## Stage 1 — Baseline and read-cost contract
**Estimate: 0.08**

- Goal/changes: Establish bounded instrumentation and representative signed/unsigned, bulk-commit and source-less fixtures; record latency, size and update costs.
- Dependencies: Step 3 approval; feature branch and planning commit.
- Verification: Reproduce standard delay locally where possible; isolate both initial/selection paths and shared startup, without production load testing.
- Risks: Do not mistake fast anonymous samples for user acceptance; unresolved broader causes block a resolution claim.
- Components: Existing Activity/rebuild/browser test harnesses; investigation notes.
- Stage-work driver/uncertainty: Shared baseline owner; production reproducibility.

## Stage 2 — Compact evidence during rebuilding
**Estimate: 0.18**

- Goal/changes: Store relevant evidence/readiness beside existing Activity data; reuse reaction-signature results and batch commit lookups. Define updater/read interfaces; no HTML storage.
- Dependencies: Stage 1; current field semantics captured.
- Verification: Rebuilt evidence parity, missing keys/history, bulk commits, storage delta; schema version/spec, old-version startup and normal rebuild checks in this change set.
- Risks: Schema upgrade and duplicate metadata; retain transactional rebuild/recovery before changing readers.
- Components: ReadModelSchema/Metadata/Builder, ReactionSignatureIndex, shared Activity evidence helper.
- Stage-work driver/uncertainty: Evidence extraction; legacy signature variants.

## Stage 3 — Evidence follows writes and recovery
**Estimate: 0.18**

- Goal/changes: Maintain affected evidence in incremental transactions and direct insert paths; keep rebuild/import/delete/key-change paths complete and cache retirement intact.
- Dependencies: Stage 2; audit checklist covers every producer.
- Verification: Incremental-versus-rebuild parity for posts, labels/reactions, identities/approvals, flags, profile changes, retractions/deletions, imports and source/key changes; rollback/retry cases.
- Risks: Shared-key fan-out or missed direct writers; source-less records have explicit empty evidence, never implicit reconstruction.
- Components: IncrementalReadModelUpdater, LocalWriteService, CodexHandoffStore; existing candidate rebuild/promotion and invalidation.
- Stage-work driver/uncertainty: Mutation wiring; affected-key discovery.

## Stage 4 — Maintained counts and page positions
**Estimate: 0.15**

- Goal/changes: Maintain category totals and local-event revision on writes/rebuild; align bounded page queries/indexes with existing ordering. Respect feature-flag-dependent membership.
- Dependencies: Stage 3; shared membership definition fixed.
- Verification: Exact totals versus existing predicates, ties, local inserts/rebuilds and expired cursors; schema/spec/version/startup checks for any additional schema/query change.
- Risks: Count drift and cursor invalidation; transactional old/new membership updates, no full-history hashing on reads.
- Components: Shared Activity predicates/projection maintenance; schema, direct writers, PageNavigation contract.
- Stage-work driver/uncertainty: Membership parity; policy variants.

## Stage 5 — Standard Activity reads stored data
**Estimate: 0.10**

- Goal/changes: Switch standard list/evidence/paging to indexed results; preserve honest unavailable/retry behavior without Git/GPG fallback.
- Dependencies: Stages 2–4 parity passes.
- Verification: Normal routes after restart, Next/Back, filters and restricted access; assert no evidence subprocesses/scans. RSS/source/full-commit contracts unchanged.
- Risks: Missing projections must be distinguishable from valid empty evidence; use existing maintenance recovery.
- Components: ActivityService, ActivityPageController, standard partials/pagination and regressions.
- Stage-work driver/uncertainty: Reader switch; recovery presentation.

## Stage 6 — Forte reads stored data
**Estimate: 0.12**

- Goal/changes: Switch initial selected evidence, sidebar totals, sort/append and detail API to shared stored data; cover compatibility page responses too.
- Dependencies: Stage 5 shared reader contract.
- Verification: Initial/selected/filter/sort/append, security variants, missing evidence and retry after restart; full-commit inspection remains separate.
- Risks: Returning heavy compatibility payloads or count-policy mismatch; verify actual browser/API contracts.
- Components: ForteActivityController, ActivityService, existing list/detail/filter partials and reader.
- Stage-work driver/uncertainty: Multiple entry paths; compatibility coverage.

## Stage 7 — Acceptance, operations and closeout
**Estimate: 0.15**

- Goal/changes: Own shared acceptance evidence and operator recovery/rollout guidance; summarize actual size/indexing costs and study outcome.
- Dependencies: All prior stages; no unresolved correctness risks.
- Verification: Phone/desktop first paint, overflow, empty/failure/retry and supported themes; focused regressions, real rebuild and interrupted/candidate recovery. Measure warm p95 ≤1 s over 20 controlled requests per view plus detail requests; cold/recovery separately.
- Risks: Production still slow or storage exceeds estimated 0.25–1 KB/event; report deviations, do not claim success. Replay both reported production flows only after authorized rollout; otherwise leave external acceptance pending with retry/rebuild guidance.
- Components: Existing browser/PHP harnesses, operator docs, schema spec, implementation summary and effort ledger; compare dependency inventory (zero).
- Stage-work driver/uncertainty: Shared verification owner; actual production access.

## Execution and study checkpoint

2026-10-27T23:15:13Z: Step 2 explicitly approved. Scope v3/C, baseline 7ef735dd21b3329f18716bf3d5484baab2b2b71b, R1/P2 and Codex single exposed rater retained; frozen hashes reverified. Whole-cycle 0.95 reaffirmed: reuse of signature/indexing infrastructure offsets discovered direct-writer wiring. S1–S7 estimates are separate stage work, not additive totals/hours. Prior scores retained; seven planned/zero implemented. If stage sizing or fan-out cannot fit this bounded cycle, revise/split before dependent implementation.

Step 3 approved 2026-10-28. Original planning instructions follow: After approval, organize plans on the feature branch, commit approved planning first, then one verified stage and summary update per commit. No new dependencies or new scope requests; production deployment remains separate.

*— Codex · 2026-10-27 UTC*
