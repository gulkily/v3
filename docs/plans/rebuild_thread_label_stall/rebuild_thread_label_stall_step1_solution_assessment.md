> **Feature plan:** [Step 1](./rebuild_thread_label_stall_step1_solution_assessment.md) · [Step 2](./rebuild_thread_label_stall_step2_feature_description.md) · [Step 3](./rebuild_thread_label_stall_step3_development_plan.md) · [Step 4](./rebuild_thread_label_stall_step4_implementation_summary.md)

Debug mode: enabled

## Original Query
On production, `./v3 rebuild` appears to stall after this step:

`[2/3] Read model: parsing thread-label records (210/210).`

The next line to print out is:

`[2/3] Read model: index thread labels complete (31259.2 ms).`

Please articulate what the issue is and write Step 1 of @railspike. Railspike debug on.

## Revised scope
The user also requested a check for similar issues, citing post-reaction parsing at `135/135` followed by phase completion at `14901.3 ms`. Assess repeated history lookups and silent work across the rebuild; preserve the original query above.

## Problem statement
Rebuild progress can reach a parsing total while substantial unreported indexing remains, including repeated Git history lookups and database work.

## Evidence and uncertainty
Static review of [ReadModelBuilder](../../../src/ForumRewrite/ReadModel/ReadModelBuilder.php) and its CLI/candidate path found:
- **Same repeated-Git pattern:** thread labels, post reactions, and signed feature-flag changes resolve source commits individually; only posts use the existing bulk source-commit resolver. Reactions then update every post; labels update every root thread, regardless of the displayed record count.
- **Other silent work:** post sorting/thread-summary writes and thread-subject derivation/writes follow parsing or writing totals. Approval derivation repeatedly scans posts and sorts/replays invitation candidates; profile counting, author linking, and activity insertion have only phase-level reporting. Activity also performs per-event author queries. These are scaling candidates, not measured bottlenecks.
- **Long operations without intermediate progress:** commit indexing already uses one bulk Git command, followed by row inserts; post-history preloads also block until Git returns. Transaction commit and initial candidate integrity validation lack their own progress boundaries. Promotion reports its lock wait and validation; first-time offline snapshot creation reports only its result.
- **Conditional fallback:** [legacy timestamp resolution](../../../src/ForumRewrite/Canonical/CanonicalRecordRepository.php) can still run per-post Git history queries when a missing timestamp cannot be resolved from metadata or the preload, including renamed paths. Preserve rename/fallback semantics before considering changes.
- Both reported times (31.26 s labels; 14.90 s reactions) measure entire phases, not just silent intervals. No production profiling was performed; relative Git/SQL/CPU costs remain unknown.

## Option A — Expose and measure remaining work
Add progress boundaries and phase timings across the identified gaps to distinguish parsing, history resolution, derivation, writing, and validation.

- Pros: Makes long operations understandable and supplies evidence for optimization.
- Cons: Leaves the confirmed repeated-query patterns intact; boundaries alone do not provide a heartbeat during blocking calls.

## Option B — Batch source history and improve rebuild visibility
Extend the existing bulk source-commit approach to labels, reactions, and feature-flag changes, with progress/timing for the identified rebuild gaps and bounded updates during long loops.

- Pros: Addresses the shared likely bottleneck and misleading progress together; reuses existing behavior without schema changes.
- Cons: Requires provenance/output equivalence and missing-history/Git-failure checks; bulk Git calls can still be slow. Preserve legacy timestamp fallbacks rather than treating them as equivalent provenance lookups.

## Option C — Optimize all scaling candidates now
Also redesign approval replay, activity author resolution, database writes, and legacy timestamp fallback handling.

- Pros: Could remove additional repeated work.
- Cons: Broadens correctness risks before production timings identify which changes matter.

## Recommendation
Choose **Option B**: one bounded slice through normal `./v3 rebuild`, covering all three unbatched source-commit families and visibility through candidate validation/promotion and snapshot bootstrap. Preserve labels, scores, votes, moderation, activity provenance, and failure recovery. Measure representative histories and compare derived output before claiming speedups. Defer algorithmic changes to approval replay, author lookup, row-write reduction, and legacy timestamp semantics until evidence justifies them. This remains Step 1; no implementation or production execution.
