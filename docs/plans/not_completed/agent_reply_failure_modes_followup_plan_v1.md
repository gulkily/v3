# Agent Reply Failure Modes Followup Plan V1

## Goal

Address two observed failure modes in the automatic `reply-agent` feature before making code or prompt changes:

1. repeated, similar, low-value agent replies in a self-authored gratitude thread
2. failure to summarize/comment on posts whose main content is a URL

This file is a planning artifact only.

## Evidence Reviewed

### Self-authored gratitude thread

Thread:

```text
https://zenmemes.com/threads/thread-20260506233917-753b5159
```

Observed behavior:

- The thread root and follow-up replies were all authored by the same approved user.
- `reply-agent` replied after each user post.
- The agent replies were structurally similar: praise, encouragement, "gratitude fuels momentum", and light coaching.
- The replies did not add durable context for other readers and risked turning a reflective thread into an automated affirmation loop.

Classification:

- Not an agent loop in the existing narrow sense, because the agent was not replying to itself.
- Better classified as `low_marginal_value_repetition` plus `private_journaling_or_self_conversation`.
- The failure is partly gating and partly response policy:
  - gating allowed repeated replies because each user post was individually "positive" and low risk
  - the response style had no thread-level novelty or "already said enough" constraint

### URL-only thread: Dark Forrest Protocol

Thread:

```text
https://zenmemes.com/threads/thread-20260506201837-8b6c1045
```

Posted URL:

```text
https://www.dark-forrest-protocol.live/
```

Observed behavior:

- The forum post body was only the URL.
- The public page is reachable server-side, but its initial HTML is mostly an app shell with a title and script bundle.
- The agent did not summarize or comment on the target page.

Classification:

- `url_context_missing`
- The current analyzer sees the forum post text, not the fetched page content.
- Even if it fetched raw HTML, app-shell pages need either metadata extraction, JavaScript bundle text extraction, or rendered-page extraction.

### URL plus explicit request: Snippetry

Thread:

```text
https://zenmemes.com/threads/thread-20260506203929-b5ac6c54
```

Posted URL:

```text
http://snippetry.evancole.be/
```

Observed behavior:

- The root post contained only the URL.
- A later user reply explicitly asked the agent to summarize the website.
- The target page is reachable server-side and includes some static page text, plus JavaScript-loaded behavior.
- The agent did not provide the requested summary.

Classification:

- `explicit_agent_request_unfulfilled`
- `url_context_missing`
- The current feature does not have a durable concept of "agent, please do X" requests beyond normal respondability analysis.

## Current System Constraints

- `/api/analyze_post` analyzes the target forum post and bounded thread comments.
- `/api/generate_agent_reply` posts the existing `engagement.suggested_response` from completed post analysis.
- Automatic frontend triggering is tied to the newly created post on the thread page.
- Existing hard gates cover:
  - reply-agent loop prevention
  - `respondability.should_generate_response`
  - public-response flag
  - minimum respondability score
  - high response risk
  - high/critical moderation severity
- There is no URL extraction, URL fetch, page summarization, rendered-page capture, robots/SSRF policy, or URL-context storage.
- There is no thread-level budget for agent reply count, novelty, or cooldown.

## Product Direction

The agent should not try to reply to everything positive. It should reply when it can add information, useful framing, a concrete answer, or a discussion-moving question.

For URL posts by approved users, the agent should be able to inspect a safe public URL and produce a short summary/comment when:

- the post is URL-only
- the post asks for a summary or opinion on a URL
- the user directly addresses the agent with a URL-related request

If the agent cannot access or extract enough page content, it should say so in a short, honest reply only when the user explicitly asked for a summary. It should not fabricate a summary from the URL/title alone.

## Proposed Slices

### Slice 1: Observability and Fixtures

Objective:

Make these failures reproducible before changing behavior.

Work:

- Add test fixtures or captured contexts for the three reviewed threads.
- Store representative analysis contexts for:
  - repeated self-authored gratitude comments
  - URL-only root post
  - explicit "agent, please summarize" reply with prior URL in thread context
- Add assertion helpers for gate reasons and generation decisions.
- Add a debug-only way to inspect why a post did or did not trigger an agent reply:
  - analysis status
  - respondability fields
  - gate failure reason
  - whether frontend auto-trigger would call publish

Acceptance:

- The system can classify current behavior for the three cases without reading production logs manually.
- Tests can fail for the desired new behavior before implementation.

### Slice 2: Repetition and Self-Conversation Gate

Objective:

Stop repeated low-value replies in threads where one user is reflecting with themselves and the agent has already responded enough.

Work:

- Add thread-level features to analysis/gating:
  - count existing `reply-agent` posts in the thread
  - count consecutive posts by the same non-agent author
  - count recent agent replies to that same author in the same thread
  - detect low-information gratitude/journaling style posts
- Add a hard or soft gate:
  - no more than one automatic agent reply per same-author self-conversation thread unless a later post asks an explicit question or requests the agent
  - no agent reply when the candidate response would mainly be encouragement/praise and an agent has already replied in the thread
- Add a gate reason such as:

```text
self_conversation_low_marginal_value
```

or:

```text
thread_agent_reply_budget_exhausted
```

Prompt/policy direction for later implementation:

- Tell analysis that gratitude, journaling, status updates, and self-reflection can be valid posts without needing an agent response.
- Require novelty: if the agent's useful contribution would be similar to an earlier agent reply, set `should_generate_response=false`.

Acceptance:

- The first gratitude post may receive at most one agent reply, depending on final policy.
- Later similar gratitude updates in the same thread do not receive automatic replies.
- Explicit user questions in the same thread can still be eligible.

### Slice 3: Explicit Agent Request Detection

Objective:

Treat "agent, please summarize..." as a distinct intent rather than generic respondability.

Work:

- Add lightweight intent detection in the analysis context or pre-analysis layer:
  - direct address to `agent`, `reply-agent`, or `bot`
  - verbs like summarize, review, explain, comment on, inspect, what is this
  - URL reference in the target post or earlier thread context
- Represent the detected intent in stored analysis or generation context, for example:

```json
{
  "agent_request": {
    "is_direct_request": true,
    "request_type": "summarize_url",
    "target_urls": ["http://snippetry.evancole.be/"]
  }
}
```

- For direct safe requests from approved users, allow the request to raise author/audience benefit, but do not bypass moderation or safety.

Acceptance:

- The Snippetry follow-up is classified as a direct `summarize_url` request.
- A direct request can trigger a reply even if the target post text is short.
- A direct request with no accessible content gets an honest failure/limitation reply, not silence.

### Slice 4: Safe URL Extraction and Fetching

Objective:

Give the agent bounded, auditable page context for public URLs.

Work:

- Extract URLs from:
  - target post body
  - root post body
  - parent post body
  - recent thread comments when the target post explicitly asks about "this site/page/link"
- Limit to one or two URLs per analysis.
- Only fetch for approved-user posts or direct approved-user agent requests.
- Add SSRF and operational safety:
  - allow only `http` and `https`
  - block localhost, private IP ranges, link-local, metadata IPs, and internal hostnames
  - cap redirects
  - cap body bytes
  - set a clear user agent
  - timeout aggressively
  - skip binary/non-text content unless explicitly supported later
  - consider robots.txt policy before production deployment
- Store fetched URL metadata in SQLite or the generation request context:
  - URL
  - final URL
  - status
  - content type
  - title
  - extracted text preview
  - failure reason
  - fetched_at

Acceptance:

- URL-only posts produce a deterministic fetched-page context when safe and reachable.
- Failed fetches produce a structured reason.
- Tests cover redirect, private IP rejection, timeout, non-text response, and byte cap.

### Slice 5: Page Content Extraction

Objective:

Extract useful text from both normal pages and app-shell pages without overbuilding a crawler.

Work:

- Start with static extraction:
  - HTML title
  - meta description / Open Graph / Twitter card fields
  - visible body text after removing script/style/nav noise
  - canonical URL
- For app-shell pages:
  - include title and metadata
  - optionally scan same-origin JavaScript bundles for short literal strings only when the HTML has very little body text
  - defer full browser rendering unless static extraction proves inadequate
- Add a future optional rendered extraction path:
  - Playwright or similar server-side renderer
  - strict timeout and no credentialed browsing
  - disabled by default until operationally reviewed

Acceptance:

- Snippetry yields enough static context to summarize it as a small experimental programming/snippet site.
- Dark Forrest Protocol yields at least a title/app-shell limitation if no meaningful text is available statically.
- The agent does not present app-shell title-only context as a full page review.

### Slice 6: URL-Aware Response Policy

Objective:

Make URL replies useful and honest.

Prompt/policy direction for later implementation:

- If page content was fetched:
  - summarize what the page appears to be
  - mention uncertainty when extraction was partial
  - add one useful comment, caveat, or question
- If only title/metadata was available:
  - say extraction was limited
  - avoid pretending to know the full page
- If fetch failed and the user explicitly asked:
  - give one concise limitation reply
  - include the failure class, not stack traces
- If fetch failed and the post was merely URL-only:
  - prefer no automatic reply

Acceptance:

- URL-only Dark Forrest Protocol does not generate a hallucinated summary.
- Explicit Snippetry request produces either a useful summary from extracted content or a clear limitation reply.
- Responses stay under the existing short public reply budget.

### Slice 7: Triggering and Retry UX

Objective:

Make the user-facing behavior understandable when URL summarization is pending, skipped, or failed.

Work:

- Show approved users a small status when agent reply is skipped due to missing URL context.
- Consider a manual "Ask agent" or "Summarize linked page" action for approved users.
- Keep automatic behavior conservative:
  - auto-fetch for explicit requests
  - optionally auto-fetch URL-only roots
  - avoid repeated background fetches on refresh
- Add retry semantics for transient URL fetch failures.

Acceptance:

- Approved users can tell whether the agent skipped because it could not fetch/extract the URL.
- Manual retry does not create duplicate replies.
- Existing automatic agent reply flow remains idempotent.

## Suggested Implementation Order

1. Observability and fixtures.
2. Repetition/self-conversation gate.
3. Explicit agent request detection.
4. Safe URL extraction/fetching.
5. Static page content extraction.
6. URL-aware response policy.
7. Triggering/retry UX.

## Key Decisions Needed Before Implementation

- Should URL fetching be automatic for URL-only posts, or only when the user explicitly asks the agent?
- Should URL fetching be limited to approved users?
- Is title/metadata-only context enough for a reply, or should it produce a limitation instead?
- What is the per-thread automatic agent reply budget?
- Should direct `agent, ...` requests bypass the one-agent-reply-per-target-content-hash rule when the target is a different post in the same thread?
- Should full browser rendering be allowed on the server, or should V1 stay static-only?

## Non-Goals For V1

- General web search.
- Crawling more than the posted URL.
- Logging into sites or using user browser credentials.
- Summarizing private, local-network, or non-public URLs.
- Rewriting the whole generation flow to a second model call unless needed after URL context is available.
- Replacing the earlier agent-reply removal plan.

## Verification Plan

- Unit tests for URL extraction, URL safety checks, and extraction summaries.
- Application tests for gate reasons:
  - gratitude self-thread suppresses repeated agent replies
  - explicit URL summary request is detected
  - URL-only post without extractable content does not hallucinate
- Smoke tests for `/api/analyze_post` and `/api/generate_agent_reply` with stored fetched-page context.
- Manual check against local fixture equivalents of the three reviewed threads before production deployment.
