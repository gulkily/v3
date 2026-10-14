> **Feature plan:** [Step 1](./llm_model_and_completion_failures_step1_solution_assessment.md) · [Step 2](./llm_model_and_completion_failures_step2_feature_description.md) · [Step 3](./llm_model_and_completion_failures_step3_development_plan.md) · [Step 4](./llm_model_and_completion_failures_step4_implementation_summary.md)

Debug mode: disabled

# LLM Model and Completion Failures Step 3 Development Plan

## Completion Contract

- Moderation and chooser-requested analysis produce a valid score and one published reply; queue/API/page outcomes agree on failure and recovery.
- Add `AGENT_REPLY_MAX_COMPLETION_TOKENS`: default 8192, accepted integer range 1–32768. Application limits require provider validation; no automatic escalation.
- Release requires regression/live checks and targeted recovery. Preserve deployment settings for rollback; exclude secrets from commits. No migration.

## Key Risks

- **High risk:** Recovery can duplicate publication or replay unrelated work. Test version-checked recovery and interrupted enqueue before live use.
- **High risk:** Queue completion currently hides returned failures. Map outcomes before changing retries; configuration/exhaustion failures require operator action.
- Overrides and larger budgets can increase failures or cost. Preserve identifiers; validate bounds. Cap live checks at six calls of at most 8192 completion tokens; stop on failure.

## Stage 1

- Goal: Restore predictable moderation model selection (about one hour).
- Dependencies: Step 3 approval, feature branch, organized plans, and planning commit.
- Expected changes: Inherit missing/blank moderation models; update defaults/guidance and correct the known deployment override.
- Verification approach: Config/schema/writer tests cover inheritance and explicit/custom overrides; inspect effective settings.
- Risks or open questions: Preserve intentional overrides; compare effective configuration before and after correction.
- Canonical components/API contracts touched: `FastScoringConfig`, `PrivateConfigSchema`, private-config writer and setup, moderation configuration reference.

## Stage 2

- Goal: Bound reply generation and reject exhausted output (about one hour).
- Dependencies: Stage 1 committed.
- Expected changes: Wire the setting into `AgentResponseGenerator` with optional constructor parameter `int $maxCompletionTokens = 8192`. Reject exhaustion before accepting text; propagate its failure code.
- Verification approach: Test bounds, invalid settings without calls, empty/partial exhaustion, and successful responses.
- Risks or open questions: Check exhaustion before partial text for both existing provider formats.
- Canonical components/API contracts touched: Configuration schema/writer, `PostWorkflowService`, generator, text decoder/provider diagnostics, fulfillment failure contract.

## Stage 3

- Goal: Make worker and reader feedback truthful (about one hour).
- Dependencies: Stage 2 committed.
- Expected changes: Return outcome arrays from worker callbacks; distinguish publication, duplicates, skips, unavailable requests, and failures. Block configuration/exhaustion retries; expose safe feedback.
- Verification approach: Test counts, repeated runs without calls, and consistent thread/post feedback.
- Risks or open questions: Distinguish unavailable requests from publication; preserve transient-error policy.
- Canonical components/API contracts touched: Task worker, queue runner/history/status and runtime views, request/status API, shared post cards and feedback script.

## Stage 4

- Goal: Enable deliberate recovery of selected work (about one hour).
- Dependencies: Stage 3 committed.
- Expected changes: Add reply retry by post ID/content hash with mode, eligibility, version, and publication guards. Reconcile terminal tasks; reuse moderation retry and provide target-only execution.
- Verification approach: Store/CLI tests cover repeated retry, concurrent claims, changed content, already-posted work, interrupted enqueue, and historical completed-task/failed-reply pairs.
- Risks or open questions: Test idempotence and interrupted enqueue across the two stores.
- Canonical components/API contracts touched: Reply store recovery operation `retryRequestedForTarget(string $postId, string $contentHash): array`, queue store/locking, operator commands, existing moderation retry.

## Stage 5

- Goal: Verify the complete repair and recover the affected request (about one hour).
- Dependencies: Stages 1–4 committed; focused checks pass.
- Expected changes: Finish guidance/summary; validate isolated live flows before recovering identified failures.
- Verification approach: Test config, providers, scoring, reply generation, queue, CLI, and API suites; check thread/post failure feedback. Live checks must yield valid moderation and usable analysis, then show one publication after recovery.
- Risks or open questions: Live failure blocks release; retain evidence. Rollback never resets published records.
- Canonical components/API contracts touched: Existing worker, moderation/reply commands, LLM exchange diagnostics, shared reader surfaces, CLI and recovery documentation.
