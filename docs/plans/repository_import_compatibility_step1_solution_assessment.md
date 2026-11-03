> **Feature plan:** [Step 1](./repository_import_compatibility_step1_solution_assessment.md) · Step 2 (not started) · Step 3 (not started) · Step 4 (not started)

Debug mode: enabled (project marker)
Effort study: railspike-effort / v3/repository_import_compatibility; [P2 ledger](effort_estimation_trial.md#cycle-v3repository_import_compatibility)
Extended estimates: enabled

**Cycle estimate: 0.45**

## Original Query

Please write Step 1 for each.

## Understood Intent

Assess the unchanged export/import mismatch from the [capacity study](application_capacity_study/capacity_report.txt), keeping existing import semantics and historical archive compatibility.

## Problem

The ordinary TAR download contains repository/records, but import-repository accepts only records or local_repository/records, so an operator cannot directly import the downloaded archive.

## Option A — Add strict support for the exported wrapper (Recommended)

**Estimate: 0.45**

Extend the existing command's validated archive-root recognition to accept the current export layout alongside supported historical layouts, without changing export bytes or merge policy.

- Pros: Small compatibility repair; existing commands and archives continue working; no manual repacking.
- Cons: Multiple-root ambiguity and archive safety need explicit rejection rules; does not unify the import implementations.
- Driver/uncertainty: Direct round-trip parity and failure/retry behavior around wrapper selection.

## Option B — Share archive ingestion with import-instance

**Estimate: 0.80**

Reuse the newer import path's bounded archive ingestion for both commands while preserving each command's existing accepted content and authority rules.

- Pros: Reduces divergent validation and layout handling; benefits future compatibility work.
- Cons: Broader regression surface around legacy timestamps, receipts, score supplements and intentional authority exclusions; unification can accidentally change command semantics.
- Driver/uncertainty: Maintaining both contracts through shared ingestion and recovery.

## Recommendation and boundary

Choose A as an operator-facing slice: download the actual TAR, preview/import it unchanged, verify canonical/signature bytes and applicable derived output, then exercise duplicate retry and interrupted/failing import recovery. Preserve old layouts; reject ambiguous roots, unsafe paths/links and malformed archives before writes. No recursive guessing, export-format change, ZIP-import promise, trust-policy expansion or automatic image restore. Images remain a separately verified matching archive. This cycle can proceed independently of the memory fixes.

Reuse: `scripts/import_repository_archive.php`, `src/ForumRewrite/Images/ImageBackup.php`; compare `Import/RepositoryArchive.php` validation without replacing command semantics. Reuse `tests/{RepositoryArchiveImportCommandTest,RepositoryArchiveValidationTest,ArchiveExportTest,ContentImportRecoveryTest}.php` and existing import guidance. Keep recovery honest: newer import-instance resume success does not establish the older command's recovery.

Concepts touched: [content export](../concepts/content_snapshot_export.md) used unchanged; [instance import](../concepts/instance_content_import.md) corrected for command compatibility. Zero proposed dependencies/schema changes; preserve signed bytes and canonical formats.

## Study checkpoint and approval

2026-11-01 UTC; scope v1/A; baseline 75b5e30eb40b16f6297f69d83214b4e4926499b2; R1/P2 standing enrollment, all frozen hashes verified. First recorded intake after prior study/source exposure: 0.45; Step 1 handoff reaffirms 0.45. Codex single exposed rater; pristine pre-inspection intake unavailable, not reconstructed. Options estimate complete scoped cycles, not hours or additive forecasts. Separate follow-up to v3/application_capacity_study, not a duplicate study outcome. Plan only; no implementation, host execution, commits or accepted outcome. Ledger/debug updates stay uncommitted. Await Approved Step 1 for this cycle; future branch reuse is not assumed.

*— Codex · 2026-11-01 UTC*
