# Step 2: Feature Description — Feature Flag Write Triggers Spurious Read-Model Rebuild

## Problem
Feature-flag writes commit a new canonical-record SHA to git but never update the read model's stored `repository_head` metadata, so `Application::ensureReadModel()` sees a false mismatch on the very next request and performs a full synchronous rebuild — making a routine flag toggle feel like it hangs, and briefly slowing down whoever hits the site right after.

## User Stories
- As a site operator, I want toggling a feature flag to complete quickly and predictably, so I'm not blocked waiting on an unrelated full rebuild every time I change a setting.
- As anyone browsing the site right after a flag change, I want my page load to stay fast, so a routine config change doesn't momentarily degrade the site for me.
- As a maintainer, I want feature-flag writes to follow the same read-model consistency guarantees every other write type already follows, so this bug doesn't need independent re-discovery for the next new write path.

## Core Requirements
- After a successful feature-flag write, the read model's `repository_head` metadata must match the new commit SHA, so the next request doesn't trigger a full rebuild.
- The activity-log insert and metadata update must happen in one transaction, so a crash between the two can't reintroduce staleness.
- A successful feature-flag write must clear any pre-existing stale marker, matching what other successful incremental writes already do.
- No change to the feature flag's own value semantics, save/reset UX, or the in-flight page redesign — this fix touches only the write path's read-model bookkeeping.
- No database schema changes; reuse the existing `metadata`/`activity`/stale-marker mechanisms already used by other write types.

## Shared Component Inventory
- `LocalWriteService::setFeatureFlag()` / `insertFeatureFlagActivity()` — the only feature-flag write path. **Extended in place**, not replaced.
- `IncrementalReadModelUpdater::writeMetadata()` — the canonical "update `repository_head`" logic every other write type already uses. **Reused** (called or mirrored on the same open PDO connection), not duplicated as a new ad hoc mechanism.
- `Application::ensureReadModel()` — the staleness check this bug is exposed through. **Not modified**; this fix makes the data it reads correct instead of changing its logic (Step 1's Option A, not C).
- `ReadModelStaleMarker` — existing stale-marker mechanism. **Reused** (cleared on success), no new signal introduced.
- `/api/set_feature_flag` response contract, the feature-flags page template, and its JS — **untouched**; this is purely a write-path fix with no visible contract change.

## Simple User Flow
1. Operator toggles or resets a feature flag on `/tools/feature-flags/`.
2. The write commits the new value to the canonical record and, in one transaction, updates the read model's activity log, updates `repository_head` metadata, and clears any stale marker.
3. Operator (or anyone else) loads any page next.
4. `Application::ensureReadModel()` sees the metadata already matches the current commit and skips the rebuild, serving the page immediately.

## Success Criteria
- After a feature-flag write, `metadata.repository_head` in the read-model database equals the actual git HEAD immediately following the commit.
- The next request after a feature-flag write does not perform a full read-model rebuild.
- A regression test simulating a pre-existing stale marker confirms a successful feature-flag write clears it.
- All existing feature-flag tests (evaluator, behavior, smoke) continue to pass unchanged.
