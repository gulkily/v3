# PHP User Approval Vouching Slices V1

This document outlines the implementation slices for adding operator-facing user approval through user-to-user vouching.

## Goal

Allow already-approved users to approve other users from the profile page.

The approval action should:

- be available only to already-approved users
- be unavailable for self-approval
- allow multiple approvals over time
- be recorded canonically as a reply on the target profile's bootstrap thread
- stay hidden from normal board and activity feeds
- remain visible when the bootstrap thread is opened directly

## Product Decisions

Chosen behavior for V1:

- approved users can vouch for other users
- approval is additive only for now
- revoke or unapprove is out of scope
- multiple approvals for the same target are allowed
- current acting user is resolved from the existing `identity_hint` cookie
- the first approved user should be bootstrapped by an explicit canonical seed record, not by git history
- approval replies should be hidden from feeds but visible on direct bootstrap-thread inspection

## Current Context

The repo already has:

- profile pages keyed by identity/profile slug
- canonical identity bootstrap records
- auto-created hidden bootstrap posts and threads for account setup
- reply creation infrastructure with git commit, rebuild, invalidation, locking, and stale-marker handling
- hidden bootstrap-only content filtered out of normal board/activity surfaces

So this feature should reuse the existing bootstrap-thread and reply machinery instead of inventing a separate approval store.

## Slice 1: Canonical Approval Contract And Seed

Focus:

- define what an approval record is
- define how the first approved user enters the system

Checklist:

- define the canonical approval reply shape in the relevant spec/docs
- choose the board tags for approval replies
- make approval replies reply to the target identity's bootstrap post/thread
- define a deterministic structured body/header shape for the approval action
- define how the approver identity is represented
- add an explicit canonical seed record for the initial approved user
- document why the seed is canonical data and not derived from git history

Recommended contract shape:

- approval is a normal canonical reply post
- `Thread-ID` is the target profile bootstrap thread
- `Parent-ID` is the target profile bootstrap post
- `Author-Identity-ID` is the approving identity
- board tags include `identity`
- body includes a deterministic line such as `Approve-Identity-ID: <identity_id>`

Expected outcome:

- approval status has a clear canonical representation
- the first approved user is seeded explicitly and rebuildably

Implementation status:

- approval seed contract is implemented under `records/approval-seeds/`
- the fixture repo seeds the existing OpenPGP fixture identity as approved
- parser/repository coverage exists for the new seed family

## Slice 2: Read-Model Approval Derivation

Focus:

- derive approved status from canonical data

Checklist:

- index approval replies during read-model rebuild
- derive whether each profile is approved
- derive whether an approval is valid only when its author is already approved
- make the seed user count as approved
- ensure approval replies stay hidden from board/activity feed surfaces
- keep approval replies visible in direct bootstrap-thread inspection
- expose enough read-model data for profile-page button logic

Important rule:

- approval chains should be deterministic from canonical data alone
- a user becomes approved if they have at least one valid approval from an already-approved identity

Expected outcome:

- approved status is rebuildable and queryable
- feed behavior remains clean

Implementation status:

- profiles now derive `is_approved` during rebuild from approval seeds plus structured approval replies
- approval validity is sequence-based and only counts when the approver is already approved
- profile text API output now exposes `Approved: yes|no`

## Slice 3: Profile UI And Approve Action

Focus:

- expose the action on profile pages for eligible users

Checklist:

- add approved-state awareness to profile rendering
- show `Approve user` only when:
  - viewer resolves from `identity_hint`
  - viewer is approved
  - viewer is not the target profile
- hide the button for unapproved users
- hide the button for self-profile views
- add a `POST` action that records the approval reply on the target bootstrap thread
- keep success and failure messages deterministic
- keep the action on `/profiles/<slug>` rather than adding a separate admin area in V1

Expected outcome:

- approved users can vouch for others directly from profile pages
- non-approved users do not see the control

Implementation status:

- profile pages now know the viewer profile from the `identity_hint` cookie
- `Approve user` renders only for approved non-self viewers
- profile approval writes record a structured hidden reply on the target bootstrap thread

## Slice 4: Production Hardening And Tests

Focus:

- make approval writes match the rest of the production contract

Checklist:

- commit approval replies to git
- rebuild the read model immediately after approval writes
- invalidate affected profile/thread/post artifacts
- ensure stale-marker behavior works after post-commit refresh failure
- ensure locking covers approval writes and rebuild interactions
- add smoke coverage for:
  - seeded approved user visibility
  - approved user can approve another user
  - unapproved user cannot approve
  - self-approval is rejected
  - multiple approvals are accepted
  - approval replies stay out of board/activity feeds
  - approval replies remain visible on direct bootstrap-thread inspection

Expected outcome:

- approval/vouching behaves like a first-class production write flow

Implementation status:

- approval writes now commit structured reply posts through the normal git-backed write path
- profile, bootstrap-thread, and direct post surfaces are invalidated after approval writes
- smoke coverage now covers seeded approval, successful approval, permission denial, self-approval rejection, multiple approvals, and feed visibility rules

## Recommended Order

1. define canonical approval reply contract and seed strategy
2. derive approved status in the read model
3. add profile-page action and backend write route
4. harden and test the full flow

## Summary

This feature should be built as an extension of the existing identity bootstrap model.

Instead of creating a separate approval datastore, V1 should:

- seed the initial approved user canonically
- record later approvals as structured replies on bootstrap threads
- derive approved status during rebuild
- expose a simple `Approve user` action only to already-approved users

Current status:

- all four planned slices are implemented on this branch
