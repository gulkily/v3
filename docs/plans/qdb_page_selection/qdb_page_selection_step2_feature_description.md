> **Feature plan:** [Step 1](./qdb_page_selection_step1_solution_assessment.md) · [Step 2](./qdb_page_selection_step2_feature_description.md) · [Step 3](./qdb_page_selection_step3_development_plan.md) · Step 4 (not started)

Debug mode: enabled (inherited)
Effort study: railspike-effort / v3/qdb_page_selection; [P2 ledger](../effort_estimation_trial.md#cycle-v3qdb_page_selection)
Extended estimates: enabled

**Cycle estimate: 0.80**

## Problem and user stories

QDB numbered views load the collection before selecting a page: Latest, Top and Search reached 72 MiB PHP at 10000 ordinary records for 25 results in the capacity study.

- As a reader, I want complete, correctly ordered pages and search results so that a growing collection remains readable.
- As an operator, I want bounded page memory and compatible startup recovery so that an upgrade preserves existing links and content.

## Core requirements

- Select/count eligible results before hydrating the selected page; retain at most 25 selected quote bodies, without truncating content or changing eligibility, pin priority, score ordering, tie breaks or search matching (including literal punctuation and case behavior).
- Preserve Latest, Top, Leetness and Search URLs/query aliases, counts, complete pages, empty results, page clamping, navigation and viewer reaction/caption behavior.
- Preserve current trust, visibility and retraction semantics and first-paint behavior; reuse existing controls and cards without new UI copy or navigation.
- Verify prior-schema startup recovery, ordinary rebuild and normal HTTP reads under CONTRIBUTING's read-model compatibility contract; preserve affected shared consumers.

## Delivery scope and shared component inventory

Work type: Application change; this handoff authorizes requirements review only. Extend `src/ForumRewrite/Http/BoardPageController.php`, `Qdb/{QdbExperience,QdbBoardPolicy}.php` and `ReadModel/ThreadRepository.php` (last two prefixes relative to `src/ForumRewrite/`); reuse `ReadModel/TagPage.php` as the bounded-selection precedent. Reuse `templates/pages/{board,board_list,qdb_search}.php`, `templates/partials/{quote_card,qdb_quote_actions,board_pagination_nav,qdb_footer}.php` and the [pagination contract](../../reference/pagination.md). No new presentation component is needed. Shared board/Forte, RSS/API, numeric quote, static publication and offline consumers keep their contracts and receive affected-consumer checks.

Exclude random/welcome optimization, rebuild-capacity repair, archive changes, new caches/dependencies and host execution. [Collection selection](../../concepts/collection_selection.md) needs its QDB status/evidence updated upon delivery; [static publication](../../concepts/static_publication.md) is reused unchanged. No new concept entry proposed.

## Risks and early validation

- Selection drift could hide or reorder quotes. Before Step 3 is finalized, inventory eligibility, pins, ties and literal search behavior against existing policy/tests; require parity fixtures before replacing selection.
- Search/scoring may still scan the corpus. Before Step 3 is finalized, distinguish scanned rows from retained bodies and define representative fixtures; no latency or scan-count guarantee follows from a memory bound.
- Upgrade or large-fixture failures could mask reader results. Before Step 3 is finalized, map schema/startup obligations and fixture provenance; separate reader acceptance from full canonical rebuild capacity and stop for scope review if parity requires broader changes.

## User flow, completion and success criteria

1. Open Latest, Top or Leetness, or submit Search through existing HTTP entry points.
2. Follow numbered links, including deep/final pages; see the same ordered IDs, bodies, totals and reaction controls as before.
3. An empty search remains usable; an out-of-range page clamps as today. After an upgrade, normal startup recovers the prior read model; an operator can use the existing rebuild/retry path and verify the same URL.

Release requires parity and peak PHP memory at or below the proposed 32 MiB ceiling for ordinary 1000/10000/40000-record HTTP fixtures, including deep pages and searches; assess maximum-valid records separately at 128 MiB without a 32 MiB promise. Prepared 40000-record models require canonical/body provenance; they do not prove full rebuild capacity. Verify affected shared consumers and rendered phone/desktop themes and roles, including empty/recovery states. Record environment, failures and limitations; local evidence does not establish PHP 8.3/FastCGI host recovery, which remains a separate cycle.

## Study checkpoint and approval

2026-11-02T01:21:17Z: User approved Step 1 for this cycle, selecting recommended A; scope v1/A unchanged. Step 2 projected total 0.80 reaffirmed; baseline 75b5e30eb40b16f6297f69d83214b4e4926499b2, R1/P2 and all three verified frozen hashes retained. Codex single exposed rater; prior source/capacity exposure and missing pristine intake preserved. Whole-cycle estimate, not hours or additive stage work. No implementation, measurements or accepted outcome; all records uncommitted. Await Approved Step 2; other three cycles remain at Step 1.

*— Codex · 2026-11-02 UTC*

## Step 2 approval

2026-11-02T02:20:28Z: User: “approved step 2”, referring to this cycle’s preceding handoff. Requirements approved unchanged; proceed to Step 3 only.

*— Codex · 2026-11-02 UTC*
