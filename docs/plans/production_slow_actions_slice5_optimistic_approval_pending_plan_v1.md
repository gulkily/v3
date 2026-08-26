# Production Slow Actions Slice 5 Optimistic Approval Pending Plan V1

This plan breaks out the approval-action part of Slice 5 from `production_slow_actions_client_responsiveness_plan_v1.md`. The goal is to make approving pending users feel immediate while preserving server-side authorization, canonical approval writes, approval-derived read-model updates, and artifact invalidation.

## Goal

When an approved viewer clicks `Approve` on `/users/pending`, the row should immediately move into a visible pending-approved state instead of waiting for `/api/approve_user` to finish.

The server remains authoritative. The client may temporarily remove or mark the row as pending, then reconcile on success or rollback on failure.

## Non-Goals

- Do not change canonical approval record semantics.
- Do not change `LocalWriteService::approveUser()` authorization rules.
- Do not optimistically approve users on profile pages in this first pass.
- Do not persist pending approval operations across reloads.
- Do not update the full user directory, profile page, score totals, activity feed, or static artifacts client-side.
- Do not claim final approval until `/api/approve_user` returns `status=ok`.

## Current State

- `/users/pending` renders `templates/pages/users_pending.php`.
- `public/assets/pending_approvals.js` handles `data-action="approve-user"` clicks.
- The current client flow disables the button and shows `Approving user...`, but leaves the row in place until the API response returns.
- On success, the row is removed and the empty state is shown if no pending rows remain.
- On failure, the button is re-enabled and the server error is displayed.
- This action does not currently call `/api/get_profile` or `/api/set_identity_hint`; the remaining delay is the approval write itself.

## Desired User Experience

For `/users/pending`:

- clicking `Approve` immediately removes the user from the active pending table or moves it into a clearly pending-approved area
- feedback appears in the same event turn, before the network response resolves
- duplicate clicks cannot send duplicate approval requests
- if the server confirms success, the optimistic removal stays in place
- if the server rejects the approval, the row is restored to its original position, the approve button is enabled again, and the server error is shown
- if the last row is pending removal, the empty state can appear optimistically but must rollback if the request fails

## Integrity Rules

- The server remains authoritative for:
  - whether the viewer is approved
  - whether the target profile exists
  - self-approval rejection
  - duplicate approval rejection
  - bootstrap parent and approval reply validity
  - canonical write, git commit, read-model refresh, and artifact invalidation
- Client-side pending state must be reversible.
- A pending approval row must not be permanently removed until the server returns `status=ok`.
- Duplicate clicks for the same profile must issue at most one request while pending.
- Debug/timing payloads must not include profile private data beyond existing public profile slugs/usernames already rendered on the page.

## Implementation Slices

### Slice 5A: Approval Pending State Helpers

Goal:

- make optimistic row removal and restoration explicit and testable without changing server behavior

Work:

- add helper functions in `pending_approvals.js` for:
  - capturing a row's parent, sibling position, username, profile slug, and button disabled state
  - moving or removing a row into pending state
  - restoring the row to its original position on failure
  - refreshing table/empty-state visibility from current pending rows
- keep `approveUser(profileSlug)` unchanged
- do not alter timing/debug behavior in this slice unless needed by tests

Acceptance:

- browser test proves a row can be optimistically removed and restored in original order
- browser test proves empty/table state recalculates when the last row is pending
- existing pending approval smoke tests still pass

### Slice 5B: Optimistic Pending Approval Flow

Goal:

- update `/users/pending` immediately after clicking approve, before `/api/approve_user` resolves

Work:

- on click:
  - call `preventDefault()`
  - start a page-local pending operation for the profile slug
  - capture row state
  - optimistically remove the row from the table
  - refresh empty/table visibility
  - show feedback such as `Approving user...`
  - submit `/api/approve_user`
- on success:
  - keep the row removed
  - clear pending operation state
  - show `Approved user {username}.`
- on failure:
  - restore the row and original button state
  - refresh empty/table visibility
  - clear pending operation state
  - show the server error

Acceptance:

- test proves the row is removed before the fetch promise resolves
- test proves the last-row empty state appears before the fetch promise resolves
- test proves success keeps the row removed
- test proves failure restores the row and button

### Slice 5C: Duplicate Approval Guard and Retry

Goal:

- prevent duplicate approval writes while still allowing retry after a failure

Work:

- add an in-memory pending approval operation set keyed by profile slug
- repeated clicks while a profile approval is pending must not issue additional `/api/approve_user` requests
- pending operation state must clear on success and failure
- failed approvals can be retried with a new request

Acceptance:

- repeated fast clicks for the same row produce one approval fetch
- retry after failure produces a second approval fetch
- successful retry leaves no duplicate row or stale pending state

### Slice 5D: Timing and Compatibility

Goal:

- keep the slow-action timing/debug model useful for approval actions

Work:

- add or reuse browser performance marks for:
  - action start
  - first optimistic feedback
  - fetch start
  - response received
  - reconcile complete
  - action complete
- parse `Server-Timing` from `/api/approve_user` if the endpoint already returns it; otherwise leave server timing empty and do not expand server scope in this slice
- keep debug timing opt-in via existing `?debug_timing=1` or `localStorage.forum_debug_timing=1`

Acceptance:

- timing/debug tests cover optimistic approval success and failure
- debug payload does not include private key material or authored post bodies
- approval flow still works without JavaScript through existing server form paths where present

## Reconciliation Strategy

Initial recommendation:

- optimistically remove the row from `/users/pending`
- keep the server-rendered pages authoritative for final profile status, directory membership, scores, and activity
- rely on rollback to restore the row if `/api/approve_user` rejects the action

Rationale:

- the pending approvals table is a queue, so removing an item from the queue is the expected immediate feedback
- approval effects touch several derived surfaces, but the current page only needs to reflect queue membership
- server reconciliation remains simple: success leaves the queue item gone; failure restores it

## Test Plan

Use Node-backed browser script tests in `BrowserSigningNormalizationTest` for `pending_approvals.js`.

Coverage should include:

- optimistic row removal before fetch resolution
- optimistic empty state for last-row approval
- server success keeping the row removed
- server failure restoring the row in the original order
- duplicate click suppression
- retry after failure
- timing/debug payload shape if Slice 5D adds timing

Run before each implementation commit:

- `node --check public/assets/pending_approvals.js`
- `php tests/run.php`

## Suggested Commit Sequence

1. Extract approval pending state helpers and tests.
2. Wire optimistic pending approval success/failure flow.
3. Add duplicate guard and retry coverage.
4. Add timing/debug compatibility coverage.

## Open Questions

- Should the row be removed immediately or moved to a small `Approving...` holding area? Initial recommendation: remove immediately and use feedback text, because the queue semantics are clearer and rollback can restore the exact row.
- Should profile-page approval use the same optimistic machinery? Initial recommendation: no for this slice; plan it after `/users/pending` is stable.
- Should `/api/approve_user` return `Server-Timing` if it does not already? Initial recommendation: only add this if missing and cheap; do not expand server scope if approval responsiveness is solved by immediate pending UI.
