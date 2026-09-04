# Agent Reply Manual Trigger and Cron Plan

## Goal

Let operators intentionally trigger a `reply-agent` response for a specific post,
and let production run automated replies from cron when automation is enabled.

The intended production behavior is:

1. Browser-triggered automatic replies can remain disabled while latency is being
   investigated.
2. An authorized operator can manually trigger an agent reply for a specific
   post when needed.
3. A cron command can scan eligible posts and publish replies only when automatic
   replies are enabled.
4. All paths reuse the same analysis, gate, idempotency, and canonical posting
   semantics already used by `POST /api/generate_agent_reply`.

This plan keeps the single model-call design: post analysis creates
`engagement.suggested_response`, and agent reply publishing posts that stored
suggestion as `reply-agent`.

## Current Behavior

- `DEDALUS_AGENT_REPLIES_ENABLED=false` disables all agent reply publishing,
  including direct `POST /api/generate_agent_reply`.
- Newly created post pages render browser-side automatic work only when
  `DEDALUS_AGENT_REPLIES_ENABLED` is enabled.
- `POST /api/analyze_post` can analyze a post and, when enabled and gates pass,
  publish an agent reply in the same request.
- `POST /api/generate_agent_reply` is idempotent for a target post/content hash
  and posts from stored analysis when gates pass.
- There is no CLI worker that can safely run agent replies outside a browser
  request.

## Design Decisions

### Separate Automatic and Manual Controls

Add separate configuration concepts:

- `DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED`
- `DEDALUS_AGENT_REPLIES_MANUAL_ENABLED`

Keep `DEDALUS_AGENT_REPLIES_ENABLED` as a backward-compatible umbrella setting
for the initial implementation:

- If the new automatic setting is absent, automatic behavior falls back to
  `DEDALUS_AGENT_REPLIES_ENABLED`.
- If the new manual setting is absent, manual behavior falls back to
  `DEDALUS_AGENT_REPLIES_ENABLED`.
- Explicit new settings take precedence over the legacy umbrella setting.

Recommended production state during the latency investigation:

```text
DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED=false
DEDALUS_AGENT_REPLIES_MANUAL_ENABLED=true
```

This lets production avoid surprise latency while still allowing an intentional
operator-triggered reply.

### Define Trigger Modes

Introduce an internal trigger mode for reply publishing:

- `automatic`: used by browser work and cron; requires automatic replies enabled.
- `manual`: used by the explicit operator endpoint/command; requires manual
  replies enabled.

The trigger mode must be recorded in operational metadata where practical,
preferably in the generated-response `raw_response` JSON alongside the existing
`source: "analysis_suggested_response"` value.

### Preserve Gates by Default

Manual triggering should still use normal safety gates by default:

- no replies to `reply-agent`
- current analysis must be complete
- respondability gates must pass
- risk, moderation, and public-response gates must pass
- idempotency must prevent duplicate replies for the same post/content hash

Do not add a manual force override in this plan. If operators later need
force-publish behavior, that should be a separate plan because it changes safety
semantics.

## Implementation Slices

### 1. Configuration Helpers

Extend `ForumRewrite\Support\PrivateConfig` to load:

- `DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED`
- `DEDALUS_AGENT_REPLIES_MANUAL_ENABLED`

Replace the single `Application::agentRepliesEnabled()` decision with clearer
helpers:

```php
private function automaticAgentRepliesEnabled(): bool
private function manualAgentRepliesEnabled(): bool
```

Use the existing false parsing behavior: `0`, `false`, `no`, and `off` are
disabled.

Update call sites:

- rendered browser work uses `automaticAgentRepliesEnabled()`
- `POST /api/analyze_post` automatic publish uses
  `automaticAgentRepliesEnabled()`
- cron uses `automaticAgentRepliesEnabled()`
- manual trigger uses `manualAgentRepliesEnabled()`

Keep `agentRepliesEnabled()` temporarily only as a compatibility wrapper if it
reduces churn, but new code should call the more specific helpers.

### 2. Shared Reply Service

Move reply publishing logic out of `Application` into a reusable application
service so HTTP and CLI paths share one implementation.

Suggested class:

```text
src/ForumRewrite/Agent/AgentReplyPublisher.php
```

Suggested public methods:

```php
public function publishForPost(array $post, string $triggerMode): array
public function analyzeAndPublishForPost(array $post, string $triggerMode): array
```

`publishForPost()` should contain the current `agentReplyResultForPost()`
behavior:

- compute current post analysis context and content hash
- inspect `SqliteAgentReplyGenerationStore`
- return `already_posted`, `failed`, or `in_progress` when appropriate
- require completed current analysis
- apply reply gates
- create a generated-response row from stored `engagement.suggested_response`
- reserve posting
- ensure the `reply-agent` identity
- create the canonical reply through `LocalWriteService::createReply()`
- mark the generated-response row as posted

`analyzeAndPublishForPost()` should be used by cron when no current analysis
exists. It should run the same post-analysis service used by HTTP, then call
`publishForPost()` only when the resulting analysis is complete and gates pass.

### 3. Manual HTTP Trigger

Keep `POST /api/generate_agent_reply` as the manual trigger endpoint.

Change its configuration check from automatic-enabled to manual-enabled. When
manual replies are disabled, return the existing response shape:

```json
{
  "status": "ok",
  "generation_status": "not_recommended",
  "reason": "manual_config_disabled"
}
```

When manual replies are enabled, delegate to the shared publisher with
`triggerMode = "manual"`.

Authorization must be explicit. Use the existing approved-profile mechanism for
V1:

- require a resolved viewer profile
- require `is_approved = 1`
- return `403` for unauthenticated or unapproved callers

If the product needs a narrower operator role later, add a separate role/claim
before broadening this endpoint in production.

### 4. Manual UI Trigger

Add a visible manual trigger control only for approved viewers.

Recommended placement:

- post cards and thread root cards
- only when no existing agent reply has been posted for the current
  post/content hash
- hidden for posts authored by `reply-agent`

The control should call `POST /api/generate_agent_reply` with `post_id`.

UI behavior:

- show an in-progress state while the request runs
- show `View agent reply` when the response is `generated` or
  `already_posted`
- show a concise skipped/failure message for `not_recommended`,
  `analysis_required`, `failed`, or `in_progress`
- do not expose private generated-response internals to unauthorized viewers

The existing `public/assets/post_analysis.js` feedback renderer can likely be
reused after renaming or extracting the agent-reply feedback helpers.

### 5. Cron Worker

Add a CLI script:

```text
scripts/run_agent_replies.php
```

Suggested options:

```text
--limit=10
--dry-run
--post-id=<id>
--analyze-missing
--max-age-minutes=<n>
--repository-root=<path>
--database-path=<path>
```

Default behavior:

- exit successfully with a clear message when automatic replies are disabled
- scan candidate posts from the read model in ascending sequence order
- skip posts authored by `reply-agent`
- skip posts with existing posted, pending, posting, or failed reply rows
- skip posts whose current analysis is missing unless `--analyze-missing` is
  present
- publish only when current analysis exists, is complete, and gates pass
- stop after `--limit` published or attempted candidates, whichever is clearer
  in the implementation summary

When `--post-id` is provided, process only that post. This is useful for
operator testing from the shell while still using automatic-mode configuration.

Use `ForumRewrite\Support\ExecutionLock` around the worker to avoid overlapping
cron invocations. The lock should share the same lock file family as read-model
and write operations, for example:

```text
<database-directory>/forum-rewrite.lock
```

### 6. Candidate Selection

Candidate selection should be conservative in V1.

Include posts where:

- `posts.is_hidden = 0`
- author is not `reply-agent`
- post has no current generated-response row with `agent_post_id`
- post has no generated-response row in `pending`, `posting`, or `failed`
- post has current complete analysis, or `--analyze-missing` was supplied

Prefer processing newer unhandled posts after older ones only if there is a
product reason. Otherwise use `sequence_number ASC` for deterministic behavior.

Do not retry failed generated-response rows from cron in V1. Retrying failed
rows needs a separate policy for backoff, retry count, and operator visibility.

### 7. Static Artifacts and Read Model Freshness

After cron creates canonical replies, production needs those replies to appear
in the read model and static pages.

For V1, pick one operational contract:

- Cron calls the existing incremental update/write path if `LocalWriteService`
  already keeps the read model current.
- If not, document that the deployment cron sequence must run:

```bash
php scripts/run_agent_replies.php --limit=10 --analyze-missing
php scripts/rebuild_read_model.php
php scripts/build_static_artifacts.php
```

Prefer the first option if the write service already updates or invalidates
derived state safely.

### 8. Observability

The cron command should print a concise summary:

- automatic enabled or disabled
- scanned candidate count
- analyzed count
- generated count
- already posted count
- skipped count by reason
- failed count by failure code
- elapsed seconds

Avoid printing generated reply text or provider raw responses.

Manual HTTP responses should preserve the existing compatibility response shape
for `/api/generate_agent_reply`.

## Required Verification

Add or update tests in `tests/WriteApiSmokeTest.php` and focused unit tests as
needed:

- automatic disabled and manual enabled: browser render emits no automatic
  `data-agent-reply-work`
- automatic disabled and manual enabled: approved viewer can call
  `/api/generate_agent_reply` and publish one reply when gates pass
- manual disabled: `/api/generate_agent_reply` returns
  `manual_config_disabled`
- unapproved viewer cannot manually trigger a reply
- cron exits without publishing when automatic replies are disabled
- cron publishes eligible replies when automatic replies are enabled
- cron does not publish duplicate replies on repeated runs
- cron skips `reply-agent` authored posts
- cron skips failed, pending, and posting generated-response rows
- cron with `--analyze-missing` analyzes a missing candidate before publishing
- cron without `--analyze-missing` skips missing-analysis candidates
- generated-response metadata records the trigger mode when available

## Documentation Updates

Update:

- `docs/runbooks/production_deploy.md`
- `docs/examples/env.production.example`
- `docs/examples/secrets.php.example`

Document the recommended production setting during the latency investigation:

```text
DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED=false
DEDALUS_AGENT_REPLIES_MANUAL_ENABLED=true
```

Document a sample cron entry:

```cron
*/5 * * * * cd /srv/forum-rewrite/app && php scripts/run_agent_replies.php --limit=10 --analyze-missing >> /var/log/forum-agent-replies.log 2>&1
```

Document that cron should run as the same deployment user that can write the
canonical repository, SQLite database, private `reply-agent` key directory, and
static artifact directory.

## Open Questions

- Should manual triggering require only approved status, or a narrower operator
  role?
- Should cron analyze missing posts by default, or should analysis remain a
  separate scheduled job?
- Should manual triggering be available on every post card, or only on a
  moderation/operator surface?
- Should failed generated-response rows have a manual retry/reset workflow?
- Should `DEDALUS_AGENT_REPLIES_ENABLED` eventually be removed after the split
  flags have been deployed long enough?
