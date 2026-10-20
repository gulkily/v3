> **Feature plan:** [Step 1](./legacy_backup_compatibility_step1_solution_assessment.md) · [Step 2](./legacy_backup_compatibility_step2_feature_description.md) · [Step 3](./legacy_backup_compatibility_step3_development_plan.md) · [Step 4](./legacy_backup_compatibility_step4_implementation_summary.md)

Debug mode: enabled

## Scope and state

- Steps 1–3 explicitly approved. Branch: `fix/legacy-backup-compatibility`; planning commit: `099870a5`.
- Application work follows four approved stages. No deployment or production import is authorized. Production/WSL acceptance remains pending until verified there.
- Requests: diagnose/prevent recurrence (implementation authorized); preserve remote findings (saved unchanged; include with operator documentation stage).
- Stage 1 complete; Stage 2 next.

## Stage 1 - Legacy-compatible backup validation

- Changes: Reuse canonical loading and batch history preloading during image-reference scans; disable filesystem-date fallback for backups through an optional constructor flag preserving existing callers. Attach record paths to parse/reference errors. Retain image integrity checks and original bytes.
- Verification: `php tests/run.php ImageContentTest CanonicalRecordParsersTest LegacyTimestampImportTest` passed (55 tests). Covers Git/metadata dates, 40-post scan under 10 seconds, supported dated-path rename, absent provenance, stale metadata, invalid path, symlinks, legacy image tar/ZIP/image exports, unchanged archived bytes, missing images, and existing corrupt-image checks. `git diff --check` passed.
- Notes: Initial test runs exposed two fixture errors (unsupported rename path and reading multiple Git date lines); corrected before passing. No production evidence inferred. No schema/UI changes; those checks are inapplicable.
