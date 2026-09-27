# Fastmod Historical Audit and Backfill

Use this runbook only as a deployment operator. Historical scoring is opt-in;
normal Fastmod publication behavior is unchanged.

1. Run `./v3 fast-score audit --include-existing`. Confirm it identifies the
   expected read-model path, selected model, candidate states, estimate, and
   read-only confirmation.
2. For a non-default model, set both private per-million-token price values or
   pass both pricing options, then rerun the audit.
3. Start small: `./v3 fast-score backfill --include-existing --confirm
   --max-posts=1 --max-cost-usd=0.01`.
4. Run `./v3 task-queue run --limit=1 --score-limit=1 --work-limit=250`, then inspect
   `./v3 fast-score status`. Confirm the recent batch has a private state and
   a reserved estimate no larger than its maximum cost.
   `--score-limit` is the provider-call cap; `--work-limit` is a separate cap
   for all examined rows, including no-cost local exclusions.
5. Inspect the matching `fast_post_score` exchange privately. Confirm its
   post/content hash and provider model match the batch's target; do not copy
   its payload into public data.
6. Create a deliberately invalid provider configuration only in a disposable
   environment. Confirm retries follow the usual cap, then confirm a retry is
   not started if its reservation would exceed the batch maximum cost.
7. Confirm a newly published post still enters ordinary work and is not counted
   as historical backfill.
8. In a disposable private database, age a completed batch past one year and
   run `./v3 fast-score prune --before=...`. Confirm its batch, provenance,
   work, score, and matching private exchange records are removed.

Do not use direct SQLite writes to alter batch limits, work state, or reserved
cost. Create a new bounded batch instead.
