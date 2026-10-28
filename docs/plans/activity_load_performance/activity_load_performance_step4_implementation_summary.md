> **Feature plan:** [Step 1](./activity_load_performance_step1_solution_assessment.md) · [Step 2](./activity_load_performance_step2_feature_description.md) · [Step 3](./activity_load_performance_step3_development_plan.md) · [Step 4](./activity_load_performance_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/activity_load_performance; [ledger](../effort_estimation_trial.md#cycle-v3activity_load_performance)
Extended estimates: enabled
**Cycle estimate: 0.95**

Entry: 2026-10-28; Step 3 approved, branch feat/activity-load-performance, planning commit 2028f6d3a. Scope v3/C, baseline 7ef735dd21b3329f18716bf3d5484baab2b2b71b, R1/P2, Codex single exposed rater. Entry total 0.95 reaffirmed. Original S1–S7 stage work 0.08/0.18/0.18/0.15/0.10/0.12/0.15 copied from approved plan (not summed). No new dependencies; production deployment/acceptance remain separate.

## Stage 1 - Baseline and read-cost contract
**Original estimate: 0.08**
**Retrospective stage work: 0.08**

- Changes: Added repeatable read benchmark using the existing disposable bulk-commit fixture; preserved investigation/writer audit. It measures data-layer work, not HTTP/paint or production.
- Verification: Fixture generation and 20 samples per standard/Forte/detail path passed; baseline timings recorded below. Fixture includes 205 long-path events sharing 2,300 unrelated files; existing signed/missing-history tests supply complementary cases.
- Notes: Existing production observations remain the user-facing baseline; local fast reads do not reproduce server contention. No live runtime/database mutated. Stage 1 owns shared benchmark setup; later stages verify correctness and request cost after restart.

*— Codex · 2026-10-28 UTC*

Baseline data-layer p95: standard 7.457 ms, Forte 5.018 ms, detail 4.921 ms; 851,968 bytes / 210 events. Warm manifest cache and unsigned bulk data make this a structural comparison, not a production latency prediction.

## Stage 2 - Compact rebuild evidence
**Original estimate: 0.18**
**Retrospective stage work: 0.18**

- Changes: Schema 32 adds compact evidence, pending index and source-field invalidation. Transactional rebuild computes relevant paths once per commit group and reuses stored signature statuses. Links/HTML/full manifests are not stored.
- Verification: ActivityProjectionTest + schema/prior-index startup checks 6/6; earlier standard pagination checks 11/11. Actual `./v3 rebuild` on disposable bulk fixture passed. Prior schema test removes the new column/index/triggers and verifies normal Activity startup rebuilds before reading it.
- Notes: Initial schema inventory assertion failed as expected and was updated; rerun passed. Live readers still use their prior paths until stages 5/6. Stage 1 commit 3e924f927. No dependency changes.

*— Codex · 2026-10-28 UTC*

## Stage 3 - Evidence follows writes
**Original estimate: 0.18**
**Retrospective stage work: 0.18**

- Changes: Incremental metadata completion now refreshes pending evidence inside the write transaction, including direct flag inserts. Source-less handoffs receive empty evidence through the schema trigger. Identity/key arrival invalidates only that identity's prior evidence and refreshes its stored reaction status; imports/deletions use the already-wired rebuild path.
- Verification: Projection/reaction/label/candidate recovery tests 13/13; new test checks incremental/rebuild parity and rollback, plus direct source-less insertion.
- Discovery/fix: Parity exposed an existing reaction updater defect: refreshing a target assigned the newest commit to all older reaction events. It now resolves each source's actual commit, matching rebuild and preserving correct evidence. This is required evidence correctness within scope, not a new feature.
- Notes: Stage 2 commit d372c401a. No reader switch yet. Full signed/identity/retraction acceptance continues in Stage 7.

*— Codex · 2026-10-28 UTC*

## Stage 4 - Transactional counts and positions
**Original estimate: 0.15**
**Retrospective stage work: 0.15**

- Changes: Schema 33 adds maintained totals with/without profile-name events, commit totals, a source-less cursor revision, and standard/Forte ordering indexes. Triggers cover all Activity writers and post visibility changes; standard pagination no longer reads/hashes every source-less event. Count read switch remains Stage 6.
- Verification: Projection/schema checks 7/7; standard navigation suite 11/11. Membership parity covers malformed/scalar/case-varied tags, feature-name variants, direct inserts/updates/deletes and post hide/retract/delete. Prior schema 32 startup rebuilds missing counters; actual CLI rebuild on the bulk fixture passed.
- Notes: Stage 3 commit 307cb5633. Trigger maintenance is within the same transaction as mutations; no new worker or configuration. Schema inventory assertion updated for new objects.

*— Codex · 2026-10-28 UTC*

## Stage 5 - Standard stored-data reader
**Original estimate: 0.10**
**Retrospective stage work: 0.10**

- Changes: Standard Activity hydrates compact evidence and URLs without canonical reads, Git or GPG. Missing/malformed projections render an honest unavailable state with Reload through the existing shared source component. Schema 34 identifies the new read contract; unavailable historical links remain unavailable.
- Verification: Projection suite 6/6 and standard suite 11/11; repository-removal test proves fresh standard readers return identical evidence without source access. Prior-version normal startup and CLI rebuild pass. Initial strict array-order parity failure corrected without changing output values.
- Notes: Stage 4 commit bca0fa451. UI phone/theme acceptance remains Stage 7. No runtime evidence reconstruction fallback.

*— Codex · 2026-10-28 UTC*

## Stage 6 - Forte stored-data reader
**Original estimate: 0.12**
**Retrospective stage work: 0.12**

- Changes: Forte initial/selected and compatibility-page details hydrate stored evidence; categories/commits read maintained totals. Schema 35 records changed query contracts. RSS/full-commit inspection remain independent.
- Verification: Projection 6/6, standard navigation 11/11, Forte recovery 2/2 after updating its deliberately partial schema fixture to current schema minus commits. Fresh readers without repository access preserve Forte detail and totals; real CLI rebuild passed.
- Measurements: Same bulk fixture data-layer p95 standard 1.540 ms (baseline 7.457), Forte 0.265 ms (5.018), detail 0.196 ms (4.921). Database 1,146,880 vs 851,968 bytes: +294,912 / 210 events = 1,404 bytes/event. This long-path/long-label fixture exceeds the indicative 0.25–1 KB range because evidence plus ordering indexes retain those values; measure normal-length records in Stage 7 and report both, not a guaranteed cap.
- Notes: Stage 5 commit e6ee91a5a. HTTP/browser and signed mutation acceptance remain Stage 7; local data-layer timings do not establish production resolution.

*— Codex · 2026-10-28 UTC*
