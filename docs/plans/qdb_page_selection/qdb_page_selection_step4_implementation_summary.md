> **Feature plan:** [Step 1](./qdb_page_selection_step1_solution_assessment.md) · [Step 2](./qdb_page_selection_step2_feature_description.md) · [Step 3](./qdb_page_selection_step3_development_plan.md) · [Step 4](./qdb_page_selection_step4_implementation_summary.md)

Debug mode: enabled (inherited)
Effort study: railspike-effort / v3/qdb_page_selection; scope v1/A; R1/P2
Extended estimates: enabled

**Cycle estimate: 0.80**

## Implementation entry — 2026-11-02 UTC

User approved Step 3. Planning commit: 69661da0; branch: feature/qdb-page-selection. Step 4 total 0.80 reaffirmed before implementation. Original baseline 75b5e30eb40b16f6297f69d83214b4e4926499b2; prior estimates/exposure preserved, Codex single exposed rater. No measured labor or maintainer acceptance claimed.

Approved stages: S1 Latest/upgrade 0.30; S2 Top/Leetness 0.15; S3 Search 0.20; S4 HTTP capacity 0.20; S5 shared verification/closeout 0.15. These are independent stage-work estimates, not additive. Original scopes/drivers are in Step 3.

Reuse decisions: Extend BoardPageController and QdbExperience; reuse QdbBoardPolicy, QdbQuoteNumbers, ThreadRepository::byId and ThreadRowSupport. New QdbPage handles numbered pages because TagPage's tag/cursor contract differs. Existing quote cards, search, reaction and pagination rendering remain canonical. No dependencies or markup changes planned. Success includes normal HTTP, schema startup, rebuild/retry and shared consumers; host deployment remains out of scope.

*— Codex · 2026-11-02 UTC*

## Stage 1 — Latest and upgrade recovery

**Original estimate: 0.30**

**Retrospective stage work: 0.30**

- Changes: Added snapshot-consistent QdbPage selection with exact existing PHP eligibility/tag decoding; Latest hydrates at most 25 roots and reuses existing pagination/reactions/cards. Schema 41 and reference document the changed query contract. No stored columns changed.
- Verification: `php tests/run.php QdbPageTest QdbBoardPolicyTest QdbExperienceRoutingTest`: 10/10 passed. Differential mixed legacy fixtures cover ordering, clamping, empty pages and body-load counts; caller-owned transactions stay open. Prior-version startup rebuild restores canonical body bytes before Latest. Ordinary `./v3 rebuild` on a disposable copied fixture passed (3 posts, 2 threads); existing offline snapshot was reported already available. Actual HTTP capacity/rendered checks remain S4/S5.
- Notes: Initial startup assertion mistakenly expected the subject in the body; corrected to compare original canonical body bytes, then passed. No runtime failure hidden. Original scope/estimate unchanged; no measured labor. Dependency delta zero.

*— Codex · 2026-11-02 UTC*

## Stage 2 — Top and Leetness

**Original estimate: 0.15**

**Retrospective stage work: 0.15**

- Changes: Extended the same selector to Top/Leetness and wired existing normal routes. Pin priority precedes score/distance ordering; creation and binary ID comparison break ties. Shared non-QDB and nonmatching board contexts keep their existing readers.
- Verification: `php tests/run.php QdbPageTest QdbBoardPolicyTest QdbExperienceRoutingTest`: 11/11 passed, including pins, negative/equal scores, equidistant values and clamped last pages compared with legacy policy. `git diff --check` passed.
- Notes: S1 commit 56587695. Schema 41 covers this cycle's query contract; no further stored-schema change. Scope/estimate unchanged; HTTP capacity remains S4.

*— Codex · 2026-11-02 UTC*

## Stage 3 — Search

**Original estimate: 0.20**

**Retrospective stage work: 0.20**

- Changes: Search now counts/selects before hydration, retaining trimmed PHP stripos semantics and activity/ID order. Empty searches remain empty. Existing template, encoding and viewer-reaction wiring are reused.
- Verification: `php -d memory_limit=128M tests/run.php QdbPageTest QdbBoardPolicyTest QdbExperienceRoutingTest`: 13/13 passed. Literal percent/underscore, ASCII/non-ASCII case, whitespace, no-match, injection-like text and deep/clamped pages match legacy selection. Nearly 16 MiB body matching succeeds; a missing-table error rolls back the owned transaction and retry succeeds after restoration. HTTP replay/encoding remains S4.
- Notes: S2 commit fe661c5d. Callbacks inspect transient bodies during scans; only selected bodies are retained. No promise of bounded scan work. Scope/estimate unchanged, dependency delta zero.

*— Codex · 2026-11-02 UTC*

## Stage 4 — Normal HTTP capacity

**Original estimate: 0.20**

**Retrospective stage work: 0.20**

- Changes: Archived isolated setup/replay and plain-text results in [capacity report](capacity_report.txt). S3 implementation commit: 486de3d5.
- Verification: 72 ordinary HTTP requests across 1000/10000/40000 records passed exact ID/body/count and 32 MiB checks (2–4 MiB observed). Six maximum-valid requests passed under 128 MiB; numbered views/search peaked about 82.1 MiB, numeric quote 100 MiB. Normal rebuilds passed at 1000, 10000 and the maximum-record fixture.
- Notes: Initial checker failure and corrected presentation normalization retained in capacity_initial_failures.txt. Prepared 40000 provenance is explicit; full rebuild capacity and PHP 8.3/FastCGI remain unverified here. No application changes or new dependencies in S4; original scope/estimate unchanged.

*— Codex · 2026-11-02 UTC*
