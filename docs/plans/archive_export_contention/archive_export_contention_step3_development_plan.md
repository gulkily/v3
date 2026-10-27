> **Feature plan:** [Step 1](./archive_export_contention_step1_solution_assessment.md) · [Step 2](./archive_export_contention_step2_feature_description.md) · [Step 3](./archive_export_contention_step3_development_plan.md) · [Step 4](./archive_export_contention_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/archive_export_contention; [ledger](../effort_estimation_trial.md#cycle-v3archive_export_contention); standing P2 enrollment
Extended estimates: enabled
Status: Step 3 approved; implementation authorized
**Cycle estimate: 0.90**

## Completion Contract

Entry: Existing Backup, Codebase and direct tar/ZIP/image URLs, including the instance importer.
Outcome: Complete fresh archives reused across compatible requests, coordinated bounded builds, revision-safe retry and bounded cleanup protecting admitted transfers.
Recovery/release: Focused concurrency, invalidation, interruption, disk-pressure and phone/desktop recovery evidence; operator runbook replays qdb.us failure and verifies extraction/reuse. Local delivery is distinct from host acceptance; no deployment or production repair claimed without host evidence.
Boundary: Shared exporter/delivery, focused tests, runbook, UI-copy audit and study ledger; no dependencies, schema/format changes, new service or automatic worker termination. Canonical records unchanged.

## Key Risks

- **High risk:** Stale archives could expose withdrawn content. S1 validates freshness and writer-lock coverage before reuse; unknown eligibility fails closed, with revalidation before admission.
- **High risk:** Existing send/shutdown cleanup deletes exports. S2 establishes ownership; S5 tests eviction against active transfers before enabling cleanup.
- Long builds can occupy workers. S3 validates bounded waits and single-builder resource use; persistent host stalls remain diagnostic work, not a cache-success claim.

## Stage 1 — Freshness contract (S1/v1)

**Estimate: 0.20**

- Goal/Dependencies: Establish reusable eligibility; none.
- Expected changes: Define content-byte fingerprint, image inventory, revision, format and export-version identity under existing writer coordination; retain dirty-record/integrity refusals.
- Verification: Same-head image/file changes, deletion/retraction, revision mismatch and changes during compression invalidate reuse; benchmark fingerprint cost on representative content.
- Risks: Git-head/stat-only identity misses changes; audit writers and use actual exported inputs, blocking S2 on unresolved admission races.
- Contracts: `ImageBackup`, `ImageStore`, snapshot/readme generation; existing `export(string $kind, ?string $expectedRevision = null): array` entry.
- Estimate details: Stage work; driver is eligibility coverage; uncertainty is large-repository validation cost.

## Stage 2 — Reusable downloads (S2/v1)

**Estimate: 0.25**

- Goal/Dependencies: Deliver a valid warm-cache download; S1.
- Expected changes: Private cache outside exported/web roots; publish only complete artifacts; revalidate before admission. Introduce explicit reusable ownership and transfer protection; remove request/shutdown deletion of shared archives.
- Verification: Cold/warm tar, ZIP and image downloads extract identically; warm hits skip compression; corrupt/incomplete entries rebuild or fail safely.
- Risks: Cache deletion or path reuse can break readers; secure artifact lifetime through transfer and test shared shutdown behavior.
- Contracts: `ImageBackup::export` metadata, `InstancePageController`, `RouteServices::sendDownload`; preserve default semantics for unrelated downloads.
- Estimate details: Stage work; driver is ownership integration; uncertainty is direct-export caller compatibility.

## Stage 3 — Coordinated contention (S3/v1)

**Estimate: 0.20**

- Goal/Dependencies: Share duplicate builds with bounded waiting; S2.
- Expected changes: Recheck cache after acquiring build coordination; preserve bounded global build concurrency and consistent lock ordering; valid hits avoid unrelated compression waits.
- Verification: Multiprocess same/different-format requests demonstrate one compatible build, bounded refusal, no deadlock and success after release.
- Risks: Nested waits exceed budget; establish one request wait budget and validate lock ordering before HTTP wiring.
- Contracts: Export coordination and `ExecutionLock`; avoid changing other callers' timeout policy.
- Estimate details: Stage work; driver is deterministic races; uncertainty is worker scheduling.

## Stage 4 — Failure and retry flow (S4/v1)

**Estimate: 0.15**

- Goal/Dependencies: Make contention recoverable through normal routes; S3.
- Expected changes: Distinguish temporary busy, revision conflict and export failure; return retry guidance without private paths, preserving import compatibility and private response caching policy.
- Verification: HTTP status/body/retry checks, importer retry, refreshed revision-paired downloads and unrelated SQLite downloads.
- Risks: Blanket exception rewriting hides recovery distinctions; classify failure causes and retain private operator evidence.
- Contracts: `InstancePageController`, shared response handling, `InstanceArchiveDownloader` compatibility; no new UI surface.
- Estimate details: Stage work; driver is error semantics; uncertainty is existing importer assertions.

## Stage 5 — Bounded lifecycle and recovery (S5/v1)

**Estimate: 0.25**

- Goal/Dependencies: Reclaim safely and recover interrupted exports; S2–S4.
- Expected changes: Explicit byte/count bounds, free-space reservation and reclaimable obsolete/abandoned work; refuse new builds when protected readers consume capacity. Preserve active builders, transfers and lock files.
- Verification: Disk-pressure, interrupted-builder restart, malformed cache, eviction-during-send and invalidation-during-build/admission; no partial or stale fallback.
- Risks: Abandoned-work detection can race active work; require ownership evidence before deletion and account for protected bytes in capacity.
- Contracts: Cache lifecycle, export cleanup, transfer protection; constants preferred, any new setting follows `PrivateConfigSchema` and template checks.
- Estimate details: Stage work; driver is resource/failure races; uncertainty is fault-injection support.

## Stage 6 — Acceptance and operator handoff (S6/v1)

**Estimate: 0.20**

- Goal/Dependencies: Verify normal outcomes and document qdb.us recovery; S1–S5.
- Expected changes: Focused regression/browser coverage, operator runbook, copy audit and study/implementation closeout; this stage owns shared acceptance/documentation overhead.
- Verification: Desktop/phone busy/conflict/retry, cold/warm/concurrent extraction and import; replay reported lock failure locally. Provide host commands to inspect holder/resources, retry, extract and confirm reuse; record actual host evidence or mark it pending.
- Risks: Local passes cannot certify production; explicitly retain unavailable host checks and safe manual recovery, with no held-lock deletion or automatic termination.
- Contracts: Existing Backup/Codebase links and download routes, focused archive/import tests; `docs/runbooks/archive_export_recovery.md`, copy checklist and ledger.
- Estimate details: Stage work; driver is end-to-end recovery; uncertainty is access to production evidence.

## Execution and study checkpoint

Execution: After Approved Step 3, create feature branch, organize/commit approved plans, then initialize summary; each stage requires verification and its own scoped commit before the next. Resolve stage risks before dependent work; split oversized stages within the eight-stage boundary or return for scope approval. No implementation yet.
Snapshot: 2026-10-26T00:57:42Z; 0.90 whole-cycle reaffirmed independently, not a sum of stage work; v2/Option B, baseline 28530e1b59b6b5d98d314bc080dda94588cc4217, R1/P2. Exact frozen hashes verified; Codex single exposed rater with exporter/delivery/image-lock inspection. Original v1 0.45 and v2 0.90 checkpoints preserved; S1–S6 estimates above are original stage-work ratings, not hours or measured labor. No accepted outcome; Step 3 explicitly approved by user.

*— Codex · 2026-10-26 UTC*
