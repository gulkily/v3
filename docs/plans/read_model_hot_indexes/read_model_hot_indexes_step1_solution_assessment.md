> **Feature plan:** [Step 1](./read_model_hot_indexes_step1_solution_assessment.md) · [Step 2](./read_model_hot_indexes_step2_feature_description.md) · [Step 3](./read_model_hot_indexes_step3_development_plan.md) · [Step 4](./read_model_hot_indexes_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/read_model_hot_indexes`; [ledger](../effort_estimation_trial.md), standing enrollment P2.

## Original Query

please look for the lowest hanging fruit for improving performance and build a document with a list of them. [...] please write three Step 1 documents.

## Understood Intent

Deliver the first of three Railspike cycles drawn from the performance punch-list in [`performance_low_hanging_fruit_v1.md`](../performance_low_hanging_fruit_v1.md), item #2: add the missing SQLite indexes on the read model so hot board and single-thread queries stop doing full scans + filesorts. This cycle is a read-model schema change and therefore carries the `CONTRIBUTING.md` schema-change obligations (version bump, spec update, rebuild regression test, `./v3 rebuild` verification); it is intentionally isolated from the other two performance cycles because of that distinct migration and verification surface.

## Problem

`posts`, `threads`, and `profiles` carry only their primary keys, so the board listing sort (`threads.last_activity_at`) and the per-thread reply query (`posts.thread_id` + `sequence_number`) force full table scans and in-memory sorts on nearly every dynamic content page.

## Option A — Add the three targeted indexes to the live read model

Add `posts(thread_id, sequence_number)`, `posts(author_identity_id)`, and `threads(last_activity_at DESC, root_post_id DESC)` to `ReadModelSchema::statements()`, bump `ReadModelMetadata::SCHEMA_VERSION`, update the schema spec, and add a prior-version rebuild regression test.

- Pros: directly removes the scans/filesorts on the hottest queries; mirrors the index the offline snapshot builder already creates; mechanical, low behavioral risk.
- Cons: triggers a one-time read-model rebuild on deploy; slightly larger read-model file and marginally slower writes during rebuild.

## Option B — Only the single highest-value index

Add just `posts(thread_id, sequence_number)` (the thread-permalink scan), deferring the board-sort and author-join indexes.

- Pros: smallest change; still fixes the most common per-request scan.
- Cons: leaves the board/RSS/tags sort unindexed; a second schema change (and second rebuild) later to finish the job — more total churn than doing the set once.

## Option C — Composite/covering index tuning

Design wider covering indexes tuned to each query's exact column list to avoid row lookups entirely.

- Pros: potentially the fastest reads.
- Cons: speculative without profiling; larger index footprint; higher maintenance and review cost; over-engineered for a "lowest-hanging-fruit" cycle.

## Recommendation

**Option A.** It is a releasable vertical slice: the indexes land, the schema version/fingerprint change drives an automatic rebuild, and the affected board, tags, RSS, and single-thread flows are verified to still render identical results, faster. Scope stays within the mandatory schema-change checklist and one day of work. Decline Option C's speculative tuning unless later profiling justifies it; prefer A over B to avoid a second rebuild.

Verify: `./v3 rebuild` on a current read model; a regression test that starts from the prior schema version and confirms normal startup rebuilds before the changed query runs; `EXPLAIN QUERY PLAN` shows the new indexes used for the board sort and reply query; board, `/tags`, RSS, and a thread permalink render unchanged content.

## Follow ups and scope

Only index additions and the required schema-change bookkeeping are in scope. Query rewrites (SQL-level LIMIT/sort/search, punch-list item #3), viewer-tag memoization (item #1), and the cheap request-path wins (items #4–#6) are separate cycles. No covering-index tuning without profiling evidence.

## Effort snapshots and approval

- Cycle `v3/read_model_hot_indexes`, scope v1; starting revision `8d51a177` (current `main` after the punch-list commit); reference R1 / protocol P2; single exposed rater; UTC 2026-10-24.
- Intake: recorded after reading `ReadModelSchema.php`, `ReadModelMetadata.php`, `ThreadRepository.php`, and the offline snapshot builder; prior exposure to the punch-list disclosed. No pristine pre-inspection estimate claimed.
- Step 1 handoff projected total (Option A, against recovered R1): **≈0.30** — mechanical index additions plus the standard schema-change checklist (version bump, spec update, rebuild regression test, `./v3 rebuild` verification), comparable to the "score emphasis" R1 anchor. Single exposed, non-blind rater; not measured effort, an approval, or a completed outcome.
- Documentation boundary: this cycle's plans/debug log, later index organization, and ledger bookkeeping. Planning writes remain uncommitted.
- Await `Approved Step 1` before Step 2; recommendation may be revised without advancing.
