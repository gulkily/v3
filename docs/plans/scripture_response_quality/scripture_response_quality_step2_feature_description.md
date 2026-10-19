> **Feature plan:** [Step 1](./scripture_response_quality_step1_solution_assessment.md) · [Step 2](./scripture_response_quality_step2_feature_description.md) · [Step 3](./scripture_response_quality_step3_development_plan.md) · [Step 4](./scripture_response_quality_step4_implementation_summary.md)

Debug mode: enabled

# Scripture Response Quality and Latency Step 2 Feature Description

## Problem

Scripture replies can take nearly a minute yet overwhelm ordinary questions with repeated advice and loosely applied passages. Prompt edits alone have not demonstrated reliable improvement.

## User Stories

- As a reader, I want relevant, accurately quoted scripture so that I can distinguish its teaching from practical interpretation.
- As a requester, I want a useful answer promptly so that asking for analysis does not interrupt participation.
- As a reader, I want optional expansion so that depth remains available without burdening every initial reply.

## Core Requirements

- Answer the concern directly with proportionate explanation, accurate quotations and translation labels, contextual interpretation, and no unsupported scriptural promises. Allow warranted depth; impose no word limits, verse quotas, or mandatory templates.
- Keep initial answers complete and expansions additive. Avoid repeated scripture/application sections and unsupported offers of continuing conversation.
- Select guidance and generation settings through a fixed, repeated comparison of baseline, revised guidance, and one alternative model/configuration. Retain anonymized outputs, source checks, failures, timing, and cost evidence.
- Measure request submission through completed reply visibility, separating queue, applicable moderation, generation, publication, and browser discovery. Improve demonstrated bottlenecks without bypassing moderation, truncating output, or reducing completion allowance to force speed.
- Preserve permissions, source checks, duplicate prevention, progress, and explicit failure recovery. A failed or exhausted call must not publish a partial answer or silently launch repeated generation.

## Delivery Scope and Completion Boundary

Work type: **application change**. Improve scripture guidance and its generation/delivery path through existing analysis and Expand controls. Release requires both quality and latency criteria below; an unsuccessful experiment is a finding, not completion. Preserve historical replies and unrelated modes. Exclude automatic review passes, streaming, external scripture retrieval, and new conversation features.

## Risks

- **Faster but less faithful:** Earlier samples show interpretive overreach. Before Step 3, establish independent source checks and critical-error rules; reject speed improvements that fail them.
- **Misleading timing:** Provider time excludes other waits. Before Step 3, define normal-flow timing boundaries and matched test conditions; measure stages and retain slow/failed runs.
- **Overfitting or overspending:** Examples may mask weak generalization. Before Step 3, freeze unseen cases and comparison rules; stop at the budget and reassess rather than tune against every test.

## Shared Component Inventory

- **Thread/post cards and shared expansion partial:** Reuse canonical rendering and keyboard behavior.
- **Analysis/expansion request and status APIs, browser feedback:** Extend existing timing/progress where needed; preserve contracts.
- **Queue, generator, publication, operator CLI and private exchange diagnostics:** Reuse lifecycle/recovery; scope candidate settings to scripture.
- **Cached/static pages and offline reader/snapshots:** Reuse stored-result rendering; retain visibility rules and expansion grouping.
- **Profile listings and RSS:** Preserve existing initial-reply presentation and supplementary-record exclusions.

## Simple User Flow

1. Request scripture analysis through the normal discussion controls.
2. See progress, then a complete reply; recover explicitly if generation fails.
3. Request Expand when desired and receive shared supplementary content.

## Success Criteria and Evaluation Budget

- **Sample:** Eight fixed questions, twice per approach: four everyday cases (including both observed failures), plus complex interpretation, sensitive pastoral concern, explicit depth, and a supplied-translation/doubtful-citation case. Keep six questions unseen by prompt examples. Compare three approaches: 48 initial generations. Compare baseline/winner expansions on three cases twice: 12 expansions.
- **Quality:** Selected approach passes every rubric requirement on at least 15/16 initial answers, including all eight everyday runs, and all six expansions. It improves at least four everyday comparisons over baseline without degrading complex-case completeness. Any fabricated/misattributed quotation or unsupported scriptural promise in its evaluated outputs blocks release. Check quotations and contextual claims against source passages; word count is diagnostic only.
- **Latency:** In matched normal-flow trials, initial replies and expansions each achieve median submission-to-visible-completion time ≤20 seconds, ≥30% below their baseline median, with no sampled completion exceeding 40 seconds. These are acceptance targets, not production guarantees; report all failures separately and do not omit slow runs. Failure/duplicate/permission recovery checks must also pass.
- **Budget:** After implementation approval, allow at most 60 scripture-generation calls plus 120 supporting moderation calls, and $5 total provider spend, stopping at the first limit. Retries count; freeze candidates before trials and publish no trial posts to live threads. If evidence is insufficient or either goal fails, return for reassessment rather than silently expanding the budget or weakening criteria.
