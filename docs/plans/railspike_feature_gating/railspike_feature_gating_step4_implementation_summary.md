> **Feature plan:** [Step 1](./railspike_feature_gating_step1_solution_assessment.md) · [Step 2](./railspike_feature_gating_step2_feature_description.md) · [Step 3](./railspike_feature_gating_step3_development_plan.md) · [Step 4](./railspike_feature_gating_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/railspike_feature_gating — [ledger](../effort_estimation_trial.md#cycle-v3railspike_feature_gating)
Extended estimates: enabled
Status: Implementation started; Step 3 approved

**Cycle estimate: 0.60**

Entry: 2026-10-29 UTC; v2/A, R1/P2, original baseline cdb7a4e75a8b0e9065140656ec79dba5cf12e229; Codex single exposed rater. Step 3 projection reaffirmed; verified references carried forward. Planning commit ef47c56ae on feat/railspike-feature-gating. Original stage-work estimates S1–S5: 0.12/0.14/0.15/0.14/0.12, separately scoped in Step 3 and not additive. No measured labor. Production verification and maintainer acceptance pending.

*— Codex · 2026-10-29 UTC*

## Stage 1 - Flag and API enforcement

**Original estimate: 0.12**
**Retrospective stage work: 0.12**

- Changes: Added default-off FORUM_RAILSPIKE_ENABLED in the existing Agent replies group, site/environment precedence and private-config schema without a template default. Workflow GET and both POST phases refuse before storage access when disabled. Updated API catalog/runbook.
- Verification: `php tests/run.php RailspikeFeatureFlagTest FeatureFlagEvaluatorTest PrivateConfigSchemaTest PrivateConfigCommandTest`: 32/32 passed, including disabled routes, enabled anonymous rejection, defaults/overrides and config creation/refresh. `git diff --check` passed.
- Notes: Browser rendering and worker suspension belong to subsequent stages. Zero dependency/schema delta; original scope unchanged. R1/P2, Codex exposed rater, 2026-10-29 UTC; no measured labor.

*— Codex · 2026-10-29 UTC*
