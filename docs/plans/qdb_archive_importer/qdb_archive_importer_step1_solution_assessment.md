# QDB Archive Importer — Step 1: Solution Assessment

## Problem

We need to import qdb.us's historical quote archive (~14,881 approved,
non-spam, non-deleted quotes from `~/qdb_database/backup.sql`, 2003–2025)
into this app's git-backed canonical-record system, showing each quote's
original score/vote tally, without replaying its ~8.6M individual
historical votes as one canonical record per vote.

## Decisions already made

- Import approved/non-spam/non-deleted quotes only.
- Import final score/vote tallies as aggregates, not per-vote history.
- Preserve qdb's original `quote_id` as the quote's permanent public
  number (requires separating "display number" from internal `Post-ID`
  in the read model/`quote_card.php`, since the two are conflated today).
- One-shot backfill; no re-run/idempotency requirement.

## Open question this step resolves

The read model's `score_total`/`vote_count` are purely *derived* by
counting live `post-reaction` canonical records (`TagScore`) — there is
no existing field for seeding an aggregate count directly. Full read-model
rebuilds are routine in this system (task queue, incremental-repair
fallback, static artifact builds), not a rare admin action. So how do we
represent 79k imported score/vote tallies safely?

## Options

### Option A — Seed the SQLite read model directly, no canonical record
- Import quotes as normal post records; after `ReadModelBuilder::rebuild()`,
  patch `score_total`/`vote_count` straight into the read-model DB.
- Pros: no schema change; smallest file/commit count (batched per-quote
  posts only).
- Cons: breaks the architecture's core invariant that the read model is
  always reconstructable from git records alone — any later full rebuild
  (which happens routinely) silently zeroes every imported score.

### Option B — One small aggregate reaction record per quote (Recommended)
- Extend the post-reaction canonical schema additively with optional
  `Imported-Score` / `Imported-Vote-Count` headers on a single reaction
  record per quote (79k small files/commits, not millions); teach
  `ReadModelBuilder`/`TagScore` to fold those into `score_total`/
  `vote_count` directly instead of expanding them into per-vote tags.
- Pros: scores stay rebuild-safe and reconstructable from git records,
  consistent with every other write path in this system; bounded,
  additive schema change.
- Cons: touches canonical schema + `ReadModelBuilder` (more surface than
  Option A).

## Recommendation

**Option B.** Option A is simpler but silently violates this system's
one load-bearing invariant — that the read model can always be rebuilt
from canonical records alone — and real rebuilds run routinely (task
queue, incremental repair, static artifact builds), so the data loss
isn't hypothetical. Option B keeps the footprint bounded (79k records,
not 8.6M) while staying consistent with how every other score in this
app is derived.
