# Agent Reply One-Step Analyze/Publish Plan

## Goal

Make automatic agent reply publishing happen inside `POST /api/analyze_post`, while keeping `POST /api/generate_agent_reply` as an idempotent compatibility and manual retry endpoint.

The intended production behavior when agent replies are enabled is:

1. A newly created post renders with `data-agent-reply-work="analyze"` when no current analysis exists.
2. The browser calls only `POST /api/analyze_post`.
3. The backend stores the post analysis.
4. If the reply gates pass, the backend immediately posts the stored `engagement.suggested_response` as the `reply-agent`.
5. The response reports both analysis status and agent reply posting status.

This keeps the single model-call design: the analysis prompt drafts the suggested response, and posting consumes that stored suggestion.

## Current Behavior

- `public/assets/post_analysis.js` calls `POST /api/analyze_post` for `work === "analyze"`.
- If analysis returns `agent_reply_generation_allowed === true`, the browser then calls `POST /api/generate_agent_reply`.
- `POST /api/generate_agent_reply` no longer calls a reply model. It reads the completed analysis and posts `engagement.suggested_response`.
- This creates a confusing second browser/API step even though there is not a second model-generation step.

## Contract Decisions

These decisions are part of the implementation contract. Do not reinterpret them during implementation.

### Configuration

- `DEDALUS_AGENT_REPLIES_ENABLED=false` disables automatic browser work rendering for agent replies. Newly created post pages must not render `data-agent-reply-work` solely to report disabled reply status.
- A direct `POST /api/analyze_post` call still analyzes the post according to the existing analysis endpoint behavior, but it must not post an agent reply while agent replies are disabled.
- When a direct `/api/analyze_post` call runs while agent replies are disabled, the analysis response must include `agent_reply_generation_allowed: false` and compact reply status fields with `agent_reply_generation_status: "not_recommended"` and `agent_reply_reason: "config_disabled"`.
- `POST /api/generate_agent_reply` must keep returning its existing compatibility response shape, including `not_recommended/config_disabled` when agent replies are disabled.

### Generated-Response Rows

For the current target post/content hash, existing generated-response rows have these meanings. Unless a row says otherwise, endpoint behavior in this table assumes agent replies are enabled; disabled configuration follows the Configuration contract above.

| Existing row state | Automatic render work | `/api/analyze_post` one-step behavior | `/api/generate_agent_reply` behavior |
| --- | --- | --- | --- |
| no row, no current analysis | `analyze` when agent replies are enabled | analyze, then post if gates pass | `analysis_required` |
| no row, completed current analysis, gates pass | `publish` when agent replies are enabled | post from stored suggestion if called directly | post from stored suggestion |
| no row, completed current analysis, gates fail | `none` | do not post; return compact `not_recommended` | existing `not_recommended` |
| `complete` with no `agent_post_id` | `publish` when agent replies are enabled | post existing stored suggestion if called directly | post existing stored suggestion |
| `pending` or `posting` | `none` | do not start duplicate work; return compact `in_progress` if reached | existing `in_progress` |
| `failed` | `none` | do not auto-retry; return compact `failed` if reached | existing failed response |
| any status with `agent_post_id` | `none` | return compact `already_posted` if reached | existing `already_posted` |

Automatic browser work must not retry `failed`, `pending`, or `posting` rows. Manual retry semantics remain limited to the existing `/api/generate_agent_reply` endpoint behavior unless a separate plan changes retry handling; this plan does not add failed-row retry.

### Privacy

- `/api/analyze_post` must never expose generated reply internals such as `response_text`, reply `provider`, reply `provider_model`, `provider_request_id`, `raw_response`, `failure_message`, or `retry_after` through the compact `agent_reply_*` fields.
- Unauthorized viewers must not receive concrete gate-failure reasons from `/api/analyze_post`. When a gate fails and `viewer_can_see_analysis` is false, use `agent_reply_reason: "not_recommended"`.
- Authorized viewers may receive concrete gate-failure reasons from `/api/analyze_post`, such as `respondability_score_low`, `response_risk_high`, `moderation_severity_high`, `response_not_public`, or `agent_loop_prevention`.
- Analysis-provider failure details remain governed by the existing analysis response visibility rules. Do not copy analysis-provider failure codes into `agent_reply_failure_code`.

### Analyze Response Contract

Every successful `POST /api/analyze_post` response (`status: "ok"`) must include:

- existing analysis fields returned today
- `agent_reply_generation_allowed`
- `agent_reply_generation_status`
- `agent_reply_posted`
- `agent_reply_post_id`
- `agent_reply_post_url`
- `agent_reply_reason`
- `agent_reply_failure_code`

`agent_reply_generation_allowed` means "posting would be allowed by configuration, completed analysis, and gates." It is `true` only when agent replies are enabled, analysis completed, and `agentReplyGateFailure()` returns `null`. It is `false` for disabled config, incomplete analysis, and gate failures.

The compact fields must always use `null` for unavailable IDs, URLs, reasons, and failure codes. Do not omit those keys on successful analyze responses.

### Existing Test Expectation Changes

- Tests that currently expect "analyze now, render `publish` on reload" must be updated. After this change, a passing `/api/analyze_post` call should already post the reply, so a reload should render no `data-agent-reply-work` for that post.
- Tests for `/api/generate_agent_reply` should continue to assert the compatibility response shape and idempotency behavior.

## Implementation Steps

### 1. Extract Shared Reply Posting Logic

Move the core body of `Application::handleGenerateAgentReply()` into this private helper:

```php
private function agentReplyResultForPost(array $post): array
```

The helper must be an array-returning application helper, not an HTTP responder. It may perform the existing reply-posting side effects, but it must not call `sendJson()` or terminate request handling. `handleGenerateAgentReply()` and `handleAnalyzePost()` should decide how to serialize or embed the returned result.

The helper should preserve current behavior:

- check `DEDALUS_AGENT_REPLIES_ENABLED`
- compute the current post analysis context and content hash
- check existing rows in `SqliteAgentReplyGenerationStore`
- return `already_posted`, `failed`, or `in_progress` when appropriate
- require a completed current analysis
- apply `agentReplyGateFailure()`
- build the reply generation row from `engagement.suggested_response`
- reserve posting
- ensure the `reply-agent` identity
- create the canonical reply with `LocalWriteService::createReply()`
- mark the generated response row as posted
- return the same response shape currently returned by `/api/generate_agent_reply`

### 2. Keep `/api/generate_agent_reply`

Leave the route in place for compatibility and manual retries.

After method, `post_id`, and post existence validation, delegate to the shared helper and return its JSON result.

### 3. Publish During `/api/analyze_post`

In `Application::handleAnalyzePost()`:

- run analysis exactly as today
- compute `agent_reply_generation_allowed` from both the analysis/gate result and reply configuration:
  - `true` only when `DEDALUS_AGENT_REPLIES_ENABLED` is enabled, analysis completed, and `agentReplyGateFailure()` returns `null`
  - `false` when agent replies are disabled, analysis did not complete, or a reply gate fails
- build compact skipped reply status fields directly for disabled config, incomplete analysis, and gate-failed cases
- when `agent_reply_generation_allowed === true`, call the shared reply-posting helper before sending the response
- always embed a compact agent reply result in the analysis JSON response

Add this allowlisted mapper:

```php
private function agentReplySummaryForAnalysisResponse(array $replyResult): array
```

This mapper should expose only compact posting status fields and must not expose hidden analysis details or generated response text. It should translate the full helper result into `agent_reply_*` fields.

Suggested response fields:

```json
{
  "agent_reply_generation_status": "generated",
  "agent_reply_posted": true,
  "agent_reply_post_id": "post-id",
  "agent_reply_post_url": "/posts/post-id",
  "agent_reply_reason": null,
  "agent_reply_failure_code": null
}
```

For successful posting, map:

- `generation_status` to `agent_reply_generation_status`
- `posted` to `agent_reply_posted`
- `agent_post_id` to `agent_reply_post_id`
- `agent_post_url` to `agent_reply_post_url`
- `reason` to `agent_reply_reason`
- `failure_code` to `agent_reply_failure_code`

For skipped cases, the response should still include the same compact fields, for example `agent_reply_generation_status: "not_recommended"` with `agent_reply_reason: "respondability_score_low"` or `agent_reply_reason: "config_disabled"`. These skipped summaries should be created in `handleAnalyzePost()` from the already-known conditions instead of calling the posting helper when posting is not allowed. For failed posting, include `agent_reply_generation_status: "failed"` and `agent_reply_failure_code`.

Use an explicit compact status mapping:

| Full or local status | `agent_reply_generation_status` | `agent_reply_posted` | `agent_reply_post_id` / `agent_reply_post_url` | Reason/failure fields |
| --- | --- | --- | --- | --- |
| generated | generated | `true` when helper result has `posted === true` | from `agent_post_id` / `agent_post_url` | nullable |
| already_posted | already_posted | `true` | from `agent_post_id` / `agent_post_url` | nullable |
| not_recommended | not_recommended | `false` | `null` | `agent_reply_reason` from `reason` |
| analysis_required | analysis_required | `false` | `null` | `agent_reply_reason` from `reason`; do not set `agent_reply_failure_code` for analysis-provider failures |
| in_progress | in_progress | `false` | `null` | nullable |
| failed | failed | `false` | `null` unless a posted ID is already present | `agent_reply_failure_code` from `failure_code` |

For `/api/analyze_post`, the local skipped cases should map as:

- `DEDALUS_AGENT_REPLIES_ENABLED` disabled: `not_recommended`, `agent_reply_reason: "config_disabled"`
- analysis missing or not complete after the analysis attempt: `analysis_required`, with `agent_reply_reason` set to `missing_analysis` or `analysis_not_complete`. Do not copy the analysis failure code into `agent_reply_failure_code`; analysis failure details should remain available only through the existing analysis response fields and existing visibility rules.
- `agentReplyGateFailure()` result: `not_recommended`, with `agent_reply_reason` set to the gate failure reason only when the viewer is allowed to see analysis details. For unauthorized viewers, use a generic `agent_reply_reason: "not_recommended"` so `/api/analyze_post` does not reveal moderation, risk, privacy, or respondability details that are otherwise hidden.

Notes:

- Preserve existing analysis visibility rules. Do not expose hidden analysis details to unauthorized viewers beyond the fields already returned today.
- It is acceptable to expose reply posting status and reply URL because the canonical reply is public content once posted.
- Do not expose `/api/generate_agent_reply` fields such as `response_text`, `provider`, or `provider_model` from `/api/analyze_post` unless they are already part of the normal authorized analysis response.
- If posting fails, return the analysis response with the agent reply failure fields rather than failing the whole analysis request, unless an existing hard failure already behaves differently.
- The compact `agent_reply_failure_code` field in `/api/analyze_post` is for reply posting/generation failures only, such as `analysis_suggestion_error` or `posting_error`; it should not duplicate analysis-provider failure codes.
- The mapper must intentionally drop `failure_message` and `retry_after` from `/api/analyze_post`, even though `/api/generate_agent_reply` may continue returning those fields.

### 4. Update Browser Automation

In `public/assets/post_analysis.js`:

- For `work === "analyze"`:
  - call only `analyzePost(postId)`
  - convert the embedded `agent_reply_*` fields into the existing generation-result shape with a small adapter named `agentReplyResultFromAnalysis(analysis)`
  - pass the adapted result into the existing feedback renderer
  - do not call `generateAgentReply(postId)` after successful analysis
- For `work === "publish"`:
  - continue calling `generateAgentReply(postId)`, since this covers pages where analysis already existed before render
- Keep the idempotency guard around created post IDs.

The adapter should map:

- set `status` to `"ok"` when `analysis.status === "ok"` and embedded agent reply fields are present
- `agent_reply_generation_status` to `generation_status`
- `agent_reply_posted` to `posted`
- `agent_reply_post_id` to `agent_post_id`
- `agent_reply_post_url` to `agent_post_url`
- `agent_reply_reason` to `reason`
- `agent_reply_failure_code` to `failure_code`

Optional cleanup:

- Rename local JS helper text from "generation" toward "agent reply" where it improves clarity.
- Keep API field names compatible unless there is a clear reason to change them.

### 5. Rendering Work State

`Application::agentReplyWorkForPost()` and the surrounding `agentReplyWorkByPostId()` configuration gate must follow the generated-response row contract above:

- if `DEDALUS_AGENT_REPLIES_ENABLED` is disabled, render no automatic work
- if the target post is authored by the `reply-agent`, render no automatic work
- no current analysis: `analyze`
- completed current analysis and gates pass with no generated-response row: `publish`
- completed current analysis and gates pass with a `complete` generated-response row and no `agent_post_id`: `publish`
- completed current analysis and gates fail: `none`
- analysis exists but is not complete: `none`
- existing generated-response row with `agent_post_id`: `none`
- existing generated-response row with `pending`, `posting`, or `failed`: `none`

After the new one-step path succeeds, a reload should find an agent reply row with `agent_post_id`, so the created post card should not render `data-agent-reply-work`.

### 6. Tests

Add or update tests in `tests/WriteApiSmokeTest.php`.

Required coverage:

- `POST /api/analyze_post` posts an agent reply when analysis passes gates.
- The response includes the embedded agent reply status and post URL.
- The canonical reply file exists.
- The reply has `Parent-ID` equal to the analyzed post ID.
- The reply is authored by the `reply-agent` identity.
- The generated response row uses the analysis provider/model.
- The generated response row's decoded `raw_response.source` is `analysis_suggested_response`.
- Repeating `POST /api/analyze_post` for the same content does not post a duplicate reply.
- Existing `/api/generate_agent_reply` behavior remains idempotent and compatible.
- Low respondability, high risk, high moderation, private response, disabled config, and agent-loop cases do not post from `/api/analyze_post`.
- For unauthorized viewers, gate-failed `/api/analyze_post` responses use generic `agent_reply_reason: "not_recommended"`.
- For authorized viewers, gate-failed `/api/analyze_post` responses may include the concrete gate reason.
- `/api/analyze_post` never exposes reply `response_text`, reply provider/model, `raw_response`, `failure_message`, or `retry_after` through the compact agent reply fields.
- Direct `/api/analyze_post` while `DEDALUS_AGENT_REPLIES_ENABLED=false` returns `agent_reply_generation_allowed: false`, `agent_reply_generation_status: "not_recommended"`, and `agent_reply_reason: "config_disabled"` without posting.
- Pages rendered while `DEDALUS_AGENT_REPLIES_ENABLED=false` do not render `data-agent-reply-work`.
- After a successful one-step `/api/analyze_post`, a reload renders no `data-agent-reply-work` for the created post.
- Existing tests that expected a reload to render `data-agent-reply-work="publish"` after analysis are updated to the new posted/no-work expectation.
- Add a source-level JS assertion in `tests/WriteApiSmokeTest.php` unless a dedicated JS test harness already exists: `public/assets/post_analysis.js` must not call `generateAgentReply(postId)` from the `work === "analyze"` branch, and must still call it for the `work === "publish"` branch.

### 7. Verification

Run:

```bash
php tests/run.php
node --check public/assets/post_analysis.js
```

Manual production-like check:

1. Create a new post.
2. Open the redirected thread URL with `created_post_id`.
3. Confirm the browser makes one `POST /api/analyze_post` call for the new post.
4. Confirm an agent reply is posted from the suggested response.
5. Reload the thread.
6. Confirm the reply is visible and no extra automatic posting occurs.

## Expected Outcome

The automatic path becomes one backend step from the browser's perspective. There is still only one model call, and the stored suggested response is posted immediately after the analysis passes gates. The separate `/api/generate_agent_reply` endpoint remains available for older clients, already-analyzed posts, and manual retry workflows.

## Implementation Status

- 2026-05-12: Step 0 complete on branch `agent-reply-one-step-analyze-publish` - added the implementation contract document and this status log. No runtime behavior changed.
- 2026-05-12: Step 1 complete - extracted `Application::agentReplyResultForPost()` as the shared array-returning reply posting helper and changed `POST /api/generate_agent_reply` to delegate after request validation. Verified with `php -l src/ForumRewrite/Application.php`.
