> **Feature plan:** [Step 1](./quiet_debug_mode_step1_solution_assessment.md) · [Step 2](./quiet_debug_mode_step2_feature_description.md) · [Step 3](./quiet_debug_mode_step3_development_plan.md) · [Step 4](./quiet_debug_mode_step4_implementation_summary.md)

Debug mode: enabled

# Quiet debug mode

## Problem
Completed-step debug summaries duplicate findings already saved in Markdown, adding conversation noise. Users need the full diagnostic record with a minimal, discoverable handoff.

## User stories
- As a workflow user, I want detailed diagnostics in the debug log so that the conversation stays focused on my work.
- As a reviewer, I want a short log link and reliable history so that I can inspect findings when needed.

## Core requirements
- When enabled, save complete completed-step reports in the existing `{feature_name}_debug_log.md` before linking to them. Keep required useful/redundant groups, empty-group markers, source references, evidence, suggestions, and applicable inapplicable/uncertain groups in the file.
- Each completed Step 1–4 handoff includes one short debug line linking to the saved log, before approval where applicable. Do not repeat findings, group headings, or empty-group markers in chat, or narrate diagnostic collection. Phases and implementation stages produce no separate reports.
- Allow brief exceptions for save failures and unresolved mode-state clarification, and conversational findings when explicitly requested. Do not claim failed writes succeeded or dump unsaved diagnostics into chat as a fallback; resolve saving before presenting a report as logged.
- Preserve enable/disable behavior, durable state and recovery, UTC timestamps, append-only revisions, unchanged-report skipping, and existing log organization/commit rules. An unchanged report may link to the existing log without adding an entry. Disabled mode emits no diagnostics or debug link and preserves history.
- Ordinary task updates, blockers, approval gates, and instruction-reprint rules remain unchanged. Add no verbosity setting or automatic instruction edits.

## Delivery scope and shared surfaces
**Work type:** documentation-only, for Railspike users and agents. Extend the canonical contract in `RAILSPIKE.md` and align debug-related wording in `docs/workflow/*.md`, `README.md`, and `docs/failure-modes.md`; reuse the existing Markdown log. No UI/API surfaces apply.

Workflow bookkeeping is limited to this feature's plans, summary, optional debug log, their organization under `docs/plans/quiet_debug_mode/`, and its `docs/plans/README.md` entry. Historical plans retain approved content. Application/runtime, tests, CI, deployment, and generated-artifact changes are not permitted.

## Risks
- **Conflicting instructions:** Old summary wording could restore chat noise. Validate active debug references before Step 3; mitigate by aligning handoffs and examples with the canonical contract.
- **Over-suppression:** Quiet output could hide a save failure or needed decision. Review exception scenarios before Step 3; retain brief failure/clarification messages and ordinary task communication.
- **Weakened history:** Short handoffs could be mistaken for permission to shorten reports. Compare log obligations before Step 3; retain complete evidence and append-only recovery rules.

## Simple user flow
1. Enable debug mode; the workflow records and restores that state.
2. Complete a step; full findings are saved and the handoff links to the log.
3. Open the log or explicitly request findings in chat; resume, revise, or disable using existing rules.

## Completion boundary and success criteria
Deliver consistent instructions and examples covering enabled handoffs, resumption, revisions, unchanged reports, disabled mode, explicit requests, and save failures. Scenario review must show one routine debug link line per completed step, zero routine inline findings, complete saved reports, and no disabled-mode debug output. Validate document integrity, links, and allowed file scope; record evidence in the Step 4 summary. Resolve contradictory wording or broken links before handoff; disclose unresolved save failures briefly without claiming success. These documentation checks establish the intended behavior, not guaranteed adherence by every agent.
