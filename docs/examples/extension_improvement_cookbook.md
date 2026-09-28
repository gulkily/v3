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
