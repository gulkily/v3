> **Feature plan:** [Step 1](./agent_reply_length_step1_solution_assessment.md) · [Step 2](./agent_reply_length_step2_feature_description.md) · [Step 3](./agent_reply_length_step3_development_plan.md) · [Step 4](./agent_reply_length_step4_implementation_summary.md)

Debug mode: disabled

# Scripture Response Guidance and Expansion Step 2 Feature Description

## Problem

Readers need complete scripture responses shaped by the question, with optional expansion for further depth.

## User Stories

- As a reader, I want relevant scripture and explanation so that I understand its application.
- As a reader, I want proportionate answers so that useful nuance survives without repetition.
- As an approved member, I want expansion so that I can explore further context.

## Core Requirements

- Guide responses to answer directly, quote actual scripture where appropriate, explain relevance, retain meaningful nuance, and finish when complete. Use no word targets, verse quotas, mandatory templates, or editorial cutoff. Preserve the technical allowance and rejection of exhausted output.
- Identify passages/translations and distinguish quotation, paraphrase, and interpretation. Omit uncertain exact wording. Initial answers must stand alone; longer answers are acceptable when warranted.
- Offer “Expand” on published scripture responses to members authorized to request agent responses. Allow one shared expansion per initial response, readable by authorized discussion viewers. Repeated/concurrent requests reuse it; no expansion of expansions.
- Generate expansion on request from the post and initial answer, adding useful context, passages, nuance, or application without repetition. Preserve the initial response; display labeled supplementary content beneath it, collapsed until opened and opened for the requester when ready.
- Show progress and actionable failure feedback with explicit recovery. Recheck permissions, availability, and source versions before generation/publication; reject stale work and prevent duplicates.

## Delivery Scope

Work type: **application change**. Update editable scripture guidance and add requested expansion through the existing agent workflow. Existing recognizable scripture responses may receive expansion without being rewritten. Other modes, depth selectors, historical rewriting, external scripture retrieval, and changes to the interpretive lens are excluded. Length alone does not trigger human moderation.

## Completion Boundary

Members request analysis and expansion in the discussion; authorized readers retain the shared result across reloads. Release requires normal-flow, recovery, permission, duplicate, accessibility, and output-quality checks.

## Risks

- **Formulaic brevity:** Guidance could suppress substance. Before Step 3, define varied short/complex review cases; assess relevance, quotation accuracy, completeness, and added value rather than uniform length.
- **Publication conflict:** Existing reply limits could block expansion or allow duplicates. Before Step 3, map expansion identity and persistence separately from the initial reply; verify concurrent and repeated requests.
- **Stale or hidden content:** Expansion could outlive its source or permissions. Before Step 3, define version/visibility checks and recovery; apply existing moderation and access rules.

## Shared Component Inventory

- **Thread and standalone post views:** Extend shared cards with reusable, keyboard-accessible expansion.
- **Request/status API and browser feedback:** Extend canonical authorization, progress, and errors.
- **Task catalog, queue, generator, and publication records:** Reuse lifecycle/prompts/diagnostics; distinguish expansion publication.
- **Operator CLI/status and cached/offline views:** Preserve diagnosis/recovery and consistent rendering alongside supported source replies.

## Simple User Flow

1. Request scripture analysis and read the complete initial response.
2. Select “Expand”; see progress while additional material is generated.
3. Open or collapse the shared expansion, or follow failure feedback toward explicit recovery.

## Success Criteria

- Reviewed answers vary appropriately in length and accurately use relevant scripture.
- Expansion adds useful material, persists across reloads, and leaves the initial reply intact.
- Repeated requests publish one expansion; unauthorized/stale requests publish none.
- Desktop/mobile and keyboard flows expose consistent progress, content, and recovery feedback.
