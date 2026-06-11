# Production Slow Actions and Client Responsiveness Plan V1

This plan identifies user-visible actions that can still feel slow in production and sets a starting point for making them feel instantaneous while preserving canonical server-side integrity.

## Problem Summary

The current production write path is intentionally conservative:

1. accept a POST
2. validate request data and viewer identity
3. write one or more canonical files
4. commit the canonical files to git
5. update or rebuild the SQLite read model
6. invalidate static artifacts
7. return a response or redirect

Several actions already expose `Server-Timing` details, and previous incremental read-model work reduced the worst full-rebuild cases. Users are still reporting slow interactions, so the next step is to inventory every action that can block on disk, git, SQLite, static invalidation, identity generation, OpenPGP inspection, or external model calls.

The product goal is not only to reduce server latency. The interface should optimistically perform any action that can be predicted locally, then reconcile with the server response. Canonical writes remain authoritative.

## Slow-Capable Action Inventory

### Compose Thread

- Entry points:
  - `POST /compose/thread`
  - `POST /api/create_thread`
- Server path:
  - `Application::handleComposeThreadSubmit()`
  - `Application::handleCreateThread()`
  - `LocalWriteService::createThread()`
- Why it can be slow:
  - global write lock
  - canonical post file write
  - optional thread-label record write when body contains labels
  - git add, commit, and rev-parse
  - read-model incremental update or full rebuild fallback
  - board and thread artifact invalidation
  - redirect waits for all write work to complete
- Client-side opportunity:
  - validate and normalize compose fields before submit
  - create an optimistic thread shell immediately with temporary id, subject, body, board tags, author hint, and pending state
  - navigate immediately to a pending thread view or insert the thread at the top of the board
  - reconcile temporary id with returned `thread_id` and `commit_sha`
- Integrity requirement:
  - server still assigns canonical ids and timestamps
  - optimistic thread must be marked failed or retried if validation, identity, git, or read-model work fails

### Compose Reply

- Entry points:
  - `POST /compose/reply`
  - `POST /api/create_reply`
- Server path:
  - `Application::handleComposeReplySubmit()`
  - `Application::handleCreateReply()`
  - `LocalWriteService::createReply()`
- Why it can be slow:
  - global write lock
  - parent and thread canonical reads
  - canonical reply file write
  - optional thread-label record write from reply body labels
  - git add, commit, and rev-parse
  - read-model incremental update or full rebuild fallback
  - reply, thread, board, and post artifact invalidation
  - redirect waits for all write work to complete
- Client-side opportunity:
  - append a pending reply to the current thread immediately
  - preserve form draft until server confirms success
  - update reply count and last-activity affordances locally
  - replace temporary id and pending status with returned `post_id`
- Integrity requirement:
  - server must verify parent belongs to target thread
  - failed writes must restore the draft and remove or mark the pending reply

### Apply Thread Tag

- Entry point:
  - `POST /api/apply_thread_tag`
- Server path:
  - `Application::handleApplyThreadTag()`
  - `LocalWriteService::applyThreadTag()`
  - `public/assets/thread_reactions.js`
- Why it can be slow:
  - browser identity readiness can run before the request
  - global write lock
  - duplicate tag detection scans existing records
  - canonical thread-label record write
  - git add, commit, and rev-parse
  - read-model incremental update or full rebuild fallback
  - board and thread artifact invalidation
- Client-side opportunity:
  - immediately set the button pressed state and feedback text
  - increment or otherwise adjust visible thread score optimistically when the viewer is known approved
  - store a pending reaction marker keyed by `thread_id`, `tag`, and viewer identity
  - reconcile `score_total`, `wrote_record`, and `viewer_is_approved` from the server response
- Integrity requirement:
  - server remains responsible for deduping one identity/tag/thread tuple
  - optimistic score must roll back when server returns an error or `viewer_is_approved=no`

### Apply Post Tag

- Entry point:
  - `POST /api/apply_post_tag`
- Server path:
  - `Application::handleApplyPostTag()`
  - `LocalWriteService::applyPostTag()`
  - `public/assets/thread_reactions.js`
- Why it can be slow:
  - browser identity readiness can run before the request
  - global write lock
  - target post canonical read
  - duplicate reaction detection scans existing records
  - canonical post-reaction record write
  - git add, commit, and rev-parse
  - read-model incremental update or full rebuild fallback
  - reply/thread artifact invalidation
- Client-side opportunity:
  - immediately set the button pressed state and show applied feedback
  - optimistically hide a post when the selected tag is expected to hide it, while allowing undo of the local pending state before confirmation
  - reconcile `post_score_total`, `approved_flag_count`, `is_hidden`, `wrote_record`, and viewer approval from the server response
- Integrity requirement:
  - server remains authoritative for approval-sensitive hiding and score totals
  - failed writes must restore visibility and button state

### Link Identity

- Entry points:
  - `POST /account/key`
  - `POST /api/link_identity`
- Server path:
  - `Application::handleAccountKeySubmit()`
  - `Application::handleLinkIdentity()`
  - `LocalWriteService::linkIdentity()`
- Why it can be slow:
  - OpenPGP public key inspection
  - optional bootstrap post creation
  - public key and identity canonical file writes
  - git add, commit, and rev-parse
  - identity read-model incremental update or full rebuild fallback
  - profile, bootstrap post, and thread artifact invalidation
- Client-side opportunity:
  - parse and inspect the public key in the browser before submit where feasible
  - show a pending profile state immediately after submit
  - set identity hint locally after server confirmation
- Integrity requirement:
  - server must remain authoritative for fingerprint uniqueness, username extraction, bootstrap anchoring, and canonical key storage
  - never treat a client-parsed key as linked until the server returns `identity_id` and `commit_sha`

### Approve User

- Entry points:
  - `POST /profiles/{slug}/approve`
  - `POST /api/approve_user`
- Server path:
  - `Application::handleApproveUserSubmit()`
  - `Application::handleApproveUserApi()`
  - `LocalWriteService::approveUser()`
- Why it can be slow:
  - viewer profile and approval checks
  - target profile lookup
  - bootstrap thread and parent canonical reads
  - canonical approval reply write
  - git add, commit, and rev-parse
  - approval-sensitive read-model update or full rebuild fallback
  - profile, thread, and approval artifact invalidation
- Client-side opportunity:
  - immediately disable the approve button and show pending approval state
  - optimistically move the user out of pending lists only when the current viewer is already known approved
  - reconcile profile approval status after server confirmation
- Integrity requirement:
  - server must enforce approved approver, no self-approval, valid bootstrap parent, and approval-derived score refresh
  - local pending approval must be reversible

### Analyze Post

- Entry point:
  - `POST /api/analyze_post`
- Server path:
  - `Application::handleAnalyzePost()`
  - `PostAnalysisService::analyze()`
  - `DedalusPostAnalyzer::analyze()` when configured
- Why it can be slow:
  - fetches post and builds analysis context
  - may call an external model provider
  - may check or update analysis storage
  - may also compute agent reply eligibility and summary
- Client-side opportunity:
  - cache completed analysis responses by post id and content hash
  - render stale cached analysis immediately with a refreshing state
  - split agent reply status fetch from analysis when it adds avoidable latency
- Integrity requirement:
  - server remains authoritative for moderation visibility and viewer permission
  - client must not expose privileged analysis details unless server has already returned them for the current viewer

### Generate Agent Reply

- Entry point:
  - `POST /api/generate_agent_reply`
- Server path:
  - `Application::handleGenerateAgentReply()`
  - `Application::agentReplyResultForPost()`
  - `DedalusAgentReplyGenerator`
  - `LocalWriteService::createReply()`
  - `AgentIdentityService::ensureReplyAgentIdentity()`
- Why it can be slow:
  - requires complete post analysis
  - may call an external model provider or reuse stored generated text
  - may create or ensure an agent identity
  - posts a canonical reply through the normal slow reply write path
  - stores generation status before and after posting
- Client-side opportunity:
  - treat generation as a background job from the user's perspective
  - show pending/in-progress status immediately
  - poll or refresh only the affected post/thread region
  - reuse the same optimistic pending reply rendering as human replies when posting begins
- Integrity requirement:
  - server must gate recommendation, generation, idempotency, and canonical posting
  - client must never synthesize final agent reply text unless it came from server-authorized generation output

### Set Identity Hint

- Entry point:
  - `GET|POST /api/set_identity_hint`
- Server path:
  - `Application::handleSetIdentityHint()`
- Why it can be slow:
  - this should be fast, but it still requires a request round trip and cookie write
- Client-side opportunity:
  - update local identity-dependent controls immediately before server confirmation
  - reconcile with cookie response on completion
- Integrity requirement:
  - this is only a hint, not authorization; write endpoints must continue resolving and validating viewer identity server-side

## Cross-Cutting Server Bottlenecks to Measure

Every write-like endpoint should expose or log these timings consistently:

- `lock_wait`: time spent waiting to acquire the global write lock
- `request_data`: parsing form or API input
- `identity_ready`: viewer profile, identity hint, or key inspection work
- `canonical_read`: reads needed to validate parent/thread/profile state
- `write_file`: canonical file writes
- `git_add`
- `git_commit`
- `git_rev_parse`
- `read_model_incremental_*`
- `read_model_*_fallback`
- `artifact_invalidate`
- `external_provider`: model-provider calls for analysis and agent replies
- `response_render` or `redirect`
- `total`

Current timing already covers several write phases. Missing lock-wait and identity/client-preparation timing are important because user reports can include time before the POST starts.

## Client-Side Duplication Strategy

The client should duplicate deterministic, user-visible effects, not canonical authority.

Use this contract for each action:

1. Build a local operation object with a temporary id, action type, viewer identity hint, target ids, form data, and created-at client timestamp.
2. Apply a local optimistic reducer to update the visible UI immediately.
3. Send the server request with a client operation id.
4. On success, replace temporary ids and pending markers with canonical ids, server totals, and `commit_sha`.
5. On duplicate/no-op success, keep the final UI state but remove pending markers.
6. On failure, roll back or mark the local operation failed with a retry path.
7. On reload, persist pending operations in session storage and reconcile against canonical server state.

This keeps the interface fast while preserving server-side validation, deduplication, authorization, git history, and read-model correctness.

## Implementation Slices

### Slice 1: Instrument the Full User-Perceived Path

Goal:

- distinguish server latency from browser preparation, network time, redirect/render time, and lock wait

Work:

- add `lock_wait` timing around `ExecutionLock::withExclusiveLock()`
- ensure all write endpoints return `Server-Timing`, including approval APIs
- add client-side performance marks around reaction clicks, compose submits, identity readiness, fetch start, first optimistic paint, response received, and reconciliation
- log slow action samples in a structured browser-safe format during production debugging

Acceptance:

- for each slow-capable action, a production report can identify whether the delay is client identity prep, lock wait, git, read-model work, artifact invalidation, provider calls, redirect, or rendering

### Slice 2: Optimistic Reactions First

Goal:

- make the smallest high-frequency actions feel instantaneous

Work:

- update `thread_reactions.js` to apply button state, feedback, and visible score/post visibility before the fetch completes
- preserve current server response parsing for reconciliation
- add rollback handling for errors
- persist pending reaction operations until confirmation

Acceptance:

- clicking like/flag changes visible state in under one frame after identity is ready
- server response still corrects score totals, duplicate states, approval-sensitive visibility, and failures

### Slice 3: Optimistic Reply Submit

Goal:

- remove the most painful discussion-flow delay

Work:

- submit replies through `fetch` where JavaScript is available
- append a pending reply card immediately
- keep the form draft until server success
- reconcile returned `post_id`, `thread_id`, and `commit_sha`
- fall back to the current POST redirect when JavaScript is unavailable

Acceptance:

- reply content appears in the thread immediately after submit
- canonical response replaces the temporary reply without duplicate cards
- failed replies keep recoverable text and show a retryable error

### Slice 4: Optimistic Thread Creation

Goal:

- make starting a new thread feel immediate without losing canonical redirect behavior

Work:

- submit thread creation through `fetch` when JavaScript is available
- insert a pending board item and optionally navigate to a pending thread route
- reconcile the final `thread_id`, `post_id`, and `commit_sha`
- retain non-JavaScript redirect behavior

Acceptance:

- users see their new thread immediately
- final canonical route replaces the temporary state after success
- failed thread creation preserves the draft

### Slice 5: Identity and Approval Pending States

Goal:

- make slow account and moderation actions visibly progress instead of appearing stuck

Work:

- add pending UI states for identity linking and user approval
- avoid claiming success until the server confirms
- move pending users optimistically only when rollback is clear and visible

Acceptance:

- account and approval actions give immediate state feedback
- all authorization and uniqueness decisions remain server-owned

### Slice 6: Provider-Backed Actions as Background Jobs

Goal:

- stop model-backed analysis and agent reply flows from blocking the interactive path

Work:

- split analysis request, status, and agent-reply generation where currently coupled
- render cached or pending states immediately
- poll or refresh only affected fragments
- use the same pending reply renderer for agent reply posting

Acceptance:

- analysis and agent reply controls move immediately to pending/in-progress states
- users are not blocked waiting for external provider latency

## Data Integrity Rules

- The server is always authoritative for canonical ids, timestamps, authorization, duplicate detection, approval-sensitive scores, hidden state, and `commit_sha`.
- Client operations are speculative until the server confirms.
- Every optimistic operation must have rollback or failed-pending behavior.
- The non-JavaScript POST and redirect path must keep working.
- Client-side validation may prevent obvious invalid submits, but server validation must not be weakened.
- Reconciliation must tolerate duplicate submissions, back/forward navigation, refreshes, and multiple tabs.

## Initial Priority

Start with:

1. add missing end-to-end timing for lock wait and client-perceived action duration
2. ship optimistic thread/post reactions
3. ship optimistic replies
4. use measured production data to decide whether thread creation, approval, identity linking, or provider-backed actions are next

This order targets frequent small interactions first, then the main discussion workflow, while collecting enough data to avoid guessing about the remaining slow paths.
