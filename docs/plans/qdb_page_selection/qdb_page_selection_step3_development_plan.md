> **Feature plan:** [Step 1](./qdb_page_selection_step1_solution_assessment.md) · [Step 2](./qdb_page_selection_step2_feature_description.md) · [Step 3](./qdb_page_selection_step3_development_plan.md) · Step 4 (not started)

Debug mode: enabled (inherited)
Effort study: railspike-effort / v3/qdb_page_selection; [P2 ledger](../effort_estimation_trial.md#cycle-v3qdb_page_selection)
Extended estimates: enabled

**Cycle estimate: 0.80**

## Completion Contract

Existing Latest/Top/Leetness/Search HTTP routes and aliases return identical ordered IDs, full bodies, totals, clamping and reactions while hydrating at most 25 selected roots. Ordinary 1000/10000/40000-record requests must stay within 32 MiB peak PHP; maximum-valid records are assessed separately at 128 MiB. Prior-schema startup and `./v3 rebuild` recover normal reads; retry uses existing recovery. Prepared 40000-record fixtures need canonical/body provenance, not a successful full rebuild. Actual PHP 8.3/FastCGI acceptance, deployment and rebuild-capacity repair remain separate. No dependencies, new UI or caches.

## Key Risks

- **High risk:** Changed eligibility/order could omit content. Inspection confirms suffix-based quote eligibility and shared hidden/retracted filtering; Latest uses pins/creation/ID, Top pins/score/creation/ID, Leetness pins/distance-from-1337/creation/ID, Search activity/ID and trimmed literal `stripos`. Lock these into parity fixtures before replacement; do not substitute SQL wildcard matching.
- **High risk:** Upgrade or concurrent writes could invalidate count/page agreement. Use one consistent read snapshot, preserve shared hydration, and test prior-version startup before routing traffic. Rollback restores application revision and rebuilds disposable state; canonical content stays untouched.
- Memory is not scan cost. Count/select may traverse all candidates; retain only selected IDs and transient match data. Measure request peaks independently of fixture construction; stop for Step 2 review if the approved bound requires broader changes.

## Stage 1

**Estimate: 0.30**

- Goal: Bound Latest through its normal route, including upgrade recovery.
- Dependencies: Approved Step 3, feature branch and planning commit.
- Expected changes: Add `QdbPage::page(PDO, string section, int page, string term = ''): array` returning threads/count/page; extend policy pagination from totals. Bump schema version and spec for the changed query contract; no new stored columns planned.
- Verification approach: Latest first/deep/clamped/empty pages; eligible/hidden/retracted/pinned fixtures, prior-schema normal startup and rebuild/retry.
- Risks or open questions: Parity precedes routing; snapshot counts and hydrate selected IDs only.
- Canonical components/API contracts touched: New `ReadModel/QdbPage.php` is needed because TagPage has tag/cursor semantics; reuse `ThreadRepository::byId`, `ThreadRowSupport`, QDB policy and BoardPageController, unchanged cards/navigation.
- Estimate details: Driver: selection/upgrade wiring; uncertainty: malformed legacy metadata. Owns shared selector tests and schema documentation.

## Stage 2

**Estimate: 0.15**

- Goal: Bound Top and Leetness end to end.
- Dependencies: S1 parity and recovery pass.
- Expected changes: Extend selector ordering and wire both existing routes/aliases.
- Verification approach: Equal/negative scores, pins, equidistant Leetness, creation/ID ties and final pages against current policy.
- Risks or open questions: Incorrect ties move quotes between pages; compare complete ordered fixtures before switching.
- Canonical components/API contracts touched: QdbPage, BoardPageController, QdbBoardPolicy.
- Estimate details: Driver: ordering parity; uncertainty: numeric edge cases.

## Stage 3

**Estimate: 0.20**

- Goal: Bound Search with exact matching and activity order.
- Dependencies: S1 selector contract; S2 regression pass.
- Expected changes: Extend selection and wire QdbExperience search; use a SQLite scalar callback to existing PHP matching/eligibility where SQL would change semantics, without retaining scanned bodies.
- Verification approach: Literal percent/underscore, case/non-ASCII, whitespace, empty/no-match, deep/clamped pages and query encoding through HTTP.
- Risks or open questions: Callback memory/platform support; local in-memory PDO callback/literal-percent probe passed; verify maximum-valid allocation before integration and rescope if unsupported on the target.
- Canonical components/API contracts touched: QdbExperience, QdbQuoteNumbers, QdbPage and existing search template.
- Estimate details: Driver: exact search parity; uncertainty: transient large-body allocation.

## Stage 4

**Estimate: 0.20**

- Goal: Replay reported memory growth through ordinary HTTP.
- Dependencies: S1–S3 pass.
- Expected changes: Adapt archived capacity-study collectors in an isolated workspace; retain reproducible Key: value evidence and fixture provenance.
- Verification approach: All four routes at 1000/10000/40000, first/deep/final pages, matching/nonmatching search, exact bodies/IDs/totals and peaks; separate maximum-valid replay under 128 MiB.
- Risks or open questions: Fixture/rebuild failure can contaminate results; isolate construction and request measurements, preserve failures and retry fresh fixtures.
- Canonical components/API contracts touched: Existing capacity reproduction/collector contract and normal HTTP front controller.
- Estimate details: Driver: reproducible comparisons; uncertainty: fixture setup. Owns capacity evidence.

## Stage 5

**Estimate: 0.15**

- Goal: Verify shared consumers and close out release evidence.
- Dependencies: S4 acceptance passes.
- Expected changes: Update collection-selection concept, implementation summary and study ledger; static-publication concept remains unchanged.
- Verification approach: Existing QdbBoardPolicy/ExperienceRouting/QuoteNumbers, LocalAppSmoke, BoardList/ForteBoardReader, HighTraffic/publication and offline suites; affected RSS/API/numeric routes; rendered phone/desktop themes and roles, reactions, empty/recovery states and first paint.
- Risks or open questions: Shared-reader regressions; retain unrelated full-list contracts and record every unverified environment.
- Canonical components/API contracts touched: Step 2 component inventory, shared renderers and concepts; no new markup.
- Estimate details: Driver: cross-consumer acceptance; uncertainty: browser coverage. Owns final documentation and verification summary.

## Execution and study checkpoint

2026-11-02T02:20:28Z: Step 2 approved unchanged; scope v1/A. Plan stages S1–S5 are sequential, bounded sessions (about one hour each); split before implementation if inspection invalidates that size. Each stage commits with its summary before the next. After Step 3 approval, create a feature branch, organize only this cycle and make its planning commit; no branch reuse assumed. All records remain uncommitted now.

Step 3 total 0.80 reaffirmed independently of stage estimates (non-additive, not hours). Baseline 75b5e30eb40b16f6297f69d83214b4e4926499b2, R1/P2 and frozen hashes verified; Codex single exposed rater, earlier exposure/missing intake retained. No capacity measurements or accepted outcome. Planning links/whitespace passed; runtime suites remain pending. No pending scope additions; siblings remain Step 1. Await Approved Step 3.

*— Codex · 2026-11-02 UTC*
