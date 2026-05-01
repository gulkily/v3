# Deadlus Post Response And Content Assessment Outline V1

This document brainstorms a post-write feature for asking the Deadlus Labs API to:

- generate an engaging response to a newly created thread or comment
- assess the new post for unwanted behavior such as trolling, bad-faith argumentation, aggression, harassment, spam, or escalation risk

The key product constraint is that the post creation path must stay fast. The first page after submit should render normally, then trigger a separate backend request after load.

## Goals

- Keep `create_thread` and `create_reply` fast and reliable.
- Analyze newly written posts only after the successful page load path has started.
- Use the backend as the only caller of the Deadlus Labs API, so API keys and prompts never reach the browser.
- Produce one structured result per post with both engagement and moderation fields.
- Make the operation idempotent, so reloads, duplicate browser events, and retries do not create duplicate analysis work.
- Give the UI a clean path to show useful output later without making the initial V1 depend on a polished moderation dashboard.

## Non-Goals For V1

- Do not block post publishing on Deadlus Labs analysis.
- Do not auto-delete, auto-hide, or auto-punish users from the first iteration.
- Do not auto-publish generated replies without a separate explicit product decision.
- Do not expose raw prompts, private API diagnostics, or full moderation reasoning to public pages.
- Do not require a full queue worker if the current deployment model cannot support one yet.

## Current Code Context

The current PHP app has these relevant flows:

- `POST /compose/thread` writes a thread and redirects to `/threads/<thread-id>`.
- `POST /compose/reply` writes a reply and redirects to `/threads/<thread-id>`.
- `POST /api/create_thread` and `POST /api/create_reply` return plain text with `post_id`, `thread_id`, and `commit_sha`.
- Public read pages are rendered from the SQLite read model and static artifacts where possible.
- Existing scripts are deferred from `templates/layout.php`, making a post-load JavaScript trigger consistent with the current frontend shape.

## Recommended Shape

Use a two-step post-write flow:

1. The normal write request creates the post and redirects or returns quickly.
2. The landing page includes enough metadata to identify the newly created post.
3. A small deferred browser script runs after load and calls the backend, for example `POST /api/analyze_post`.
4. The backend validates the post id, checks whether analysis already exists, and either returns the cached result or calls Deadlus Labs.
5. The backend stores the result and returns a compact status payload.

This keeps the expensive AI request out of the initial write and first render while still allowing analysis to start immediately after the user lands on the new content.

## Triggering Strategy

### Form Redirects

For compose form submissions, add a newly created post marker to the redirect target:

- thread creation: `/threads/<thread-id>?created_post_id=<post-id>&__v=<commit-sha>`
- reply creation: `/threads/<thread-id>?created_post_id=<post-id>&__v=<commit-sha>`

The thread page can then render an inert marker such as:

```html
<main data-created-post-id="reply-123">
```

or the post card can carry:

```html
<article data-post-id="reply-123" data-newly-created-post="1">
```

The frontend script should only auto-trigger analysis for the explicitly newly created post, not every post on the thread page.

### API Writes

For clients using `POST /api/create_thread` or `POST /api/create_reply`, return the same `post_id` already present today. API clients can opt into analysis by calling:

```text
POST /api/analyze_post?post_id=<post-id>
```

This keeps API write behavior backward compatible.

### Browser Timing

The browser should trigger the request after the page is usable:

- prefer `requestIdleCallback` when available
- fall back to `setTimeout(..., 0)` or `DOMContentLoaded`
- use `navigator.sendBeacon` only if the endpoint is fire-and-forget
- use `fetch` if the UI needs a returned status or result

V1 should probably use `fetch`, because it is easier to test and gives a clear success or failure state.

## Backend API Contract

Proposed endpoint:

```text
POST /api/analyze_post
```

Inputs:

- `post_id`: required
- optional `force=1`: moderator-only future feature to refresh stale or failed analysis

Response examples:

```text
status=ok
post_id=reply-123
analysis_status=complete
moderation_severity=low
```

or JSON if this feature becomes the first JSON-first internal API:

```json
{
  "status": "ok",
  "post_id": "reply-123",
  "analysis_status": "complete",
  "moderation": {
    "severity": "low",
    "labels": ["good-faith-disagreement"],
    "confidence": 0.74
  },
  "engagement": {
    "suggested_response": "That framing makes sense. One angle worth separating is whether the disagreement is about the goal or the implementation."
  }
}
```

Recommendation: use JSON for this endpoint even if older APIs are plain text. This result is structured enough that plain text will become awkward quickly.

## Deadlus Labs Request Shape

The backend should send Deadlus Labs a bounded context object:

- post id
- post kind: `thread` or `reply`
- thread id
- parent id when available
- subject for threads
- body text
- board tags
- author display label or stable author id if needed
- limited parent context for replies, such as the parent body and root subject

Avoid sending the entire thread in V1 unless response quality clearly requires it. Start with the post plus parent context to control latency, cost, and data exposure.

## Deadlus Labs Output Schema

Ask for a strict structured response:

```json
{
  "engagement": {
    "suggested_response": "string",
    "response_style": "curious|clarifying|supportive|challenging|deescalating",
    "response_should_be_public": true
  },
  "moderation": {
    "severity": "none|low|medium|high|critical",
    "labels": ["trolling", "bad_faith", "aggression"],
    "confidence": 0.0,
    "summary": "short moderator-facing summary",
    "recommended_action": "none|watch|review|hide_pending_review|escalate"
  },
  "quality": {
    "discussion_value": "low|medium|high",
    "good_faith_likelihood": 0.0,
    "needs_human_review": false
  }
}
```

Keep labels stable and machine-readable. Human-facing copy can evolve separately.

## Moderation Taxonomy

Initial labels:

- `trolling`: provocative behavior mainly intended to derail or upset
- `bad_faith`: repeated misrepresentation, refusal to engage with the point, or strategic ambiguity
- `aggression`: hostile tone, insults, intimidation, or contempt
- `harassment`: targeted abuse toward a person or group
- `threat`: credible or implied threat of harm
- `spam`: promotional, repetitive, irrelevant, or automated-looking content
- `low_effort`: content unlikely to sustain useful discussion
- `off_topic`: unrelated to thread or board context
- `escalation_risk`: likely to trigger a hostile back-and-forth

Severity should be separate from labels. A post can be aggressive but low severity, or spam with medium severity.

## Storage Options

### Option A: SQLite-Only Analysis Table

Store analysis in the local read-model database or a sibling operational database.

Pros:

- fastest to implement
- keeps moderation metadata private by default
- avoids committing model judgments into the public canonical repository

Cons:

- analysis is not part of repository replication
- rebuilds need either a retained operational database or re-analysis

Recommended for V1.

### Option B: Canonical Analysis Records

Write analysis records under something like `records/post-analyses/`.

Pros:

- portable with the repository
- auditable as part of the append-only content history

Cons:

- moderation judgments may become public
- generated text and scores can age poorly
- harder to delete sensitive model output

Better for later only if the product intentionally wants portable, public analysis.

### Option C: Hybrid

Store public-safe engagement metadata canonically and private moderation details operationally.

Pros:

- supports federation or static display of approved assistant responses
- keeps sensitive moderation details private

Cons:

- more moving parts
- requires a clear boundary between public and private fields

Good future direction after V1 proves useful.

## Idempotency And Caching

Use `post_id` plus a content hash as the idempotency key:

- if analysis exists for the same `post_id` and hash, return it
- if analysis exists but the post text changed in a future edit system, create a new version
- if an analysis is currently running, return `analysis_status=pending`
- if the Deadlus Labs request fails, store a short failure state with retry timing

Suggested fields:

- `post_id`
- `content_hash`
- `status`: `pending`, `complete`, `failed`
- `requested_at`
- `completed_at`
- `provider`
- `provider_model`
- `provider_request_id`
- `moderation_json`
- `engagement_json`
- `failure_code`
- `retry_after`

## UI Possibilities

V1 can be quiet:

- trigger the analysis in the background
- do not show anything to ordinary users unless the result is immediately useful
- expose failures only in the console or a hidden debug surface

Moderator-facing later:

- show posts with `medium` or higher severity in a review queue
- show labels and recommended action
- show the suggested de-escalating response as a draft, not as an automatic post

Author-facing later:

- after submit, show a private "possible reply starter" or "suggested follow-up"
- for risky posts, show a non-blocking prompt such as "This may read as aggressive. Consider revising."

Public-facing later:

- allow a moderator or trusted user to publish a generated engaging response
- mark generated responses clearly if they are ever posted as content

## Safety And Product Rules

- Generated responses should never impersonate another user.
- Generated responses should not be auto-posted in V1.
- For hostile content, generated responses should favor de-escalation, clarification, and boundary-setting.
- For high-severity moderation results, do not generate a provocative public reply. Return a safe moderator summary instead.
- Do not present model judgments as final truth. Treat them as triage signals.
- Keep prompts and provider diagnostics server-side.

## Security, Privacy, And Abuse Controls

- Store `DEADLUS_API_KEY` in environment configuration.
- Add a backend timeout and short circuit if the provider is slow.
- Rate limit by IP, identity, and post id.
- Cap post and context length before sending to Deadlus Labs.
- Strip or minimize secrets if future post bodies can contain private data.
- Log provider errors without logging full post bodies by default.
- Do not let arbitrary users analyze arbitrary historical posts in a tight loop.

## Suggested Implementation Slices

### Slice 1: Backend Skeleton And Storage

Goal:

- add an idempotent `POST /api/analyze_post` endpoint with local storage

Expected changes:

- add route handling in `Application::handle()`
- add a small analysis repository or service class
- add SQLite table creation or migration path
- return fake deterministic analysis while the Deadlus client is stubbed

Verification:

- missing post returns `404`
- duplicate calls for the same post return one stored analysis
- endpoint does not affect normal post creation latency

### Slice 2: Post-Load Browser Trigger

Goal:

- trigger analysis only for the newly created post after the thread page loads

Expected changes:

- redirect compose submissions with `created_post_id`
- render a data attribute for the created post
- add a deferred `post_analysis.js`
- call `POST /api/analyze_post` after load

Verification:

- creating a thread triggers one analysis request
- creating a reply triggers one analysis request
- reloading without `created_post_id` does not analyze every visible post
- duplicate browser events do not create duplicate rows

### Slice 3: Real Deadlus Labs Client

Goal:

- replace the fake analyzer with the real provider call

Expected changes:

- add a `DeadlusLabsClient`
- read endpoint, API key, timeout, and model from environment
- enforce strict output parsing and fallback behavior
- store provider request id and model metadata

Verification:

- provider timeout returns a stored failure state
- malformed provider JSON is handled safely
- successful provider response stores structured moderation and engagement fields

### Slice 4: Moderator Review Surface

Goal:

- make the moderation assessment useful without auto-enforcement

Expected changes:

- add a simple pending-review page filtered by severity
- show post link, labels, severity, confidence, and recommended action
- hide or restrict sensitive analysis fields if identity permissions are not ready

Verification:

- medium and high severity posts appear in review
- low severity posts do not create noise by default
- generated response is displayed as a draft suggestion only

### Slice 5: Product Refinement

Goal:

- decide how generated engaging responses should be used

Options:

- private moderator draft
- private author suggestion
- public assistant-authored reply after approval
- no visible response, use only to help moderators de-escalate

The safest default is private moderator draft.

## Open Questions

- Should the generated response be for the original author, for moderators, or for a public assistant persona?
- Should moderation metadata stay private forever, or should some low-risk fields become canonical records?
- Does Deadlus Labs support strict JSON schemas, request ids, and moderation-specific models?
- What latency and timeout budget is acceptable for the post-load request?
- Who is allowed to see analysis results before a permissions model exists?
- Should high-severity analysis create a notification, a dashboard item, or only stored metadata in V1?

## Acceptance Criteria

The first production-ready slice is complete when:

- post creation and reply creation still complete without waiting on Deadlus Labs
- the landing page triggers exactly one backend analysis request for the newly created post
- the backend endpoint is idempotent by `post_id` and content hash
- provider failures do not affect post visibility or page rendering
- analysis results are stored server-side with structured moderation and engagement fields
- tests cover endpoint validation, idempotency, duplicate trigger behavior, and failure handling

## Recommended V1 Decision

Start with:

- JSON `POST /api/analyze_post`
- SQLite-only private analysis storage
- post-load trigger scoped to `created_post_id`
- real Deadlus Labs call behind an environment flag
- no auto-posting and no auto-moderation action

That gives the product immediate learning value while keeping the forum write path fast and avoiding irreversible moderation or public-content decisions.
