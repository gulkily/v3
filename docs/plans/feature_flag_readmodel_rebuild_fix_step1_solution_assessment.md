# Step 1: Solution Assessment — Feature Flag Write Triggers Spurious Read-Model Rebuild

## Problem
`LocalWriteService::setFeatureFlag()` commits a new git SHA but never updates the read model's stored `repository_head` metadata, so the very next request to hit `Application::ensureReadModel()` always sees a mismatch and performs a full synchronous rebuild.

## Options

**Option A: Update `metadata.repository_head` directly after the feature-flag write, matching the full incremental-write pattern**
- Checked this against what every other incremental write actually does (`applyPostWrite`, `applyThreadLabelWrite`, etc. in `IncrementalReadModelUpdater`) to confirm parity, not just the metadata piece:
  - Wrap the activity insert + metadata update in one `$pdo->beginTransaction()`/`commit()`/`rollBack()`, like every other incremental method does — without this, a crash between the two statements could leave activity updated but metadata still stale, reintroducing the same bug for that one write.
  - Clear the stale marker on success (`$this->staleMarker()->clear()`), matching `synchronizePostDerivedState`'s incremental-success branch — `ensureReadModel()` checks the stale marker *before* `repository_head`, so a marker left over from some earlier, unrelated failed write would still force a rebuild after a "successful" flag save otherwise.
  - Confirmed **not needed**: no materialized-current-state table update (threads/posts/profiles have cached derived state to keep in sync; feature flags don't — their effective value is always read live from the canonical text file via `FeatureFlagEvaluator`, never cached in SQLite) and no delete-then-reinsert activity dedup (thread-label activity re-derives current state each time; feature-flag activity is correctly an append-only changelog of each change).
- Pros: fixes the confirmed root cause with real parity to the established pattern; no shared-class changes; no schema change.
- Cons: duplicates a few lines of "write metadata" logic that already exist in `IncrementalReadModelUpdater`, instead of reusing it.

**Option B: Route feature-flag writes through `IncrementalReadModelUpdater`**
- Add a small method there (e.g. `applyFeatureFlagWrite($commitSha)`) so all read-model-touching writes share one "update metadata + activity" code path.
- Pros: single source of truth; avoids future drift if `writeMetadata()`'s shape changes.
- Cons: `IncrementalReadModelUpdater` is built around `PostRecord`/thread writes (`applyPostWrite`); bending it to fit a non-post write is a larger, shared-class change for a small win.

**Option C: Make `ensureReadModel()`'s full-rebuild fallback cheaper or async instead**
- Change the core staleness-triggered rebuild itself (e.g., a lighter catch-up path, or defer to background) so any write path that forgets to sync metadata doesn't cost a full synchronous rebuild.
- Pros: also hardens against future write paths making the same mistake, not just this one.
- Cons: substantially larger, riskier change to a well-tested core safety mechanism; treats the symptom instead of the cause; conflicts with the standing preference to avoid touching core read-model/schema machinery when a narrower fix exists.

## Recommendation
**Option A**, now scoped to include the transaction wrap and stale-marker clear above — that gives it real parity with the established incremental-write pattern, not just the metadata piece, while still touching only the feature-flag write path with no schema or shared-class changes. Option B's reuse benefit is smaller now that Option A already matches the pattern correctly, and still isn't worth bending a post-oriented class for one small write type; Option C fixes the wrong layer and carries real risk to a core safety mechanism other writes depend on.
