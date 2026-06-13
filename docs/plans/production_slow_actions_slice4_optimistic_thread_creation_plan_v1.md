# Production Slow Actions Slice 4 Optimistic Thread Creation Plan V1

This plan breaks out Slice 4 from `production_slow_actions_client_responsiveness_plan_v1.md`. The goal is to make creating a new thread feel immediate while preserving canonical server-side validation, identity handling, post/thread id assignment, git history, read-model updates, artifact invalidation, and the current non-JavaScript form fallback.

## Goal

When JavaScript is available, submitting a new thread should immediately show a pending thread state after browser identity is ready and client-side validation passes.

The server remains authoritative. The client may render a speculative pending thread shell and then reconcile against `/api/create_thread`.

## Non-Goals

- Do not change canonical post record format.
- Do not change `LocalWriteService::createThread()` semantics.
- Do not remove or weaken `/compose/thread` POST redirect behavior.
- Do not optimistically create anonymous threads in this slice unless the same rollback and reconciliation path is explicitly implemented.
- Do not implement a durable pending thread route in the first pass.
- Do not persist pending thread operations across reloads.
- Do not update tag pages, board sort order, RSS, activity, or static artifacts client-side.

## Current State

- The compose thread page is rendered by `templates/pages/compose_thread.php`.
- The form has `data-compose-form` and `data-compose-kind="thread"`.
- `public/assets/browser_signing.js` currently prepares browser identity, writes `author_identity_id`, updates status text, and then calls `form.submit()`.
- `/api/create_thread` already accepts POST input and returns plain text:
  - `status=ok`
  - `post_id=...`
  - `thread_id=...`
  - `commit_sha=...`
- `/compose/thread` POST remains the non-JavaScript fallback and redirects to:
  - `/threads/{thread_id}?created_post_id={post_id}&__v={commit_sha}`
- Slice 3 added optimistic reply helpers for API transport, draft clearing before network wait, rollback-on-failure, and timing/debug summaries.

## Desired User Experience

For `/compose/thread`:

- pressing `Create thread` prepares identity as it does today
- once identity is ready, the page immediately shows a pending thread shell using the submitted subject, body, and board tags
- the form controls remain disabled or visibly pending while the request is in flight
- the local draft is cleared before waiting on the slow network/server response, with rollback if the API fails
- on success, the browser navigates to the canonical thread URL returned by the server
- on failure, the pending shell is removed or marked failed, the draft is restored, the typed fields remain recoverable, and the server error is shown

For board pages:

- the first implementation does not need to insert a pending board item because thread creation starts from `/compose/thread`
- if later product direction wants instant board feedback, that should be a follow-up slice using the same pending thread renderer

## Integrity Rules

- The server remains authoritative for:
  - final `post_id`
  - final `thread_id`
  - timestamp
  - subject/body validation and normalization policy
  - author identity validity
  - board tags and extracted thread labels
  - git commit success
  - read-model and artifact refresh state
- The client may only render pending UI from already-visible local form data.
- Pending thread UI must be visually distinct from a canonical thread.
- Failed thread creation must keep the typed subject/body/board tags available for editing and retry.
- A duplicate or delayed server response must not create duplicate visible pending shells.
- The current form submit fallback must stay intact when JavaScript is disabled or the optimistic handler opts out.

## Implementation Slices

### Slice 4A: Thread Submit Transport Helpers

Goal:

- add testable browser helpers for `/api/create_thread` without changing visible behavior yet

Work:

- add helpers in `browser_signing.js` or a small shared compose-submit section for:
  - detecting thread compose forms
  - collecting `author_identity_id`, `board_tags`, `subject`, and `body`
  - posting URL-encoded data to `/api/create_thread`
  - parsing the existing plain-text API response
  - sharing response parsing and `Server-Timing` parsing with reply submit helpers where simple
- keep the existing `form.submit()` path as the default until optimistic rendering is wired
- do not change anonymous submit behavior in this slice

Acceptance:

- helper tests prove request payloads match current form fields
- helper tests prove successful responses parse `post_id`, `thread_id`, and `commit_sha`
- helper tests prove error responses preserve the server error message
- existing compose, reply, and thread tests still pass

Status:

- Implemented in commit pending: added `isThreadComposeForm`, `collectThreadSubmitFields`, `parseCreateThreadResponse`, and `submitThreadFormToApi`.
- Added Node-backed browser helper tests for field collection, response parsing, URL-encoded payloads, and parsed `Server-Timing`.
- No visible submit behavior changed in this slice.

### Slice 4B: Pending Thread Shell Renderer

Goal:

- render a local pending thread shell on the compose page without pretending it is canonical

Work:

- add a client-side renderer for a pending thread shell:
  - temporary id such as `pending-thread:{sequence}`
  - `data-pending-thread-id`
  - escaped subject text
  - escaped body text with line breaks preserved
  - visible board tags text
  - pending status text such as `Creating thread...`
- insert the shell near the compose form:
  - initial recommendation: immediately before the compose form card or directly after it, whichever causes less layout jump
  - keep the form visible for failure recovery
- add minimal CSS for pending state that is visible but does not imply canonical success

Acceptance:

- test proves pending shell renders exactly one pending thread
- test proves subject/body/tag text is escaped and cannot inject HTML
- test proves line breaks in body remain readable
- test proves insertion does not remove the form

Status:

- Implemented in commit pending: added `createPendingThreadShell` and `insertPendingThreadShell`.
- Added dashed pending-shell styling and preserved body line breaks with `white-space: pre-wrap`.
- Added a Node-backed renderer test covering escaped subject/body/tag text, placement before the compose card, and form preservation.

### Slice 4C: Optimistic Thread Submit Flow

Goal:

- wire the compose thread form to show the pending shell before `/api/create_thread` resolves

Work:

- after existing identity readiness succeeds:
  - write `author_identity_id` to the form as today
  - capture the current form state and draft
  - render a pending thread shell
  - clear the local draft and set the same recently-cleared draft marker before network wait
  - disable submit controls while the request is in flight
  - set compose status to pending copy
  - submit to `/api/create_thread` through `fetch`
- on success:
  - keep the draft cleared
  - navigate to `/threads/{thread_id}?created_post_id={post_id}&__v={commit_sha}`
- on failure:
  - remove or mark the pending shell failed
  - restore the saved draft and clear the recently-cleared marker
  - restore submit controls
  - keep field values in the form
  - show server error feedback
- preserve the current `form.submit()` path when the optimistic handler cannot run

Acceptance:

- test proves a pending thread shell appears before the fetch promise resolves
- test proves the draft is cleared and marked before the fetch promise resolves
- test proves server success navigates using `post_id`, `thread_id`, and `commit_sha`
- test proves server failure restores the draft and leaves fields recoverable

Status:

- Implemented in commit pending: thread compose submits now render a pending shell after browser identity is ready and post to `/api/create_thread`.
- Draft clearing now happens before the thread create fetch resolves, with rollback on API failure or fetch failure.
- Successful creates navigate to the canonical server thread URL returned by the API response.
- Added success and failure tests covering pending paint, draft clearing, canonical navigation, shell removal, and draft restoration.

### Slice 4D: Duplicate Submit Guard and Retry

Goal:

- prevent accidental double thread creation from repeated clicks while still allowing retry after failure

Work:

- add or reuse a page-local pending compose operation guard keyed by:
  - `thread:${clientOperationId}`
- repeated submit events must call `preventDefault()` before in-flight checks
- duplicate submits while the request is pending must not issue additional `/api/create_thread` requests
- clear pending operation state after success or failure
- failed drafts can be retried with a new operation id

Acceptance:

- repeated fast submit produces one create-thread fetch
- retry after failure produces a second create-thread fetch
- successful retry does not leave duplicate pending shells

Status:

- Implemented in commit pending: added page-local thread pending operation tracking with cleanup on success and failure.
- The existing submit handler continues to call `preventDefault()` before in-flight checks, so duplicate submits do not fall through to native form submit.
- Added a duplicate/retry test covering one pending create request, rollback after failure, and successful retry without duplicate pending shells.

### Slice 4E: Timing and Compatibility

Goal:

- keep Slice 1 timing useful for thread creation after optimistic rendering lands

Work:

- add or reuse browser performance marks for:
  - action start
  - identity start
  - identity ready
  - first optimistic paint
  - fetch start
  - response received
  - reconcile complete
  - action complete
- emit opt-in debug summaries using the existing `?debug_timing=1` or `localStorage.forum_debug_timing=1` behavior
- include parsed `Server-Timing` without subject, body, request body, key material, or authored content
- ensure anonymous thread submit keeps the current safe behavior unless explicitly moved to the optimistic API path

Acceptance:

- debug timing stays opt-in
- debug payloads do not include subject/body text or key material
- tests cover optimistic success, rollback/failure, duplicate submit guard, and fallback behavior

## Reconciliation Strategy

Initial recommendation:

- render a pending shell immediately on `/compose/thread`
- on success, navigate to the canonical thread URL returned by `/api/create_thread`
- rely on the server-rendered thread page for final content, labels, timestamps, author label, reactions, and static/version state

Rationale:

- thread pages have more canonical state than a reply card, including root post metadata, labels, score, reaction state, and artifact versioning
- immediate pending UI solves the user-perceived stall without requiring a client clone of the full thread page
- in-place canonical reconciliation can be a later refinement if users need to stay on the compose page

## Test Plan

Use the existing Node-backed browser script tests in `BrowserSigningNormalizationTest`.

Add or update tests for:

- thread API response parsing
- thread submit field collection
- pending thread shell appears before fetch resolves
- pending subject/body/tag text is escaped
- body line breaks remain readable
- draft is cleared and recently-cleared marker is set before fetch resolves
- server success navigates using `post_id`, `thread_id`, and `commit_sha`
- server failure restores draft and shows error
- duplicate fast submit produces one fetch
- retry after failure works
- non-JavaScript form markup and `/compose/thread` POST fallback remain unchanged

Run:

- `node --check public/assets/browser_signing.js`
- `php tests/run.php`

## Recommended Commit Breakdown

1. Extract thread submit API helpers with no visible behavior change.
2. Add pending thread shell renderer and focused escaping tests.
3. Wire optimistic thread submit success path with early draft clear.
4. Add failure rollback and duplicate-submit guard.
5. Add timing/debug compatibility and final plan status updates.

## Open Questions

- Should the pending shell appear above or below the compose form? Initial recommendation: above the form so the user sees the created thread immediately while the form remains available below for rollback.
- Should board pages get a pending item when a user starts from `/compose/thread`? Initial recommendation: no in this slice; canonical navigation after success is simpler and safer.
- Should anonymous thread creation use the optimistic API path? Initial recommendation: no for this slice; keep current submit behavior.
- Should pending thread operations survive reloads? Initial recommendation: no; the early draft clear marker handles the successful slow-response reload case, while durable pending operations need a separate reconciliation design.
- Should optimistic thread labels be rendered from hashtags in the body? Initial recommendation: no; label extraction stays server-owned in this slice.

## Completion Criteria

This slice is complete when thread submissions show a pending thread shell immediately after identity readiness, successful server responses navigate to the canonical thread without stale draft restoration, failed submissions preserve recoverable draft text, duplicate submits are guarded, and the existing non-JavaScript thread creation flow remains unchanged.
