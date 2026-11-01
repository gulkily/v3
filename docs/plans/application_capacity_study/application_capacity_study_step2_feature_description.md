> **Feature plan:** [Step 1](./application_capacity_study_step1_solution_assessment.md) · [Step 2](./application_capacity_study_step2_feature_description.md) · [Step 3](./application_capacity_study_step3_development_plan.md) · Step 4 (not started)

Debug mode: enabled
Effort study: railspike-effort / v3/application_capacity_study; [P2 ledger](.././effort_estimation_trial.md#cycle-v3application_capacity_study)
Extended estimates: enabled

**Cycle estimate: 0.90**

## Problem and user stories

The QDB audit identifies allocation risks but does not establish sustainable application or concurrent-host capacity. Subsequent fixes require a fresh measured baseline; historical failures remain evidence about their original revisions.

- As an operator, I want reproducible resource measurements so that I can judge which workloads fit my environment.
- As a maintainer, I want isolated family costs followed by controlled overlap and recovery checks so that I can prioritize improvements without changing content semantics.

## Core requirements

- Cover public QDB/shared reading and search, authenticated reads/writes, rebuild/static/offline jobs, activity/Git evidence, imports/exports/images, scoring and queue maintenance. Give every major entry-point family a tested, inapplicable or deferred disposition with reasons; distinguish normal HTTP/CLI results from direct-method probes.
- Compare fixed-result workloads at 1000/10000/40000 records; vary body size, reactions, history, pins and concurrency separately. Include cold/warm repeats, persistent-worker behavior, one maximum-size valid record, large threads/commits and interrupted-job retry. Preserve content, ordering, access/trust decisions and complete-output contracts.
- Report PHP allocations, process/child RSS, elapsed time, I/O and records loaded/used separately, with revision, fixture provenance, environment, failures and repeat variability. Evaluate public 16 MiB typical/32 MiB ceiling and maintenance 48/64 MiB proposals; these are study targets, not verified limits or requirements to optimize. Aggregate host capacity cannot be inferred from PHP limits.
- Measure isolated families before controlled overlap; publish reproducible commands, per-family results, a qualified operating envelope and ranked remediation proposals. Define duration, concurrency, latency/error thresholds and resource stop conditions before runs; retain failed samples and distinguish measured bounds from extrapolation.
- Use isolated local fixtures only; live workloads/environment require separate agreement. No production targets, external provider calls, new dependencies, permanent monitoring, server-setting changes, content limits or runtime fixes.

## Delivery and completion boundary

Work type: Documentation-only capacity investigation, with disposable local measurement execution after implementation approval. Allowed deliverables: this cycle's `docs/plans/application_capacity_study*` artifacts (later organized into its own directory), archived reproducible probe source/commands and Key: value result records there, cycle-specific ledger/debug updates, and corrections to the concepts below. Temporary fixtures/harnesses stay outside the repository; application, CI, deployment and generated-site changes are not permitted.

Completion: An operator can reproduce each reported sample, identify tested limits and gaps, and rerun a failed/interrupted case from documented reset/resume instructions. Success requires explicit coverage dispositions, semantic parity checks, separate isolated/overlap evidence and recovery outcomes; exceeded budgets are findings, not grounds to omit records. Missing evidence narrows the report's claims. Handoff includes prioritized follow-ups, not implemented remediation.

Known environment: The reported host uses PHP 8.3/FastCGI with a 128 MiB PHP limit. Its ordinary rebuild/build-static flow must finish through validation and activation, or retain recoverable state with an actionable retry. Local CLI success cannot establish host recovery; preserve existing user-reported recovery separately and mark new host/worker/concurrency claims unverified without actual host evidence.

## Risks and early validation

- Unrepresentative data or unavailable host access can overstate capacity: before Step 3 fixes the matrix, identify environment/fixture gaps and label synthetic versus host evidence.
- Overlap or interruptions can damage real state: Step 3 must establish isolated paths, bounded runs and reset/retry checks before dependent measurements.
- Instrumentation and recent fixes can distort comparisons: pin revisions and measure harness overhead; preserve earlier provenance instead of treating old failures as current reproductions.

## Shared components and concepts

Reuse normal application entry points and their existing contracts; no new UI/API surface. Reading/selection uses `ReadModel/ThreadRepository.php` and `Qdb/QdbBoardPolicy.php`; rebuild/publication uses `scripts/rebuild_read_model.php`, `scripts/build_static_artifacts.php`, `Host/StaticArtifactBuilder.php` and `Offline/PublicOfflineSnapshotBuilder.php`. Reuse history evidence, import/export, image validation, scoring and `TaskQueue/TaskQueueWorker.php` contracts under `src/ForumRewrite/`. The QDB audit's archived probes and `scripts/qdb_capacity_probe.js` seed workloads; the latter requires k6 and does not authorize installing it. Prefer available platform tools; any new disposable harness exists only to collect missing measurements, not duplicate application behavior. Step 3 resolves exact route/command and existing-test coverage before execution.

Concepts touched: [collection selection](../../concepts/collection_selection.md), [rebuild](../../concepts/read_model_rebuild.md), [publication](../../concepts/static_publication.md), [history evidence](../../concepts/history_evidence_indexing.md), [export](../../concepts/content_snapshot_export.md), [import](../../concepts/instance_content_import.md), [scoring](../../concepts/fastmod_scoring.md), all used unchanged; correct measurement limitations only. Proposed addition: resource measurement, describing reproducibility and evidence boundaries.

Flow: Inventory and pin fixtures → measure each family → test bounded overlap and recovery → publish envelope, gaps and ranked follow-ups.

## Approval and study checkpoint

Date: 2026-10-31 UTC; Step 1 v1/B approved; Step 2 awaits approval. Standing P2/R1; original baseline cfb8364f6db03cc09e27cd26afb93c109c723a08 retained, current inspection 3dc2c3feaa17af591eb6e819a055d26ab6990efb. Whole-cycle 0.90 reaffirmed for unchanged scope, including planning, local fixtures/measurements, recovery and reporting; inherited parent 0.90 remains separate. All three frozen R1 hashes verified. Codex single exposed rater; prior audit/plan and current component inspection disclosed. No campaign, runtime changes, measured labor, independent rating or accepted outcome; zero dependency delta. Plans remain uncommitted. The related selection audit and completed QDB fixes supply context, not duplicate discoveries or outcomes.

*— Codex · 2026-10-31 UTC*

Approval: 2026-10-31 UTC, user “approved step 2”; v1/B requirements approved unchanged. Proceed to Step 3; execution awaits Approved Step 3.

*— Codex · 2026-10-31 UTC*
