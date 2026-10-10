> **Feature plan:** [Step 1](./instance_content_sync_step1_solution_assessment.md) · [Step 2](./instance_content_sync_step2_feature_description.md) · [Step 3](./instance_content_sync_step3_development_plan.md) · [Step 4](./instance_content_sync_step4_implementation_summary.md)

## Completion Contract

- Entry: `./v3 import-instance <name|hostname|url> [--dry-run]` downloads, merges approved public content, and refreshes local views. Preserve local `import-repository`; defer UI, scheduling, incremental sync, and authenticated sources. No database migration.
- Recovery: privately journal import-owned changes/publication; resume without overwriting unrelated edits, including publication retries with zero additions. No wholesale reset.
- Release: two-instance HTTP acceptance, preview/repeat/failure tests, and served dynamic/static/offline checks pass; document prerequisites and production smoke steps. Production deployment is outside this change.

## Key Risks

- **High risk—authority/content:** Audit found approval/invitation posts and missing subject imports. Validate mixed-family fixtures first; classify semantically, preserve signed bytes, exclude authority/private/settings data, and report unresolved dependencies/timestamps.
- **High risk—recovery:** Writes precede locking; duplicate-only retries skip rebuilds. Fault-test each boundary; lock mutations and persist recovery state through publication.
- **High risk—archives:** Export directory names vary. Validate representative/hostile archives early; bound transfers/extraction and reject ambiguous roots, links, or unsafe paths. Failed risk checks block dependent stages.

## Stage 1

- Goal: Complete content coverage (≤1 hour).
- Dependencies: None.
- Expected changes: Shared importer; public-content policy, subject changes, signature/dependency grouping, category counts; retain local options.
- Verification approach: Mixed families, preserved timestamps/relationships/signatures, duplicates/conflicts, unchanged approvals.
- Risks or open questions: Authority leakage; test semantic tags first; exclude approval/invitation actions and unresolved groups.
- Canonical components/API contracts touched: Archive importer, canonical readers, import tests.

## Stage 2

- Goal: Validate archives (≤1 hour).
- Dependencies: Stage 1.
- Expected changes: Unambiguous root discovery; entry-type/path/count/size limits; isolated extraction.
- Verification approach: Export-shaped renamed roots, traversal/links, ambiguous roots, truncated/oversized archives.
- Risks or open questions: Unsafe extraction; hostile fixtures first; reject before destination changes.
- Canonical components/API contracts touched: Archive validation/extraction, repository download contract.

## Stage 3

- Goal: Recover interrupted merges (≤1 hour).
- Dependencies: Stages 1–2.
- Expected changes: Lock destination checks/writes; private run manifest; verified import-owned write/commit recovery; reject unrelated dirty changes.
- Verification approach: Interrupt copy/staging/commit; retry alongside competing writers; preserve unrelated files.
- Risks or open questions: Partial writes; fault-test boundaries; stop on divergence without destructive cleanup.
- Canonical components/API contracts touched: Import orchestration, ExecutionLock, Git commits.

## Stage 4

- Goal: Publish and recover publication (≤1 hour).
- Dependencies: Stage 3.
- Expected changes: Candidate read-model promotion, active static releases, applicable standalone offline publication; retain recovery state until complete.
- Verification approach: Fail each publisher; retry with zero additions; inspect served content/private-site gates.
- Risks or open questions: Staleness/deadlock; audit lock ownership first; avoid nested locks and preserve usable artifacts.
- Canonical components/API contracts touched: Read-model/static/offline publishers, presentation paths.

## Stage 5

- Goal: Import by URL (≤1 hour).
- Dependencies: Stages 1–4.
- Expected changes: Wire import-instance; bounded HTTP(S), verified TLS/redirects, base-path handling, destination overrides.
- Verification approach: Real CLI between local instances; timeout, HTTP/size failures, non-archive responses.
- Risks or open questions: Wrong/incomplete source; validate before mutation; reject credentials/unsupported schemes.
- Canonical components/API contracts touched: v3, import-instance script, shared importer, download endpoint.

## Stage 6

- Goal: Finish operator workflow (≤1 hour).
- Dependencies: Stage 5.
- Expected changes: HTTPS hostnames; aliases via `--sources=<JSON file>`; resolved endpoints, preview/progress, review paths, complete/partial/failed exit statuses.
- Verification approach: Name/URL equivalence, unknown aliases, preview isolation, conflicts.
- Risks or open questions: Misdirection; resolution tests first; never infer addresses from presentation profiles.
- Canonical components/API contracts touched: Source resolver, CLI help/results.

## Stage 7

- Goal: Verify/document release (≤1 hour).
- Dependencies: Stages 1–6; risk gates passed.
- Expected changes: Register acceptance coverage; document names, limits, exclusions, recovery, deployment prerequisites/smoke steps.
- Verification approach: Import/publication suites, PHP/shell lint, two-instance content/attribution/authority checks through normal routes.
- Risks or open questions: False completion; inspect served artifacts/failure recovery; record environmental limitations.
- Canonical components/API contracts touched: Test runner, CLI reference, operator/production runbooks.

Verify and commit each stage with its summary before continuing. Target seven hours; split oversized stages before implementation; return to scope review if eight stages/one day cannot suffice.
