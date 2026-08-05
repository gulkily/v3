# Agent Reply Request Button Step 3 Development Plan

## Stage 1
- Goal: Define durable request state on the existing generated-response store.
- Dependencies: Approved Step 2.
- Expected changes: Extend `SqliteAgentReplyGenerationStore` status usage to include request/fulfillment states such as `requested`, `pending`, `posting`, `posted`, `skipped`, and `failed`; use the existing unique target post/content hash row and `request_context_json` for requester metadata, with no schema migration planned.
- Planned signatures: `requestForTarget(array $context, array $requestContext): array`; `claimNextRequested(int $limit = 1): array`; `markSkipped(string $postId, string $contentHash, string $reason): array`.
- Verification approach: Focused store tests for first request, duplicate request, existing posted row, failed/skipped row, and row-level claim behavior.
- Risks or open questions:
  - Stale claimed rows need a simple timeout/retry policy if a worker dies mid-run.
- Canonical components/API contracts touched: `SqliteAgentReplyGenerationStore`, `post_generated_responses` status contract.

## Stage 2
- Goal: Convert the manual HTTP path into a fast request-recording endpoint.
- Dependencies: Stage 1.
- Expected changes: Adapt `POST /api/generate_agent_reply` to authorize approved viewers, compute the current post content hash, reject `reply-agent` authored targets, create/request the durable generated-response row, and return a compatibility JSON response such as `generation_status=requested` or `already_posted` without provider work.
- Planned signatures: `private function handleGenerateAgentReply(string $method, array $query): void`; `private function agentReplyRequestResultForPost(array $post, array $viewerProfile): array`.
- Verification approach: API smoke tests for unauthorized, unapproved, approved request, duplicate request, agent-authored target, and existing posted response.
- Risks or open questions:
  - Existing raw callers of `/api/generate_agent_reply` will now enqueue rather than fulfill immediately.
- Canonical components/API contracts touched: `Application`, `/api/generate_agent_reply`, approved profile resolution.

## Stage 3
- Goal: Render request controls and existing request state on eligible cards.
- Dependencies: Stages 1-2.
- Expected changes: Extend thread/post render data so cards know whether current content has generated-response state; add a compact request button only for authorized viewers, human-authored posts, and rows with no request/generated-response state.
- Verification approach: Render/smoke tests for approved viewer button visibility, hidden unauthenticated/unapproved state, hidden `reply-agent` state, and hidden existing request/post state.
- Risks or open questions:
  - Card action rows are already dense, so copy and placement should stay minimal.
- Canonical components/API contracts touched: `templates/partials/post_card.php`, `templates/partials/thread_root_card.php`, thread/post render data.

## Stage 4
- Goal: Add browser behavior for requesting and displaying status.
- Dependencies: Stages 2-3.
- Expected changes: Extend existing post-analysis/agent-reply JS helpers to submit the request asynchronously, disable the button while requesting, hide it after success, and show requested/already-posted/skipped/failed feedback in the existing card feedback node.
- Verification approach: Browser-signing or JS normalization tests for request submission, duplicate-click prevention, feedback text, and agent reply link handling.
- Risks or open questions:
  - The page will not live-update after cron fulfillment unless a later stage adds polling.
- Canonical components/API contracts touched: `public/assets/post_analysis.js`, card feedback markup, `/api/generate_agent_reply` JSON result shape.

## Stage 5
- Goal: Extract the existing publish-only agent reply path behind a reusable service.
- Dependencies: Stage 1.
- Expected changes: Move current inline gate/generate/post behavior for posts with completed analysis behind an application service while preserving existing result shapes.
- Planned signatures: `AgentReplyFulfillmentService::publishForPost(array $post): array`.
- Verification approach: Focused service tests for completed analysis, gate rejection, suggested-response failure, posting success, and duplicate prevention.
- Risks or open questions:
  - Existing helper methods in `Application` are private, so extraction must stay narrow.
- Canonical components/API contracts touched: agent reply generation store, post analysis service/store, `AgentIdentityService`, `LocalWriteService`, reply gate/result helpers.

## Stage 6
- Goal: Add request fulfillment behavior for claimed rows.
- Dependencies: Stage 5.
- Expected changes: Fulfill one claimed request row by loading the target post, performing missing analysis when needed, publishing only when gates pass, and marking skipped/failed states durably.
- Planned signatures: `AgentReplyFulfillmentService::fulfillRequest(array $requestRow): array`.
- Verification approach: Focused service tests for missing analysis, analysis failure/config missing, gate skip, successful publish, and existing posted row.
- Risks or open questions:
  - Stale claimed-row recovery must stay simple enough for V1.
- Canonical components/API contracts touched: `AgentReplyFulfillmentService`, post analysis service/store, generated-response request status contract.

## Stage 7
- Goal: Add periodic fulfillment command that is safe under overlapping cron runs.
- Dependencies: Stages 1 and 6.
- Expected changes: Add `scripts/run_agent_reply_requests.php` with `--limit`, `--dry-run`, `--post-id`, `--repository-root`, and `--database-path`; claim queued rows before work; use `ExecutionLock` as an operational guard while relying on row-level claims for correctness.
- Verification approach: Command tests for no queued work, automatic duplicate avoidance, dry run, single post processing, overlapping claim simulation, and successful canonical reply creation.
- Risks or open questions:
  - Production cron cadence and timeout defaults should be documented after behavior is verified.
- Canonical components/API contracts touched: new CLI script, `ExecutionLock`, generated-response request claims, canonical write/read-model synchronization.

## Stage 8
- Goal: Update operator documentation and final regression coverage.
- Dependencies: Stages 1-7.
- Expected changes: Document the request/fulfillment split, sample cron entry, local dev invocation, and expected button states; keep cron/backfill/retry policy minimal for V1.
- Verification approach: Run targeted PHP tests plus the local smoke suite sections covering write API, rendering, agent reply generation, and the new command.
- Risks or open questions:
  - A richer retry/reset operator workflow remains out of scope.
- Canonical components/API contracts touched: `docs/runbooks/production_deploy.md`, `docs/examples/env.production.example`, Step 4 implementation summary later.
