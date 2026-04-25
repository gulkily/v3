# PHP Approval Incremental Refresh Slices V1

This document outlines the implementation slices for removing full read-model rebuilds from approval writes while preserving the current approval semantics.

## Goal

Optimize the `Approve user` write path so it does not require a full read-model rebuild on the hot path, while keeping approval state, approval-chain semantics, and approval-sensitive thread scores equivalent to a fresh rebuild.

## Implementation Status

- slice 1 is implemented on this branch
- slices 2-6 are pending

## Current Context

The repo already has:

- incremental read-model updates for thread/reply writes
- incremental read-model updates for thread-label writes
- incremental read-model updates for identity linking
- a retained approval write path that still commits the approval reply and then fully rebuilds the read model
- approval state derived from canonical approval replies plus approval seeds
- thread-label score semantics that depend on whether the label author is approved

So approval optimization is not blocked on infrastructure. The remaining work is to add a dedicated approval-write incremental updater that handles the broader semantic blast radius of approval changes.

## Why Approval Is Different

Approval writes are not just local row inserts.

A new approval reply can:

- mark the target identity as approved
- make earlier approvals authored by that identity become valid
- cause additional identities to become approved transitively
- make existing scored thread labels from newly approved identities start affecting thread scores

That means approval optimization is not just:

- insert one reply row
- update one profile row

It requires a controlled recomputation of derived approval state and approval-sensitive aggregates across already indexed data.

## Slice 1: Define The Incremental Approval Refresh Contract

Focus:

- define exactly what approval writes must update immediately in the read model

Checklist:

- audit the current full-rebuild semantics of approval writes
- list all read-model surfaces that can change after a new approval reply:
  - `profiles.is_approved`
  - `profiles.approved_by_*`
  - bootstrap-thread/profile rendering
  - approval activity visibility
  - thread scores affected by existing `like` or `flag` labels from newly approved identities
- confirm whether any non-score thread-label aggregates also need refresh
- define the retained fallback rule:
  - incremental refresh when the DB is warm and not stale
  - full rebuild when the DB is missing, stale, or incremental refresh fails
- define the merge bar as parity with a fresh rebuild, not merely “looks right in one page”

Expected outcome:

- the incremental approval updater target behavior is explicit before implementation starts

Implementation status:

- implemented
- `approveUser()` still commits the canonical approval reply before any derived-state refresh
- the incremental warm-path contract is rebuild parity across these read-model surfaces:
  - `profiles.is_approved`
  - `profiles.approved_by_identity_id`
  - `profiles.approved_by_profile_slug`
  - `profiles.approved_by_label`
  - approval and identity activity rows that render the approval reply author state
  - bootstrap thread/profile/API rendering that depends on approval-derived profile fields
  - `threads.score_total` for threads with existing scored labels from identities whose approval state changed
- additive thread-label state remains unchanged by approval refresh:
  - `threads.thread_labels_json` still reflects all canonical label records, not just approved scored labels
  - thread-label activity rows remain driven by canonical label additions, while their `author_is_approved` fields may need refresh when profile approval changes
- the retained fallback rule is explicit:
  - use incremental refresh only when the read-model DB exists, matches the current schema/root contract, and is not marked stale
  - fall back to a full rebuild when the DB is missing, stale, incompatible, or the incremental approval refresh throws
- the merge bar for later slices is fresh-rebuild parity, not just visible approval on one page

## Slice 2: Incremental Approval-State Recompute

Focus:

- add an approval-specific incremental updater path that recomputes approval state from indexed data

Checklist:

- add `applyApprovalWrite()` to `IncrementalReadModelUpdater`
- insert the approval reply post row
- update the target thread summary for the new reply
- insert the approval activity row with current semantics
- load the indexed profiles and posts needed to derive approval state
- rerun the same approval derivation rules used by rebuild:
  - seed-approved identities start approved
  - approval replies are sequence-sensitive
  - self-approval remains invalid
  - reply target must match the target identity bootstrap thread/post
- update all affected `profiles.is_approved` and `approved_by_*` fields
- track which identities changed approval status during the recompute
- write metadata with the current repository head and `write_incremental`

Expected outcome:

- approval writes can refresh profile approval state without a full rebuild on the warm path

## Slice 3: Recompute Approval-Sensitive Thread Scores

Focus:

- refresh derived thread score state affected by newly approved identities

Checklist:

- identify identities whose approval status changed during the approval recompute
- find thread-label records authored by those identities
- recompute thread-label score totals for affected threads using rebuild-equivalent rules
- update `threads.score_total` for those threads
- update any other approval-sensitive thread-label derived fields if needed
- confirm that additive labels remain unchanged and only approval-sensitive scoring changes
- keep hidden/bootstrap thread behavior aligned with rebuild semantics

Expected outcome:

- previously written likes/flags from newly approved identities start counting immediately without a full rebuild

## Slice 4: Route Approval Writes Through The Incremental Path

Focus:

- switch the production approval write flow to use the new updater

Checklist:

- update `LocalWriteService::approveUser()` to use incremental refresh when possible
- keep canonical write ordering and git commit boundaries unchanged
- preserve current locking and validation behavior
- retain artifact invalidation for profile, bootstrap thread, target bootstrap post, and approval post
- expose timing output that distinguishes:
  - approval incremental refresh
  - incremental fallback
  - rebuild fallback

Expected outcome:

- the `Approve user` flow stops blocking on a full rebuild in the warm case

## Slice 5: Parity And Failure Coverage

Focus:

- prove that incremental approval refresh is equivalent to a fresh rebuild and fails safely

Checklist:

- add parity tests comparing incremental approval writes to a fresh rebuild of the same repository state
- cover direct target approval
- cover transitive approval cases where one approval unlocks another
- cover approval-sensitive score changes becoming visible after approval
- cover incremental approval failure with rebuild fallback recovery
- cover incremental approval failure plus rebuild failure resulting in a stale marker
- verify approval feed/bootstrap-thread/profile rendering remains rebuild-equivalent

Expected outcome:

- approval optimization is production-safe instead of just faster in simple cases

## Slice 6: Follow-On Cleanup And Measurement

Focus:

- verify the real latency improvement and tighten any broad recompute steps if needed

Checklist:

- measure approval write timing before and after the change
- confirm the hot path no longer spends multi-second time in `read_model_rebuild`
- identify whether approval-state recompute or thread-score recompute dominates the new path
- decide whether a later slice should narrow the set of affected threads further
- update docs if timing semantics or operational expectations changed

Expected outcome:

- the approval optimization has measured impact and a clear next step if further narrowing is worthwhile

## Recommended Order

1. define the incremental approval refresh contract
2. implement approval-state recompute
3. recompute approval-sensitive thread scores
4. route approval writes through the incremental path
5. add parity and failure coverage
6. measure and refine

## Summary

This work follows the same overall pattern as the recent optimizations:

- add a dedicated incremental updater
- keep rebuild fallback
- prove parity against a fresh rebuild

But approval is materially broader than thread/reply, thread-label, or identity-link optimization because a new approval can change the interpretation of older writes.

So the key design rule for this effort is:

- optimize the approval write path without weakening rebuild-equivalent approval and score semantics
