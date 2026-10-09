> **Feature plan:** [Step 1](./event_feature_gating_step1_solution_assessment.md) · [Step 2](./event_feature_gating_step2_feature_description.md) · [Step 3](./event_feature_gating_step3_development_plan.md) · [Step 4](./event_feature_gating_step4_implementation_summary.md)

## Stage 1 - Register event support

- Changes:
  - Added the site-mutable, default-off `FORUM_EVENT_SUPPORT_ENABLED` flag in the Authored content group.
  - Exposed its evaluated value to the existing template-rendering data contract.
  - Added evaluator coverage for default and site-enabled behavior.
- Verification:
  - `./v3 test FeatureFlagEvaluatorTest` — 14 run, 14 passed.
  - Deployment and external verification: not applicable; this stage changes only the local flag registry and rendering context.
- Notes:
  - The Feature Flags tool enumerates the registry, so the new flag requires no separate settings UI.

## Stage 2 - Gate event UI

- Changes:
  - Hid the shared non-compact composer event inputs unless event support is enabled.
  - Suppressed the shared event block on board cards and thread pages unless event support is enabled.
  - Added smoke coverage for default-disabled and enabled compose, board, thread, and Forte surfaces.
- Verification:
  - `./v3 test LocalAppSmokeTest::testEventSupportUiIsHiddenByDefaultAndShownWhenEnabled` — 1 run, 1 passed.
  - Deployment and external verification: not applicable; UI behavior is covered by local application renders.
- Notes:
  - The compact QDB composer remains unchanged and event-free.
