> **Feature plan:** [Step 1](./high_traffic_absent_retractions_step1_solution_assessment.md) · [Step 2](./high_traffic_absent_retractions_step2_feature_description.md) · [Step 3](./high_traffic_absent_retractions_step3_development_plan.md) · [Step 4](./high_traffic_absent_retractions_step4_implementation_summary.md)

Debug mode: enabled.
Effort study: `railspike-effort`, cycle `v3/high_traffic_absent_retractions`; [host ledger](../effort_estimation_trial.md#cycle-v3high_traffic_absent_retractions), R1/P2. Extended estimates not enabled.

## Completion Contract

Normal `/` and eligible public pages must cold-generate, refresh and warm-serve through Apache with both production absent-target retractions, without deleted/stale attributable content and with surviving replies intact. Preserve evidence/privacy/concurrency checks, bounded work and disable/restore recovery. Deliver local tests and DreamHost deploy/cron/rollback instructions; host acceptance stays pending until operator verifies HTTP 200, cache hit and refresh. No production changes, canonical repair, dependencies or core schema changes. Offline-bootstrap fatal handling stays deferred.

## Key Risks

- **High risk:** stale rows/summaries/generated effects leak deleted content. Validate excluded render input before wiring; preserve independent labels and replies.
- **High risk:** source reappearance/evidence/mode changes invalidate candidates. Pin inputs, recheck at publication and reject/retry safely.
- Cold preparation can exceed host limits. Bound it within existing rendering limits, keep warm hits fast and verify Apache locally; record DreamHost separately.

## Stage 1 — Scoped admission

- Goal: classify absent targets without relaxing strict callers.
- Dependencies: planning commit on fresh branch.
- Expected changes: high-traffic evidence/token contract, reusing snapshot classification.
- Verification approach: both production IDs, flat/dated sources, missing/malformed/unsafe/unreadable evidence, trusted removals and strict/offline regressions.
- Risks or open questions: snapshot reappearance exemptions cannot silently apply to HTML; resolve candidate validation before Stage 2.
- Canonical components/API contracts touched: `ExchangeRetractionState`, `PublicationGuard`, focused tests.

## Stage 2 — Safe render input

- Goal: exclude stale target and attributable generated content while preserving survivors.
- Dependencies: Stage 1 verified/committed.
- Expected changes: consistent build-local excluded rendering view and corrected summaries, reusing derivation; preserve live database/canonical bytes. Unsafe projection reports rebuild/retry; no warm-hit preprocessing.
- Verification approach: stale reply/root/expansion/attributable-label fixtures, independent actions, actual board/thread HTML, resource bounds and cleanup.
- Risks or open questions: full HTML exceeds snapshot projection; block Stage 3 until isolation and surviving content pass.
- Canonical components/API contracts touched: renderer/`StaticArtifactBuilder`, shared read-model/render helpers; no core schema change.

## Stage 3 — Cache/refresh integration

- Goal: restore bounded normal generation and refresh.
- Dependencies: Stage 2 verified/committed.
- Expected changes: carry matching validated context through worker and atomic HTML/asset publication; clean temporary views, preserve eligible old pages on safe refresh failure.
- Verification approach: HTTP/CLI cold 503-to-200 recovery, all eligible routes, ordinary/private paths, slots/bytes and retry.
- Risks or open questions: parent/worker disagreement; require context identity and final validation before promotion.
- Canonical components/API contracts touched: `HighTrafficCache`, render worker, `HighTrafficRefresh`, existing CLI/HTTP contracts.

## Stage 4 — Race/shared recovery

- Goal: verify concurrency safety and shared-consumer compatibility.
- Dependencies: Stage 3 verified/committed.
- Expected changes: deterministic failure/retry regressions and in-scope corrections.
- Verification approach: source reappearance, changed/deleted evidence, mode withdrawal, interruption, old-cache preservation; direct/queued offline base/update, static/import/removal checks.
- Risks or open questions: expensive full-doc regeneration; use existing bounded frozen-doc harness and disclose limits.
- Canonical components/API contracts touched: publication fence, retraction/high-traffic/offline tests and import-publication harness.

## Stage 5 — Visitor/operator acceptance

- Goal: replay production failures and deliver host recovery instructions.
- Dependencies: Stage 4 verified/committed.
- Expected changes: Apache acceptance for both IDs; deployment, enable/warm, cron, verification and rollback runbook; deferred-fatal follow-up.
- Verification approach: cold HTTP 200, warm cache-hit without repeated PHP, refreshed timestamp, surviving replies/absent secrets, phone/desktop loaded layout/overflow, CLI modes and focused shared suites; diff/dependency inventory.
- Risks or open questions: enable precedes generation; document explicit warm/verify and immediate disable on failure. DreamHost acceptance remains pending.
- Canonical components/API contracts touched: existing Apache/browser harness, high-traffic runbook and cycle/study records.

## Execution and study checkpoint

2026-10-24T05:09:04+00:00: Step 2 approved, scope v1/baseline `bce2c29e3`, R1/P2, Codex single exposed rater. **0.75 projected total**, revised from 0.65 for inspected full-HTML summaries/ownership and isolated-input safety; prior snapshots retained, no scope expansion, measured labor or independent outcome. Exact R1 hashes verified. Five approximately-hour stages; split oversized work before dependents. After **Approved Step 3**, branch, organize plans/debug, repair index/links and commit approved planning only; then create Step 4 summary. Verify/update summary/commit each stage before advancing. Standalone investigation and substantive ledger updates stay outside planning commit; ledger updates enter authorized stage documentation. No dependencies added.
