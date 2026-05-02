# Dedalus Agent Reply Implementation Slices

## Goal

Implement a fully automatic, transparent agentic reply agent.

When a newly written thread or comment is analyzed as respondable, the backend should generate a concise agent reply with Dedalus, post it as a canonical reply authored by `reply-agent`, store operational generation metadata in SQLite, and notify the user on the thread page.

## Non-Goals

- Do not silently impersonate a human author.
- Do not expose private key material, raw provider responses, or private diagnostics publicly.
- Do not let the agent reply to itself.
- Do not post more than one agent reply for the same target post content hash.
- Do not bypass the current analysis gates.

## Slice 1: Agent Identity Bootstrap

Status: implemented in branch `dedalus-agent-reply-slices`; committed after tests passed.

### Objective

Create a backend path that ensures a canonical `reply-agent` identity exists and can be used for server-side authored replies.

### Work

- Add an agent identity service responsible for `reply-agent`.
- Check whether a `reply-agent` profile/identity already exists.
- If missing, generate a server-side keypair.
- Store private key material outside `public/` and outside committed canonical records.
- Create required canonical public identity/public-key/bootstrap records.
- Mark or seed `reply-agent` as approved if normal profile rendering requires approval.
- Ensure rendered posts by this identity visibly identify the author as `reply-agent`.

### Tests

- Bootstrapping creates `reply-agent` once.
- Repeated bootstrap reuses the existing identity.
- Private key material is not written under `public/`.
- Public profile/read model resolves `reply-agent`.
- Agent-authored post rendering includes visible agent labeling once that rendering hook exists.

### Commit Boundary

Commit after identity bootstrap is deterministic and covered by tests.

## Slice 2: Generation Storage And Prompt Template

Status: implemented in branch `dedalus-agent-reply-slices`; committed after tests passed.

### Objective

Add response-generation infrastructure without posting replies yet.

### Work

- Add `post_generated_responses` SQLite table.
- Store generation lifecycle fields:
  - target `post_id`
  - target `content_hash`
  - `analysis_hash`
  - status
  - provider/model/request id
  - response text/style/intent
  - agent identity/profile/post ids
  - posted timestamp
  - failure code/message/retry timestamp
  - raw provider response JSON
- Add response generator interface.
- Add stub response generator for tests.
- Add Dedalus response generator using structured JSON schema.
- Add prompt template:

```text
prompts/dedalus_agent_reply_system.txt
```

- Keep response-generation instructions out of PHP except schema/enums/template interpolation.

### Tests

- Store saves and hydrates completed generation rows.
- Store saves retryable failures.
- Store prevents or reuses duplicate completed generations for the same target tuple.
- Stub generator returns deterministic structured output.
- Dedalus decoder accepts expected response shapes.
- Prompt template loads from file.

### Commit Boundary

Commit after storage, prompt, and generator interfaces work independently of canonical posting.

## Slice 3: Agent Reply Gating API

Status: implemented in branch `dedalus-agent-reply-slices`; committed after tests passed.

### Objective

Add an API endpoint that decides whether an agent reply should be generated, but initially does not write the canonical reply.

### Work

- Add:

```text
POST /api/generate_agent_reply?post_id=<id>
```

- Load target post and current analysis context.
- Require completed analysis for the current content hash.
- Enforce gates:
  - `respondability.should_generate_response = true`
  - `respondability.overall_score >= 0.65`
  - `respondability.response_risk != high`
  - `moderation.severity` is not `high` or `critical`
  - target post is not authored by `reply-agent`
  - no agent reply already exists for the same post content hash
- Return clear statuses:
  - `analysis_required`
  - `not_recommended`
  - `already_posted`
  - `generated`
  - `failed`
- In this slice, store generated response metadata but leave `agent_post_id` empty.

### Tests

- Missing analysis returns `analysis_required`.
- Failed analysis returns `analysis_required` or `not_recommended` with a clear reason.
- Low respondability returns `not_recommended`.
- High response risk returns `not_recommended`.
- High/critical moderation severity returns `not_recommended`.
- Agent-authored target returns `not_recommended` with `agent_loop_prevention`.
- Successful stub generation stores metadata but does not create a canonical post.

### Commit Boundary

Commit after the endpoint can safely generate and store metadata without posting.

## Slice 4: Canonical Agent Reply Posting

### Objective

Turn a successful generated response into a canonical reply authored by `reply-agent`.

### Work

- Extend or reuse write service code to create a reply with server-side author identity.
- Ensure generated agent reply body includes or renders with transparent agent labeling.
- Post reply under the same thread with parent equal to the target post.
- Store resulting `agent_post_id`, `agent_identity_id`, `agent_profile_slug`, and `posted_at` in generation metadata.
- Invalidate relevant artifacts.
- Refresh read model incrementally or fall back consistently, matching existing write behavior.
- Keep duplicate checks immediately before posting to avoid double replies.

### Tests

- Successful endpoint creates a canonical reply.
- Reply parent is the target post.
- Reply author is `reply-agent`.
- Reply is visible publicly as thread content.
- Generation metadata includes `agent_post_id`.
- Repeated endpoint call returns `already_posted` and does not create a second reply.
- If posting fails after generation, metadata records a failure or retryable state.

### Commit Boundary

Commit after canonical posting is fully wired and idempotent.

## Slice 5: Agent Reply UI Notification

### Objective

Notify the user on the thread page when agent generation is running, skipped, failed, or posted.

### Work

- Extend `public/assets/post_analysis.js`.
- After `/api/analyze_post` completes:
  - if analysis says generation is allowed, call `/api/generate_agent_reply`
  - show inline state near the created post
- UI states:
  - `Generating agent reply...`
  - `Agent reply posted`
  - link to posted reply
  - approved-only skipped/error reason if available
- Avoid blocking initial page load.
- Avoid repeated generation calls if page refresh sees existing posted metadata.

### Tests

- Existing JS test coverage if available, or server smoke tests that rendered markup exposes required hooks.
- Endpoint smoke test verifies response includes link data.
- Manual local check on a newly created post confirms:
  - analysis request fires after page load
  - generation request follows only when gates pass
  - posted agent reply appears after refresh or via notification link

### Commit Boundary

Commit after the user-visible flow works from page load to posted reply notification.

## Slice 6: Agent Labeling And Safety Polish

### Objective

Make agent authorship unmistakable and harden edge cases before considering the feature complete.

### Work

- Add visible agent label on agent-authored post cards.
- Consider a dedicated CSS class for agent replies.
- Ensure activity/profile surfaces do not hide the agent nature.
- Decide whether `reply-agent` appears in `/users/`.
- Add runbook notes for private key location and rotation.
- Add config knobs if needed:
  - disable automatic agent replies
  - stub mode for generation
  - response model
  - response prompt path

### Tests

- Agent-authored post card contains visible label.
- Agent reply does not trigger a follow-up agent reply.
- Config-disabled mode prevents generation/posting.
- Stub mode remains deterministic.

### Commit Boundary

Commit after labeling, safety switches, and docs are in place.

## Suggested Implementation Order

1. Agent identity bootstrap.
2. Generation storage and prompt template.
3. Gating API without canonical posting.
4. Canonical posting.
5. UI notification.
6. Labeling, config, and docs hardening.

## Key Risks

- Server-side key generation/signing may not match existing browser-signing assumptions.
- Automatic canonical posting can create duplicate or looping content if gates are wrong.
- Agent identity private key storage must be handled carefully on shared hosting.
- Read-model/artifact refresh failures must not leave canonical posts invisible or duplicated.
- Prompt failures must not produce replies that imply human identity, moderation authority, or private analysis details.

## Acceptance Criteria

- Creating a respondable post can lead to one automatic canonical reply by `reply-agent`.
- The reply is visibly agent-authored.
- A non-respondable or risky post does not get an agent reply.
- Agent-authored posts never get agent replies.
- Repeated requests do not duplicate replies.
- Generation metadata is stored in SQLite.
- Raw provider responses and private key material are not public.
- The thread page notifies the user when the agent reply is posted.
- Full test suite passes.
