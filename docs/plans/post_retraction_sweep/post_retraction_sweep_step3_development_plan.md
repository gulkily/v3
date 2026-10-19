> **Feature plan:** [Step 1](./post_retraction_sweep_step1_solution_assessment.md) · [Step 2](./post_retraction_sweep_step2_feature_description.md) · [Step 3](./post_retraction_sweep_step3_development_plan.md) · [Step 4](./post_retraction_sweep_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

- Approved application slice: ordinary signed retraction queues cleanup; the existing cron worker discovers missed/imported work; operators can preview, run immediately, inspect status, and retry without signing again. Covered live copies converge to clean while suppression stays effective.
- Cover generated HTML across configured roots, active/inactive releases and candidates, offline snapshot/update copies and manifests, and proven-dependent LLM payloads. Document analysis caches, other logs, canonical/history, backups, attachments, published replies, and external copies as exclusions. Unknown exchange dependencies stay withheld and unchanged.
- Release requires normal browser/API, CLI and actual generated-cron-command verification, failure/restart/late-write evidence, and accurate coverage/status. Local evidence does not claim production deployment. Deploy readers/writers/worker together; pause/drain workers and writers before rollback; never restore a pre-cleanup diagnostic database as recovery.

## Key Risks

- **High risk: data loss.** Incorrect provenance or arbitrary diagnostic metadata could delete unrelated data or retain prose. Validate mixed fixtures first; reuse dependency classification, preserve uncertain rows, and retain only allowlisted identifiers/timestamps/status/provenance.
- **High risk: resurrection.** Static release activation, snapshot publication, and provider completion can race cleanup. Trace publication entry points before edits; use consistent lock ordering and retraction-generation checks; mark late work pending and reconcile.
- **High risk: false completion.** Freed pages/journals, permissions, stale read models, and missed enqueueing can hide residual copies. Validate SQLite modes and writer paths early; use secure deletion/checkpoint handling, durable progress and explicit failed/pending status. No forensic-erasure claim.

## Stage 1 — Operator preview and coverage

- Goal: ship a read-only sweep preview and explicit coverage inventory.
- Dependencies: Step 3 approval; feature branch and planning commit.
- Expected changes: shared effective-retraction policy; proposed `RetractionSweep::preview(int $limit): array`; CLI preview/status, documented roots/stores and exclusions.
- Verification approach: mixed direct/context/unaffected/uncertain fixtures; preview leaves all stores unchanged, including absent-store cases.
- Risks or open questions: inspect all covered writers and SQLite modes; unresolved coverage or provenance blocks Stage 2.
- Canonical components/API contracts touched: RetractedPosts, ExchangeDependencies, CLI dispatch; no read-model schema change planned.

## Stage 2 — Immediate exchange cleanup

- Goal: operators can immediately scrub proven-dependent exchange payloads.
- Dependencies: Stage 1 inventory/classification verified.
- Expected changes: bounded `RetractionSweep::run(int $limit): array`, durable progress and dry-run parity; scrub request/response/error/context and arbitrary diagnostic strings, keeping allowlisted non-content metadata and dependency evidence; private sweep state only.
- Verification approach: sentinel payloads absent from live rows/free pages; WAL/journal cleanup or visible pending failure; uncertain/unaffected rows unchanged; interruption/retry and exhausted limits.
- Risks or open questions: database contention leaves retryable status, never an unqualified completion.
- Canonical components/API contracts touched: LlmExchangeRecorder/store, private sweep state, sweep CLI; private schema changes documented, not disposable read-model migrations.

## Stage 3 — Late exchange writes

- Goal: a provider result arriving after cleanup cannot silently restore completed payloads.
- Dependencies: Stage 2 scrub policy.
- Expected changes: recording-boundary coordination with current retractions and sweep state; scrub known-affected writes or durably expose them as pending; recheck historical rows on new retractions.
- Verification approach: pause provider before retraction, finish after cleanup; exercise successful/error calls, missing canonical evidence and restart.
- Risks or open questions: canonical-state uncertainty must preserve withholding and block clean claims; lock-order checks precede concurrency wiring.
- Canonical components/API contracts touched: exchange recorder factories, visibility/dependency policy, sweep progress.

## Stage 4 — Artifact cleanup and publication safety

- Goal: direct sweep retires stale artifacts without repeatedly deleting clean output.
- Dependencies: Stage 1 writer inventory and Stage 2 recovery contract.
- Expected changes: generation-aware retirement across configured roots/releases/candidates and offline copies; coordinate build/activate/shared refresh and snapshot publication; refresh stale read model before regeneration.
- Verification approach: stale active/inactive/candidate fixtures, concurrent activation/publication, symlink/path boundaries, permission failure, interrupted retirement; second clean run preserves fresh artifacts.
- Risks or open questions: reject unverified stale publication; unfinished builds remain pending until retired or regenerated. Resolve races before Stage 5.
- Canonical components/API contracts touched: StaticArtifactInvalidator, StaticArtifactBuilder/ReleasePublisher, OfflineSnapshotPublisher/Builder, read-model freshness.

## Stage 5 — Automatic queue and cron recovery

- Goal: ordinary retractions and missed historical/imported work reach the same sweep.
- Dependencies: Stages 2–4.
- Expected changes: acceptance/retry enqueue, coalesced sweep task, bounded continuation and safe periodic discovery in existing worker startup; queue status exposes progress/failures without content.
- Verification approach: missed enqueue, duplicate triggers, worker offline, abandoned job, queue contention/backlog, invalid canonical state, imported retraction, bounded continuation; execute generated cron command in fixture environment.
- Risks or open questions: discovery must not flood/starve queue or suppress terminal failures; retry stays operator-accessible.
- Canonical components/API contracts touched: LocalWriteService, SqliteTaskQueueStore, TaskQueueWorker, task_queue CLI/cron.

## Stage 6 — Retraction feedback and recovery

- Goal: users distinguish accepted retraction from pending/failed cleanup.
- Dependencies: Stage 5 status contract.
- Expected changes: minimal shared API/notice/control feedback and retry; retain immediate suppression and partial-refresh recovery; update UI copy checklist.
- Verification approach: browser author/operator acceptance with unavailable worker, enqueue failure and retry; unchanged signature/acceptance evidence; keyboard/mobile control recovery.
- Risks or open questions: never describe enqueueing as completed cleanup; reuse existing controls and accessible status.
- Canonical components/API contracts touched: prepare/finalize responses, post_retraction.js, RetractedPostNotice and existing controls; no navigation addition.

## Stage 7 — Release verification and operator handoff

- Goal: verify the complete slice and document deployment/recovery.
- Dependencies: Stages 1–6; each stage approximately one hour, split/replan before oversized work.
- Expected changes: CLI/privacy/retraction references and deployment/recovery runbooks, coverage/exclusions and truthful completion semantics.
- Verification approach: focused sweep/queue/publication tests, retraction browser regressions, `php tests/run.php`, and CLI-to-cron acceptance; record passed/failed/pending/inapplicable separately. If a read-model schema dependency emerges, follow CONTRIBUTING version/spec/startup-rebuild requirements before proceeding.
- Risks or open questions: any covered-store race or residual-payload failure blocks release; live deployment remains operator-owned unless separately authorized.
- Canonical components/API contracts touched: existing docs/test entry points and Step 4 summary; stage commits include verification and summary, with no history rewriting.
