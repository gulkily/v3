# Extension and Improvement Cookbook

This cookbook helps developers turn the site’s existing facilities into safe, practical extensions. It is a guide for planning a feature, not a promise that an example can be copied verbatim into production.

## What this covers

- Per-post agent assistance, such as a structured follow-up or a proposed reply.
- Queued or scheduled work over more than one post.
- Developer workflows that turn community input into a reviewable handoff.

## How to use it

1. Choose a recipe with the trigger and outcome closest to the desired feature.
2. Use the shared pattern to identify the existing facilities and safety boundaries that apply.
3. Adapt the recipe through the project’s Feature Development Process before implementation.

The recipes treat model classifications as advisory. A feature that creates canonical content must make its review or publishing guard explicit, preserve source provenance where relevant, and retain an operator disable path.

## Shared extension pattern

Use this lifecycle for every recipe:

1. Start with a narrow trigger, such as a new post, an explicit user request, or a scheduled sweep.
2. Apply deterministic eligibility checks before requesting model work.
3. Keep work and its current result durable and private when it may be retried or audited.
4. Bound model work by scope, cost, and time; do not make ordinary publishing wait for it.
5. Return a draft, reviewed outcome, or explicitly guarded canonical write.
6. Preserve a way to inspect, limit, retry, or disable the workflow.

## Choose the existing facility

| Need | Start with |
| --- | --- |
| Analyze or draft a response for one post | [Agent reply analyze/publish contract](../specs/agent_reply_one_step_analyze_publish_contract_v1.md) |
| Add an inexpensive advisory moderation signal | [Fastmod](../reference/fast_post_scoring.md) |
| Run bounded deferred or batch work | [`v3` task-queue commands](../reference/v3_cli.md#manage-the-background-task-queue) |
| Turn a community request into developer work | [`v3` Codex-handoff commands](../reference/v3_cli.md#run-approved-codex-handoff-requests) |
| Configure, audit, or operate LLM-backed work | [production LLM and agent-work guidance](../runbooks/production_deploy.md#llm-provider-config) |

Use a per-post workflow when one post supplies enough context. Use a queued workflow when provider work must not delay publishing or when it examines multiple posts. Use a handoff when the intended result is a developer decision or implementation task rather than a user-facing reply.

## Recipe: Offer an NVC reply for a high moderation signal

**Use when:** a post has a current, high Fastmod score and a community wants a constructive response option.

- **Trigger and eligibility:** Start after the score is current for the post content and rubric. Treat the threshold as an explicit local policy, then apply the normal response-safety and loop-prevention checks before any reply work.
- **Outcome:** Draft a nonviolent-communication-style reply that names observable content, its possible effect, and a constructive next step. Preserve the original post; never rewrite it or represent the score as a moderation decision.
- **Guard:** Begin with an operator or moderator review. Automatic publishing, if ever enabled, needs its own explicit feature flag and the existing guarded agent-reply path.
- **Operations:** Keep the scoring result, reply state, and model exchange auditable; bound retries and provide a disable path.
- **Reuse:** [Fastmod](../reference/fast_post_scoring.md), the [agent-reply contract](../specs/agent_reply_one_step_analyze_publish_contract_v1.md), and [agent-reply operations](../runbooks/production_deploy.md#automatic-agent-replies).

## Recipe: Turn a short bug report into a structured follow-up

**Use when:** a post appears to report a bug but lacks reproduction details needed to investigate it.

- **Trigger and eligibility:** Start from an explicit bug tag, moderator request, or conservative classifier result. Skip posts with enough existing detail or content that should not be sent to the configured model.
- **Outcome:** Draft a reply organized as steps to reproduce, expected behavior, actual behavior, environment/version, and open questions. Label assumptions rather than inventing facts.
- **Guard:** Show the draft for review before posting; the author or operator decides whether it is relevant and accurate.
- **Operations:** Keep the result tied to the current post content, avoid duplicate follow-ups, and retain the normal audit and disable controls.
- **Reuse:** the [agent-reply contract](../specs/agent_reply_one_step_analyze_publish_contract_v1.md), [`v3` agent-reply diagnostics](../reference/v3_cli.md#show-agent-reply-diagnostics), and [LLM operations guidance](../runbooks/production_deploy.md#llm-provider-config).

## Recipe: Aggregate high-signal information into a reviewed synthesis

**Use when:** a community wants a periodic digest of independently useful posts rather than an immediate reply to one post.

- **Trigger and eligibility:** Use a bounded queued sweep and a clearly defined high-signal rubric. Select only current results, exclude already-consumed sources, and gather related posts before drafting.
- **Outcome:** Draft a new synthesis post that links every source, distinguishes common themes from disagreement, and states what remains uncertain.
- **Guard:** Require human review before publishing. Do not imply consensus merely because several posts were selected, and do not publish a duplicate digest.
- **Operations:** Limit each run by post count and cost; keep source selection and the draft auditable; allow the sweep to be paused or disabled.
- **Reuse:** [`v3` task-queue commands](../reference/v3_cli.md#manage-the-background-task-queue), [Fastmod’s bounded-work model](../reference/fast_post_scoring.md#batch-scoring), and [LLM operations guidance](../runbooks/production_deploy.md#llm-provider-config).

## Recipe: Turn a feature proposal into a reviewable development handoff

**Use when:** a post proposes a product or technical change that deserves a structured feature request or development review.

- **Trigger and eligibility:** Start from an explicit user request, an appropriate tag, or a conservative proposal classifier. Check for related existing requests before creating a new handoff.
- **Outcome:** Draft a feature-request post or Codex handoff that preserves the source link, problem, intended benefit, constraints, unanswered questions, and possible duplicates.
- **Guard:** Require a developer or moderator to approve the handoff or publication. The workflow must not silently create a roadmap commitment or change the original author’s words.
- **Operations:** Keep the origin, review decision, and resulting work auditable; expose rejection and retry paths.
- **Reuse:** [`v3` Codex-handoff commands](../reference/v3_cli.md#run-approved-codex-handoff-requests), the [Feature Development Process](../fdp/README.md), and [LLM operations guidance](../runbooks/production_deploy.md#llm-provider-config).

## Recommended next recipes

- **Duplicate/related-post concierge (recommended):** draft links to the closest earlier discussion and summarize the difference.
- Claim/evidence extractor: identify factual claims and draft a neutral verification checklist.
- **Decision log builder (recommended):** draft the decision, rationale, dissent, owner, and follow-ups from a converged thread.
- **FAQ candidate generator (recommended):** identify recurring questions and draft an FAQ entry with source links.
- Accessibility/plain-language companion: offer an author-approved clearer, shorter version of dense text.
- Thread title/tag suggester: propose a concise title and normalized tags for author approval.
- Onboarding responder: draft a welcoming first response with relevant guides and discussions.
- Stale issue follow-up: draft a status check on unresolved bug or feature threads from visible activity.
- Contradiction finder: draft a neutral note when new content may conflict with earlier decisions or documentation.
- Release-note synthesizer: turn completed development discussions into a reviewed release-note draft.
