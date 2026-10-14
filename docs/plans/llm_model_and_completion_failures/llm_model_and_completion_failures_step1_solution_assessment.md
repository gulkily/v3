> **Feature plan:** [Step 1](./llm_model_and_completion_failures_step1_solution_assessment.md) · [Step 2](./llm_model_and_completion_failures_step2_feature_description.md) · [Step 3](./llm_model_and_completion_failures_step3_development_plan.md) · [Step 4](./llm_model_and_completion_failures_step4_implementation_summary.md)

Debug mode: disabled

# LLM Model and Completion Failures Step 1 Solution Assessment

## Original Query

Please write Step 1 for fixing both of these issues.

## Understood Intent

Fix the two failures found while investigating missing analysis after `task-queue run`: moderation sends `openai/gpt-5-nano` to the direct OpenAI endpoint because a separate moderation override supersedes `gpt-5-nano`; agent replies use the correct model but exhaust their completion allowance without returning text. Make failed fulfillment visible in queue results so operators can distinguish processing from successful publication.

## Problem Statement

An incompatible moderation model override and an insufficient agent-reply completion allowance prevent useful results, while queue completion can conceal failed reply generation.

## Option A: Correct the current settings and increase the reply allowance

Repair the deployed moderation override, raise the fixed reply allowance, and retry the affected work using existing recovery behavior.

- Pros: Smallest immediate repair; can restore service quickly.
- Cons: The configuration template still supplies the problematic default; a larger fixed allowance may fail again, and queue results remain misleading.

## Option B: Repair defaults, configure reply limits, and report fulfillment outcomes

Make moderation inherit the main model unless deliberately overridden, correct the affected deployment, and give agent replies a configurable, bounded completion allowance validated with the configured model. Surface unsuccessful fulfillment in worker results and support deliberate retry after correction.

- Pros: Addresses both observed failures and their operational diagnosis; preserves intentional model overrides and controls spending.
- Cons: Requires coordinated configuration, generation, and recovery checks; a suitable allowance needs live validation and cannot guarantee every response succeeds.

## Option C: Add automatic model correction and adaptive retries

Translate model names according to provider and automatically retry exhausted replies with increasing allowances until a configured ceiling is reached.

- Pros: Can recover from common configuration mistakes and variable generation needs without operator intervention.
- Cons: Model-name rewriting may alter valid gateway identifiers; automatic retries add latency and cost and complicate duplicate prevention and failure diagnosis.

## Recommendation

Recommend **Option B** as one small operational vertical slice, retaining manual recovery rather than adding automatic model rewriting or budget escalation.

- Make absent or blank moderation overrides inherit the main model; stop introducing a provider-specific default in generated configuration. Preserve explicit custom identifiers and correct the known incompatible deployment override deliberately.
- Expose a bounded reply completion allowance and validate it against the observed exhaustion case. Report exhaustion distinctly; do not treat empty or truncated output as a successful reply.
- Reflect actual fulfillment failure in queue results, distinguish legitimate skips and already-published work, and allow affected requests to be retried after correction without duplicate publication.
- Complete the slice through ordinary moderation and requested-analysis flows: valid moderation response, published analysis, truthful failure feedback, and verified recovery. Live checks must demonstrate usable output; larger limits alone are not evidence of success.
- Keep scripture prompt wording, broader queue scheduling, and provider migrations outside this repair.
