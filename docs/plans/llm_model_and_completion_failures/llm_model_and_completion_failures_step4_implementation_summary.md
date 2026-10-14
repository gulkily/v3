> **Feature plan:** [Step 1](./llm_model_and_completion_failures_step1_solution_assessment.md) · [Step 2](./llm_model_and_completion_failures_step2_feature_description.md) · [Step 3](./llm_model_and_completion_failures_step3_development_plan.md) · [Step 4](./llm_model_and_completion_failures_step4_implementation_summary.md)

Debug mode: disabled

# LLM Model and Completion Failures Implementation Summary

## Stage 1 - Moderation model inheritance

- Changes: Missing/blank moderation model overrides inherit the main model; generated configuration defaults to inheritance, preserving explicit overrides. Corrected the known private deployment override, with a permissions-restricted backup outside Git.
- Verification: Config/schema/writer suites: 13 passed. Effective deployment now resolves to `openai / gpt-5-nano`. Diff whitespace check passed.
- Notes: No keys or private configuration were committed; no live calls made in this stage.

## Stage 2 - Bounded generation and exhaustion

- Changes: Added validated reply completion setting (8192 default, 1–32768); wired application generation and config templates. OpenAI-compatible length exhaustion and Anthropic max-token stops reject partial/empty text before decoding success; fulfillment records `completion_limit_exhausted`.
- Verification: Generator, provider, schema, and config command suites: 25 passed. Generator rerun after explicit-null validation: 7 passed. Diff check passed.
- Notes: Limits are application policy; live-provider behavior will be validated in Stage 5. Invalid values make no calls.
