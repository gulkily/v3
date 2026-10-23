> **Feature plan:** [Step 1](./railspike_workflow_visibility_step1_solution_assessment.md) · [Step 2](./railspike_workflow_visibility_step2_feature_description.md) · [Step 3](./railspike_workflow_visibility_step3_development_plan.md) · [Step 4](./railspike_workflow_visibility_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/railspike_workflow_visibility`, scope v2, [P2 ledger](../effort_estimation_trial.md#cycle-v3railspike_workflow_visibility). Steps 1–2 approved.

## Step 4 Entry

Step 3 scope v2 approved. Planning commit `4e176747`; branch `fix/activity-workflow-block`. R1/P2 total projection remains unavailable pending exact frozen sources, reaffirming Step 3. Same baseline and exposed rater; no measured effort.

## Stage 1 - Remove detached workflow integration
- Changes: removed Activity placeholder, workflow refresh branch and Activity workflow script inclusion; initialization now requires post mounts. Other signing/auth dependencies and documented API remain intact. Existing browser journey now checks rendered absence and no workflow requests over 5.5 seconds plus visibility refresh.
- Verification: changed PHP/JS syntax and whitespace checks passed. Workflow browser journey passed: signed actions, failure/retry, rebuild, stale approval, standalone post, saved-key resumption, privacy, Activity absence/network silence and overflow. Cached Node/Chromium and extracted library paths were needed after initial environment lookup failures; no project dependency changes.
- Notes: thread refresh/rendering preserved. Stage 2 covers additional roles, no-JS, desktop/themes, pagination/RSS and documentation.
