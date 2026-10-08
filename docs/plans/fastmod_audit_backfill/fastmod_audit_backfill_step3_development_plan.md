# Fastmod Audit and Backfill Step 3 Development Plan

## Stage 1
- Goal: Define one canonical historical-content classification used by audit and backfill.
- Dependencies: Approved Step 2.
- Expected changes: Add a read-only `FastmodHistoricalAuditService::audit(): FastmodAuditReport` and private-store queries for current-content/current-rubric outcomes.
- Verification approach: Fixture coverage classifies scored, excluded, pending, failed, changed, and never-scheduled posts without writes.
- Risks or open questions:
  - Explicit historical backfill must not alter ordinary new-content eligibility.
- Canonical components/API contracts touched: Read model, `SqliteFastScoreStore`, Fastmod content hash and rubric revision.

## Stage 2
- Goal: Produce a transparent configured-model cost estimate.
- Dependencies: Stage 1.
- Expected changes: Add `FastmodCostEstimator::estimate(FastmodAuditReport): FastmodCostEstimate`; derive observed usage from matching private LLM exchanges and report assumptions/fallbacks.
- Verification approach: Tests cover observed-usage, no-history fallback, and conservative range formatting.
- Risks or open questions:
  - Provider billing cannot be known exactly before a request; label cost bounds as estimates and reserve conservatively.
- Canonical components/API contracts touched: Private LLM-exchange store, configured Fastmod provider/model.

## Stage 3
- Goal: Expose the read-only historical audit to operators.
- Dependencies: Stages 1–2.
- Expected changes: Add `./v3 fast-score audit --include-existing`; print candidate states, selected model, estimate assumptions, and no-write confirmation.
- Verification approach: CLI test confirms no score/work/task/exchange mutation and stable report output.
- Risks or open questions:
  - Require `--include-existing` so a corpus-wide inspection is always explicit.
- Canonical components/API contracts touched: `scripts/fast_score.php`, `v3` usage, Fastmod operator reference.

## Stage 4
- Goal: Persist an explicit, bounded historical-backfill request before provider work begins.
- Dependencies: Stage 1 and approved audit semantics.
- Expected changes: Add private migration-backed backfill-batch metadata and work provenance; add `./v3 fast-score backfill --include-existing --max-posts=N --max-cost-usd=N --confirm`.
- Verification approach: Tests reject missing confirmation/bounds, persist only the authorized candidate snapshot, and preserve deduplication.
- Risks or open questions:
  - Batch metadata is new private state and needs one-year retention alignment.
- Canonical components/API contracts touched: `SqliteFastScoreStore`, private Fastmod database migrations, task queue enqueue API.

## Stage 5
- Goal: Process authorized backfill work safely through the existing worker.
- Dependencies: Stage 4.
- Expected changes: Extend worker reporting and batch accounting; reserve conservative estimated cost before claiming work, retain existing retry rules, and never create historical work from a normal sweep.
- Verification approach: Worker tests prove bounds, retry behavior, batch progress, and new-post isolation.
- Risks or open questions:
  - A single provider response can vary from the estimate; report actual observed usage and stop future claims once the reserved budget is consumed.
- Canonical components/API contracts touched: `FastScoreSweepService`, `fast_score_sweep`, private LLM-exchange usage records.

## Stage 6
- Goal: Document and verify the controlled operator workflow.
- Dependencies: Stages 3–5.
- Expected changes: Update Fastmod reference, CLI help, status output, prune behavior, and manual test guidance.
- Verification approach: Focused CLI/store/worker tests plus manual audit, bounded backfill, retry, and prune checks.
- Risks or open questions:
  - No public UI, public API, automatic historical sweep, or public backfill-progress surface is added.
- Canonical components/API contracts touched: `docs/reference/fast_post_scoring.md`, `docs/reference/v3_cli.md`, Fastmod status command.

## Next

If this plan is accepted, reply **Approved Step 3**. I will then create the
Step 4 feature branch and begin staged implementation.
