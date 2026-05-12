# Agent Reply One-Step Analyze/Publish Contract V1

This document defines the implementation contract for moving automatic
`reply-agent` publishing into `POST /api/analyze_post`.

Source plan:
`docs/plans/agent_reply_one_step_analyze_publish_plan_v1.md`

## Scope

V1 covers the production behavior for:

- automatic browser work rendered on newly created post cards
- `POST /api/analyze_post`
- `POST /api/generate_agent_reply`
- generated-response row interpretation for the current target post/content hash
- compact agent reply status embedded in successful analyze responses

The intended automatic path is:

1. A new post renders with `data-agent-reply-work="analyze"` only when automatic
   agent reply work is enabled and no current analysis exists.
2. The browser calls `POST /api/analyze_post`.
3. The backend stores the post analysis.
4. If configuration, analysis completion, and reply gates all allow posting, the
   backend posts the stored `engagement.suggested_response` as `reply-agent`.
5. The analyze response reports both analysis status and compact agent reply
   status.

## Non-Goals

- Do not add a second model call for reply generation.
- Do not remove `POST /api/generate_agent_reply`.
- Do not add automatic retries for failed, pending, or posting generated-response
  rows.
- Do not expose generated reply internals through `POST /api/analyze_post`.
- Do not change the canonical reply record format.

## Configuration Contract

`DEDALUS_AGENT_REPLIES_ENABLED=false` disables automatic agent reply work.

When disabled:

- newly rendered post pages must not render `data-agent-reply-work` solely to
  report disabled agent reply status
- a direct `POST /api/analyze_post` still performs analysis according to the
  existing analyze endpoint behavior
- `POST /api/analyze_post` must not post an agent reply
- successful analyze responses must include:

```json
{
  "agent_reply_generation_allowed": false,
  "agent_reply_generation_status": "not_recommended",
  "agent_reply_posted": false,
  "agent_reply_post_id": null,
  "agent_reply_post_url": null,
  "agent_reply_reason": "config_disabled",
  "agent_reply_failure_code": null
}
```

`POST /api/generate_agent_reply` keeps its existing compatibility response shape
and must continue returning `not_recommended` with reason `config_disabled` when
agent replies are disabled.

## Generated-Response Row Contract

For the current target post/content hash, existing generated-response rows have
the following meanings when agent replies are enabled.

| Existing row state | Automatic render work | `/api/analyze_post` one-step behavior | `/api/generate_agent_reply` behavior |
| --- | --- | --- | --- |
| no row, no current analysis | `analyze` | analyze, then post if gates pass | `analysis_required` |
| no row, completed current analysis, gates pass | `publish` | post from stored suggestion if called directly | post from stored suggestion |
| no row, completed current analysis, gates fail | none | do not post; return compact `not_recommended` | existing `not_recommended` |
| `complete` with no `agent_post_id` | `publish` | post existing stored suggestion if called directly | post existing stored suggestion |
| `pending` or `posting` | none | do not start duplicate work; return compact `in_progress` if reached | existing `in_progress` |
| `failed` | none | do not auto-retry; return compact `failed` if reached | existing failed response |
| any status with `agent_post_id` | none | return compact `already_posted` if reached | existing `already_posted` |

Automatic browser work must not retry generated-response rows in `pending`,
`posting`, or `failed` states.

## Shared Posting Helper Contract

`Application::handleGenerateAgentReply()` and `Application::handleAnalyzePost()`
must share one reply-posting helper:

```php
private function agentReplyResultForPost(array $post): array
```

The helper is an application helper, not an HTTP responder.

It may perform posting side effects, but it must not call `sendJson()` or
terminate request handling.

The helper must preserve existing `POST /api/generate_agent_reply` behavior:

- check `DEDALUS_AGENT_REPLIES_ENABLED`
- compute the current analysis context and content hash
- inspect `SqliteAgentReplyGenerationStore` for the current target tuple
- return `already_posted`, `failed`, or `in_progress` when appropriate
- require a completed current analysis
- apply `agentReplyGateFailure()`
- build the generated-response row from stored
  `engagement.suggested_response`
- reserve posting before creating the canonical reply
- ensure the `reply-agent` identity
- create the canonical reply through `LocalWriteService::createReply()`
- mark the generated-response row as posted
- return the same full response shape currently returned by
  `POST /api/generate_agent_reply`

`POST /api/generate_agent_reply` must validate method, `post_id`, and post
existence, then delegate to this helper and serialize the full helper result.

## Analyze Response Contract

Every successful `POST /api/analyze_post` response with `status: "ok"` must
include all existing successful analysis fields plus these keys:

- `agent_reply_generation_allowed`
- `agent_reply_generation_status`
- `agent_reply_posted`
- `agent_reply_post_id`
- `agent_reply_post_url`
- `agent_reply_reason`
- `agent_reply_failure_code`

`agent_reply_generation_allowed` means that posting is allowed by configuration,
completed analysis, and reply gates. It is `true` only when:

- agent replies are enabled
- analysis completed for the current post/content hash
- `agentReplyGateFailure()` returns `null`

It is `false` for disabled configuration, incomplete analysis, missing analysis,
and gate failures.

The compact fields must always use `null` for unavailable IDs, URLs, reasons,
and failure codes. Successful analyze responses must not omit these keys.

## Analyze Posting Contract

`Application::handleAnalyzePost()` must:

- run post analysis exactly as it does today
- compute `agent_reply_generation_allowed` from configuration, analysis
  completion, and reply gate result
- build compact skipped reply status directly for disabled config, incomplete
  analysis, missing analysis, and gate-failed cases
- call `agentReplyResultForPost()` only when
  `agent_reply_generation_allowed === true`
- embed a compact agent reply result in the analysis JSON response
- return the analysis response even if reply posting fails, unless an existing
  hard analysis failure path already behaves differently

The analyze endpoint must not call the posting helper for known skipped cases.

## Compact Summary Mapper Contract

Add an allowlisted mapper:

```php
private function agentReplySummaryForAnalysisResponse(array $replyResult): array
```

The mapper translates a full reply helper result into compact analyze response
fields:

| Full or local status | `agent_reply_generation_status` | `agent_reply_posted` | ID and URL fields | Reason and failure fields |
| --- | --- | --- | --- | --- |
| `generated` | `generated` | `true` when helper result has `posted === true` | from `agent_post_id` and `agent_post_url` | nullable |
| `already_posted` | `already_posted` | `true` | from `agent_post_id` and `agent_post_url` | nullable |
| `not_recommended` | `not_recommended` | `false` | `null` | `agent_reply_reason` from `reason` |
| `analysis_required` | `analysis_required` | `false` | `null` | `agent_reply_reason` from `reason`; no analysis-provider failure code |
| `in_progress` | `in_progress` | `false` | `null` | nullable |
| `failed` | `failed` | `false` | `null` unless a posted ID already exists | `agent_reply_failure_code` from `failure_code` |

For local skipped cases in `POST /api/analyze_post`:

- disabled config maps to `not_recommended` with reason `config_disabled`
- missing analysis maps to `analysis_required` with reason `missing_analysis`
- incomplete analysis maps to `analysis_required` with reason
  `analysis_not_complete`
- gate failure maps to `not_recommended`

The mapper must intentionally drop full helper fields that are not allowlisted
for analyze responses.

## Privacy Contract

`POST /api/analyze_post` must never expose generated reply internals through the
compact `agent_reply_*` fields.

Forbidden compact analyze fields include:

- `response_text`
- reply `provider`
- reply `provider_model`
- `provider_request_id`
- `raw_response`
- `failure_message`
- `retry_after`

Unauthorized viewers must not receive concrete gate-failure reasons from
`POST /api/analyze_post`. If a gate fails and `viewer_can_see_analysis` is false,
return:

```json
{
  "agent_reply_generation_status": "not_recommended",
  "agent_reply_reason": "not_recommended"
}
```

Authorized viewers may receive concrete gate-failure reasons, including:

- `respondability_score_low`
- `response_risk_high`
- `moderation_severity_high`
- `response_not_public`
- `agent_loop_prevention`

Analysis-provider failure details remain governed by existing analysis response
visibility rules. Do not copy analysis-provider failure codes into
`agent_reply_failure_code`.

The compact `agent_reply_failure_code` field is only for reply posting or stored
suggestion failures, such as `analysis_suggestion_error` or `posting_error`.

## Browser Automation Contract

`public/assets/post_analysis.js` must treat `work === "analyze"` as a single
backend operation:

- call only `analyzePost(postId)`
- adapt embedded `agent_reply_*` fields into the existing generation-result
  shape
- pass the adapted result into the existing feedback renderer
- do not call `generateAgentReply(postId)` after successful analysis

Add this small adapter:

```js
agentReplyResultFromAnalysis(analysis)
```

The adapter maps:

- `analysis.status === "ok"` plus embedded agent reply fields to `status: "ok"`
- `agent_reply_generation_status` to `generation_status`
- `agent_reply_posted` to `posted`
- `agent_reply_post_id` to `agent_post_id`
- `agent_reply_post_url` to `agent_post_url`
- `agent_reply_reason` to `reason`
- `agent_reply_failure_code` to `failure_code`

For `work === "publish"`, the browser must continue calling
`generateAgentReply(postId)`. This covers pages where current analysis already
existed before render.

The existing idempotency guard around created post IDs must remain in place.

## Rendering Work Contract

`Application::agentReplyWorkForPost()` and the surrounding
`agentReplyWorkByPostId()` configuration gate must follow this contract:

- disabled `DEDALUS_AGENT_REPLIES_ENABLED`: render no automatic work
- target post authored by `reply-agent`: render no automatic work
- no current analysis: render `analyze`
- completed current analysis, gates pass, and no generated-response row: render
  `publish`
- completed current analysis, gates pass, and a `complete` generated-response
  row exists with no `agent_post_id`: render `publish`
- completed current analysis and gates fail: render no automatic work
- analysis exists but is not complete: render no automatic work
- generated-response row has `agent_post_id`: render no automatic work
- generated-response row is `pending`, `posting`, or `failed`: render no
  automatic work

After a successful one-step analyze/publish call, a reload should find a
generated-response row with `agent_post_id`, so the created post card must not
render `data-agent-reply-work`.

## Idempotency Contract

For the same target post/content hash:

- successful one-step analyze/publish must create at most one canonical
  `reply-agent` reply
- repeated `POST /api/analyze_post` calls must not post duplicate replies
- repeated `POST /api/generate_agent_reply` calls must continue returning the
  existing idempotent compatibility result
- concurrent or repeated work must honor existing `pending`, `posting`,
  `complete`, `failed`, and `agent_post_id` generated-response states

## Required Verification

Tests should be added or updated in `tests/WriteApiSmokeTest.php` to cover:

- `POST /api/analyze_post` posts an agent reply when analysis passes gates
- the analyze response includes compact embedded agent reply status and post URL
- the canonical reply file exists
- the canonical reply has `Parent-ID` equal to the analyzed post ID
- the canonical reply is authored by the `reply-agent` identity
- the generated-response row uses the analysis provider and model
- decoded `raw_response.source` on the generated-response row is
  `analysis_suggested_response`
- repeating `POST /api/analyze_post` for the same content does not post a
  duplicate reply
- `POST /api/generate_agent_reply` keeps its full compatibility response shape
  and idempotency behavior

