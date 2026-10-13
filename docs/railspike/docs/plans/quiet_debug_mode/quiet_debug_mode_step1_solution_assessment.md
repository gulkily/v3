> **Feature plan:** [Step 1](./quiet_debug_mode_step1_solution_assessment.md) · [Step 2](./quiet_debug_mode_step2_feature_description.md) · [Step 3](./quiet_debug_mode_step3_development_plan.md) · [Step 4](./quiet_debug_mode_step4_implementation_summary.md)

Debug mode: disabled

## Original Query
I want to restrict/limit debug mode to almost entirely write to the md file and not write to the conversation much. Please write Step 1 of Railspike.

## Understood Intent
Make the existing debug log the primary destination for Railspike instruction diagnostics, with minimal conversation output. This request plans a change to debug mode; it does not enable diagnostics for this workflow.

## Problem
Railspike currently requires diagnostic summaries in completed-step conversations even though the full findings are already saved in a Markdown log, duplicating feedback and adding noise.

## Option A — File-first diagnostics with a one-line link
Write all diagnostic findings and required report groups to the existing debug log. At each completed-step handoff, include at most one short debug line linking to it, without repeating findings in the conversation.

- **Pros:** Predictable conversation limit; preserves discoverability and the complete diagnostic record; fits the existing handoff workflow.
- **Cons:** Users must open the log to review findings; still adds one line per completed step.

## Option B — Silent logging by default
Save the same complete reports to the debug log, but omit routine debug mentions from handoffs. Surface the log or its findings only when the user asks, or when saving fails or unresolved mode state requires input.

- **Pros:** Lowest routine conversation overhead; retains diagnostic history.
- **Cons:** Users may not know new findings exist or where to find them; successful logging is less visible.

## Option C — File-first diagnostics with selectable conversation verbosity
Keep detailed findings in the log and let users choose silent, link-only, or summary handoffs, defaulting to link-only.

- **Pros:** Supports different review preferences without losing evidence.
- **Cons:** Adds a setting to preserve and recover across steps; greater instruction complexity for a narrow change.

## Recommendation
Choose **Option A** as one bounded documentation-only feature: complete diagnostic reports in `{feature_name}_debug_log.md`, with at most one short link line in each completed-step conversation. Required useful/redundant groups, source references, evidence, suggestions, and any inapplicable/uncertain findings belong in the log only. Do not narrate diagnostic collection or echo empty groups in chat.

Keep exceptions narrow: briefly disclose log-write failures or request genuinely necessary mode-state clarification; provide findings in chat when explicitly requested. Ordinary task updates, blockers, and approval requests remain available. Instruction reprints are a separate source of conversation volume and are outside this debug-specific change.

Retain existing enable/disable behavior, durable state, append-only timestamps and revisions, unchanged-report skipping, completed-step reporting frequency, and organization/commit rules. Align the canonical debug contract, affected handoff instructions, and user-facing documentation; historical approved plans remain records of their original scope.

The complete usable slice spans enabled logging, a link-only handoff, resumption, revisions, disabled mode, and save-failure recovery. Review these scenarios for consistent file output and bounded conversation output before considering the change complete.
