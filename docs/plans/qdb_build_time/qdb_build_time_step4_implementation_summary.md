> **Feature plan:** [Step 1](./qdb_build_time_step1_solution_assessment.md) · [Step 2](./qdb_build_time_step2_feature_description.md) · [Step 3](./qdb_build_time_step3_development_plan.md) · Step 4

State: Approved Step 3 received; implementation in progress on feature/qdb-build-time.
Planning commit: f526da98. Frozen baseline: 4abda3db, /tmp/qdb-build-time-baseline.
Inherited state: Prior pagination cycle complete; preserve unrelated audit drafts, ledger edits and prior-cycle host report. No merge, push or production changes in this cycle yet.
Study: railspike-effort, R1/P2, v3/qdb_build_time; original v1/A 0.50 retained, selected v2/C 1.25. Single exposed Codex rater; reliable measured labor unavailable.

**Entry cycle estimate: 1.25**

## Stage 1 — Reproducible baselines and parity fixtures

**Original stage-work estimate: 0.15**
**Retrospective stage-work assessment: 0.18**

- Changes: Fixed corpus generator and separate-operation harness; frozen full row outputs beside baseline checkout. Added production rendering batch timings.
- Verification: Frozen baseline activity suite 8/8, normal CLI static build 1/1; instrumented CLI static test 1/1. Edge and 10000-event full row streams repeat exactly; empty/missing Git produce empty streams. Valid 5215-profile baseline: 115.4633 seconds, 10 MiB PHP peak, 5215 files. PHP lint and diff whitespace checks pass. Failed exploratory samples and fixes retained in findings. Stage 7 owns repeated matched acceptance.
- Notes: Originals for subsequent stages: S2 0.16, S3 0.15, S4 0.20, S5 0.23, S6 0.16, S7 0.15, S8 0.10. Scope unchanged from approved Step 3; no revisions.

*— Codex · 2026-10-30 UTC*

## Stage 2 — Explicit profile delivery control

**Original stage-work estimate: 0.16**
**Retrospective stage-work assessment: 0.13**

- Changes: Added site-mutable FORUM_STATIC_PROFILE_PAGES_ENABLED, default true, adjacent to static detail control. Only full-build profile enumeration is conditional. Registered nullable private schema metadata, excluded from template defaults like other site flags so templates cannot override canonical settings.
- Verification: Default/on profile HTML byte equality; off produces zero profile artifacts while thread flag remains independent; all profile rows and offline snapshot retained. Configuration creation/refresh and schema tests passed. Updated registry inventory after expected first-run failure; evaluator/presentation suite 23/23 passes. Earlier configuration/control run: other 32 checks passed.
- Notes: Runtime/stale-artifact transitions and browser flow belong to Stage 3. Dependencies added: zero. Scope/estimate unchanged; labor unmeasured.

*— Codex · 2026-10-30 UTC*

## Stage 3 — Profile transitions and recovery

**Original stage-work estimate: 0.15**
**Retrospective stage-work assessment: 0.15**

- Changes: Disabled profiles bypass both public-root and release artifacts in PHP; shared refresh excludes copied profiles; single-route regeneration skips them. Added operator mode/rebuild/recovery guidance and concept entry.
- Verification: Four focused PHP checks pass: both transitions, stale public/current artifacts, repeated runtime reads, changed profile, failed candidate retaining prior release and retry, shared refresh, approved and pending viewer access. Real Chromium browser flow passes directory → account → profile, disabled runtime delivery and flag presentation with JavaScript on/off, phone/desktop and 16 profile themes; no page overflow. Initial browser failures were incorrect test selectors (directory links to accounts first; flag title contains badges), corrected without product changes. Enabled artifacts remain covered by Stage 2 and transition tests. Diff/lint checks pass.
- Notes: Direct physical HTML requests bypass PHP, documented for old exported public trees. No dependency delta, no UI navigation additions. Original scope/estimate unchanged; labor unmeasured.

*— Codex · 2026-10-30 UTC*

## Stage 4 — Cheaper streamed commit indexing

**Original stage-work estimate: 0.20**
**Retrospective stage-work assessment: 0.18**

- Changes: CommitHistoryReader streams raw tree changes from a file-backed process spool, counts files without line diffs, and yields one metadata row at a time. Git must finish successfully before rows are inserted; failed/empty/missing history preserves the former empty result. Existing SQL and schema unchanged.
- Verification: Exact complete rows match frozen edge and 1001-commit outputs. Two new tests pass across rename settings true/false/copies and four merge settings, including mode-only, empty-file, binary, Unicode/tab/newline names, corrupt objects and absent/empty Git. Initial integrated ordinary measurement: 0.023859s, 2 MiB PHP peak (acceptance repeats remain Stage 7). Three timing checks pass; testProgressSeparatesParsingDerivationAndWritingWithBoundedCounts fails identically in untouched baseline and branch and is recorded as pre-existing, not a pass.
- Notes: Temporary spool prevents partial output on Git failure and retains heap bounds; no rename/merge-policy overrides. Dependency delta zero; original scope/estimate unchanged; labor unmeasured.

*— Codex · 2026-10-30 UTC*

## Stage 5 — Bounded manifest reuse in evidence refresh

**Original stage-work estimate: 0.23**
**Retrospective stage-work assessment: 0.22**

- Changes: Operation-local file-backed SQLite lookup streams batched Git manifests, retains current small commit group on disk, and fetches only source/signature/key paths into PHP. Shared commits survive adjacent 200-row batches. Transactions prevent partial manifests; failed batches retain per-commit fallback. Full commit-detail requests keep their existing path.
- Verification: All eight ActivityProjection tests pass. Frozen 10000-event/20000-path output is byte-identical; preliminary optimized run 0.579404s, 4 MiB PHP peak versus baseline 16 MiB. New tests pass exact 501-row evidence across three batches with one traced manifest process, small-commit batching, explicit/destructor cleanup and an injected partial-output/nonzero Git failure with no partial evidence or repeated per-event retry.
- Notes: No core read-model queries/schema changed; scratch is private and not exported. Temporary-store cache budget is 1 MiB; final matched acceptance remains Stage 7. Dependencies zero; original scope/estimate unchanged; labor unmeasured.

*— Codex · 2026-10-30 UTC*

## Stage 6 — History maintenance and failure parity

**Original stage-work estimate: 0.16**
**Retrospective stage-work assessment: 0.14**

- Changes: Added complete signed-event/manifest equivalence checks (record, signature and key), rename/new-operation isolation and injected scratch-write failure/rollback/cleanup coverage. Updated rebuild concept with operation lifecycle and failure behavior.
- Verification: Focused maintenance/reader/candidate suite 48/48 passes: full and incremental refresh, key arrival/removal, missing/broken history, bootstrap fallback, standard and Forte activity/RSS, current/blob/commit source routes, normal rebuild CLI, candidate promotion failure/rollback/retry, provenance batching, exact signed evidence and scratch cleanup. Existing schema recovery tests remain green.
- Notes: Core table definitions and queries are unchanged; only private scratch has a new schema, so deployed schema stays 39. No dependency delta, trust decision or export-format change. Original scope/estimate unchanged; labor unmeasured.

*— Codex · 2026-10-30 UTC*
