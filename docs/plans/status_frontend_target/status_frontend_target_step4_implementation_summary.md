> **Feature plan:** [Step 1](./status_frontend_target_step1_solution_assessment.md) · [Step 2](./status_frontend_target_step2_feature_description.md) · [Step 3](./status_frontend_target_step3_development_plan.md) · [Step 4](./status_frontend_target_step4_implementation_summary.md)

Debug mode: enabled

# Status frontend target — implementation summary

Planning commit: `1f7e49d1`. Production deployment is outside this cycle; actual production acceptance remains pending access.

## Stage 1 - Explicit and saved targets
- Changes: `FORUM_STATUS_URL` uses the existing private-config/environment machinery; explicit `--url` wins. Shared URL validation rejects credentials, whitespace and fragments; status reports target source. Default discovery remains for later stages.
- Verification: 24 targeted resolver/status/health/TUI tests passed; diff check passed.
- Notes: configuration does not write from status. Stage 2 will validate proof and HTTP routing before automatic discovery.
