> **Feature plan:** [Step 1](./viewer_tag_lookup_memoization_step1_solution_assessment.md) · [Step 2](./viewer_tag_lookup_memoization_step2_feature_description.md) · [Step 3](./viewer_tag_lookup_memoization_step3_development_plan.md) · [Step 4](./viewer_tag_lookup_memoization_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/viewer_tag_lookup_memoization`; [ledger](../effort_estimation_trial.md), standing enrollment P2.

Extended estimates: enabled (late activation, requested 2026-10-24, after Step 1/2 were originally drafted). Per-option estimates below are current-dated retrospective additions, not reconstructed original Step 1 judgments; earlier checkpoints had no per-option estimates and none are invented.

## Original Query

please look for the lowest hanging fruit for improving performance and build a document with a list of them. [...] please write three Step 1 documents.

## Understood Intent

Deliver the second of three Railspike cycles from the performance punch-list in [`performance_low_hanging_fruit_v1.md`](../performance_low_hanging_fruit_v1.md), item #1: stop `ViewerTagLookup` from globbing and parsing the entire `records/post-reactions/` and `records/thread-labels/` directories multiple times per request for a logged-in viewer. This is the highest-impact localized win and has its own correctness surface (per-request memoization keyed correctly, the N+1-over-tags on qdb, and consistent results across the board/thread/Forte call sites), so it is isolated from the other two performance cycles.

## Problem

`ViewerTagLookup::postTags()`/`threadTags()` scan and parse every reaction/label record just to find one viewer's records, and are invoked 5+ times per thread render (including once per tag on qdb), so request cost scales with the whole site's reaction corpus rather than the posts on the page.

## Option A — Per-request memoization inside ViewerTagLookup

Parse each records directory at most once per request, index the records by `authorIdentityId` in an in-memory map keyed by repository root + record family, and filter that map per tag. No schema, no stored state.

- Pros: smallest change that removes the repeated full scans; collapses the 5+ scans into one; pure read path with unchanged results; mirrors the already-cached activity-manifest pattern.
- Cons: still one directory scan per request; memory holds the viewer-relevant slice for the request; cache lifetime/invalidation must be request-scoped to avoid staleness.
- Estimate (retrospective, 2026-10-24): **≈0.45**; driver: correctness across multiple call sites + qdb per-tag path + folding in the bypassing scan; uncertainty: medium (identity-keyed memo must match current filter logic exactly).

## Option B — Push reactions/labels into the SQLite read model

Add reaction/label rows to the read model and answer viewer-tag questions with an indexed `WHERE author_identity_id = ?` query instead of filesystem scans.

- Pros: eliminates filesystem scanning entirely; scales to large corpora; reusable by other features.
- Cons: read-model schema change (version bump, spec, rebuild test, `./v3 rebuild`); larger surface and rebuild cost; more than "lowest-hanging-fruit" for this cycle.
- Estimate (retrospective, 2026-10-24): **≈0.9**; driver: read-model schema change + builder work to project reactions/labels + the full schema-change checklist, on top of rewiring the call sites; uncertainty: high (new derived rows, rebuild cost, migration surface).

## Option C — Narrow the qdb N+1 only

Keep per-call scanning but batch the per-tag `threadTags` loop into a single scan that checks all tags at once.

- Pros: targets the worst offender (qdb per-tag loop) with minimal change.
- Cons: leaves the repeated full-directory scans on non-qdb and board paths; partial fix that still re-scans across the multiple call sites in one render.
- Estimate (retrospective, 2026-10-24): **≈0.25**; driver: single-site batch of the per-tag loop; uncertainty: low — but it only partly solves the problem, so its lower cost does not make it preferable.

## Recommendation

**Option A.** It is a releasable vertical slice: a logged-in viewer renders a thread/board and sees identical reaction/label state, produced by a single directory parse per request instead of 5+. It inherently subsumes Option C's qdb fix (one memoized map answers every tag). Keep Option B as a documented follow-up if profiling on large instances shows the single remaining scan is still too costly. Scope stays within one day and no schema change.

Verify: logged-in viewer flag/like state on a thread and on the board is unchanged; qdb vote/label state unchanged; anonymous and no-reaction paths unchanged; the Forte board call site unchanged; a counter/trace confirms one parse per family per request. Check phone/desktop and affected themes for the reaction controls.

## Follow ups and scope

Only request-local memoization of the existing lookups is in scope; results and record formats are unchanged. Moving reactions/labels into the read model (Option B) is a separate, larger cycle requiring schema approval. The read-model index cycle (item #2) and the cheap request-path wins (items #4–#6) are separate cycles.

## Effort snapshots and approval

- Cycle `v3/viewer_tag_lookup_memoization`, scope v1; starting revision `8d51a177` (current `main` after the punch-list commit); reference R1 / protocol P2; single exposed rater; UTC 2026-10-24.
- Intake: recorded after reading `ViewerTagLookup.php` and tracing the thread/board/qdb call sites; prior exposure to the punch-list disclosed. No pristine pre-inspection estimate claimed.
- Step 1 handoff projected total (Option A, against recovered R1): **≈0.45** — localized memoization with a non-trivial correctness surface across multiple call sites and qdb's per-tag path, plus read-path verification; between the "toolbar identity" (0.35) and "composer decluttering" (0.60) R1 anchors. Single exposed, non-blind rater; not measured effort, an approval, or a completed outcome.
- Extended-estimate note (late activation, 2026-10-24): extended estimates were enabled after Step 1/2 were drafted. The per-option estimates added above (A ≈0.45, B ≈0.9, C ≈0.25) are current-dated retrospective judgments, not reconstructed original Step 1 scores; the recommended-cycle total ≈0.45 is preserved separately and is not the sum of options. Alternatives are not summed or normalized into fractions.
- Documentation boundary: this cycle's plans/debug log, later index organization, and ledger bookkeeping. Planning writes remain uncommitted.
- Await `Approved Step 1` before Step 2; recommendation may be revised without advancing.

## Revision — B-then-A sequencing (scope v2, 2026-10-24)

The user chose Path 1 (two cycles) and then reordered it to **B then A**. This cycle is now the **second** of the two: the read-model migration ships first in [`v3/viewer_tag_read_model`](../viewer_tag_read_model/viewer_tag_read_model_step1_solution_assessment.md), and this cycle wraps the resulting indexed SQL path in a request-scoped memo.

- **Scope v2 supersedes v1** above. The original v1 recommendation (memoize the per-request *file scans*) is retained for history but no longer applies: with B landed, there are no file scans to memoize — this cycle memoizes the per-request *SQL lookups* instead, collapsing the multiple per-family queries per page to one. v1 checkpoints remain historical and are not erased.
- **Dependency:** this cycle now depends on `viewer_tag_read_model` having landed. It wraps that cycle's `ViewerTagLookup` SQL seam; it does not reintroduce file scanning.
- **Step 1 approval status:** the earlier `Approved Step 1` was for v1's file-scan memo. Because scope changed, that approval no longer carries; this revised v1 direction requires a **fresh `Approved Step 1`**, and the drafted Step 2 (written around the file-scan framing) will be rewritten before any Step 2 approval.
- **Revised projected total (scope v2):** **≈0.35** — slightly below v1's ≈0.45. Memoizing an already-indexed SQL path is simpler than memoizing glob+parse logic, and folding in `viewerHasThreadTag` is handled by B's rewiring. The correctness surface (per-identity keying across call sites) remains the main driver. Single exposed, non-blind rater; same cycle/baseline/R1/P2; prior v1 snapshots retained, not overwritten. Not measured effort, an approval, or a completed outcome.
- Extended estimates remain enabled; per-stage stage-work estimates begin at this cycle's Step 3.
