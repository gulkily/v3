> **Feature plan:** [Step 1](./fastmod_meta_score_step1_solution_assessment.md) · [Step 2](./fastmod_meta_score_step2_feature_description.md) · [Step 3](./fastmod_meta_score_step3_development_plan.md) · [Step 4](./fastmod_meta_score_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

As an operator, it would be nice if I could combine multiple scores into fastmod, and for there to be some kind of dialog for it that allows me to enable/disable certain ones.

Right now, I want one additional score that identifies whether it's a meta post (asking for a feature request or reporting a bug or asking for help).

## Revised Direction — User Constraints

- One LLM call per fastmod evaluation returns multiple scores together.
- Compose its rubric prompt from repository text snippets: a shared task introduction followed by a list of rubrics. Initially include the existing moderation rubric and one meta rubric for feature requests, bug reports, and help requests.
- Return comma-separated numeric scores in a fixed, explicitly declared rubric order (Option B, selected by the user).
- The harness sanitizes and validates output before accepting scores.
- The operator dialog enables/disables any rubric; disabled rubric templates are omitted from the prompt and response contract. Require at least one enabled sub-rubric, with no mandatory individual rubric.
- Each rubric lives in its own repository `.txt` template; keep its text compact, ideally one bullet. Use a shared introduction for common task and output instructions. The design must allow adding/removing rubrics without assuming that hostility/moderation or meta always exists.
- Fastmod only records each returned score. No aggregate, filtering, ranking, triggers, or automatic actions are included.
- Meta means catch-all support concerning the platform, the instance, or users’ interactions with the application and its interfaces, including feature requests, bug reports, and help requests.
- Overlap is intentional: a post may score highly on both the existing hostility/moderation rubric and meta. Compacting the existing rubric should preserve its intended coverage.

## Problem

Fastmod needs several independently defined scores from one evaluation, with reusable prompt text and reliable harness validation.

## Option A — Compact named JSON

Assemble the introduction and rubric snippets into one prompt and return all requested scores as a small named JSON object in one call.

- Pros: Reuses the current structured-output provider; explicit score identity; harness can validate exact keys, numeric types, and ranges.
- Cons: More output punctuation and tokens than positional numbers; schema and rubric identities must stay aligned.

## Option B — Comma-separated numeric scores

Use the same composed prompt and one call, returning numbers in an explicitly declared rubric order.

- Pros: Very short output and simple numeric payload.
- Cons: Wrong ordering can silently misattribute valid numbers; harness must enforce count, order contract, numeric syntax, and ranges. Current provider expects structured output, so compatibility needs investigation.

## Recommendation and Open Questions

Adopt the user's single-call, snippet-composed design with Option B, as selected by the user: comma-separated numeric scores in an explicitly declared rubric order. The harness must validate the expected number of fields, numeric syntax, finite values, and permitted ranges, and map positions to stable rubric identities. Reject malformed output rather than silently clamping values or substituting zero. Provider compatibility remains a planning risk because the current evaluation path expects structured JSON. Missing rubric files must not silently remove a score. Version results against the effective rubric composition so older results cannot appear current after prompt changes.

All previously raised questions are resolved by the user. Option B is selected. Require at least one enabled rubric and keep each score independently identified in recorded results; changing the enabled set must not shift the meaning of historical score positions. The dialog must prevent saving an empty selection, and the harness must reject an empty rubric set. Adding or removing templates must not require a redesign of the scoring pipeline or dialog.

The releasable slice includes compact repository templates, prompt composition, the selection dialog, one-call multi-score evaluation, harness validation, and recording individually identified scores through normal fastmod use. Triggers and other score-driven behavior are deferred. This remains Step 1 pending explicit approval; no application changes have been made.
