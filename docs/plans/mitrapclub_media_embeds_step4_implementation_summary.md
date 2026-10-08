> **Feature plan:** [Step 1](./mitrapclub_media_embeds_step1_solution_assessment.md) · [Step 2](./mitrapclub_media_embeds_step2_feature_description.md) · [Step 3](./mitrapclub_media_embeds_step3_development_plan.md) · [Step 4](./mitrapclub_media_embeds_step4_implementation_summary.md)

## Stage 1 - Feature flag

- Changes:
  - Added `FeatureFlagRegistry::MEDIA_EMBEDS_ENABLED` (`FORUM_MEDIA_EMBEDS_ENABLED`) and its `FeatureFlagDefinition` entry (`siteMutable: true`, default `false`), positioned after `THREAD_DENSITY_TOGGLE_ENABLED`.
  - Updated `tests/FeatureFlagEvaluatorTest.php`'s hardcoded public-flag canary list and env-cleanup key list to include the new flag (both previously enumerated every public flag by name).
- Verification:
  - `php tests/run.php FeatureFlagEvaluatorTest FeatureFlagsBehaviorTest` — 16 run, 16 passed.
- Notes:
  - No changes needed to `testDefaultsMatchExistingSiteFlags` — that test only individually asserts a subset of flags (e.g. `THREAD_DENSITY_TOGGLE_ENABLED` is likewise absent from it), consistent with existing precedent.
