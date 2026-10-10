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

## Stage 7 - Acceptance verification and operator documentation
- Changes: Added the import runbook and CLI/deployment/recovery references; extended real HTTP acceptance to subject updates, replies, attribution, reactions, API output, served static pages and served offline bytes. Added actual CLI recovery after source shutdown and rejection of non-archive responses. Final review tightened signature association across duplicate paths, transfer deadlines, oversized-record handling, ordinary-topic versus authority classification, and destination-directory permissions.
- Verification: `php tests/run.php ContentImportPlannerTest RepositoryArchiveValidationTest ContentImportRecoveryTest ImportedContentPublicationTest ImportInstanceCommandTest InstanceSourceResolverTest RepositoryArchiveImportCommandTest ReadModelCandidateBuilderTest CanonicalRecordParsersTest PrivateSiteAuthTest OfflineSnapshotLocatorTest OfflineSnapshotPublisherTest` — 82/82 passed. Extended HTTP/API/offline assertions: `php tests/run.php ImportInstanceCommandTest` — 5/5 passed. Final semantic-policy check: `php tests/run.php ContentImportPlannerTest RepositoryArchiveImportCommandTest` — 6/6 passed. All 18 affected/new PHP files linted; `bash -n v3`, plan/runbook local links, and `git diff --check` passed.
- Notes: The tested source and destination are disposable real local PHP HTTP instances. Production deployment/host smoke remains an operator task documented in the runbook. No schema migration, schedule, source-side API, or web UI was introduced. Fixed resource limits and unsupported/legacy cases are documented; a complete result means the supported public-content set, not a full-instance restore.

## Delivery
- All seven implementation stages are complete on `feature/instance-content-sync`, following the initial approved-planning commit and one implementation/summary commit per stage.
- Normal entry: `./v3 import-instance <name|hostname|url>`; preview with `--dry-run`, aliases with `--sources=<JSON file>`, interrupted-run recovery with `--resume`.
- Operator instructions: [Instance Content Import](../../runbooks/instance_content_import.md).

## Follow-up - Top-level help discovery
- Changes: Made `./v3 help`, `./v3 --help`, and `./v3 -h` show the existing command list successfully; added remote-import examples, resume usage, and a pointer to detailed import help. Updated the CLI reference.
- Verification: All four top-level help entry points show identical output containing import, resume, and detailed-help usage; explicit help returns 0 and no-argument usage retains exit 1. Import-specific help, `bash -n v3`, and `git diff --check` passed.

## Follow-up - Explicit dry-run documentation
- Changes: Added a dedicated `--dry-run` option explanation and example to the CLI reference and command help; clarified unchanged destination data, temporary/lock files, and incompatibility with `--resume` in the runbook.
- Verification: Command help returns 0 and includes the dry-run description, example, and resume restriction; PHP lint and `git diff --check` passed. Runtime import behavior is unchanged.

## Follow-up: legacy archive compatibility

The original explicit-timestamp restriction is superseded by isolated history
lookup and portable `records/post-timestamps/` metadata. Source Git configuration
and history remain excluded from the destination. Signed records stay unchanged;
rebuilds, subsequent imports, and recovery use the saved hash-bound dates. See
[the fix checklist](instance_content_sync_fix_checklist.md) for validation.
