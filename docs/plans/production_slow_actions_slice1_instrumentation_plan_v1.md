# Production Slow Actions Slice 1 Instrumentation Plan V1

This plan breaks out Slice 1 from `production_slow_actions_client_responsiveness_plan_v1.md`. The goal is to measure the full user-perceived path before changing behavior, so the next optimization work targets the actual production bottlenecks.

## Goal

Add enough server and browser timing to answer, for every slow reported action:

- did the browser spend time preparing identity, normalizing input, or rendering feedback before the request?
- did the request wait on the global write lock?
- did server time go to validation, canonical reads, git, read-model work, artifact invalidation, external providers, or response rendering?
- did the user experience slow redirects or post-response reconciliation?

This slice should not introduce optimistic UI behavior yet. It should only add observability with minimal behavior risk.

## Non-Goals

- Do not change write semantics, lock timeout behavior, git commit behavior, read-model update behavior, or static artifact invalidation.
- Do not add a new production telemetry backend in this slice.
- Do not expose privileged analysis details or raw request contents in timing/debug output.
- Do not block later optimistic UI work on perfect instrumentation coverage for every endpoint.

## Current State

- `LocalWriteService` already returns `timings` for common write phases such as `write_file`, `git_add`, `git_commit`, `git_rev_parse`, `read_model_incremental_*`, fallback rebuild phases, `artifact_invalidate`, and `total`.
- `Application::serverTimingHeaders()` already emits valid timing names as a `Server-Timing` header.
- `Application::handleAnalyzePost()` already records a more detailed non-write timing chain.
- `ExecutionLock::withExclusiveLockTimed()` exposes `lock_wait`, and `LocalWriteService` merges it into successful canonical write timings.
- Write endpoints now merge application-level pre-writer timings with write-service timings. Handler-level `total` covers the full request handler, while writer-internal `total` is preserved as `write_total`.
- Provider-backed post analysis now returns an `external_provider` timing bucket, and the Dedalus agent reply generator attaches the same bucket when used directly.
- Browser-side action duration remains mostly invisible. Reaction code shows progress messages, but it does not mark identity preparation, fetch timing, or first visible feedback.

## Timing Names

Use lowercase snake case so existing `Server-Timing` filtering accepts each metric.

Server timing names:

- `request_data`
- `viewer_profile`
- `identity_inspect`
- `canonical_read`
- `lock_wait`
- `write_file`
- `git_add`
- `git_commit`
- `git_rev_parse`
- `read_model_incremental_update`
- `read_model_incremental_*`
- `read_model_*_fallback`
- `artifact_invalidate`
- `external_provider`
- `response_render`
- `write_total`
- `total`

Browser mark/measure names:

- `forum_action_start`
- `forum_identity_start`
- `forum_identity_ready`
- `forum_first_feedback`
- `forum_fetch_start`
- `forum_response_received`
- `forum_reconcile_complete`
- `forum_action_complete`

When a browser action has a specific type, include it in the detail object or measure label, for example `apply_thread_tag`, `apply_post_tag`, `compose_reply`, or `compose_thread`.

## Implementation Slices

### Slice 1A: Lock Wait Timing

Status:

- implemented
- `ExecutionLock::withExclusiveLock()` remains as a compatibility wrapper
- `ExecutionLock::withExclusiveLockTimed()` returns the callback result and `lock_wait`
- `LocalWriteService` write methods merge `lock_wait` into returned timing arrays

Goal:

- expose time spent waiting to acquire the global lock without changing lock behavior

Work:

- add a low-risk way for `ExecutionLock` to report wait duration
- preferred shape: add `withExclusiveLockTimed(callable $callback): array` returning both callback result and `lock_wait`
- keep existing `withExclusiveLock()` as a compatibility wrapper
- update `LocalWriteService` write methods to merge `lock_wait` into returned `timings`
- preserve timeout behavior and error messages

Important detail:

- `lock_wait` should measure from the first lock attempt until successful acquisition, not callback execution time.
- If the lock is acquired immediately, report `0.0` or the measured near-zero duration.

Candidate files:

- `src/ForumRewrite/Support/ExecutionLock.php`
- `src/ForumRewrite/Write/LocalWriteService.php`
- `tests/LocalAppSmokeTest.php` or a focused lock test

Verification:

- uncontended writes include `lock_wait`
- a contended lock test proves `lock_wait` is greater than zero
- existing lock timeout tests continue passing

### Slice 1B: Normalize Write Endpoint Timing Coverage

Status:

- implemented
- write-like API handlers now include `request_data`, `viewer_profile`, `target_profile`, writer timings, `write_total`, and full handler `total` where applicable
- account key and approval redirects now include `Server-Timing`
- expected pre-writer validation failures now return timing headers where practical

Goal:

- make timing headers available for every slow-capable write endpoint and redirect

Work:

- ensure these success paths return `Server-Timing`:
  - `POST /compose/thread`
  - `POST /compose/reply`
  - `POST /api/create_thread`
  - `POST /api/create_reply`
  - `POST /api/apply_thread_tag`
  - `POST /api/apply_post_tag`
  - `POST /account/key`
  - `POST /api/link_identity`
  - `POST /profiles/{slug}/approve`
  - `POST /api/approve_user`
  - `POST /api/analyze_post`
  - `POST /api/generate_agent_reply`
- add missing pre-writer phase timings where they are materially outside `LocalWriteService`:
  - `request_data`
  - `viewer_profile`
  - target profile lookup for approval
  - post lookup and generation status checks for agent reply
- include timing headers on expected validation/error responses where feasible, especially identity-missing and validation failures before the writer is invoked
- keep response bodies unchanged unless tests require a minimal update

Candidate files:

- `src/ForumRewrite/Application.php`
- `tests/ApplicationServerTimingTest.php`
- `tests/LocalAppSmokeTest.php`

Verification:

- existing server timing formatting tests pass
- new tests assert timing headers on representative API success responses
- approval and account key redirects include timing headers when successful
- validation errors that occur after timing starts include `Server-Timing`

### Slice 1C: External Provider Timing

Status:

- implemented
- `PostAnalysisService` measures analyzer execution and returns `external_provider` on success and provider failure
- `DedalusPostAnalyzer` measures the actual provider HTTP call and returns that value as `external_provider`
- `DedalusAgentReplyGenerator` also returns `external_provider`; the current `/api/generate_agent_reply` path still primarily reports the whole `agent_reply` flow because it usually reuses analysis-derived response text

Goal:

- distinguish model-provider latency from local analysis/generation work

Work:

- wrap Dedalus post-analysis provider calls with an `external_provider` timing
- wrap agent reply generation provider calls with `external_provider` or `agent_external_provider` if both can appear in one response
- avoid leaking provider request payloads, response text, or privileged analysis details
- preserve existing cached-analysis behavior and timing names

Candidate files:

- `src/ForumRewrite/Analysis/PostAnalysisService.php`
- `src/ForumRewrite/Analysis/DedalusPostAnalyzer.php`
- `src/ForumRewrite/Agent/DedalusAgentReplyGenerator.php`
- `src/ForumRewrite/Application.php`

Verification:

- cached analysis can still report fast local timings
- provider-backed analysis/generation reports an external-provider bucket
- tests can use stub providers or reflection-level assertions without real network calls

### Slice 1D: Browser Action Performance Marks

Goal:

- measure user-perceived duration around frequent interactions without changing UI behavior

Work:

- add a small browser timing helper in an existing asset or a new `public/assets/action_timing.js`
- use `performance.mark()` and `performance.measure()` when available
- add marks to reaction actions first:
  - click/action start
  - identity preparation start
  - identity ready
  - first feedback paint path
  - fetch start
  - response received
  - reconciliation complete
  - action complete/error
- add marks to compose submit preparation where current JS already owns normalization and draft handling
- keep this passive:
  - no beacon submission by default
  - optionally print compact summaries only when a debug flag is present, for example `?debug_timing=1` or `localStorage.forum_debug_timing=1`

Candidate files:

- `public/assets/thread_reactions.js`
- `public/assets/browser_signing.js`
- `templates/layout.php` if a new shared asset is added
- `tests/BrowserSigningNormalizationTest.php` if existing JS harnesses need stubs

Verification:

- existing JS tests still pass
- timing helper no-ops cleanly when `performance` is unavailable
- debug output contains action type and durations, not form bodies, post bodies, public keys, private keys, or analysis content

### Slice 1E: Slow Sample Debug Format

Goal:

- make local and production debugging consistent without adding infrastructure

Work:

- define a compact event shape for browser debug output:
  - `action`
  - `status`
  - `duration_ms`
  - `identity_ms`
  - `network_ms`
  - `server_timing`
  - `error_kind` when applicable
- parse the `Server-Timing` response header in browser timing helper when available
- print only when debug timing is explicitly enabled
- document how to enable and collect a slow sample

Candidate files:

- `public/assets/thread_reactions.js`
- `public/assets/browser_signing.js`
- this plan or a short runbook note if the workflow needs more detail

Verification:

- a manual slow sample includes both browser duration and parsed server buckets
- debug output does not include sensitive authored content

## Recommended Implementation Order

1. Implement `lock_wait` timing in `ExecutionLock` and `LocalWriteService`.
2. Extend `Server-Timing` coverage for approval and identity-linking success paths.
3. Add pre-writer timings for reaction viewer-profile lookup and approval profile lookup.
4. Add browser timing helper and reaction marks.
5. Add compose-submit marks only where current JS already intercepts or prepares the submit.
6. Add external-provider timing if it can be done without broad provider interface churn; otherwise record it as the first follow-up from this slice.

## Acceptance Criteria

This slice is complete when:

- representative production write actions include `lock_wait` in `Server-Timing`
- slow-capable write endpoints consistently expose timing on success
- expected pre-writer failures include timing when practical
- reaction clicks can produce a browser-side debug sample showing identity, network, server, and reconciliation duration
- instrumentation is passive by default and does not submit telemetry externally
- tests cover timing header formatting, lock wait reporting, and at least one newly instrumented endpoint

## Risks

- Adding timing return shapes around locks could accidentally alter exception propagation. Keep `withExclusiveLock()` unchanged for existing callers and add a timed variant.
- Too many `Server-Timing` fields can become noisy. Keep names stable and use the existing formatter filtering.
- Browser debug output can leak sensitive data if event payloads include request bodies. Only log durations, action names, status, and parsed timing buckets.
- Provider timing may require small interface changes. If that becomes invasive, defer provider-specific timing rather than expanding this slice.

## Follow-Up After Slice 1

Use collected data to choose the next optimization:

- high `lock_wait`: reduce global lock hold time or queue writes differently
- high browser identity time: cache or pre-warm identity preparation
- high git time: inspect repository size, hooks, filesystem, and commit strategy
- high read-model fallback time: fix incremental invalidation or stale-marker causes
- high redirect/render time: move compose/reaction flows to fetch and optimistic rendering
- high provider time: turn analysis and agent replies into clearer background jobs
