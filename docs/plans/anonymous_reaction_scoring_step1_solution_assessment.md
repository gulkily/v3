# Anonymous Reaction Scoring — Step 1: Solution Assessment

## Problem

Reactions (like/flag, and now upvote/downvote) always write a canonical
record regardless of voter approval, but the displayed score only counts
reactions from identities already marked approved — so a QDB-style
drive-by visitor's vote never visibly moves the number.

## Context found

- Confirmed in code during `qdb_quotes_instance` Stage 3:
  `LocalWriteService::isApprovedIdentity()` gates scoring in
  `ReadModelBuilder`/`IncrementalReadModelUpdater`'s score-reduction
  loops, not the write path itself.
- Every reaction write is a real git commit (this app's canonical-record
  model commits per write). Uncapped anonymous voting at QDB-style
  traffic volumes means uncapped anonymous git commits — a real
  operational cost the approval gate may exist to bound, not just a
  spam-prevention nicety.

## Options

### Option A — Leave as-is (no change)
- Pros: zero risk, zero new work; matches today's Like/Flag behavior
  exactly.
- Cons: anonymous voting never visibly moves the score anywhere on the
  site, undercutting the classic-QDB feel specifically for this instance.

### Option B — Scope an exception to score-counting, not to writing
- Count upvote/downvote toward displayed score regardless of approval,
  while leaving the write path, and Like/Flag's existing gated scoring,
  untouched.
- Pros: narrow, additive change; doesn't touch the git-commit-per-write
  cost model, just who the number counts.
- Cons: still commits one record per vote regardless of approval, so
  the commit-volume question (above) is unresolved; needs a decision on
  whether that's acceptable or needs its own rate-limiting work.

### Option C — Lower the bar for "approved" generally
- Make approval easier/automatic (e.g. on first browser-generated
  identity) rather than changing scoring logic.
- Pros: one lever instead of a special case per tag.
- Cons: changes shared identity/approval semantics site-wide, far
  outside this feature's blast radius; likely why approval exists at all
  needs real investigation first, not just a guess.

## Recommendation

**Option B**, pending a cheap follow-up check on realistic write volume
(does this app already rate-limit or dedupe reaction writes per
viewer/post the way the existing "disable after applied" UI pattern
suggests?) before committing to the scope. Not recommending this for the
current `qdb_quotes_instance` feature — it changes shared scoring
behavior used by every reaction on the site, so it belongs in its own
plan.
