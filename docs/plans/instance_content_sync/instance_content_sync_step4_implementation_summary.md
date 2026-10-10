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

## Stage 4 - Publication and publication recovery
- Changes: Import publication uses validated read-model candidates, complete static releases, and standalone snapshot/update publishers. Added an explicitly caller-locked candidate promotion path to avoid reacquiring the writer lock. Private destinations publish only the read model.
- Verification: `php tests/run.php ImportedContentPublicationTest ReadModelCandidateBuilderTest` — 5/5 passed, including five injected publication failures followed by saved-run recovery, actual static tag content, served snapshot selection/content, unchanged commit count, and private-destination exclusion. PHP lint/diff checks passed.
- Notes: Board default views can filter unliked content; verification uses the ordinary general-tag page and offline content, preserving existing local visibility rules. Failed activation/publication leaves the recovery journal in place until a complete retry succeeds.

## Stage 5 - URL-to-import CLI
- Changes: Added `./v3 import-instance` with URL download, private workspace cleanup, destination overrides, preview, saved-run resume, and shared merge/publication wiring. HTTP(S) transfers verify TLS, reject credentials/unsupported schemes and HTTPS downgrades, bound redirects/time/bytes, and detect truncated downloads.
- Verification: `php tests/run.php ImportInstanceCommandTest` — 2/2 passed: actual source application archive endpoint to destination CLI, preview/import/repeat, static content, bounded 404/redirect/size/timeout failures, and URL scheme/credential rejection. PHP/shell lint and diff checks passed.
- Notes: Default limits are 256 MiB compressed, 1 GiB expanded, 100,000 archive entries, five redirects, and a 120-second transfer deadline. Source URL base paths are retained. No source-side changes or authentication are required for public instances.

## Stage 6 - Names, preview, and operator results
- Changes: Added explicit JSON name mappings, HTTPS hostname resolution, base-path preservation, unknown-name guidance, bounded/sanitized terminal reporting, per-category exclusions and review locations, and complete/partial/failed exit codes. Recovery verifies the saved static root and site profile as well as repository/database paths.
- Verification: `php tests/run.php InstanceSourceResolverTest ImportInstanceCommandTest ContentImportRecoveryTest` — 7/7 passed; expanded CLI partial-result test plus resolver suite — 5/5 passed. Alias-based repeat import, preview isolation, conflicts, unsupported families, authority exclusions, malformed mappings, and unknown names covered; PHP/shell lint and diff checks passed.
- Notes: Hosts default to HTTPS; HTTP remains available through an explicit URL. Names are not inferred from display profiles. Complete means the supported public content set, with intentional exclusions still listed; invalid/unsupported/conflicting records produce exit 2.
