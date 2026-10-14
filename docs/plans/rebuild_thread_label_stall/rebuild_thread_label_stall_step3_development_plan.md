> **Feature plan:** [Step 1](./rebuild_thread_label_stall_step1_solution_assessment.md) · [Step 2](./rebuild_thread_label_stall_step2_feature_description.md) · [Step 3](./rebuild_thread_label_stall_step3_development_plan.md) · [Step 4](./rebuild_thread_label_stall_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract
Normal `./v3 rebuild` batches all three provenance families and reports work through validated promotion/snapshot readiness. Preserve domain output, optional reporters/timing keys, candidate cleanup/live protection, and post-promotion snapshot retry guidance. Release requires parity, failure-path, query-count, and timing evidence on isolated fixtures. Deployment/production benchmarking are external; no production speed promise. No schema/public-signature changes or deferred algorithm redesign.

## Key Risks
- **High risk: provenance drift.** Compare bulk/per-path results on merges, missing/untracked paths, no-Git repositories, and Git failures first; block dependent stages on mismatches, preserving legacy timestamp semantics.
- **High risk: misleading status/recovery.** Check empty/large/failing transcripts early; bound updates, preserve transaction/lock boundaries, and never label failure successful.
- Performance attribution: capture fixed-history baselines before edits; compare query counts and repeated timings, excluding volatile metadata from domain parity. Resolve material failures before dependent work.

## Stage 1
- Goal: Batch label history with visible resolution (about 1 hour).
- Dependencies: Approval, feature branch, organized planning commit, then summary creation.
- Expected changes: Capture baseline output/timings/Git counts; extend existing bulk resolution to labels with named/timed boundaries.
- Verification approach: Label/score/vote/activity parity; provenance edge cases above; constant-bounded Git calls across record counts.
- Risks or open questions: Merge semantics may differ; resolve equivalence before Stage 2.
- Canonical components/API contracts touched: Builder source cache/resolver, label tests, isolated Git fixtures; signatures unchanged.

## Stage 2
- Goal: Batch reaction and signed flag history (about 1 hour).
- Dependencies: Stage 1 verified/committed.
- Expected changes: Reuse batching/timing through both normal indexing paths.
- Verification approach: Provenance/call-count checks; reaction moderation/scores, invalid targets, absent history, signed/legacy flag activity deduplication.
- Risks or open questions: Attribution affects activity inclusion; require full affected-output parity.
- Canonical components/API contracts touched: Builder, reaction/feature-flag tests; no external interface changes.

## Stage 3
- Goal: Expose remaining indexing work (about 1 hour).
- Dependencies: Stage 2 verified/committed.
- Expected changes: Time sorting/derivation/writes across posts/thread summaries, labels/subjects/reactions, approval/profile/author processing, activity/commits; bounded loop counters and truthful failure outcomes.
- Verification approach: Zero/small/large/failing progress tests; ordering, units/final counts, bounded volume, silent callers, retained timings, domain parity.
- Risks or open questions: Blocking calls/unknown totals cannot supply percentages; use boundaries/pass counts without changing algorithms.
- Canonical components/API contracts touched: Builder timing/batch reporters and tests; existing signatures retained.

## Stage 4
- Goal: Visible CLI completion/recovery (about 1 hour).
- Dependencies: Stage 3 verified/committed.
- Expected changes: Outcome/duration reporting for transaction commit, candidate validation, lock/promotion, and snapshot bootstrap.
- Verification approach: Candidate/snapshot command tests cover readiness, validation/sidecar failures, cleanup/live protection, post-promotion failure/retry guidance.
- Risks or open questions: Reporting could mask exceptions or change locking; gate acceptance on failure-path checks.
- Canonical components/API contracts touched: Builder, candidate builder/promoter, rebuild script; preserve optional reporter and snapshot interfaces.

## Stage 5
- Goal: Verify end-to-end delivery (about 1 hour).
- Dependencies: Stage 4 verified/committed.
- Expected changes: Close CLI regression gaps; document output meanings and benchmark evidence.
- Verification approach: Normal rebuild with isolated repository/database/snapshot destinations; baseline row/provenance parity, Git-call scaling, repeated timing samples; `./v3 test` including board/thread/activity/API/offline regressions.
- Risks or open questions: Fixtures cannot prove production speed; record dataset/history sizes, environment, samples, residual bottlenecks, and unavailable checks; required failures block completion.
- Canonical components/API contracts touched: CLI/consumer tests, CLI reference, implementation summary.

Each stage commits verification and summary before the next; split oversized stages before implementation. Expected cadence: planning commit, five stage commits, final debug-log handoff commit. Planning stays uncommitted until Step 3 approval.
