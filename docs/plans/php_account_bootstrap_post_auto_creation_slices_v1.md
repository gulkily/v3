# PHP Account Bootstrap Post Auto-Creation Slices V1

This document describes the implementation slices for replacing the placeholder `bootstrap_post_id` field with automatic bootstrap-post creation during account setup.

Implementation status:

- core implementation now exists in the repo
- account creation can auto-create a hidden bootstrap post when no `bootstrap_post_id` is supplied
- low-level manual fallback with explicit `bootstrap_post_id` still exists

## Goal

When a user creates an account, the system should automatically create a canonical bootstrap post and then link the new identity to that post.

The user should not need to know or choose a `bootstrap_post_id`.

## Current State

The current implementation is technically correct but still bridge-like from a product perspective.

What already works:

- the browser can prompt for a username during key generation
- that username is embedded into the OpenPGP user ID
- the backend derives the username from the submitted public key
- identity/profile bootstrap succeeds with that username

What was too technical in the earlier bridge state:

- the account flow depended on `bootstrap_post_id`
- the current page exposed that field directly to the user
- the default value was a retained technical placeholder rather than a meaningful user-facing concept

Chosen product decision:

- automatically created bootstrap posts should be hidden by default from normal board and activity views

So this plan is not about making username bootstrap possible. That already exists. The implemented direction is to remove the exposed bootstrap-post plumbing from the normal account-creation experience while preserving a low-level manual fallback.

## Why This Exists

The earlier bridge version of the account flow exposed a technical field:

- `bootstrap_post_id`

That field worked for the retained technical slice, but it was still a placeholder-style mechanism because it defaulted to a test/demo post such as `root-001`.

In slice terms, the current state is:

- valid
- working
- intentionally not the final product shape

The long-term improvement is:

- create a real bootstrap post at account-creation time
- use that new post as the canonical bootstrap anchor
- remove the need for the user to supply the bootstrap post manually

## Slice 1: Define Bootstrap Post Contract

Focus:

- define what an automatically created bootstrap post is
- make the canonical rules explicit before changing the write path

Checklist:

- document the bootstrap-post shape in the relevant spec/docs
- decide the board tags for bootstrap posts
- decide whether bootstrap posts live in a dedicated thread or as standalone roots
- define the default subject/body shape for the bootstrap post
- define how bootstrap posts are marked so they can be hidden by default from normal board/activity views
- define what metadata is derived from the bootstrap post vs the identity record
- document how this replaces the current manual `bootstrap_post_id` requirement in the normal account flow

Expected outcome:

- the repo has a clear documented canonical contract for auto-created bootstrap posts

## Slice 2: Server-Side Auto-Creation Flow

Focus:

- change account creation so the server creates the bootstrap post automatically

Checklist:

- add a write flow that:
  - creates a canonical bootstrap post
  - commits it or stages it together with identity creation as appropriate
  - links the identity to that new bootstrap post
- update `src/ForumRewrite/Write/LocalWriteService.php`
- remove the requirement that the user submits `bootstrap_post_id` for the normal account-creation path
- make the account flow return the created bootstrap post ID in success output
- decide whether the low-level `/api/link_identity` contract still accepts `bootstrap_post_id` as an advanced/manual fallback
- preserve the existing username bootstrap behavior during this transition

Expected outcome:

- account creation no longer depends on a placeholder post ID
- every new identity gets a real bootstrap anchor created at creation time
- bootstrap posts are created in a way that supports hidden-by-default read filtering

## Slice 3: UI and API Contract Cleanup

Focus:

- remove placeholder-style UX and align the page/API with the new server behavior

Checklist:

- remove or hide the `bootstrap_post_id` field from the normal account page flow
- rename/clarify user-facing actions if needed
- update account page copy so the user is not exposed to bootstrap mechanics
- keep any advanced/manual controls only where technically necessary
- update client-side JS if the browser flow should call the new account-creation contract directly
- ensure the page still presents username bootstrap as the user-facing account creation step, not bootstrap-post management

Expected outcome:

- the normal account flow feels intentional instead of test-slice-shaped
- technical bootstrap internals no longer appear in the default UI
- the default UI does not imply that bootstrap posts are normal user-authored content threads

## Slice 4: Read-Model and Rendering Impact

Focus:

- ensure the newly created bootstrap posts behave correctly in indexed reads

Checklist:

- confirm the read model indexes bootstrap posts correctly
- confirm identity/profile pages point to the new bootstrap post and thread
- keep bootstrap posts hidden by default from normal board/activity views
- add deterministic filtering in the read model and/or rendering path for normal content surfaces
- decide whether there is any explicit technical/admin/debug view where bootstrap posts remain inspectable
- verify counts and author linkage remain correct
- confirm the profile still resolves the correct visible username that was already chosen during key generation

Expected outcome:

- bootstrap posts are first-class canonical records
- they do not create confusing read-surface regressions
- account creation does not pollute normal public content listings

## Slice 5: Production Hardening

Focus:

- make the auto-bootstrap flow production-safe

Checklist:

- ensure git commit handling works for the combined bootstrap-post + identity write flow
- ensure read-model rebuild behavior stays deterministic
- ensure static artifact invalidation covers any affected routes
- ensure stale-marker behavior handles post-commit refresh failures
- ensure locking covers the full account-creation flow
- add smoke tests for success, git failure, and refresh failure

Expected outcome:

- the auto-bootstrap account flow matches the rest of the production write contract

## Recommended Order

1. define the bootstrap post contract
2. implement server-side auto-creation
3. clean up UI/API exposure
4. verify read-model/rendering behavior
5. harden and test the production flow

## Summary

This plan replaces the earlier placeholder-style `bootstrap_post_id` approach with a real canonical bootstrap-post creation flow.

That makes account creation technically coherent and removes a current bridge artifact from the user-facing flow.

The important product point is:

- username bootstrap already works
- bootstrap-post selection should stop being a normal user concern
