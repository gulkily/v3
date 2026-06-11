# Production Slow Actions Slice 3 Optimistic Reply Submit Plan V1

This plan breaks out Slice 3 from `production_slow_actions_client_responsiveness_plan_v1.md`. The goal is to make reply submission feel immediate while preserving canonical server-side validation, identity handling, git history, read-model updates, artifact invalidation, and the current non-JavaScript form fallback.

## Goal

When JavaScript is available, submitting a reply should immediately append a pending reply card to the visible thread after browser identity is ready and client-side validation passes.

The server remains authoritative. The client only renders a speculative pending reply, then reconciles against `/api/create_reply`.

## Non-Goals

- Do not change canonical post record format.
- Do not change `LocalWriteService::createReply()` semantics.
- Do not remove or weaken `/compose/reply` POST redirect behavior.
- Do not optimistically post anonymous replies in this slice unless the same rollback and reconciliation path is explicitly implemented.
- Do not implement optimistic thread creation in this slice.
- Do not persist pending replies across reloads in the first implementation.
- Do not introduce rich-text rendering or client-side markdown parsing.

## Current State

- Reply forms are rendered by `templates/partials/reply_form.php`.
- Full reply pages use `templates/pages/compose_reply.php`.
- Thread pages include an inline reply composer in `templates/pages/thread.php`.
- `public/assets/browser_signing.js` currently prepares browser identity, writes `author_identity_id`, updates status text, and then calls `form.submit()`.
- `public/assets/inline_reply_form.js` only expands and scrolls the inline composer; it does not submit.
- `/api/create_reply` already accepts POST input and returns plain text:
  - `status=ok`
  - `post_id=...`
  - `thread_id=...`
  - `commit_sha=...`
- `/compose/reply` POST remains the non-JavaScript fallback and redirects to:
  - `/threads/{thread_id}?created_post_id={post_id}&__v={commit_sha}#post-{post_id}`
- Compose draft persistence and clearing already exist in `browser_signing.js`.

## Desired User Experience

For the inline thread composer:

- pressing `Post reply` prepares identity as it does today
- once identity is ready, the typed reply appears immediately in the thread as a pending card
- the composer remains disabled or visibly pending while the request is in flight
- the draft is not destroyed until the server confirms success
- on success, the pending card becomes canonical or is replaced by canonical state using `post_id`, `thread_id`, and `commit_sha`
- on failure, the pending card is removed or marked failed and the draft remains recoverable in the composer

For the standalone compose reply page:

- JavaScript can submit through the same API path
- showing an optimistic card is optional because the thread context is not currently rendered on that page
- the first implementation may keep page behavior close to today after identity readiness, or redirect to the canonical thread after success
- the non-JavaScript POST path must continue to work unchanged

## Integrity Rules

- The server remains authoritative for:
  - final `post_id`
  - final `thread_id`
  - timestamp
  - parent/thread relationship
  - author identity validity
  - body validation and normalization policy
  - extracted thread labels
  - git commit success
  - read-model and artifact refresh state
- The client may only render the pending card from already-visible local form data.
- The pending card must be visually distinct from canonical replies.
- Failed replies must keep the typed body available for editing and retry.
- A duplicate or delayed server response must not create duplicate visible cards.
- The current form submit fallback must stay intact when JavaScript is disabled or the optimistic handler opts out.

## Implementation Slices

### Slice 3A: Reply Submit Transport Extraction

Status:

- implemented
- added browser-side helpers for reply form detection, field collection, `/api/create_reply` submission, plain-text response parsing, and `Server-Timing` parsing
- exported the helpers through the existing `window.__forumComposeNormalization` test surface
- left the visible form submit behavior unchanged; optimistic rendering begins in Slice 3B/3C
- anonymous submit behavior remains unchanged

Goal:

- create a testable JavaScript path that can submit reply forms to `/api/create_reply` without changing visible behavior yet

Work:

- add small helpers in `browser_signing.js` or a new focused module for:
  - detecting reply compose forms
  - collecting `thread_id`, `parent_id`, `author_identity_id`, `board_tags`, and `body`
  - posting URL-encoded data to `/api/create_reply`
  - parsing the existing plain-text API response
  - parsing `Server-Timing` response headers consistently with Slice 1 reaction helpers
- keep the existing `form.submit()` path as the default until optimistic rendering is ready
- do not change anonymous submit behavior in this slice

Acceptance:

- helper tests prove request payloads match the current form fields
- helper tests prove successful responses parse `post_id`, `thread_id`, and `commit_sha`
- helper tests prove error responses preserve the server error message
- existing compose and reply tests still pass

### Slice 3B: Pending Reply Card Renderer

Status:

- implemented
- added a pending reply card renderer that creates `card post-card pending-reply-card` markup with `data-pending-reply-id` and `data-parent-id`
- pending reply bodies are assigned with `textContent`, so authored text is not interpreted as HTML
- pending reply body line breaks are preserved with CSS on `.pending-reply-card .body`
- pending cards insert immediately before the inline composer when a composer parent is available

Goal:

- render a local pending reply card that matches existing post-card structure closely enough to avoid layout churn

Work:

- add a client-side renderer for a pending reply card:
  - temporary id such as `pending-reply:{sequence}`
  - `data-pending-reply-id`
  - `data-parent-id`
  - body rendered as plain text with line breaks preserved
  - author display based on the local identity hint when available, otherwise a conservative pending label
  - pending status text such as `Posting...`
- insert pending replies in the right location:
  - for the first implementation, append after the existing visible replies and before the inline composer
  - avoid threading/reordering complexity until server reconciliation exists
- add minimal CSS for pending state that is visible but does not imply canonical success

Acceptance:

- submitting through a test harness can append exactly one pending card
- pending body text is escaped and cannot inject HTML
- line breaks in the typed body remain readable
- pending card placement does not move the composer above the reply

### Slice 3C: Optimistic Inline Reply Flow

Status:

- implemented
- inline reply composer submits through `/api/create_reply` after the existing browser identity readiness path succeeds
- a pending reply card is inserted before the API response resolves
- successful API responses clear the confirmed draft locally and navigate to the canonical thread URL with `created_post_id`, `__v`, and `#post-{post_id}`
- failed API responses remove the pending card, restore submit controls, preserve the typed body, and show the server error
- standalone compose reply pages and anonymous submits still use the existing form submit path

Goal:

- wire the inline thread composer to apply the pending reply before the network response resolves

Work:

- after existing identity readiness succeeds:
  - write `author_identity_id` to the form as today
  - capture the current form state
  - render a pending reply card
  - disable submit controls while the request is in flight
  - set compose status to pending copy
  - submit to `/api/create_reply` through `fetch`
- on success:
  - reconcile the pending card with the canonical response
  - set the pending card anchor/id to `post-{post_id}` or navigate to the canonical URL if full replacement is safer
  - clear the draft using the existing successful-submit clear path
  - leave the typed form empty only after server confirmation
- on failure:
  - remove or mark the pending card failed
  - restore submit controls
  - keep the body text in the composer
  - show server error feedback
- preserve the current `form.submit()` path when the optimistic handler cannot run

Acceptance:

- test proves a pending reply appears before the fetch promise resolves
- test proves the draft remains available while the request is pending
- test proves server success reconciles `post_id`, `thread_id`, and `commit_sha`
- test proves server failure leaves the draft recoverable and removes or marks the pending card failed

### Slice 3D: Duplicate Submit Guard and Retry

Status:

- implemented
- repeated submit events now always call `preventDefault()` before checking in-flight state
- optimistic reply submits store a page-local pending operation key on the form and in an in-memory set
- duplicate submits while the request is pending do not issue additional `/api/create_reply` requests
- pending operation state is cleared after success or failure so failed drafts can be retried

Goal:

- prevent accidental double-posts from repeated clicks while still allowing retry after failure

Work:

- add a page-local pending reply operation map keyed by:
  - `reply:${threadId}:${parentId}:${clientOperationId}`
- generate a `clientOperationId` per submit attempt
- ignore repeated submit events for the same in-flight form
- clear the pending key on success or failure
- on failure, allow the same draft to be retried with a new operation id

Acceptance:

- repeated fast submit produces one fetch
- retry after failure produces a second fetch
- successful retry does not leave duplicate pending cards

### Slice 3E: Timing and Compatibility

Status:

- implemented
- optimistic inline replies mark identity start/ready, first optimistic feedback, fetch start, response received, reconcile complete, and action complete
- compose timing debug summaries now include network duration and parsed `Server-Timing` when present
- debug payloads remain metadata-only and do not include reply body, request body, public/private key material, or authored content
- anonymous reply submits and standalone compose reply pages keep their existing form-submit behavior

Goal:

- keep Slice 1 timing useful for reply submits after optimistic rendering lands

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
- include parsed `Server-Timing` without request body, reply body, key material, or authored content
- ensure anonymous reply submit keeps the current safe behavior unless explicitly moved to the optimistic API path

Acceptance:

- debug timing stays opt-in
- debug payloads do not include reply text or key material
- tests cover optimistic success, rollback/failure, duplicate submit guard, and fallback behavior

## Reconciliation Options

Preferred first implementation:

- keep the pending card visible
- on success, update its id/anchor to `post-{post_id}`
- update pending status to posted
- optionally update the URL hash to `#post-{post_id}` with `history.replaceState`

Safer fallback if local card parity becomes too complex:

- render the pending card immediately
- on success, navigate to `/threads/{thread_id}?created_post_id={post_id}&__v={commit_sha}#post-{post_id}`
- this still gives immediate feedback during the slow path while relying on server-rendered canonical HTML after success

Initial recommendation:

- start with the safer fallback after success if it materially reduces implementation risk, then follow with in-place canonical reconciliation once the pending renderer is stable.

## Test Plan

Use the existing Node-backed browser script tests in `BrowserSigningNormalizationTest`.

Add or update tests for:

- reply API response parsing
- pending reply card appears before fetch resolves
- pending reply body is escaped
- line breaks remain readable
- server success reconciles or navigates using `post_id`, `thread_id`, and `commit_sha`
- server failure preserves draft and shows error
- duplicate fast submit produces one fetch
- retry after failure works
- non-JavaScript form markup and `/compose/reply` POST fallback remain unchanged

Run:

- `node --check public/assets/browser_signing.js`
- `node --check public/assets/inline_reply_form.js`
- `php tests/run.php`

## Recommended Commit Breakdown

1. Extract reply submit API helpers with no visible behavior change.
2. Add pending reply card renderer and focused escaping tests.
3. Wire optimistic inline reply submit success path.
4. Add failure rollback and duplicate-submit guard.
5. Add timing/debug compatibility and final plan status updates.

## Open Questions

- Should the first success path reconcile in place or navigate to the canonical thread URL? Initial recommendation: navigate after success if in-place parity is risky.
- Should standalone `/compose/reply` use optimistic fetch immediately? Initial recommendation: support the API helper but prioritize inline thread replies first.
- Should anonymous replies use the optimistic path? Initial recommendation: no for this slice; keep current submit behavior until identity and draft semantics are separately planned.
- Should pending replies survive reloads? Initial recommendation: no; session-persisted pending operations belong after the basic optimistic flow is reliable.
- Should reply count update optimistically? Initial recommendation: only if the visible count is easy to update and rollback; otherwise leave it to canonical reload/reconciliation.

## Completion Criteria

This slice is complete when inline reply submissions show a pending reply immediately after identity readiness, successful server responses reconcile without duplicate cards, failed submissions preserve recoverable draft text, duplicate submits are guarded, and the existing non-JavaScript reply flow remains unchanged.
