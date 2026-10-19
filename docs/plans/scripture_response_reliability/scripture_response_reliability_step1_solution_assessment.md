> **Feature plan:** [Step 1](./scripture_response_reliability_step1_solution_assessment.md) · [Step 2](./scripture_response_reliability_step2_feature_description.md) · [Step 3](./scripture_response_reliability_step3_development_plan.md) · [Step 4](./scripture_response_reliability_step4_implementation_summary.md)

Debug mode: enabled

# Scripture Response Reliability — Solution Assessment

## Original Query

That would be a great answer. How do we change our feature so that this would have been the case?

## Revised Direction

The user selected `gpt-5-mini` and explicitly authorized switching from nano. The shared private `LLM_MODEL` setting is now mini; configuration validation passed. This selection does not establish measured answer quality or approve the remaining reliability changes.

## Understood Intent

Make the feature reliably produce the judgment demonstrated in the approved answer to the [departure-time post](http://127.0.0.1:8003/threads/thread-20261015203752-7b0dd6ba): distinguish actual deadlines from planned tasks, help the reader choose a responsible stopping point, and connect relevant scripture without turning the concern into a devotional survey. Preserve warranted depth, accurate quotations, optional expansion, and prompt visible completion.

## Problem Statement

The current writer disregards existing scope and quotation guidance often enough that prompt instructions and lower reasoning alone cannot establish reliable quality.

## Evidence

The [completed comparison](../scripture_response_quality/evaluation/report.md) found 6/16 quality passes with revised guidance and 5/16 with low reasoning; both had critical quotation errors. Low reasoning reduced completed-sample median latency to 8.94 seconds from 27.80 seconds, but one trial was interrupted. A more capable writer was not evaluated. The current prompt already prohibits catalogues and unsupported follow-up offers.

## Option A — Stronger writer centered on the reader's concern

Evaluate the user-selected `gpt-5-mini` for scripture replies and expansions. Make resolving the actual concern its primary objective; scripture supplies relevant understanding and reasons. For practical questions, identify the decision and constraints without inventing emotional or spiritual problems. Interpretive questions receive the requested context and competing readings.

- Pros: Targets the missing editorial judgment while retaining a single writing call and the verified delivery path.
- Cons: Better behavior and acceptable latency remain hypotheses; model memory can still produce inaccurate quotations.

## Option B — Stronger writer with verified quotations

Combine A with a bounded source-verification path: displayed verbatim wording and translation labels must agree with verified passage text. Clearly distinguish paraphrase, interpretation, and analogy. Unavailable source text must not become an invented quotation or a silently substituted translation.

- Pros: Addresses editorial quality and directly demonstrated quotation failures separately.
- Cons: Source availability, translation coverage, context handling, and useful recovery need explicit decisions. Matching wording cannot establish faithful interpretation.

## Option C — Bounded editorial revision

Add a separate editorial pass that can rebuild a draft around the original concern, retaining only passages and explanation that change understanding or action. Evaluate the final answer, including any quotation changes, before publication.

- Pros: Can correct misplaced emphasis and repetition that survive the writer's instructions.
- Cons: Adds waiting, cost, and recovery complexity; the editor can repeat the writer's errors. It requires its own measured comparison.

## Recommendation

Proceed with **B**, testing A's stronger writer as the single-call foundation. Keep C as a fallback if that foundation still fails. This explicitly reopens the previous plan's exclusion of a verified-source component; it does not authorize implementation yet.

- Treat the approved departure answer as a reference for judgment, not a mandatory template, word limit, or verse quota. Keep it and prior failures as visible regressions; freeze fresh held-out cases for selection.
- Define the supported quotation sources, requested-translation behavior, and unavailable/mismatched-source recovery before implementation. Do not silently patch passages in ways that change the surrounding claim.
- Deliver a complete vertical slice through existing request, publication, Expand, and explicit recovery paths. Preserve historical replies, permissions, moderation, and unrelated modes.
- Fix candidate identities, repeated initial/expansion cases, source checks, joint quality/latency gates, and a new call/spend budget before paid trials. The prior budget does not renew automatically. Keep failed/interrupted calls and verify anonymous review and full timing capture with stubs first.
