> **Feature plan:** [Step 1](./instruction_debug_mode_step1_solution_assessment.md) · [Step 2](./instruction_debug_mode_step2_feature_description.md) · [Step 3](./instruction_debug_mode_step3_development_plan.md) · [Step 4](./instruction_debug_mode_step4_implementation_summary.md)

## Original Query
Please write Step 1 for debug mode.

## Understood Intent
Complete the second, separate FDP integration cycle: bring optional instruction diagnostics into Railspike, including durable mode state and persistent logs. Plan organization is already delivered; this request plans the feature rather than enabling diagnostics for this workflow.

## Problem
Railspike has no standard way to collect evidence about which workflow instructions help or add overhead, preserve that feedback across sessions, and keep it separate from approvals and implementation decisions.

## Source and constraints
- FDP commits `00222d4`, `d3ffc48`, and `6fdf330` introduce diagnostics; latest commit `b850ed7` adds append-only logs and their commit rules. Debug mode evaluates the workflow instructions, not application bugs or hidden agent reasoning.
- New workflows default off. Explicit user requests set durable state inherited across artifacts; resumed work restores that state and asks when unresolved evidence is missing or conflicting.
- Completed steps report useful and redundant instructions with source references and observable evidence; inapplicable and uncertain findings remain distinct. Empty groups say “No supported findings.” Findings are advisory and never waive required instructions or approvals.
- Existing Railspike organization rules exclude unapproved auxiliary content. Debug logs need an explicit, narrow allowance without weakening that boundary. FDP's prior checks are documentation/manual scenario evidence, not proof of agent adherence here.

## Option A — Adapt the complete FDP debug workflow
Add one canonical Railspike debug contract, referenced by step entry, resumption, and completed-step handoffs. Preserve durable mode state, evidence-backed reports, persistent logs, and their bounded commit lifecycle.
- **Pros:** Delivers the requested behavior end to end; feedback and mode survive sessions; aligns with the completed organization feature.
- **Cons:** More recovery and commit cases to review; requires careful handling of the final report after stage commits finish.

## Option B — Add durable mode and handoff reports first
Introduce mode state and completed-step diagnostics now, leaving persistent logs and their commit rules for a later cycle.
- **Pros:** Smaller initial change; avoids log-specific commit exceptions for now.
- **Cons:** Feedback remains dependent on chat history; only partially imports the requested FDP behavior and requires another cycle.

## Option C — Use a separate retrospective document
Keep the normal workflow unchanged and ask the agent to assess its instructions in a standalone review after delivery.
- **Pros:** Minimal routine overhead; no mode propagation or planning-commit changes.
- **Cons:** Loses step-local evidence and automatic handoffs; does not reproduce FDP debug mode.

## Recommendation
Choose **Option A** as one bounded documentation-only feature with its own planning and stage commits. Use Railspike names and current paths; preserve plan editing, organization, and explicit approval gates. Add no CLI, telemetry, legacy aliases, automatic instruction rewrites, or application-debugging behavior.

Report once per completed step, not per phase or implementation stage. Save full diagnostic output before presenting it, with UTC timestamps and step identifiers; append labeled revisions, skip unchanged reports, disclose write failures, and preserve logs when disabled. Move logs with plans; keep them uncommitted during planning, include existing logs in the initial planning commit, and commit later additions with authorized stages or a final log-only handoff commit without amending history.

The usable outcome is an optional workflow spanning enablement, context recovery, disablement, and final handoff. Validate those paths plus missing/conflicting state, empty findings, revisions, folder moves, and commit boundaries before completion.
