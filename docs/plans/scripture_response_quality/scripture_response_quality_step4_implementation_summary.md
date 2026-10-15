> **Feature plan:** [Step 1](./scripture_response_quality_step1_solution_assessment.md) · [Step 2](./scripture_response_quality_step2_feature_description.md) · [Step 3](./scripture_response_quality_step3_development_plan.md) · [Step 4](./scripture_response_quality_step4_implementation_summary.md)

Debug mode: enabled

# Scripture Response Quality and Latency Implementation Summary

Planning commit: `075f91e7`. Implementation is in progress; candidate settings are not released.

## Stage 1 - Isolated normal-flow measurement

- Changes: Copied the exact working-tree baseline prompts into frozen fixtures; added eight cases, source-review rubric, isolated application/repository setup, durable reservation-based call/spend ledger, and a real HTTP/browser/CLI baseline probe.
- Verification: `python tests/Support/test_scripture_evaluation.py` passed; browser probe passed against an isolated PHP server, creating a thread, selecting scripture analysis, running the ordinary queue CLI, and reloading to observe the canonical reply. Stub request took 190 ms; submission-to-visible completion took 593 ms. Diff check passed.
- Notes: This is a plumbing measurement, not a live latency result. Baseline browser discovery explicitly uses a reload; no hidden direct fulfillment. Every application database and reply-agent key directory is under the temporary copied application. No live provider calls have occurred.

## Stage 2 - Scoped generation candidates

- Changes: Added opt-in revised scripture guidance and a scripture-only reasoning setting; the default remains current guidance with provider-default reasoning. Other analysis modes and moderation payloads remain unchanged. Unsupported provider/model/effort combinations fail explicitly. Included the previously approved scope edit in the current prompt; the frozen baseline contains the same bytes.
- Verification: Generator/provider/configuration suites: 22 passed. Covered candidate routing, other-mode isolation, unchanged completion allowance, unsupported settings, and exhausted-output rejection. PHP lint and diff checks passed. The example quotation was verified against [Proverbs 21:5 KJV](https://www.biblegateway.com/passage/?search=Proverbs+21%3A5&version=KJV).
- Notes: Candidate guidance is selected only with `SCRIPTURE_RESPONSE_GUIDANCE=revised`; effort is opt-in through `SCRIPTURE_RESPONSE_REASONING_EFFORT`. No live calls or production configuration changes.
