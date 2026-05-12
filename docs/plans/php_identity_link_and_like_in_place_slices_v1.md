# PHP Identity Link And Like In-Place Slices V1

This document outlines the implementation slices for removing full rebuilds from identity linking and for making the thread `Like` action use the same in-place browser bootstrap pattern as compose.

## Goal

Finish the remaining high-latency and bridge-like identity flows so that:

- public-key submission does not require a full read-model rebuild on the hot path
- both account-key linking and compose-triggered public-key publication benefit from that optimization
- clicking `Like` can create/bootstrap a browser identity in place when needed, instead of requiring prior manual setup
- full rebuild remains available as the fallback and repair path

## Implementation Status

- slice 1 is implemented on this branch
- slices 2-6 are pending

## Current Context

The repo already has:

- incremental read-model updates for thread/reply writes
- incremental read-model updates for thread-label writes such as `like`
- browser-side compose bootstrap that can:
  - prompt for username
  - generate a browser keypair
  - publish the public key
  - set the identity hint
  - continue the original post submit
- a retained `linkIdentity()` backend flow that still forces a full rebuild after commit
- a `Like` button flow that still assumes identity setup already exists

So the remaining work is not inventing a new identity system. It is extending the current incremental write model to identity linking, then reusing the existing browser bootstrap UX from compose for thread reactions.

## Why This Should Be Sliced

These two requests touch different risk surfaces.

`linkIdentity()` is a correctness-heavy read-model change because it affects:

- `profiles`
- `username_routes`
- bootstrap post author linkage
- hidden bootstrap-thread activity rows
- approval-derived state and profile counts

The `Like` button work is mostly a client-flow integration task once the identity-link write path is stable.

That makes the safest order:

1. incremental identity-link backend path
2. in-place like bootstrap on top of that path

## Slice 1: Incremental Identity-Link Contract

Focus:

- define exactly what must update immediately when a new identity is linked

Checklist:

- audit the current full-rebuild semantics of `linkIdentity()`
- list all immediate derived-state side effects that users can observe after link success
- confirm whether any existing surfaces depend on rebuild-only behavior that is not yet explicit
- document the retained fallback rule:
  - incremental update when the DB is warm and not stale
  - full rebuild when the DB is missing, stale, or incremental update fails
- decide whether `approveUser()` remains rebuild-based in V1 of this work

Expected outcome:

- the identity-link incremental target behavior is explicit before code changes begin

Implementation status:

- implemented
- retained V1 decision: `approveUser()` remains rebuild-based for now
- retained fallback rule: identity linking should use incremental refresh only when the DB is warm and not stale, with full rebuild fallback otherwise

## Slice 2: Incremental Read-Model Update For Identity Linking

Focus:

- add a dedicated incremental updater path for identity-link writes

Checklist:

- extend `IncrementalReadModelUpdater` with an identity-link method
- insert the new `profiles` row with the same values a rebuild would derive
- insert a `username_routes` row only when that username token is not already claimed
- update the bootstrap post row so its author fields point at the new identity/profile
- update any affected activity row(s) for the bootstrap post/thread so author info is consistent immediately
- compute the new profile's counts and approval-derived fields with rebuild-equivalent semantics
- write metadata with the current repository head and `write_incremental`
- keep transactional behavior aligned with the existing incremental write paths
- preserve rebuild fallback and stale-marker behavior on incremental failure

Expected outcome:

- identity linking can refresh derived state without a full rebuild on the hot path
- rebuild remains the correctness backstop

Implementation status:

- implemented
- `IncrementalReadModelUpdater` now has a dedicated identity-link path
- the updater handles both:
  - linking against an already-indexed bootstrap post
  - the auto-created hidden bootstrap post written in the same commit

## Slice 3: Route `linkIdentity()` Through The Incremental Path

Focus:

- switch the production identity-link write flow to use the new updater

Checklist:

- update `LocalWriteService::linkIdentity()` to use incremental refresh when possible
- keep canonical file creation, git add, and git commit boundaries unchanged
- retain current error handling for duplicate identities and invalid keys
- preserve targeted invalidation for affected profile/bootstrap surfaces
- expose timing output that distinguishes:
  - identity incremental refresh
  - incremental fallback
  - rebuild fallback
- verify both:
  - account-key form submit
  - compose-triggered background public-key publish

Expected outcome:

- the public-key submission path behaves like the already-optimized write flows
- normal identity publication no longer blocks on a full rebuild in the warm case

Implementation status:

- implemented
- `LocalWriteService::linkIdentity()` now uses incremental refresh when the DB is warm
- `/api/link_identity` now exposes timing headers for the optimized path
- identity linking now invalidates the affected profile, bootstrap thread, and bootstrap post artifacts

## Slice 4: Parity And Failure Coverage For Identity Linking

Focus:

- prove that incremental identity linking matches rebuild semantics and fails safely

Checklist:

- add parity tests comparing incremental identity linking to a fresh rebuild of the same repository state
- add failure-path coverage where incremental identity refresh throws and rebuild fallback succeeds
- add failure-path coverage where both incremental refresh and rebuild fallback fail and stale state is marked
- add smoke/API coverage for immediate visibility of:
  - profile route
  - identity hint resolution
  - bootstrap post author linkage
  - any user-directory/profile counts touched by the new identity

Expected outcome:

- the identity-link optimization is production-safe rather than just faster

Implementation status:

- implemented
- identity-link parity coverage now compares incremental results to a fresh rebuild
- identity-link failure coverage now exercises both:
  - incremental failure with rebuild fallback recovery
  - incremental failure plus rebuild failure leading to a stale marker

## Slice 5: In-Place Browser Bootstrap For `Like`

Focus:

- make `Like` behave like compose when the browser does not yet have a usable identity

Checklist:

- extend `public/assets/thread_reactions.js` to reuse the browser bootstrap flow already used by compose
- on click of `Like`, if no browser keypair exists:
  - prompt for username
  - generate keypair
  - publish public key
  - set/sync identity hint
- if a keypair exists but is not yet published:
  - publish it before applying the tag
- only call `/api/apply_thread_tag` after identity bootstrap succeeds
- keep the interaction in place on the thread page instead of redirecting to account setup
- present bootstrap errors inline in the thread feedback area
- keep the button disabled only while work is actually in progress

Expected outcome:

- brand-new users can click `Like` and complete the whole bootstrap-plus-like flow without leaving the page

Implementation status:

- implemented
- thread pages now load the browser identity/bootstrap assets needed for in-place setup
- the `Like` flow now reuses the browser bootstrap helper before calling `/api/apply_thread_tag`
- bootstrap happens in place on the thread page with inline status/error feedback

## Slice 6: Like-Flow UX And Regression Coverage

Focus:

- harden the client flow so the in-place behavior is understandable and reliable

Checklist:

- cover existing-identity like behavior
- cover first-like bootstrap behavior
- cover already-liked behavior
- cover bootstrap cancellation and failure behavior
- verify thread score, button state, and inline feedback stay correct after success
- verify drafts or other page state are not disturbed by the in-place flow
- make sure repeated clicks do not create duplicate bootstrap attempts or duplicate likes

Expected outcome:

- the in-place `Like` flow matches the compose bootstrap quality bar

Implementation status:

- implemented
- browser-side regression coverage now exercises:
  - bootstrap-before-like sequencing
  - inline bootstrap failure handling without issuing the like write
- thread-route smoke coverage now verifies the required bootstrap assets are present

## Recommended Order

1. define the exact incremental identity-link contract
2. implement the incremental identity-link updater
3. route `linkIdentity()` through it
4. add parity and failure coverage
5. wire the `Like` button to the in-place browser bootstrap flow
6. harden the `Like` UX and add regression coverage

## Summary

This work should be treated as two connected features, not one flat change.

The first feature is backend:

- make identity linking incrementally refresh derived state instead of forcing a full rebuild

The second feature is frontend:

- reuse the existing browser bootstrap flow so `Like` can create identity in place

That order keeps the read-model correctness risk isolated before client-flow changes depend on it.
