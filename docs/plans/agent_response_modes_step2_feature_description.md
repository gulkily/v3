> **Feature plan:** [Step 1](./agent_response_modes_step1_solution_assessment.md) · [Step 2](./agent_response_modes_step2_feature_description.md) · [Step 3](./agent_response_modes_step3_development_plan.md) · [Step 4](./agent_response_modes_step4_implementation_summary.md)

# Agent Response Modes Step 2 Feature Description

## Problem

“Request agent response” gives readers no meaningful indication of the response they will receive. Readers need a concise, deliberate way to choose a useful form of assistance before an agent response is requested.

## User Stories

- As an eligible reader, I want to choose a named response mode for a post so that I can request help suited to what I am trying to understand.
- As a reader, I want logic analysis to explain the structure and soundness of an argument so that I can assess its reasoning.
- As a reader, I want clear request, completion, and failure feedback so that I know whether and where to find the selected response.

## Core Requirements

- Replace the generic request action with one compact chooser offering exactly these initial modes: Logic analysis, Explain the joke or reference, Summary and key takeaways, Explain simply, and Constructive counterpoint.
- Each choice must submit and retain its distinct named task, and the resulting agent reply must visibly identify the selected mode.
- Logic analysis examines the reasoning in the target post and bounded supplied thread context, identifying premises, conclusions, assumptions, and logical gaps charitably and respectfully.
- The chooser must preserve current eligibility, duplicate-request, agent-loop-prevention, publication, and status behavior; requests recorded before release remain readable and fulfill through their compatible behavior.
- All modes use the shared plain-text response-task boundary established by the agent-response-harness simplification; free-form instructions and external research are out of scope.

## Completion Boundary

- **Normal entry:** an eligible reader opens the chooser from either a thread root or reply card and selects one mode.
- **End-to-end outcome:** the selected response is queued, its status is shown, and its published reply is identifiable and linkable from the originating post.
- **Needed recovery:** a rejected, duplicate, unavailable, or failed request supplies an understandable status and leaves no ambiguous success state; the reader can retry when the current lifecycle permits it.
- **Release condition:** all five modes work through both card surfaces without weakening existing request authorization or loop prevention.

## Risks

- **Uncharitable or shallow reasoning critique:** logic analysis may oversimplify an argument or treat an assumption as a defect. **Earliest validation:** review its label and representative responses. **Mitigation:** require premise, conclusion, assumption, and logical-gap treatment, with charitable and respectful language.
- **Mode-selection divergence:** root and reply cards could offer or submit different choices. **Earliest validation:** exercise every mode from both surfaces. **Mitigation:** extend their canonical shared interaction and request contract.
- **Duplicate or stale lifecycle states:** a modal flow could obscure existing queued/completed outcomes. **Earliest validation:** test requested, posted, failed, and repeat-request states. **Mitigation:** retain the current status and publication lifecycle as the authority.
- **Scope growth:** free-form prompting or external lookup would expand safety and product scope. **Earliest validation:** review the task list and context policy before planning. **Mitigation:** ship only the fixed five presets with bounded supplied context.

## Shared Component Inventory

- **Thread-root and reply post cards:** both render the current request entry point and feedback; extend these canonical card surfaces with the same chooser rather than creating a third entry point.
- **Post-interaction client controller:** it binds the request action and renders request feedback for both card types; extend it as the single chooser and status interaction path.
- **Post workflow request API and service:** they authorize, deduplicate, queue, and report the request lifecycle; extend their existing request contract to carry the selected mode rather than add a competing API.
- **Agent response task and fulfillment harness:** it owns the named plain-text task and published response flow; extend its supported task set for the five modes, retaining legacy-request compatibility.

## User Flow

1. An eligible reader opens the response-mode chooser on a root or reply post.
2. The reader selects one of the five fixed, clearly labelled modes.
3. The system records the selected task and shows the existing request status.
4. The agent produces and publishes a reply labelled with that mode, or the reader receives a clear lifecycle failure or recovery status.

## Success Criteria

- A manual acceptance pass can select each of the five modes from both root and reply cards and observe the matching labelled response task through publication.
- Logic-analysis acceptance examples consistently identify reasoning structure and potential gaps without becoming dismissive or personal.
- Existing eligibility, duplicate-request, agent-loop-prevention, queued, posted, and failure behaviors continue to pass for both card surfaces.
- No free-form instruction or external-research path is exposed in this release.
