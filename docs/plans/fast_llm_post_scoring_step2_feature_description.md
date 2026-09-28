# Fast LLM Post Scoring Step 2 Feature Description

## Problem

Full post analysis is too broad and expensive when a consumer only needs one focused probability. The forum needs an independent fast scorer that applies an operator-defined rubric with a lower-cost model and uses deterministic signals as a clearly identified companion.

## User Stories

- As an operator, I want a post scored against a focused rubric by a separately selectable lesser model so that simple scoring does not require full post analysis.
- As a downstream agent or workflow, I want one bounded 0-1 probability and its source so that I can make a score-based decision without interpreting a broad analysis object.
- As an operator, I want replies scored with limited relevant context so that the score reflects the reply without sending unnecessary thread content.
- As an operator, I want deterministic exclusions, fallback, and comparison signals alongside LLM scoring so that obvious cases are cheap and unavailable models do not obscure the outcome.
- As an operator, I want to inspect the scoring request and result so that I can calibrate the rubric and evaluate the lesser model.

## Core Requirements

- The scorer evaluates one explicitly supplied rubric at a time; the rubric states exactly what a probability of 0 and 1 mean.
- A model-scored request contains only a short instruction and the target post text; a reply adds only bounded context necessary to interpret that reply.
- The model is instructed to return only one numeric probability in the inclusive range 0–1; invalid, unavailable, or failed results are reported as such rather than treated as a score.
- The fast scorer has an independently selectable lower-cost model and remains separate from full post analysis and agent-reply generation.
- Deterministic signals may supply hard exclusions, routing, fallback, or comparison results; every result identifies whether it came from the LLM or heuristics and never presents a heuristic result as model output.

## Shared Component Inventory

- Existing post-analysis API and post-analysis disclosure on thread-root and reply cards: do not extend in the initial feature, because their broad analysis contract is distinct from the focused fast score.
- Existing LLM provider configuration: extend as the canonical operator configuration surface so the lesser scoring model is independently selectable.
- Existing LLM exchange links on post cards and the private LLM-exchanges tool: reuse as the canonical operator audit surface for model-scored requests; heuristic-only results require no exchange.
- Reader-facing post cards and thread views: no new score display in the initial feature, because no reader-facing decision or explanation has been defined.

## Simple User Flow

1. A workflow requests a score for a post using its focused rubric.
2. The scorer applies any deterministic exclusion or routing signal.
3. For a model-scored post, it sends the compact instruction and target text, plus bounded reply context when applicable, to the selected lesser model.
4. The scorer returns the numeric probability or an explicit non-score outcome, with its source and relevant diagnostic status.
5. The workflow uses the result according to its own defined threshold; operators can inspect model exchanges and compare deterministic signals during calibration.

## Success Criteria

- A configured workflow receives exactly one valid 0–1 probability for successful model-scored root posts and replies, with no narrative model output.
- Captured model requests contain the target text and short rubric only, except for the defined bounded context on replies.
- The fast score can use a model selected independently of the full post-analysis model.
- Failures and disabled or missing model configuration produce explicit non-score outcomes and do not block or alter full post analysis.
- Every deterministic and model-scored outcome reports its source, and operators can inspect model exchanges to evaluate disagreements.
