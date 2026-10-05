> **Feature plan:** [Step 1](./agent_response_modes_step1_solution_assessment.md) · [Step 2](./agent_response_modes_step2_feature_description.md) · [Step 3](./agent_response_modes_step3_development_plan.md) · [Step 4](./agent_response_modes_step4_implementation_summary.md)

# Agent Response Modes Step 3 Development Plan

## Completion Contract

- Normal entry: an approved reader selects one of the five modes from either canonical post-card surface.
- End-to-end outcome: the selected task is durably queued, fulfilled by the existing worker, and its labelled reply and link are visible from the originating post.
- Required recovery: invalid, duplicate, forbidden, agent-authored, in-progress, and failed requests retain accurate lifecycle feedback without a false success state.
- Deployment/external verification: run focused/write-API suites and manually exercise root/reply flows in desktop and narrow layouts.
- Release condition: all five modes preserve legacy-request compatibility, authorization, loop prevention, bounded-context facts analysis, and current worker operation.

## Key Risks

- **High risk: misleading factual authority.** Impact: supplied context is mistaken for research. Early validation: inspect chooser copy and task fixtures. Mitigation: require claims, internal support, gaps, uncertainty, and no external-verification claim.
- **High risk: task-selection loss.** Impact: a selected mode generates the default reply. Early validation: assert stored task and provider input. Mitigation: validate the catalog at request, fulfillment, and generation boundaries.
- Usability/rollback risk. Impact: lifecycle state is hidden. Early validation: exercise each state on both cards. Mitigation: preserve canonical feedback and legacy-task behavior.

## Stage 1

- Goal: Define the fixed, validated five-mode task catalog and bounded generation contracts.
- Dependencies: Approved Step 2; existing named plain-text task harness.
- Expected changes: Add supported types, labels, descriptions, and task-specific input; constrain facts analysis; preserve legacy recognition and publication slot.
- Verification approach: Unit-test all modes, invalid types, bounded/untrusted context, facts limits, and legacy requests.
- Risks or open questions:
  - Impact: prompts imply research or collapse to default.
  - Early warning / validation: inspect provider messages per mode.
  - Mitigation: assert task identity and facts-analysis language.
- Canonical components/API contracts touched: `AgentResponseTask`; `AgentResponseGenerator`; task-generator input contract.

## Stage 2

- Goal: Carry a selected mode through the existing request, lifecycle, and fulfillment path.
- Dependencies: Stage 1 task catalog and tests.
- Expected changes: Accept `response_mode`; conceptually extend `agentReplyRequestResultForPost(post, viewer, responseMode)`; persist and fulfill the stored task; return its safe label. No database migration.
- Verification approach: API/worker-test stored task, provider input, invalid/duplicate/failed states, loop prevention, authorization, and historical unmarked requests.
- Risks or open questions:
  - Impact: a second request or broken queued work.
  - Early warning / validation: inspect request context and fulfill it.
  - Mitigation: retain target/content-hash and legacy fallback semantics.
- Canonical components/API contracts touched: post-workflow API controller; `PostWorkflowService`; agent-reply generation store; `AgentReplyFulfillmentService`.

## Stage 3

- Goal: Replace the generic action with one accessible shared chooser and mode-aware feedback.
- Dependencies: Stage 2 lifecycle response and safe mode-label contract.
- Expected changes: Add the five labelled choices, descriptions, facts notice, keyboard/focus dismissal, selected-mode status, and narrow-layout dialog/sheet presentation to the canonical cards, controller, and styles.
- Verification approach: From both cards, manually select every mode and verify focus, escape/cancel, duplicate prevention, error restoration, lifecycle labels, and narrow readability; run focused, write-API, and existing worker checks.
- Risks or open questions:
  - Impact: cards or modal state diverge.
  - Early warning / validation: compare rendered controls and repeat click.
  - Mitigation: one client binding and mode definition.
- Canonical components/API contracts touched: `thread_root_card`/`post_card`; `post_analysis.js`; `content-interactions.css`; generate-agent-reply payload; agent-task, lifecycle, and write-API suites.
