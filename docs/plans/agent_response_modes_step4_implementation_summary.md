> **Feature plan:** [Step 1](./agent_response_modes_step1_solution_assessment.md) · [Step 2](./agent_response_modes_step2_feature_description.md) · [Step 3](./agent_response_modes_step3_development_plan.md) · [Step 4](./agent_response_modes_step4_implementation_summary.md)

# Agent Response Modes Step 4 Implementation Summary

## Stage 1 - Response-task catalog and contracts

- Changes:
  - Added the five approved selectable response modes with stable types, labels, and descriptions.
  - Added per-mode generation instructions, including the initial supplied-context-only analysis mode.
  - Preserved default and legacy task compatibility while allowing selected-task storage and bounded generation input.
- Verification:
  - `php -l src/ForumRewrite/Agent/AgentResponseTask.php`
  - `php -l src/ForumRewrite/Agent/AgentResponseGenerator.php`
  - `./v3 test AgentResponseTaskTest AgentResponseGeneratorTest` — 9 passed.
- Notes:
  - No request endpoint or presentation behavior changed in this stage; a selectable mode cannot reach generation until Stage 2 wiring is complete.

## Stage 2 - Selected-mode request and fulfillment lifecycle

- Changes:
  - Extended the existing request API with validated `response_mode` input while retaining the legacy default for callers that omit it.
  - Persisted the selected task through request completion and failure, surfaced its safe label in lifecycle responses, and fulfilled every supported task through mode-specific generation input.
  - Added end-to-end coverage for selected-mode request, invalid-mode rejection, durable task retention, and worker fulfillment; no schema migration was required.
- Verification:
  - `php -l src/ForumRewrite/Http/PostWorkflowApiController.php`
  - `php -l src/ForumRewrite/Agent/PostWorkflowService.php`
  - `php -l src/ForumRewrite/Agent/AgentReplyFulfillmentService.php`
  - `php -l src/ForumRewrite/Agent/SqliteAgentReplyGenerationStore.php`
  - `./v3 test AgentReplyGenerationTest AgentResponseTaskTest AgentResponseGeneratorTest WriteApiSmokeTest` — passed.
- Notes:
  - Historical unmarked requests remain legacy analysis replies; request rows now retain a selected task after completion or failure so subsequent lifecycle feedback remains mode-aware.

## Stage 3 - Shared response-mode chooser

- Changes:
  - Replaced the direct request action on both canonical post-card surfaces with “Choose agent response,” backed by one server-rendered mode catalog and one shared client dialog.
  - Added named choices, descriptions, a supplied-context analysis notice, keyboard/escape-capable native dialog behavior, selected-mode feedback, and a narrow-screen bottom-sheet presentation.
  - Retained generic status text for historical default requests while selected-mode requests display their label through queued, completed, and failed lifecycle states.
- Verification:
  - `node --check public/assets/post_analysis.js`
  - Focused smoke checks rendered one shared catalog and response chooser on both root and reply cards, hid only the requested card’s chooser, and displayed the selected-mode completion label.
  - `./v3 test AgentReplyGenerationTest AgentResponseTaskTest AgentResponseGeneratorTest WriteApiSmokeTest` — passed.
  - `./v3 test` — 720 run, 712 passed; the 8 failures are all classified by the suite as long-standing and outside this slice.
- Notes:
  - The chooser uses the browser’s native dialog focus and Escape behavior. A final human browser pass at desktop and narrow viewport remains the release handoff check.

## Stage 4 - Modal chooser refinement

- Changes:
  - Made the native dialog presentation explicit with modal semantics, backdrop, and a visible close control.
  - Removed per-choice sub-captions; the chooser presented five mode labels with a shared analysis limitation notice.
- Verification:
  - `php -l src/ForumRewrite/Agent/AgentResponseTask.php`
  - `node --check public/assets/post_analysis.js`
  - `./v3 test AgentResponseTaskTest AgentResponseGeneratorTest WriteApiSmokeTest::testApprovedViewerSeesAgentReplyRequestButtonUntilRequestExists WriteApiSmokeTest::testPostAnalysisScriptDoesNotExposeInProgressReplyGeneration` — 11 passed.
- Notes:
  - No request, fulfillment, or safety behavior changed.

## Stage 5 - Dropdown chooser correction

- Changes:
  - Replaced the modal with an anchored, non-modal dropdown menu.
  - Retained label-only choices and added Escape and outside-click dismissal with trigger state restoration.
- Verification:
  - `node --check public/assets/post_analysis.js`
  - `./v3 test AgentResponseTaskTest AgentResponseGeneratorTest WriteApiSmokeTest::testApprovedViewerSeesAgentReplyRequestButtonUntilRequestExists WriteApiSmokeTest::testPostAnalysisScriptDoesNotExposeInProgressReplyGeneration` — 11 passed.
- Notes:
  - No request, fulfillment, or safety behavior changed.

## Stage 6 - Server-wide request feature flag

- Changes:
  - Added the private, server-wide `AGENT_RESPONSE_REQUESTS_ENABLED` flag, enabled by default and configurable through the private config file or environment.
  - Applied it to both canonical card surfaces and the request endpoint, so disabling it hides the dropdown and prevents direct requests from creating work.
  - Restored the dropdown trigger label to “Request agent response.”
- Verification:
  - `./v3 test FeatureFlagEvaluatorTest WriteApiSmokeTest::testAgentResponseRequestFlagHidesChooserAndRejectsRequests WriteApiSmokeTest::testApprovedViewerSeesAgentReplyRequestButtonUntilRequestExists WriteApiSmokeTest::testGenerateAgentReplyPersistsAndFulfillsSelectedResponseMode` — 17 passed.
- Notes:
  - Set `AGENT_RESPONSE_REQUESTS_ENABLED=false` in the server private config or environment to disable the feature everywhere; re-enable it with `true` or by removing the override.

## Stage 7 - Logic analysis replacement

- Changes:
  - Replaced the selectable `facts_analysis` mode with `logic_analysis`, labelled “Logic analysis.”
  - Replaced evidence-verification instructions with a charitable analysis of premises, conclusions, assumptions, and logical gaps.
  - Removed the dropdown’s facts-analysis disclaimer and its unused styling.
  - Kept the old task identifier readable and fulfillable solely for requests already stored before this change; new API requests accept only selectable current modes.
- Verification:
  - Focused task, generator, request API, and feature-flag tests cover the new mode, rejection of no-longer-selectable modes, and legacy fulfillment compatibility.

## Stage 8 - External response-mode prompts

- Changes:
  - Moved the shared system prompt and every response-task instruction into individually reviewable files under `prompts/`.
  - The generator now loads, trims, validates, and caches those files using the same repository-root convention as other prompt-backed features.
- Verification:
  - Generator coverage exercises every selectable task and verifies each loaded instruction.

## Stage 9 - Readable plain-text task prompts

- Changes:
  - Replaced the dense all-caps/delimiter layout with readable plain-text headings for the task, response instructions, and forum context.
  - Kept each supplied forum field explicitly enclosed in `forum-content` markers and stated that those sections are untrusted text, preserving the prompt-injection boundary.
