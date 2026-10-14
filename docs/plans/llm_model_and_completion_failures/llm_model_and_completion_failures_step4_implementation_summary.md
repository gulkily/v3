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

## Stage 3 - Truthful fulfillment outcomes

- Changes: Worker callbacks return outcomes; persisted failures are terminal queue failures, and generated/already-posted/skipped outcomes retain distinct progress/history details. Unavailable requests are not reported as publication. Configuration and exhaustion failures have safe actionable API/card/browser feedback. Unexpected worker exceptions retain bounded retries.
- Verification: Queue worker/command/generator suites: 30 passed. Selected lifecycle, posting failure, and invalid-budget API/card tests: 3 passed. JS syntax and diff checks passed.
- Notes: A stored failure needs explicit state recovery; replaying its task alone cannot generate again. Provider diagnostics remain available privately; public messages are bounded static descriptions.

## Stage 4 - Targeted explicit recovery

- Changes: Added locked `task-queue retry-agent-reply` and `run --task-id`; failed unpublished requests retain their mode and recover from interruption between reset and enqueue. Version/eligibility checks reject stale or published targets. Added target-only moderation `fast-score retry --run` using the existing scoring workflow and claim machinery.
- Verification: Queue/store/scoring suites: 69 passed. Additional recovery/target-selection tests and isolated retry CLI/publication flow: 25 passed. Coverage includes historical completed queue task with failed reply, repeat recovery, interrupted enqueue, stale hashes, claimed/published guards, and leaving unrelated work queued.
- Notes: No schema change. Targeted execution does not drain/reconcile unrelated tasks. Existing backfill budget controls remain separate.

## Stage 5 - Live verification and affected-request recovery

- Changes: Completed recovery guidance; aligned the provider diagnostic command with the configured completion allowance. Updated the previously failing queue smoke test to select the intended task instead of assuming it falls within the first two mixed queue entries. Targeted moderation reports unsuccessful/non-scoring outcomes with a nonzero exit.
- Verification: Broad focused regression run: 271 tests, 270 passed; the sole long-standing queue-order assertion passed after the targeted-test correction. Subsequent CLI/worker/generator checks: 23 passed. Changed PHP lint, JS syntax, and diff checks passed.
- Verification: Headless Chrome passed eight thread/post × desktop/mobile × configuration/exhaustion cases, checking both dynamic and stored feedback and absence of private diagnostic text.
- Verification: Four live provider calls total, within the six-call cap: isolated moderation returned a valid score; an isolated chooser-requested scripture reply published successfully; the original failed scripture request published successfully; targeted moderation for that original post returned probability 0.05. No allowance exceeded 8192, and no automatic escalation was used.
- Recovery: Post `thread-20261014064659-ff9da739` now has one agent reply, `reply-20261014122712-9089d6df`, retaining `scripture_oriented_analysis`. Queue task 252 completed; repeating its targeted run claimed zero tasks and made no call. The moderation item had been marked `post_unavailable` during the old broad queue smoke check; it was explicitly reset and rescored against the correct repository. A concurrent targeted moderation attempt respected the worker lock and made no call before retrying after reply completion.
- Notes: Application changes are on the feature branch and private configuration is corrected locally. No remote deployment or schema migration was performed. Private config backup and temporary isolated live/browser logs remain outside Git. Scripture prompt wording was unchanged.
