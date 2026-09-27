# Fast LLM Post Scoring: Deterministic Heuristics Brainstorm

## Purpose

Use inexpensive, explainable rules alongside the dedicated fast LLM scorer to handle obvious cases, prioritize model calls, and provide a fallback when the model is unavailable.

## Possible Signals

- Text length and meaningful-token count: identify empty, near-empty, or unusually short submissions.
- Question and request markers: detect direct questions, help-seeking language, and explicit calls for a response.
- Spam-like patterns: repeated text, excessive links, repeated punctuation, all-caps text, or common solicitation phrases.
- Reply context: distinguish a reply that meaningfully refers to its parent from one that is generic or disconnected.
- Conversation state: recognize duplicate text, self-replies, deleted/hidden targets, and known agent-authored posts.
- Deterministic safety facts already available to the application: use them as hard exclusions where appropriate rather than asking the scorer to infer them.

## Roles Alongside the LLM

- Hard exclusions: skip scoring or force a known outcome for invalid, unavailable, or clearly ineligible posts.
- Cheap routing: send ambiguous posts to the LLM while allowing obvious low-value cases to bypass it.
- Fallback: return a clearly identified heuristic score when the LLM request fails or is not configured.
- Comparison signal: retain both scores to find disagreements, tune rules, and evaluate whether the lesser model is calibrated.

## Score Combination Ideas

- Rules first: apply hard exclusions, then use the LLM probability for all remaining posts.
- Confidence-aware fallback: prefer the LLM score when available; otherwise return the heuristic score with its source recorded.
- Guardrail band: let heuristics bypass the LLM only for extreme, well-understood cases; score the middle range with the LLM.
- Dual scoring: persist both values without combining them initially, so downstream policy remains based on the LLM until evaluation supports a change.

## Safeguards and Open Questions

- Keep rules narrow, documented, and independently testable; avoid presenting a heuristic as a model-derived probability.
- Record the score source, rule matches, and any LLM/heuristic disagreement for later evaluation.
- Define the precise score objective before selecting signals, thresholds, or a combination rule.
- Decide whether heuristic outcomes may make user-visible decisions or are initially limited to routing and observability.
