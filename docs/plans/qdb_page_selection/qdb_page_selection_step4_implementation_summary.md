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

## Stage 5 — Shared consumers and closeout

**Original estimate: 0.15**

**Retrospective stage work: 0.20**

- Changes: Added selected-page reaction checks for anonymous/unapproved/approved viewers; the existing policy now owns the single page-size constant used by QdbPage. Updated schema/concept evidence, plan navigation and this cycle's ledger. Removed only a trailing blank line from the S4 command archive (original retained in 6eb5456a); measurements unchanged.
- Verification: Broad shared run: 216 tests, 212 passed, four failures reproduced individually on baseline 75b5e30. The pre-existing failures concern rebuild-diagnosis text, private-lobby text, expired-session approval text and a removed archive filename helper; retained in shared_test_results.txt and baseline_failure_results.txt. Targeted QdbPage/QuoteCardDisplayNumber/ReadModelViewerTagsRebuild: 30/30 passed, covering static listings/numeric aliases and viewer projection. Final page-size-alias/policy run: 11/11 passed under 128 MiB. No full-suite pass claimed.
- Verification: [Browser results](browser_results.txt): 150 rendered cases across all 15 themes at phone/desktop widths, correct assets, no overflow and expected cards; no-JavaScript paging and three initial/loaded presentation states passed. Initial asset-misconfigured run was excluded and corrected before acceptance. Simulated presentation states are not live authenticated signing sessions; actual viewer-tag selection is covered by PHP tests. No markup or JS changed.
- Verification: [HTTP upgrade](http_upgrade_results.txt): schema-40 stale-body fixture rebuilt through ordinary /latest to schema 41 with exact ordered IDs and canonical bodies; ordinary CLI rebuild/retry and Search page 2 then passed. PHP lint, plan links and whitespace checks passed. Runtime evidence is local PHP 8.1.2; PHP 8.3/FastCGI remains separate.
- Notes: S4 commit 6eb5456a. S5 scope unchanged; retrospective work reflects shared/browser/recovery evidence and fixture corrections, not measured time. Dependency delta zero. No merge, push or deployment; sibling cycles untouched. Runtime templates, full-list consumers and static-publication semantics preserved.

## Study closeout

2026-11-02 UTC; scope v1/A, original baseline/R1/P2 and single exposed rater retained. Step 4 projection remained 0.80; no new independent estimate or measured labor claimed.

**Retrospective cycle scope: 0.80**

Locally complete with the documented pre-existing test failures and host boundary. Maintainer fit/acceptance pending; accepted-outcome count unchanged. Original S1–S5 scopes and estimates remain in Step 3; separate retrospective stage values above do not replace or sum into the cycle score.

*— Codex · 2026-11-02 UTC*

## Merge verification — 2026-11-02 UTC

User authorized merge and push. Integrated origin/main at 99522a99 with feature/qdb-page-selection at 752a86ad in an isolated worktree; original checkout's unrelated ledger edits and three Step 1 draft cycles were excluded. The merge includes the already committed predecessor capacity-study documentation.

Preserved both sides of additive concept/ledger conflicts. Main already uses schema 41 for message recovery and 42 for Recent; integrated identity is 43, retaining all indexes and QDB query behavior. Updated schema reference and startup regression to exercise versions 40 and 42. Earlier branch-local schema-41 evidence remains historical.

Verification: QdbPage, QdbBoardPolicy, QdbExperienceRouting, QuoteCardDisplayNumber, QdbQuoteNumbers, QdbWelcomeSelection, RecentPost, ReadModelViewerTagsRebuild, DeploymentSettings, HighTraffic and HighTrafficPublication: 70/70 passed on the merged tree. Normal isolated CLI rebuild retained profiles_sync_keys/posts_recent_idx; normal HTTP upgraded a schema-42 model to 43 and served canonical bodies on Latest/Top/Leetness/Search. Whitespace and conflict-marker checks passed. No full-suite rerun, live deployment or PHP 8.3/FastCGI acceptance claimed; earlier baseline failure evidence remains preserved.

*— Codex · 2026-11-02 UTC*
