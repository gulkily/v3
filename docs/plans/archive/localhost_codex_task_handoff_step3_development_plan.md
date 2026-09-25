# Localhost Codex Task Handoff Step 3 Development Plan

## Stage 1
- Goal: Define local eligibility and feature boundaries.
- Dependencies: Approved Step 2.
- Expected changes: Add a canonical helper for localhost/local-development detection and approved-user handoff authorization; keep production/non-local requests ineligible.
- Planned signatures: `private function viewerCanUseCodexHandoff(?array $viewerProfile): bool`; `private function requestIsLocalhost(): bool`.
- Verification approach: Smoke tests for approved localhost, unapproved localhost, approved non-local, and missing identity.
- Risks or open questions:
  - Proxy headers should not make production appear local unless explicitly trusted.
- Canonical components/API contracts touched: `Application`, viewer profile resolution, local runtime detection.

## Stage 2
- Goal: Add durable Codex handoff state.
- Dependencies: Stage 1.
- Expected changes: Add a lightweight local-only handoff store with statuses for requested, draft_ready, approved, rejected, running, failed, and completed; associate each handoff with origin post, requester, draft text, confidence summary, and timestamps.
- Planned signatures: `CodexHandoffStore::requestForPost(array $post, array $viewerProfile): array`; `CodexHandoffStore::updateStatus(string $handoffId, string $status, array $context = []): array`.
- Verification approach: Store tests for create, duplicate current-post request, status transition, refresh lookup, and invalid transition rejection.
- Risks or open questions:
  - A schema change may be justified; if avoidable, use a dedicated SQLite table only in the read/write model database.
- Canonical components/API contracts touched: read-model SQLite database, new handoff status contract.

## Stage 3
- Goal: Prepare the Codex handoff draft without starting execution.
- Dependencies: Stage 2.
- Expected changes: Add a service that converts the source post into a user story, FDP Step 1 draft, and implementation-confidence review; persist the draft as draft_ready.
- Planned signatures: `CodexHandoffDraftService::prepare(array $handoff, array $post): array`.
- Verification approach: Service tests with deterministic provider/stub output for complete draft, low-confidence draft, and draft failure.
- Risks or open questions:
  - First implementation should prefer deterministic/stubbed drafting unless an existing LLM provider contract cleanly fits.
- Canonical components/API contracts touched: FDP Step 1 format, post content context, existing LLM/provider conventions if reused.

## Stage 4
- Goal: Expose request, preview, approval, and rejection APIs.
- Dependencies: Stages 1-3.
- Expected changes: Add local-only approved-user endpoints to create a handoff, fetch its current state, approve the prepared draft, or reject it; approvals only succeed for draft_ready handoffs.
- Planned signatures: `private function handleCodexHandoff(string $method, array $query): void`; `private function handleCodexHandoffApproval(string $method, array $query): void`.
- Verification approach: API tests for forbidden access, create request, draft-ready response, approve, reject, duplicate approval, and non-local rejection.
- Risks or open questions:
  - API responses must not leak local paths or process details beyond approved localhost users.
- Canonical components/API contracts touched: `Application` routes, JSON response shapes, approved-user checks.

## Stage 5
- Goal: Render handoff controls and approval preview in the UI.
- Dependencies: Stage 4.
- Expected changes: Add a distinct Codex handoff action on eligible post/root cards, show durable status on refresh, and render a preview/approval surface for draft_ready handoffs.
- Verification approach: Render/smoke tests for visibility, hidden states, draft preview content, approve/reject controls, and refresh persistence.
- Risks or open questions:
  - Card action rows are crowded; keep the control compact and reserve detailed review for the preview surface.
- Canonical components/API contracts touched: `templates/partials/post_card.php`, `templates/partials/thread_root_card.php`, new or existing page/partial for preview.

## Stage 6
- Goal: Add browser behavior for handoff lifecycle actions.
- Dependencies: Stages 4-5.
- Expected changes: Add client behavior to request a draft, display request/draft/approval/rejection states, prevent duplicate clicks, and submit approve/reject actions asynchronously.
- Verification approach: JS or smoke coverage for request submission, state rendering, approval submission, rejection submission, and failure feedback.
- Risks or open questions:
  - Long draft preparation may need polling or refresh-first behavior if synchronous preparation is too slow.
- Canonical components/API contracts touched: public JS assets, handoff data attributes, handoff JSON response shape.

## Stage 7
- Goal: Record handoff lifecycle in activity.
- Dependencies: Stages 2-4.
- Expected changes: Extend activity indexing or direct local activity insertion so requested, draft_ready, approved, rejected, running, failed, and completed handoff events appear with origin post links.
- Verification approach: Activity smoke tests for event labels, author attribution, view filtering, and source/origin links.
- Risks or open questions:
  - Activity should summarize local automation without exposing private prompts, filesystem paths, or process output.
- Canonical components/API contracts touched: activity table/indexing, `templates/pages/activity.php`, source/origin link conventions.

## Stage 8
- Goal: Add the local Codex execution handoff after approval.
- Dependencies: Stages 2, 4, and 7.
- Expected changes: Add a localhost-only runner path or CLI command that claims approved handoffs, passes the approved draft to Codex, and records running/failed/completed outcomes without running from the initial request click.
- Planned signatures: `scripts/run_codex_handoffs.php --limit=1`; `CodexHandoffRunner::runApproved(array $handoff): array`.
- Verification approach: Command tests with a fake Codex executable, claim/idempotency tests, failure handling, and activity outcome assertions.
- Risks or open questions:
  - Executing Codex from PHP/web context is high risk; prefer a local runner command unless Step 4 explicitly approves inline local process launch.
- Canonical components/API contracts touched: local scripts, execution lock/claim semantics, handoff status contract, activity outcomes.
