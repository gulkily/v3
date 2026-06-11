# Production Slow Actions Slice 2 Optimistic Reactions Plan V1

This plan breaks out Slice 2 from `production_slow_actions_client_responsiveness_plan_v1.md`. The goal is to make thread and post reaction clicks feel immediate while preserving server-side canonical writes, authorization, deduplication, score calculation, and hidden-state decisions.

## Goal

Make these actions update visible UI immediately after browser identity is ready:

- `data-action="apply-thread-tag"`
- `data-action="apply-post-tag"`

The server remains authoritative. The client only applies speculative UI state, then reconciles with `/api/apply_thread_tag` or `/api/apply_post_tag`.

## Non-Goals

- Do not change canonical record formats.
- Do not change `LocalWriteService::applyThreadTag()` or `LocalWriteService::applyPostTag()` semantics.
- Do not add undo records or delete reaction records.
- Do not make anonymous reactions appear successful.
- Do not optimistically update server-owned approval-sensitive values as final truth.
- Do not implement optimistic compose/reply/thread creation in this slice.

## Current State

- `public/assets/thread_reactions.js` handles both thread and post reaction clicks.
- The code already disables the clicked button before network work starts.
- The code waits for `ensureReactionIdentity()` before sending the request.
- The code currently waits for the server response before changing final visible state:
  - button label
  - `aria-pressed`
  - thread score text
  - post hidden state
  - final feedback text
- Slice 1 instrumentation added passive action marks, parsed `Server-Timing`, and opt-in `[forum timing]` debug output.

## Desired User Experience

After identity is ready:

- the clicked button should immediately look applied
- feedback should immediately indicate a pending save, not leave the user waiting
- thread likes should update visible score optimistically when the client can safely infer the visible delta
- post flags may visually mark pending, but final hiding should wait for server confirmation unless the rollback UX is explicit
- duplicate or no-op server responses should settle into the same final applied UI
- failures should restore the prior UI and show the existing error feedback

## Integrity Rules

- The server remains authoritative for:
  - whether the viewer is approved
  - whether the reaction is a duplicate
  - thread score totals
  - post score totals
  - approved flag counts
  - hidden state
  - canonical commit success
- The client may only predict UI. It must reconcile server fields:
  - `score_total`
  - `post_score_total`
  - `approved_flag_count`
  - `is_hidden`
  - `viewer_is_approved`
  - `wrote_record`
  - `commit_sha`
- A failed request must restore the previous button text, disabled state, `aria-pressed`, thread score text, and post visibility.
- Pending styling must not imply canonical success if the request is still in flight.

## Implementation Slices

### Slice 2A: Extract Reaction UI State Helpers

Status:

- implemented
- added explicit helpers to capture, restore, and confirm thread/post reaction state
- rollback now restores button state, thread score text, and post visibility from captured state
- this slice is an internal structure change only; optimistic UI changes begin in Slice 2B

Goal:

- make optimistic apply and rollback explicit and testable

Work:

- add helper functions in `thread_reactions.js` for:
  - capturing previous button/root/score/feedback state
  - applying pending button state
  - applying final server-confirmed state
  - rolling back on failure
- keep behavior equivalent at first except for internal structure
- avoid duplicating thread and post reaction code where simple shared helpers fit

Candidate helpers:

- `captureButtonState(button)`
- `restoreButtonState(button, state)`
- `setPendingReactionButton(button, appliedLabel)`
- `setConfirmedReactionButton(button, appliedLabel)`
- `captureThreadReactionState(root, button, scoreNode, feedbackNode)`
- `capturePostReactionState(root, button, feedbackNode)`

Acceptance:

- existing reaction tests still pass before optimistic behavior changes
- rollback helper can restore button label, `aria-pressed`, disabled state, score text, and root hidden state

### Slice 2B: Optimistic Thread Tag UI

Status:

- implemented
- thread tag buttons switch to the applied label and `aria-pressed="true"` immediately after identity readiness
- `tag=like` increments a parseable visible thread score optimistically when the button was not already pressed
- server responses still reconcile the authoritative `score_total`, `wrote_record`, and final feedback
- server failures restore the captured button and score state

Goal:

- make thread tag clicks visibly apply immediately after identity readiness

Work:

- after `ensureReactionIdentity()` succeeds, apply pending thread state before fetch:
  - set button text to applied label
  - set `aria-pressed="true"`
  - keep button disabled while request is in flight
  - set feedback to a pending message such as `Saving tag...`
- optimistically adjust score only when a safe local assumption exists:
  - initially only for `tag=like`
  - only if the button was not already pressed
  - parse current `Score: N` text
  - increment by 1 as a temporary display
- on success:
  - replace optimistic score with server `score_total`
  - keep applied button state
  - show `Liked.` or `Already liked.` using `wrote_record`
- on failure:
  - restore captured score and button state
  - show existing error feedback

Acceptance:

- test proves button text and `aria-pressed` change before the fetch promise resolves
- test proves optimistic score appears before response and final score reconciles to server value
- test proves failed server response rolls back score and button state
- existing duplicate and unapproved-user behavior still reconciles to server response

### Slice 2C: Optimistic Post Tag UI

Status:

- implemented
- post tag buttons switch to the applied label and `aria-pressed="true"` immediately after identity readiness
- visible feedback changes to `Saving tag...` while the request is in flight
- posts are not hidden optimistically; `root.hidden` changes only after the server returns `is_hidden=yes`
- server failures restore the captured button and post visibility state

Goal:

- make post reactions visibly apply immediately after identity readiness without prematurely hiding content

Work:

- after `ensureReactionIdentity()` succeeds, apply pending post state before fetch:
  - set button text to applied label
  - set `aria-pressed="true"`
  - keep button disabled while request is in flight
  - set feedback to pending copy
- do not hide the post before server confirmation in the first implementation
  - hiding depends on server approval-sensitive state
  - premature hide has a high rollback cost and can be added later with an explicit pending-hidden affordance
- on success:
  - keep applied button state
  - hide root only when server returns `is_hidden=yes`
  - show existing final feedback for visible posts
- on failure:
  - restore captured button and visibility state
  - show existing error feedback

Acceptance:

- test proves post reaction button changes before fetch resolves
- test proves server `is_hidden=yes` hides only after response
- test proves failed server response restores button and root visibility

### Slice 2D: Pending Operation Memory

Status:

- implemented
- added page-local pending operation memory keyed as `thread:${threadId}:${tag}` and `post:${postId}:${tag}`
- identical operations are ignored while the first request is in flight, even if triggered through a separate matching button
- pending keys are cleared in `finally` after both success and failure so retries can proceed

Goal:

- prevent repeated clicks or page-local double application while a request is in flight

Work:

- add a small in-memory pending map keyed by:
  - `thread:${threadId}:${tag}`
  - `post:${postId}:${tag}`
- if an identical operation is already pending, ignore additional clicks
- remove the pending key on success or failure
- do not persist to session storage in this slice unless tests reveal reload hazards

Acceptance:

- repeated fast clicks issue one fetch
- pending key is cleared after success
- pending key is cleared after failure and retry works

### Slice 2E: Timing and Debug Compatibility

Status:

- implemented
- `forum_first_feedback` now marks the optimistic pending UI update after identity readiness, instead of the earlier identity-preparation message
- duplicate pending clicks emit opt-in debug summaries with status `ignored_pending`
- existing success and error summaries continue to use `ok` and `error`
- reaction responses continue to parse `Server-Timing` into the opt-in debug payload

Goal:

- keep Slice 1 timing useful after optimistic behavior lands

Work:

- ensure `forum_first_feedback` marks the optimistic visible update, not the later server reconciliation
- keep `forum_fetch_start`, `forum_response_received`, `forum_reconcile_complete`, and `forum_action_complete`
- add debug summary status values:
  - `ok`
  - `error`
  - `ignored_pending`
- continue parsing `Server-Timing` from reaction responses

Acceptance:

- debug timing still prints only when explicitly enabled
- no request body, post body, key material, or authored content appears in debug output
- browser tests cover at least one optimistic success and one rollback

## Test Plan

Use the existing Node-backed `BrowserSigningNormalizationTest` harness.

Add or update tests for:

- thread reaction applies optimistic UI before fetch resolves
- thread reaction final score reconciles to server `score_total`
- thread reaction failure rolls back button and score
- post reaction applies optimistic button state before fetch resolves
- post reaction hides only after `is_hidden=yes` response
- duplicate fast click produces one fetch while pending
- timing marks still include first feedback, fetch, response, reconcile, and action complete

Run:

- `node --check public/assets/thread_reactions.js`
- `php tests/run.php`

## Recommended Commit Breakdown

1. Refactor reaction state helpers with no intended behavior change.
2. Add optimistic thread tag behavior and tests.
3. Add optimistic post tag behavior and tests.
4. Add pending-operation dedupe and timing/debug compatibility tests.
5. Update this plan with final implementation status.

## Open Questions

- Should unapproved users see a different optimistic score path? Initial recommendation: no local score increment unless the client has a reliable approved-viewer signal.
- Should post flags hide optimistically? Initial recommendation: no; wait for `is_hidden=yes` from the server.
- Should pending operations survive reloads? Initial recommendation: no for this slice; reconciliation across reloads belongs with broader optimistic operation storage.

## Completion Criteria

This slice is complete when reaction clicks feel immediate after identity readiness, server responses still reconcile all authoritative values, failures roll back cleanly, and the existing non-JavaScript/server behavior remains unchanged.
