> **Feature plan:** [Step 1](./approval_seed_immediate_refresh_step1_solution_assessment.md) · [Step 2](./approval_seed_immediate_refresh_step2_feature_description.md) · [Step 3](./approval_seed_immediate_refresh_step3_development_plan.md) · [Step 4](./approval_seed_immediate_refresh_step4_implementation_summary.md)

## Completion Contract

- Both CLI commands synchronously persist seeds and refresh approval, attribution, access, activity, scores, and served output before success, with zero full rebuilds. Preserve arguments, instance overrides, seed semantics, and approval-reply behavior; no schema change.
- Reject unready models before mutation where possible. Later failures return nonzero with persistence/commit status, stale-state protection, and repair guidance. Matching-seed retries after repair neither duplicate writes nor change reasons; conflicting reasons fail.
- Release requires parity, recovery/concurrency, cached next-request visibility, and representative timing checks. Verify configured artifact roots and deployment-equivalent routing locally; record live-environment limitations. Production writes/deployment are outside this plan.

## Key Risks

- **High risk:** Outdated models or concurrent writes corrupt derived state. Validate readiness/locking first; compare pre-write repository state and isolate seed commits.
- **High risk:** Partial persistence or silent invalidation causes false success. Inject failures early; verify freshness and safe retries.
- **High risk:** Transitive/attribution changes leave access inconsistent. Inventory Step 2 readers first; require fresh-rebuild parity.

## Stage 1 — Resolve Contracts

- Goal: Establish acceptance fixtures and readiness/recovery rules (≤1 hour).
- Dependencies: Approved Step 3; feature branch and planning-only commit.
- Expected changes: Define Git/non-Git freshness evidence, dirty-data handling, lock ownership, retry outcomes, and artifact-root selection.
- Verification approach: Characterize both aliases and cached serving in isolated fixtures.
- Risks or open questions: Eligibility currently omits HEAD checks; resolve readiness evidence before Stage 2 or revisit planning.
- Canonical components/API contracts touched: Seed CLI, ExecutionLock, ReadModelMetadata/StaleMarker, instance configuration, LocalAppSmokeTest.

## Stage 2 — Apply Seeds Synchronously

- Goal: Wire both commands through incremental approval (≤1 hour).
- Dependencies: Stage 1 resolved and committed.
- Expected changes: Add service `seedApprovedIdentity(string $identityId, string $seedReason): array` and updater `applyApprovalSeedWrite(string $commitSha): array`; reuse derivation, activity/scores, metadata, and invalidation under the writer lock; no reply insertion or rebuild fallback.
- Verification approach: Direct/transitive/attribution parity, zero rebuilds, unchanged post counts, existing approval regressions.
- Risks or open questions: Shared refactoring could affect replies; preserve their contract and expose affected identities/threads for invalidation.
- Canonical components/API contracts touched: LocalWriteService, IncrementalReadModelUpdater, seed script, StaticArtifactInvalidator.

## Stage 3 — Complete Recovery

- Goal: Make failures and retries safe (≤1 hour).
- Dependencies: Stage 2 committed.
- Expected changes: Complete preflight rejection, persistence/commit/refresh diagnostics, stale handling, and matching-seed retries; preserve unrelated staged changes.
- Verification approach: File/Git/refresh/invalidation failures; missing/stale/incompatible/behind models; repaired retries, conflicting reasons, competing writers.
- Risks or open questions: Uncommitted seeds differ from completed writes; test both and never clear unrelated stale state.
- Canonical components/API contracts touched: Seed service/CLI, canonical commit boundary, execution lock, stale marker.

## Stage 4 — Complete Served Visibility

- Goal: Ensure next-request freshness (≤1 hour).
- Dependencies: Stage 3 committed.
- Expected changes: Extend verified invalidation across affected profiles, activity, scored threads, and configured static releases.
- Verification approach: Seed with cached output present; check Step 2 readers and member gates, including transitive/root-attribution cases.
- Risks or open questions: Wrong roots or silently failed deletion hide updates; verify routing and invalidation failures before success.
- Canonical components/API contracts touched: Existing readers, StaticArtifactInvalidator, publication roots; no new UI/API.

## Stage 5 — Verify Release Contract

- Goal: Finish operational verification and guidance (≤1 hour).
- Dependencies: Stage 4 committed.
- Expected changes: Document immediate success, repair/retry, and timing evidence.
- Verification approach: Seed tests; `php tests/run.php LocalAppSmokeTest WriteApiSmokeTest`; relevant access/publication suites; changed-file lint/diff checks; repeated before/after timings on representative isolated data.
- Risks or open questions: Tiny fixtures conceal latency; record dataset/method/limitations and resolve regressions.
- Canonical components/API contracts touched: Test harness, CLI help/README, Step 4 summary.

Every stage requires verification, summary update, and a commit before its successor. Split oversized stages before implementation; retain the eight-stage/one-day limit.
