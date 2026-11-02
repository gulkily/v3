> **Feature plan:** [Step 1](./qdb_page_selection_step1_solution_assessment.md) · [Step 2](./qdb_page_selection_step2_feature_description.md) · [Step 3](./qdb_page_selection_step3_development_plan.md) · [Step 4](./qdb_page_selection_step4_implementation_summary.md)

Debug mode: enabled (project marker)
Effort study: railspike-effort / v3/qdb_page_selection; [P2 ledger](../effort_estimation_trial.md#cycle-v3qdb_page_selection)
Extended estimates: enabled

**Cycle estimate: 0.80**

## Original Query

Please write Step 1 for each.

## Understood Intent

Assess the numbered-page fix from the [capacity study](../application_capacity_study/capacity_report.txt), independently of rebuild, archive compatibility and host verification.

## Problem

Latest, Top and Search return 25 records yet use 72 MiB PHP at 10000 records because eligibility and paging follow whole-collection loading.

## Option A — Select the page before loading its bodies (Recommended)

**Estimate: 0.80**

Use the existing read model to select and count eligible results, then load the chosen page through shared readers; preserve Latest, Top, Leetness and Search behavior.

- Pros: Bounds ordinary page-body retention, including deep pages; benefits uncached and viewer-specific requests.
- Cons: Exact eligibility, ties, search matching and page clamping must remain equivalent; read-model contract changes carry schema/startup verification obligations.
- Driver/uncertainty: Query and shared-consumer parity, especially search and score ordering.

## Option B — Stream selection through the existing policies

**Estimate: 0.65**

Keep policy evaluation in application code but process candidates incrementally and retain only information needed to count and choose a page.

- Pros: Reuses current matching rules; can reduce body retention without changing stored data.
- Cons: Still scans the corpus; sorting and deep-page retention may prevent the intended bound.
- Driver/uncertainty: Bounded selection across every ordering, without changing results.

## Recommendation and boundary

Choose A as one reader-facing slice: existing URLs, complete 25-result pages, identical counts/order/body and viewer reactions, and correct first/later/empty/out-of-range recovery. Compare 1000/10000/40000 ordinary records against the proposed 32 MiB ceiling; assess maximum-valid records separately at 128 MiB. No content truncation, new cache system, navigation or copy redesign; random/welcome optimization is separate. Prepared models may supply the 40000 reader fixture with documented canonical/body provenance, but acceptance must use normal HTTP. Complete canonical rebuild capacity belongs to its separate cycle; neither result substitutes for the other.

Reuse: `src/ForumRewrite/Qdb/{QdbExperience,QdbBoardPolicy}.php`, `ReadModel/{ThreadRepository,TagPage}.php`, shared pagination and quote cards; `tests/QdbBoardPolicyTest.php`. Extend existing contracts rather than introduce parallel markup. Verify affected runtime/static/offline consumers, roles and rendered themes where touched.

Concepts touched: [collection selection](../../concepts/collection_selection.md) changed; [static publication](../../concepts/static_publication.md) used unchanged. Zero proposed dependencies; new read-model contracts must follow CONTRIBUTING.md.

## Study checkpoint and approval

2026-11-01 UTC; scope v1/A; baseline 75b5e30eb40b16f6297f69d83214b4e4926499b2; R1/P2 standing enrollment, all frozen hashes verified. First recorded intake after prior study/source exposure: 0.80; Step 1 handoff reaffirms 0.80. Codex single exposed rater; pristine pre-inspection intake unavailable, not reconstructed. Options estimate complete scoped cycles, not hours or additive forecasts. Separate follow-up to v3/application_capacity_study, not a duplicate study outcome. Plan only; no implementation, host execution, commits or accepted outcome. Ledger/debug updates stay uncommitted. Await Approved Step 1 for this cycle; future branch reuse is not assumed.

*— Codex · 2026-11-01 UTC*

## Step 1 approval

2026-11-02T01:21:17Z: User: “approved step 1 for qdb_page_selection”. Recommended Option A, scope v1/A, approved; proceed to Step 2 only. Earlier checkpoint retained as history.

*— Codex · 2026-11-02 UTC*
