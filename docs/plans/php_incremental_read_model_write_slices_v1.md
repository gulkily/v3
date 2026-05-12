# PHP Incremental Read-Model Write Slices V1

This document outlines a production-safe plan for reducing write latency by removing unnecessary full read-model rebuilds from the hot path while preserving a full-rebuild recovery path.

## Problem Summary

Recent `Server-Timing` data for `POST /compose/thread` shows that the write path is dominated by `read_model_rebuild`:

- `write_file`: negligible
- `git_add`: small
- `git_commit`: small
- `git_rev_parse`: small
- `read_model_rebuild`: ~5.4 seconds
- `artifact_invalidate`: negligible

That means the user-visible delay is not in canonical file creation or git. It is in the current strategy of fully rebuilding SQLite derived state after each write.

Today, `LocalWriteService` does:

1. write canonical record
2. commit canonical record to git
3. fully rebuild the SQLite read model
4. invalidate affected static artifacts

That keeps the read model simple and recoverable, but it makes a one-record write cost scale with total repository size.

## Recommended Approach

Use four slices:

1. instrument the rebuild internals so the slow subphases are explicit
2. remove the worst full-rebuild hot loops to improve latency even before architecture changes
3. replace full rebuilds for thread/reply writes with incremental SQLite mutations
4. keep full rebuild as the repair and fallback path, and verify incremental parity against it

This sequence improves performance early, keeps risk bounded, and avoids switching to incremental updates blind.

## Slice 1: Rebuild Subphase Instrumentation

Status:

- implemented
- rebuild subphases now flow into write-path `Server-Timing` as `read_model_<phase>`
- direct test coverage added for rebuild timing capture and header formatting

Goal:

- identify which full-rebuild subphases dominate the current `read_model_rebuild` timing

Expected changes:

- add internal phase timing inside `ReadModelBuilder::rebuild()`
- measure at least:
  - `drop_schema`
  - `create_schema`
  - `index_posts`
  - `index_profiles`
  - `derive_approval_state`
  - `link_post_authors`
  - `index_instance`
  - `index_activity`
  - `write_metadata`
- surface those timings back through the existing write timing path, either:
  - folded into `Server-Timing`, or
  - logged in a structured form tied to the write request

Verification approach:

- a thread/reply POST exposes rebuild subphase timings
- timings are stable enough across repeated writes to identify the largest bucket
- no behavior change to canonical writes or rendered pages

Risks or open questions:

- avoid making timing output too noisy for normal operation
- decide whether subphase timing should always be on or be guarded by an environment flag

Components touched:

- `src/ForumRewrite/ReadModel/ReadModelBuilder.php`
- `src/ForumRewrite/Write/LocalWriteService.php`
- `src/ForumRewrite/Application.php`
- tests covering timing formatting if needed

## Slice 2: Full-Rebuild Hot-Loop Optimization

Status:

- implemented
- thread aggregates now build in one pass during `indexPosts()`
- profile counts now derive from the already-loaded post list instead of per-profile SQL rescans
- rebuild writes now run inside one SQLite transaction

Goal:

- materially reduce rebuild cost without changing the overall rebuild contract

Expected changes:

- optimize `indexPosts()` so thread aggregates are computed in one pass instead of rescanning all posts per thread
- replace per-profile `countVisibleRows()` scans with accumulated counts derived from already-loaded posts
- consider explicit SQLite transaction boundaries around rebuild writes if they are not already effectively batched
- keep the final read-model contents identical to the current rebuild output

Verification approach:

- `Server-Timing` shows `read_model_rebuild` materially lower than the current baseline
- all existing tests still pass
- a rebuild from scratch produces the same query-visible results as before

Risks or open questions:

- refactoring `indexPosts()` may accidentally change ordering or derived thread metadata
- profile counts and hidden-post filtering must preserve current semantics exactly

Components touched:

- `src/ForumRewrite/ReadModel/ReadModelBuilder.php`
- read-model and smoke tests as needed

## Slice 3: Incremental Thread/Reply Read-Model Updates

Status:

- implemented
- warm thread/reply writes now use an incremental SQLite updater instead of a full rebuild
- cold or stale read-model states still fall back to full rebuild
- timing output now distinguishes `read_model_incremental_*` from rebuild timings

Goal:

- stop performing full rebuilds on the hot path for the most common writes: thread creation and reply creation

Expected changes:

- introduce an incremental read-model updater for `createThread()` and `createReply()`
- for thread creation:
  - insert one `posts` row
  - insert one `threads` row
  - insert one `activity` row
  - update author/profile counts if `author_identity_id` is present
  - update metadata such as `repository_head`, `rebuilt_at`, and reason
- for reply creation:
  - insert one `posts` row
  - update the target `threads` row for `last_activity_at`, `reply_count`, and `last_post_id`
  - insert one `activity` row
  - update author/profile counts if applicable
  - update metadata
- keep `linkIdentity()` and `approveUser()` on full rebuild initially unless their incremental paths are also straightforward and justified by measured latency

Verification approach:

- thread/reply POST `Server-Timing` no longer includes multi-second full rebuild work
- created thread/reply pages, board, activity, and profile counts all reflect the write immediately
- targeted tests cover both anonymous and authored writes

Risks or open questions:

- incremental mutation logic can drift from full rebuild semantics
- approval-dependent profile fields must not be accidentally recomputed incorrectly
- metadata updates must continue to support stale detection and future rebuild decisions

Components touched:

- `src/ForumRewrite/Write/LocalWriteService.php`
- likely a new read-model mutation helper or service
- `src/ForumRewrite/ReadModel/*` support code
- write and smoke tests

## Slice 4: Fallback, Repair, and Parity Verification

Status:

- implemented
- incremental write failure now falls back to a same-request full rebuild when possible
- fallback failure still marks derived state stale
- parity coverage compares incremental thread/reply results to a fresh rebuild of the same repository state

Goal:

- preserve recoverability and prove that incremental updates remain equivalent to a fresh rebuild

Expected changes:

- keep full rebuild available as:
  - startup repair path
  - stale-marker recovery path
  - fallback when incremental mutation fails
- on incremental update failure after canonical commit:
  - mark derived state stale
  - fail safely with a clear error or force repair on the next read
- add parity verification tests that compare:
  - incremental thread/reply result
  - full rebuild result from the same canonical repository state

Verification approach:

- forced incremental failure marks the derived state stale and does not corrupt canonical writes
- a subsequent full rebuild repairs the read model
- parity tests confirm that incremental and full rebuild outputs match for the supported write flows

Risks or open questions:

- fallback behavior should not leave partially updated SQLite state looking valid
- parity coverage must be broad enough to catch hidden semantic drift

Components touched:

- `src/ForumRewrite/Write/LocalWriteService.php`
- `src/ForumRewrite/ReadModel/ReadModelStaleMarker.php`
- test coverage for failure and parity behavior

## Acceptance Criteria

This work is complete when:

- thread/reply write latency is no longer dominated by full rebuild cost
- rebuild subphase timing identifies and verifies the main performance wins
- thread/reply writes update SQLite derived state without a full rebuild on the hot path
- full rebuild remains available as a safe repair mechanism
- tests demonstrate parity between incremental updates and a fresh rebuild for supported flows

## Recommended Implementation Order

1. Slice 1 first, so the rebuild bottlenecks are measured instead of inferred
2. Slice 2 next, because it should produce immediate latency improvement with low architectural risk
3. Slice 3 after that, because incremental thread/reply updates are the main hot-path fix
4. Slice 4 last, because fallback and parity checks make the faster path production-safe

## Summary

The measured problem is not git and not file I/O. It is synchronous full read-model rebuild on every write.

The safest path is:

- measure rebuild internals
- optimize the current rebuild
- move thread/reply writes to incremental read-model mutation
- keep full rebuild as the correctness backstop
