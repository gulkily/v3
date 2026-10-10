> **Feature plan:** [Step 1](./instance_content_sync_step1_solution_assessment.md) · [Step 2](./instance_content_sync_step2_feature_description.md) · [Step 3](./instance_content_sync_step3_development_plan.md) · [Step 4](./instance_content_sync_step4_implementation_summary.md)

## Stage 1 - Public content coverage
- Changes: Added shared canonical archive catalog and public-content planner, including subject changes, semantic authority exclusions, dependency/signature grouping, duplicate/conflict detection, and byte hashes. Local archive CLI reuses the catalog and retains its options. Moved the four planning artifacts into their feature folder and updated navigation.
- Verification: `php tests/run.php ContentImportPlannerTest RepositoryArchiveImportCommandTest` — 4/4 passed; new PHP classes linted; `git diff --check` passed.
- Notes: Legacy posts without explicit timestamps are reported as invalid instead of rewriting signed content or importing foreign Git history. Source signatures are preserved, not represented as newly authenticated submissions.

## Stage 2 - Bounded archive validation
- Changes: Added a two-pass tar.gz reader with checksum/CRC validation, bounded compressed/expanded size and entry count, unambiguous repository-root discovery, and rejection of links/special entries/traversal. Only regular records are extracted into private temporary storage; other archive categories are counted.
- Verification: `php tests/run.php RepositoryArchiveValidationTest` — 3/3 passed (renamed export root, history exclusion, unsafe/ambiguous entries, truncation and size/count limits); PHP lint and diff checks passed.
- Notes: Accepts regular GNU/ustar archives and GNU long filenames. Unsupported tar extensions fail explicitly rather than bypass validation. Existing local archive command behavior is retained; remote imports use the new bounded reader.

## Stage 3 - Durable merge recovery
- Changes: Added a shared-write-lock and repository-lock guarded runner with private staged payloads, atomic recovery journal, recorded import ownership, conflict review copies, and commit crash-window detection. Resume preserves divergent/unrelated edits and completes publication even when no new records remain.
- Verification: `php tests/run.php ContentImportRecoveryTest` — 3/3 passed, covering four injected interruption boundaries, one-commit recovery, divergent edits, preview, duplicate-only repeat, and contention on the normal writer lock. PHP lint/diff checks passed.
- Notes: `--resume` will recover saved payloads without downloading the source again. Recovery state is under the destination's private `.git/instance-import/`; no application database/schema changes.
