> **Feature plan:** [Step 1](./archive_export_contention_step1_solution_assessment.md) · [Step 2](./archive_export_contention_step2_feature_description.md) · [Step 3](./archive_export_contention_step3_development_plan.md) · [Step 4](./archive_export_contention_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/archive_export_contention; [ledger](../effort_estimation_trial.md#cycle-v3archive_export_contention)
Extended estimates: enabled
Status: Step 4 in progress; Step 3 approved
**Cycle estimate: 0.90**

## Entry checkpoint

Planning commit: 3256d774; branch fix/archive-export-contention.
Scope: v2 / Option B; original baseline 28530e1b59b6b5d98d314bc080dda94588cc4217; R1/P2; 0.90 reaffirmed before implementation on 2026-10-26 UTC. Codex single exposed rater, prior source/reference/reproduction exposure retained. No measured labor; no acceptance claim.
Stage originals: S1/v1 0.20 freshness; S2/v1 0.25 reusable delivery; S3/v1 0.20 contention; S4/v1 0.15 retry; S5/v1 0.25 lifecycle; S6/v1 0.20 acceptance. Scope/drivers/uncertainty inherited from Step 3, not independent ratings. Shared acceptance belongs to S6.
Dependencies: Zero additions or updates authorized/proposed; use existing PHP/system tools. No schema or canonical-format change.

*— Codex · 2026-10-26 UTC*

## Stage 1 - Freshness contract

**Original estimate: 0.20**
**Retrospective stage work: 0.20**

- Changes: Content-byte/per-mode identity includes image inventory, revision and generator/guide hashes; revalidation after compression rejects source changes. Existing dirty-record and image-integrity refusals retained. ImageRestore and ImageStore explicitly require/use instance writer coordination.
- Verification: `php tests/run.php ArchiveExportTest RepositoryContentExportTest ImageContentTest`: 10/10 passed; same-size/same-mtime edits, untracked additions/removal, permissions and image identity covered. `git diff --check` passed.
- Notes: S1/v1 unchanged; warm-path representative workload timing and coordinated mutation races will be measured with cache acceptance in S6. Identity scans bytes rather than trusting timestamps. Out-of-band writers must respect the existing writer lock; no claim of atomicity against arbitrary external filesystem mutation. No schema/dependency change.

*— Codex · 2026-10-26 UTC*

## Stage 2 - Reusable downloads

**Original estimate: 0.25**
**Retrospective stage work: 0.25**

- Changes: Private identity/format-keyed artifacts with atomic directory publication and checksum validation; archive streams hold shared reader locks. HTTP delivery streams the admitted handle and closes it without deleting shared output. Existing path-based download contract unchanged.
- Verification: Focused export/image suite 11/11 passed; cold/warm reuse for all three kinds, corrupted metadata rebuild with existing reader intact, same-head source change and actual download/import compatibility. `git diff --check` passed.
- Notes: S2/v1 unchanged. Stage 1 commit 7c4a5e91. Direct export callers own/close the additional stream; PHP also closes it on request exit. Cleanup and storage bounds follow in S5; no canonical/dependency/schema changes.

*— Codex · 2026-10-26 UTC*
