> **Feature plan:** [Step 1](./fastmod_score_transfer_step1_solution_assessment.md) · [Step 2](./fastmod_score_transfer_step2_feature_description.md) · [Step 3](./fastmod_score_transfer_step3_development_plan.md) · [Step 4](./fastmod_score_transfer_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/fastmod_score_transfer; [ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)
Extended estimates: enabled
**Cycle estimate: 0.75**
Status: Steps 1–2 approved; Step 3 proposed; eight stages planned, none implemented.

## Completion Contract

Normal TAR/ZIP content download with scores → ordinary `import-instance` preview/import → preserved compatible probabilities and effective tags without model calls. Existing destination rows, timestamps, signatures and suppression policy survive; interruption resumes from saved input without requiring the remote source. Old archives without scores stay valid. Local completion requires normal HTTP/CLI/browser round-trip and recovery evidence; deployment, live score disclosure and provider accuracy are outside scope. No dependencies added. Approved plans are committed first on a new feature branch after Step 3 approval; no predecessor-branch reuse assumed.

## Key Risks and Contracts

- **High risk:** Field disclosure: scores automatically accompany content archives. Document recipient visibility. Reserve one generated supplement path outside records; replace source files at that path with the generated allowlisted supplement. No raw SQLite, jobs, failures, exchanges or suppressions.
- **High risk:** Partial import: the download workspace is deleted on exit, and current recovery saves only record payloads. Save the validated supplement in existing private recovery storage before content writes; merge transactionally before offline publication, and retain pending recovery until both finish.
- Cache identity currently ignores scores; snapshot the bounded score input consistently and bind its digest/format to content-cache identity. Never use database mtime or main-file hashing as WAL-safe evidence; image-cache identity remains unchanged. Missing score stores remain valid empty-score exports.
- Existing `save()` overwrites results and resets timestamps. Introduce insert-only imported-result semantics, preserving even failed/local destination rows; exact content/rubric matching and original ages remain authoritative. No core/private schema addition expected; any core-dependent query change must include CONTRIBUTING's version/spec/prior-schema startup checks.

## Concepts touched

Fastmod scoring (changed), content snapshot export (changed), instance content import (changed), effective tags (used unchanged). Relevant entries are missing/proposed; create concise purpose/scenario/source entries only with delivered stages below, reusing entries if concurrently added. Approval acceptance stays unchanged. No concept inventory sweep or backfill.

## Stage 1 — Define the scores supplement
**Estimate: 0.10**
- Goal/Dependencies: Bounded, deterministic versioned plain-text transfer; none.
- Changes/Contracts: Proposed `FastmodScoreTransfer::encode/decode`; format spec defines reserved path, fields, limits, timestamp/probability rules and duplicate handling.
- Verification: Round-trip, empty, unknown version, malformed/oversized data and forbidden fields; validate whole supplement before any score write.
- Risk/mitigation: Ambiguous input; reject malformed supplement as a unit, with content import still available.
- Estimate driver/uncertainty: Strict codec; numeric/timestamp edge cases.

## Stage 2 — Preserve destination scores
**Estimate: 0.10**
- Goal/Dependencies: Insert compatible imported results safely; S1.
- Changes/Contracts: `SqliteFastScoreStore::importScoredRows` (insert-only transaction) and preview/matching helper using existing content hashing/rubric composition; existing source field identifies imported results. Add scoring concept entry.
- Verification: Destination wins, original ages/pruning, stale content/rubrics, duplicate rows, rollback on interruption, no work/exchange creation; canonical/read-model hash parity.
- Risk/mitigation: Preview/apply races; recheck membership/content and use atomic conflict handling at apply.
- Estimate driver/uncertainty: Store reuse; matching rejected/conflicting posts.

## Stage 3 — Export through existing downloads
**Estimate: 0.18**
- Goal/Dependencies: Both content formats carry scores automatically; S1–S2.
- Changes/Contracts: ImageBackup/ExportIdentity/ArchiveCache snapshot identity and supplement injection; content-export concept entry. Keep image backup and signed content untouched.
- Verification: TAR/ZIP parity, empty/missing store, reserved-path collision, score-only changes/pruning without Git commit, concurrent writer/WAL snapshot and failure cleanup.
- Risk/mitigation: Stale cache or excessive lock time; digest the bounded score snapshot, preserve existing lock ordering and test empty-score/cache-hit paths.
- Estimate driver/uncertainty: Cache integration; snapshot bounds and contention fixtures.

## Stage 4 — Accept and preview supplements
**Estimate: 0.10**
- Goal/Dependencies: Automatic score import and honest preview; S1–S2.
- Changes/Contracts: RepositoryArchive narrow optional-path extraction; import_instance automatic processing/help; planner-based score preview excludes rejected/missing/conflicting posts. No ZIP importer.
- Verification: Real downloaded old/new archives, malformed/unknown supplement, limits/traversal and dry-run with no private-state initialization.
- Risk/mitigation: Broader archive trust; keep existing allowlist/limits and validate supplement separately from content.
- Estimate driver/uncertainty: Parser wiring; duplicate-only imports.

## Stage 5 — Retain recoverable score input
**Estimate: 0.12**
- Goal/Dependencies: Interrupted imports keep their exact score payload; S4.
- Changes/Contracts: ContentImportRunner existing saved-run payload/context carries validated supplement hash and destination score path; retain old-journal compatibility. Add instance-import concept entry.
- Verification: Remove original download after preparation, resume with matching context; changed/missing saved payload and different destination fail without score writes; archives without scores and old journals unchanged.
- Risk/mitigation: Cleanup loses recovery data; save/verify payload before canonical writes, never inside tracked records.
- Estimate driver/uncertainty: Existing journal extension; crash-window fixtures.

## Stage 6 — Merge before final publication
**Estimate: 0.14**
- Goal/Dependencies: Completed import includes scores and fresh offline labels; S2, S5.
- Changes/Contracts: ImportedContentPublisher after-rebuild/before-offline hook and Runner completion/report integration; CLI resume guidance/counts. Merge only content accepted by this import or identical existing content, not arbitrary matching local IDs.
- Verification: Fail before/after score commit and during offline publication; retry from saved payload preserves local results/ages, reports skips/incomplete status correctly, and makes no provider calls. Existing publication tests remain.
- Risk/mitigation: False completion or retry overwrite; transactional insert-only merge, repeat-safe publication, clear pending state last.
- Estimate driver/uncertainty: Commit/publication boundary; retry counts.

## Stage 7 — Verify the normal transfer flow
**Estimate: 0.12**
- Goal/Dependencies: End-to-end accepted behavior; S3–S6.
- Changes/Contracts: Extend real export/download/import fixtures and tag-browser journey; effective-tags concept entry describes unchanged consumption.
- Verification: Source download → destination preview/import/repeat → live/text/Forte/offline meta labels; local score conflicts, old suppressions, incompatible revision and malformed supplement. Verify no extra provider/Git writes for score-only transfer; phone/desktop reading and import recovery.
- Risk/mitigation: Unit-only success; exercise public archive route and real CLI with isolated instances and no inclusion parameters.
- Estimate driver/uncertainty: Integrated fixtures; environment-dependent browser setup.

## Stage 8 — Operations and closeout
**Estimate: 0.06**
- Goal/Dependencies: Reviewable release and recovery documentation; S7.
- Changes/Contracts: Score format/runbooks, normal export/CLI examples, portability assessment, approved study/UI records and concept source verification. No unrelated format migration.
- Verification: Focused archive/import/store suites, syntax/link/diff checks; account for baseline failures, inventory consistency and zero dependency delta.
- Risk/mitigation: Misleading trust/retention claims; document imported advisory origin, automatic score disclosure, unchanged ages, excluded suppressions and resume action.
- Estimate driver/uncertainty: Shared final documentation; inherited test failures.

## Study checkpoint and approval

2026-10-29T19:54:13Z; Step 3 whole-cycle projection 0.75, revised from preserved 0.70 for newly traced saved-payload lifetime, publication ordering and timestamp-preserving insertion. Scope v1/A unchanged; baseline 53f0c89260c08e3fdf1d5535380d84d7cec92768, planning checkout f6f295f9ddd0f80125707598081fcc7ab987a28f; R1/P2, exact three hashes reverified. Codex single exposed rater; pristine intake still missing. S1–S8 are original non-additive stage-work estimates; shared acceptance belongs to S7, closeout to S8, whole-cycle planning only to the independently judged total. Intended bounded stages must be split before dependent work if implementation inspection shows they exceed workflow sizing. Source review only; no implementation/tests or concept files created. Await Approved Step 3; no accepted outcome or measured labor.

*— Codex · 2026-10-29 UTC*

## Automatic inclusion revision — 2026-10-29

Request: Include scores without a parameter.
Disposition: User-directed scope v2/A supersedes source and destination opt-ins with automatic inclusion. All allowlist, compatibility, destination-wins and recovery constraints remain. Prior checkpoint descriptions of opt-ins are historical. No implementation authorization inferred; Step 3 approval remains pending.
Study: Existing cycle estimate reaffirmed; removing option plumbing leaves the dominant snapshot/recovery work. Original estimates and R1/P2 baseline retained, single exposed Codex rater; inherited reference verification, no measured labor or accepted outcome.

*— Revised by Codex · 2026-10-29 UTC*
