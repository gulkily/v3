> **Feature plan:** [Step 1](./archive_export_contention_step1_solution_assessment.md) · [Step 2](./archive_export_contention_step2_feature_description.md) · [Step 3](./archive_export_contention_step3_development_plan.md) · [Step 4](./archive_export_contention_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/archive_export_contention; [ledger](../effort_estimation_trial.md#cycle-v3archive_export_contention); standing P2 enrollment
Extended estimates: enabled
Status: Steps 1 and 2 approved; implementation unapproved
**Cycle estimate: 0.90**

## Problem and user stories

Overlapping archive downloads fail almost immediately on the shared export lock, while repeated requests rebuild the same archive. Visitors receive an internal-path error without useful recovery.

- As a visitor, I want a complete, current archive even when others are downloading, so I can back up or transfer content reliably.
- As an operator, I want bounded resource use and safe failure recovery, so a failed export does not leave downloads unusable.

## Core requirements

- Reuse validated, complete tar.gz, ZIP and image archives for compatible requests; coordinate duplicate builds. Preserve existing URLs, archive contents, filenames, revision checks and repository/image pairing semantics.
- Establish freshness before reuse, including content/image changes, retractions and export-version changes. Never admit a new download of an invalidated archive, silently substitute another requested revision, bypass existing integrity checks or publish partial output. Already-started transfers retain their admitted snapshot.
- Bound waiting and build concurrency; return a temporary-busy response with retry guidance when the wait expires, without exposing paths. Keep revision conflicts and actual export failures distinguishable; browser and import callers can retry safely.
- Bound retained storage and reclaim obsolete/abandoned output without disrupting admitted downloads or active builders. Interrupted builds, failed validation and insufficient space must leave a safe retry path and must not fall back to stale content.
- Provide operator diagnosis/recovery for persistent stalls, preserving mutual exclusion. Do not delete held lock files or automatically terminate workers; reuse must not require a new service or dependency.

## Delivery and completion boundary

Work type: Application change covering shared archive creation, delivery, lifecycle and focused tests; operator recovery documentation, UI-copy verification checklist and study-ledger bookkeeping included. No schema/export-format change or unrelated UI redesign.
Completion: Normal Backup/direct-link download produces a valid archive; compatible retries reuse it, concurrent requests recover, and invalidation/cleanup remain safe. Local release evidence includes actual route and browser flows; production acceptance is separately required before claiming qdb.us recovery.
Known environment: On qdb.us, open `/downloads/repository.tar.gz` directly or use Backup. After a busy response, retry the same download; after revision conflict, refresh Backup and download matching artifacts again. Persistent failure directs the operator to holder/resource diagnosis. Host evidence must show overlapping requests, a successful reused archive, extraction and safe retry; local results alone do not satisfy host acceptance.

## Risks and shared surfaces

- Freshness races could expose withdrawn content: first validate writer/retraction/image-change coverage; fail closed when freshness is uncertain and test changes during reuse/build admission.
- Resource exhaustion or cleanup races could interrupt downloads: validate overlapping builders/readers and low-space behavior early; require bounded storage/work and protected active transfers.
- The production holder is unknown; caching cannot cure every stalled build: retain host diagnosis and explicit recovery evidence, revising scope if an unrelated defect is found.

Shared inventory: Extend `ImageBackup` and `InstancePageController` for all three archive routes; reuse `ExecutionLock` and `RouteServices` delivery while preserving unrelated callers. Backup/instance aliases and Codebase download links keep their existing destinations; the instance importer keeps consuming the tar route. Extend shared failure feedback only as needed; SQLite downloads remain outside archive reuse. No new navigation surface.

## Flow and success criteria

1. Request an archive; validate requested revision and current export eligibility.
2. Reuse a valid completed archive or coordinate its build; a competing request waits within a bound or receives actionable retry feedback.
3. Download and extract the complete archive; subsequent compatible requests reuse it, while invalidated or interrupted builds recover safely.

Acceptance: Demonstrate one build for overlapping compatible requests; warm reuse without compression; correct same/different-format and revision handling; no new stale/partial downloads after changes; bounded storage and safe eviction during transfer; recovery after interruption/low space. Verify visible busy/conflict/retry flows on phone and desktop, and retain production evidence gaps explicitly.

## Study checkpoint and approval

Checkpoint: 2026-10-26T00:35:42Z; Step 2 total 0.90 reaffirmed; v2/Option B, original baseline 28530e1b59b6b5d98d314bc080dda94588cc4217, R1/P2; frozen hashes reverified. Codex single exposed rater, prior source/reproduction/reference exposure plus route inventory; earlier v1 0.45 retained in Step 1. Total includes prior work, planning, implementation, verification and recovery; no measured labor or accepted outcome.
Approval: User explicitly approved Step 2; recorded 2026-10-26T00:57:42Z. No implementation, commit or deployment.

*— Codex · 2026-10-26 UTC*
