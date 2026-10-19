> **Feature plan:** [Step 1](./fastmod_meta_score_step1_solution_assessment.md) · [Step 2](./fastmod_meta_score_step2_feature_description.md) · [Step 3](./fastmod_meta_score_step3_development_plan.md) · [Step 4](./fastmod_meta_score_step4_implementation_summary.md)

Debug mode: enabled

## Problem

Fastmod records one moderation probability today. Operators need a configurable set of independently meaningful scores from one LLM evaluation, starting with moderation/hostility and catch-all application support (meta).

## User Stories

- As an operator, I want to enable or disable scoring rubrics in a dialog so that fastmod records only the signals I choose.
- As a maintainer, I want compact repository rubric templates so that I can add, remove, or revise signals without redesigning fastmod.

## Core Requirements

- Compose one prompt from a shared task introduction and enabled repository `.txt` rubric templates, each ideally one bullet. Make exactly one LLM call per evaluation sent to the provider, regardless of rubric count; omit disabled rubrics entirely. No individual rubric is mandatory, but at least one must be enabled; both the dialog and harness enforce this.
- Return only comma-separated numeric probabilities from 0 to 1 in the declared rubric order. The harness sanitizes harmless surrounding whitespace and validates field count, numeric syntax, finiteness, and range before accepting the complete result. Reject malformed output without inventing, clamping, or partially recording scores.
- Record each score with its rubric identity and effective prompt revision; preserve historical meaning when templates, order, or selection change. Missing or invalid templates fail visibly rather than silently dropping a requested rubric. Operator selections persist for subsequent evaluations.
- Initially supply the existing moderation/hostility signal and a meta signal for feature requests, bug reports, and help concerning the platform, instance, or user interactions with the application and its interfaces. Preserve moderation coverage when compacting its text. Scores may intentionally overlap; neither rubric is permanent or required individually.
- Only record independent scores and expose them through existing result surfaces. Add no aggregate, score-based filtering/ranking, triggers, or automatic moderation actions. Existing publication/worker entry paths remain the means of scheduling evaluation; no new scoring triggers are introduced.

## Delivery Scope and Completion Boundary

Work type: application change, including operator dialog, rubric templates, scoring integration, result presentation, documentation, and appropriate verification. From normal operator access, save a valid selection, publish content through the existing flow, and have the existing worker record the selected scores together. Existing authorized result surfaces identify each score correctly. Empty selections and invalid templates produce actionable configuration errors; malformed/provider failures remain distinguishable from low scores and support the existing controlled recovery path. Completion requires this normal flow and failure recovery to work without extra score-driven behavior.

## Risks

- CSV support: the provider path currently expects structured JSON. Earliest validation: inspect provider contracts before Step 3 commits to an approach. Mitigate by establishing a supported plain-text completion path without changing unrelated structured consumers.
- Position/revision mismatch: changed selections could mislabel results or reuse stale work. Earliest validation: trace enqueue, worker, audit/backfill, storage, and readers before Step 3. Mitigate with stable rubric identities and effective-composition revision semantics across those paths.
- Configuration exposure: the dialog changes shared scoring configuration. Earliest validation: identify existing operator authorization and configuration conventions before Step 3. Reuse those boundaries and make rejected saves preserve the prior valid configuration.
- Rubric compression: shortening moderation text could change coverage. Earliest validation: compare proposed concise rubric coverage against the existing prompt during planning. Preserve meaningful categories and use representative examples in verification.

## Shared Component Inventory

Extend the existing post-page Fastmod display (`templates/pages/post.php`), result lookup/controller, and private score store to support identified score sets. Extend the existing `PostWorkflowApiController` scoring response and `scripts/fast_score.php` status/smoke/recovery output; retain task-worker progress and audit/backfill reporting with consistent result semantics. Reuse the existing publication queue, worker, provider infrastructure, and operator configuration/authorization conventions. Add a rubric-selection dialog because no existing rubric selector was found; share its rubric definitions and validation with the worker rather than hard-coding two options.

## Simple User Flow

1. Operator opens the Fastmod rubric dialog, selects one or more available rubrics, and saves.
2. Existing publication and worker flow evaluates content using the saved selection in one call.
3. Harness validates the response and records the identified scores; existing result views report scores or actionable failure status.

## Success Criteria

One enabled rubric yields one recorded score; two yield two scores from one call. Disabled rubric text never enters the prompt. Neither default rubric is individually required, but an empty selection cannot be saved or evaluated. Invalid responses never produce successful scores. Changing selection or templates never relabels historical results. Adding another rubric requires no scoring-pipeline or dialog redesign. No new score-driven actions occur.
