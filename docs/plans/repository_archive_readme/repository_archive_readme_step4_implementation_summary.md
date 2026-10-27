> **Feature plan:** [Step 1](./repository_archive_readme_step1_solution_assessment.md) · [Step 2](./repository_archive_readme_step2_feature_description.md) · [Step 3](./repository_archive_readme_step3_development_plan.md) · [Step 4](./repository_archive_readme_step4_implementation_summary.md)

Debug mode: disabled

Effort study: `railspike-effort` / `v3/repository_archive_readme`; [ledger](../effort_estimation_trial.md). P2 / R1; scope v2.

Extended estimates: disabled (inherited).

**Cycle estimate: 0.25**

## Approval and entry checkpoint

Step 3 explicitly approved on 2026-10-25 after plan delivery. Planning commit `832e6e140` precedes implementation. Feature baseline `2a6dfba08` includes current remote history-free export behavior; original cycle baseline `99a0def2af1f0b19b4b5cc284c1eac920da9464d` retained. Step 4 entry 2026-10-25: **0.25 reaffirmed**, same scope and R1; hashes verified at Step 3, Codex single exposed rater. No new requirements, dependencies or schema changes. Implementation occurs in an isolated worktree; unrelated local registration work is excluded.

*— Codex · 2026-10-25 UTC*

## Stage 1 — Deliver the guide in both formats

- Changes: shared recipient guide, snapshot-only preparation helper, collision-safe byte-preserving notes and one integration point before final snapshot sizing/packaging. Existing guide preparation is repeatable; fixture README is unchanged.
- Verification: `php tests/run.php RepositoryContentExportTest` passed 3/3, including real tar/ZIP extraction, missing/current/custom README, file/directory naming collisions, source non-mutation, legacy signed bytes, image-pair restoration and manifest identity. PHP lint and `git diff --check` passed.
- Notes: preservation errors are actionable and propagate through the existing backup failure contract. No dependencies or schema changes. Stage 2 adds explicit ordinary-route failure/retry and documented CLI evidence.

*— Codex · 2026-10-25 UTC*

## Stage 2 — Verify usage and failure recovery

- Changes: pinned/unpinned GET responses now have extracted-guide assertions; directory-in-place-of-README produces HTTP 409 without archive bytes or leaked staging, then repaired source notes permit both downloads. CLI checks exercise image verification, wrong-pair rejection and recovery, plus the guide's local import preview.
- Discovery/correction: the first command check failed because the legacy local importer accepts `records/` or `local_repository/records/`, not the current `repository/records/` wrapper. Corrected the guide to create a separate records-only import tarball from either extracted format, preserving the original snapshot for images. No importer behavior changed; this is the approved instruction-validation correction, not new feature scope. Original failure is retained as evidence rather than reported as a baseline failure.
- Verification: `php tests/run.php RepositoryContentExportTest ImageContentTest RepositoryArchiveValidationTest RepositoryArchiveImportCommandTest ImportInstanceCommandTest BackupPageTest` passed **25/25** after correction. Ordinary routes, actual tar/unzip extraction, local dry-run/no destination mutation, image CLI verification/no activation, mismatch/retry, archive limits and import compatibility passed. `git diff --check` passed.
- Notes: existing image suite verifies actual restoration including image bytes; no browser UI changes, no deployment, no dependencies or schema changes. Stage 3 closes documentation and study records.

*— Codex · 2026-10-25 UTC*

## Stage 3 — Closeout

- Changes: cycle study entries transferred without unrelated ledger edits; feature navigation/index and approval outcomes reconciled. Original workspace planning copies are superseded by this canonical feature folder, not separate observations.
- Verification: scoped diff and relative-link checks passed; all three stage commits include this summary after the separate planning commit. Zero dependency delta; no schema changes. The passing 25-test run is the final relevant test result; no subsequent application changes.
- Notes: UI/browser verification is inapplicable because no rendered interface changed. Full application suite and deployment were not performed. No runtime configuration or canonical repository migration is required.

*— Codex · 2026-10-25 UTC*

## Study closeout

**Retrospective cycle scope: 0.25**

2026-10-25: projection 0.25 retained separately, scope v2, R1/P2, Codex single exposed rater. Completed stages: 3/3. Planning `832e6e140`, guide/integration `0706092a8`, command/recovery verification `b09388be9`, and this closeout commit. Original cycle baseline `99a0def2af1f0b19b4b5cc284c1eac920da9464d`; implementation baseline `2a6dfba08`. Correction of the import command followed observed failure within approved scope; no added requests or splits. Actual active work was not measured. Maintainer comfortable-fit and study acceptance remain pending; accepted outcomes do not increment. No push or deployment is included in this implementation handoff.

*— Codex · 2026-10-25 UTC*

## User-directed correction — one archive README

The user explicitly removed the original-notes preservation requirement: only the
download guide should appear as the archive README. This supersedes the earlier
preservation requirement and its checks. The exporter now replaces README.md only
in its temporary snapshot, without creating README.repository.md or a notes link.
The source repository's README remains unchanged.

Verification: `php tests/run.php RepositoryContentExportTest` passed 4/4, covering
both archive formats, a single README with missing/custom/current source guides,
repeat preparation, unchanged source files, actual downloads, import/image
verification and preparation failure/retry. `git diff --check` passed. No new
dependencies. Existing study snapshots remain historical; this small direct
correction does not start a new Railspike cycle.

*— Revised by Codex · 2026-10-25 UTC*
