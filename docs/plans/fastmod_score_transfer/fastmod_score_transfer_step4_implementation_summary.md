> **Feature plan:** [Step 1](./fastmod_score_transfer_step1_solution_assessment.md) · [Step 2](./fastmod_score_transfer_step2_feature_description.md) · [Step 3](./fastmod_score_transfer_step3_development_plan.md) · [Step 4](./fastmod_score_transfer_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/fastmod_score_transfer; [ledger](../effort_estimation_trial.md)
Extended estimates: enabled
**Cycle estimate: 0.75**
Status: Eight stages complete locally; maintainer review pending.

## Entry checkpoint

2026-10-29 UTC. User approved Step 3 after explicitly removing inclusion controls. Scope v2/A; same baseline 53f0c89260c08e3fdf1d5535380d84d7cec92768, R1/P2, single exposed Codex rater and inherited reference verification. Projection 0.75 reaffirmed; original stage-work S1–S8: 0.10/0.10/0.18/0.10/0.12/0.14/0.12/0.06, non-additive. No measured labor or maintainer acceptance. Planning commit 157df3f3 on feature/fastmod-score-transfer. No dependencies approved or needed; no deployment/merge authorization.

*— Codex · 2026-10-29 UTC*

## Stage 1 - Define the scores supplement

**Original estimate: 0.10**
**Retrospective stage-work assessment: 0.10**

- Changes: Bounded deterministic Key: value codec and versioned format specification. Unknown fields/versions and malformed values reject the entire supplement; exporter selects only approved fields.
- Verification: FastmodScoreTransferTest 2/2 pass, including round-trip, empty input, forbidden fields, invalid dates/probabilities and oversized input. Initial strict array-order assertion corrected to compare decoded field values; no behavior changed for it.
- Notes: No dependencies/schema changes; runtime integration belongs to later stages. Estimates unchanged, same scope/rater/date as entry.

*— Codex · 2026-10-29 UTC*

## Stage 2 - Preserve destination scores

**Original estimate: 0.10**
**Retrospective stage-work assessment: 0.10**

- Changes: Transactional insert-only import with exact content/revision/named-rubric matching, preview, duplicate/existing/incompatible counts and imported origin. Added scoring concept.
- Verification: Transfer/store tests 14/14 pass; preview writes nothing, failure/local rows win, original ages persist, repeated import preserves rows, injected SQLite failure rolls back all inserts and creates no jobs.
- Notes: S1 commit a3efe835. No schema/dependency changes; original stage scope/estimate unchanged.

*— Codex · 2026-10-29 UTC*

## Stage 3 - Export through existing downloads

**Original estimate: 0.18**
**Retrospective stage-work assessment: 0.18**

- Changes: Both public content formats automatically inject the bounded score snapshot; configured private score path is resolved through existing config. Content cache identity includes score bytes, including committed WAL updates; image identity does not. Reserved file content is replaced; no private database created on read.
- Verification: Archive/export regressions plus codec/store 15/15 pass; expanded transfer tests 5/5 pass with real TAR/ZIP parity, reserved file replacement, missing/empty store, score-only WAL updates, pruning, unchanged image cache. Existing compression-race/failure cleanup tests pass. Corrected codec numeric grammar for scientific notation beginning with digits other than 1.
- Notes: S2 commit 59fd0988. No setting/flag/dependency/schema introduced. Consistent bounded query snapshot; content hash matching reads only scored committed posts. No browser changes yet.

*— Codex · 2026-10-29 UTC*

## Stage 4 - Accept and preview supplements

**Original estimate: 0.10**
**Retrospective stage-work assessment: 0.10**

- Changes: TAR extractor allows only the bounded supplement at the repository root; oversized supplements remain excluded. Shared score import service validates complete input and matches only accepted/identical plan posts. Ordinary dry-run reports score eligibility and malformed supplements without private database creation. CLI help/output has no inclusion flag.
- Verification: Archive validation/planner/import-command regressions 19/19 pass. Transfer tests 6/6 pass including eligible preview, local conflict exclusion, malformed extracted supplement and no private directory creation. Fixed a test fixture call to use the existing command helper.
- Notes: S3 commit cca64f04. Actual merge and saved score recovery follow in S5–S6; no ZIP import added.

*— Codex · 2026-10-29 UTC*

## Stage 5 - Retain recoverable score input

**Original estimate: 0.12**
**Retrospective stage-work assessment: 0.12**

- Changes: Save normalized validated supplement privately before content writes, bind its SHA-256 and destination score path into the existing journal, and verify both before resume. Old journals default to absent scores. Added instance-import concept.
- Verification: Transfer/recovery tests 10/10 pass, including removal of original source after preparation, missing/changed payload, changed score destination, original write/publication interruption cases, clean duplicate-only repeats and local edit preservation.
- Notes: S4 commit 065ee22f. No new journal format or inclusion choice. S6 owns actual merge/publication integration.

*— Codex · 2026-10-29 UTC*

## Stage 6 - Merge before final publication

**Original estimate: 0.14**
**Retrospective stage-work assessment: 0.14**

- Changes: Merge from verified saved input before publication, rechecking accepted canonical bytes under the writer lock. Keep the recovery journal until publication succeeds; report malformed supplements as partial content success. Entirely incompatible supplements do not initialize private state.
- Verification: Transfer/recovery/publication tests 14/14 pass. Injected failures before score merge, after score commit and during offline publication resume with the original source removed; original timestamps, live/offline meta tags, zero work jobs and a single content commit verified. Existing private-destination publication behavior passes. Fixture initially lacked required instance records; fixed by using the existing full fixture.
- Refinement: The planned after-rebuild hook is unnecessary: compatibility uses installed canonical bytes, and existing rebuild/offline publication does not overwrite private scores. Merge immediately before the existing publisher achieves the approved ordering and recovery contract with no new callback API. Scope/estimate unchanged.
- Notes: S5 commit 4d659120. No core queries/schema changes, external components or provider calls.

*— Codex · 2026-10-29 UTC*

## Stage 7 - Verify the normal transfer flow

**Original estimate: 0.12**
**Retrospective stage-work assessment: 0.15**

- Changes: Added real HTTP → CLI → browser transfer journey and recovery/compatibility regressions; effective-tags concept. Bounded decoder splitting prevents field/block floods from allocating unbounded arrays. Normalized negative-zero serialization. Fixed identical post matching across dated/flat canonical paths and skipped hash scans for absent/malformed supplements.
- Verification: Transfer tests 10/10 pass, including actual concurrent score change during compression (stale archive rejected, retry fresh), malformed supplement with successful content, score-only import without Git commit, preserved local suppression, and dated-path duplicates. Browser test passes public download, dry-run with no private store, import/repeat preserving results, live/Forte/text/offline/static meta tags, 390/1280 widths, JS on/off and zero provider calls. Viewed both phone/desktop screenshots; score display and actions visible without overflow. Static assertion initially used pre-release path; corrected to current/tags/meta.html.
- Notes: S6 commit 6c4b4df4. Existing UI consumes scores unchanged; no new controls, navigation, theme assets or first-paint mutation. Chromium/local fixture verified; physical devices and exhaustive theme/browser matrix not claimed. Retrospective increase reflects acceptance fixtures/edge corrections, not measured time or a scope expansion.

*— Codex · 2026-10-29 UTC*


## Stage 8 - Operations and closeout

**Original estimate: 0.06**
**Retrospective stage-work assessment: 0.06**

- Changes: Updated archive recipient README, CLI/scoring references, import/image runbooks, format specification and portability assessment. Documented automatic score visibility, advisory origin, destination-wins matching, unchanged retention ages, excluded private workflow/suppressions, supported remote/saved-archive path and pending-journal recovery. Repaired plan navigation and indexed completion.
- Verification: Combined archive/import/recovery/store/derived-tag/flag/legacy-date suite 77/77 pass; normal browser flow including static build passes (S7). PHP syntax, local Markdown path checks, diff whitespace and dependency inventory checks pass. No dependency or core/private schema delta; todo.txt unchanged.
- Notes: S7 commit bbdb8d9e. Existing import-repository remains records-only and ZIP import remains unsupported, as scoped. Full repository suite, physical devices, exhaustive themes/browser matrix, production migration, model accuracy, deployment and merge/push are not claimed.

*— Codex · 2026-10-29 UTC*

## Study closeout and release limits

Entry projection 0.75 retained; separate retrospective cycle scope assessment 0.75. Scope v2/A, original baseline/R1/P2 and single exposed Codex rater retained; no independent rating or measured labor. Stage original/retrospective S1–S8: 0.10/0.10, 0.10/0.10, 0.18/0.18, 0.10/0.10, 0.12/0.12, 0.14/0.14, 0.12/0.15, 0.06/0.06; non-additive. Earlier 0.70 projections and missing pristine intake preserved. Maintainer comfortable-fit/acceptance pending; no accepted-count increment.

Planning 157df3f3; S1 a3efe835; S2 59fd0988; S3 cca64f04; S4 065ee22f; S5 4d659120; S6 6c4b4df4; S7 bbdb8d9e; S8 contains this closeout. All stage commits include their summary update. Work is local on feature/fastmod-score-transfer; no deployment, merge or push performed.

*— Codex · 2026-10-29 UTC*
