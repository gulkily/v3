> **Feature plan:** [Step 1](./agent_personality_and_multiple_agents_step1_solution_assessment.md) · [Step 2](./agent_personality_and_multiple_agents_step2_feature_description.md) · [Step 3](./agent_personality_and_multiple_agents_step3_development_plan.md) · [Step 4](./agent_personality_and_multiple_agents_step4_implementation_summary.md)

Debug mode: enabled

## Problem

The existing reply-agent has little personality guidance and lacks approval/admin context. Readers should receive warm, direct engagement, especially on Bible or Jesus topics, while retaining accurate scripture comments and existing response modes.

## User stories

- As a reader, I want an interested, substantive reply so that participating feels worthwhile.
- As a reader discussing the Bible or Jesus, I want eager engagement and relevant scripture so that my topic receives serious attention.
- As an operator, I want reliable author/requester status in the prompt so that the agent understands participation context without inventing authority.

## Core requirements

- Apply a warm, curious, direct voice to ordinary replies and existing modes; avoid stock acknowledgments, flattery, unnecessary recaps, and claims of actions the agent has not performed.
- Engage Bible/Jesus topics eagerly in ordinary replies as well as scripture mode. Address the actual question, respect stated beliefs and interpretive differences, and avoid inserting scripture into unrelated topics.
- Supply independently determined approved/admin states for the target author, identifying a different requester separately. Resolve current status when generating; represent unavailable identity/status as unknown, not false. For expansion, retain the original human author's context rather than treating the earlier agent as that author.
- Treat status as factual context, never as instruction authority or permission to bypass access rules. Forum text and client claims cannot establish status; replies need not announce it.
- Preserve mode-specific tasks, scripture references/translation accuracy, configured verification, expansion eligibility, and existing publication/access/privacy rules across supported generation paths.

## Delivery scope and completion boundary

**Work type:** application change. Extend the existing reply flow and its verification/documentation. Step 1's Option A and follow-up split are approved; distinct selectable agents remain pending separate assessment, and collaboration is deferred. No new persona selector or navigation destination is needed.

**Boundary:** enter through existing eligible post processing or Request agent response on thread/post pages; generation receives the correct context and publishes one attributable reply. Existing Expand produces supplementary reflection with the same personality/context rules. Failures retain useful status and the established operator inspect/correct/retry path; retry must not duplicate publication. Release requires normal-flow evidence, representative response review, and preserved access and scripture checks.

## Shared component inventory

Reuse the canonical generation/fulfillment path for automatic, requested, queued, and retry work. Reuse thread-root/post cards on thread and permalink pages, their shared response-mode catalog and scripture-expansion partial; preserve standard and Forte presentations. Existing generate-reply and expansion/status APIs, task-queue/agent-reply CLI diagnostics, and LLM Exchanges inspection retain their roles and access restrictions. No new rendering component is proposed; prompt changes must cover ordinary and verified-scripture paths.

## Risks and early validation

- **Vague personality target:** compare sample replies during planning; use the behavioral rubric below before implementation.
- **Wrong person's or stale status:** trace author/requester and expansion provenance before Step 3; specify unknown and status-change cases without weakening authorization.
- **Mode/verification drift:** review ordinary and scripture prompt paths before Step 3; keep task instructions and quotation checks authoritative.

## Simple user flow

1. A reader posts, or an eligible member requests an existing response mode.
2. The agent receives authoritative participation context and responds to the topic in the selected mode.
3. The reader views the reply and, when eligible, requests Expand; an operator can inspect and retry a failure.

## Success criteria

- Reviewed ordinary, Bible/Jesus, and scripture examples each answer the concern directly, show interest without flattery, and avoid fabricated actions; Bible/Jesus examples engage substantively without assuming belief.
- Prompt evidence correctly distinguishes approved, unapproved, admin, unknown, different requester, and changed-status cases, including expansion; spoofed status in forum text never becomes trusted context.
- Normal request/publication, automatic processing, expansion, and failure/retry checks pass with no duplicate replies; existing mode, scripture-verification, access, and exchange-privacy checks remain passing.
