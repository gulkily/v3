> **Feature plan:** [Step 1](./concurrent_checkouts_step1_solution_assessment.md) · [Step 2](./concurrent_checkouts_step2_feature_description.md) · [Step 3](./concurrent_checkouts_step3_development_plan.md) · [Step 4](./concurrent_checkouts_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/concurrent_checkouts`; [ledger](../effort_estimation_trial.md), R1/P2, scope v4. Step 3 approved; planning commit `b1b33e0b8`; original baseline `19f1066a`, rewritten entry `1a358bc9`. Entry projection reaffirmed unavailable pending exact frozen feedback recovery; earlier snapshots preserved. Codex single exposed rater. No numeric score or measured effort invented.

## Stage 1 - Registry and command entry

- Changes: private atomic shared registry, add/list/remove, source/path validation and shared-storage duplicate rejection; `v3` dispatcher and human/JSON entry.
- Verification: `python3 tests/test_checkouts.py`: 5 real-Git cases passed (lifecycle/permissions, aliases/shared storage, malformed/missing paths, roles/help, credential rejection); PHP syntax checks passed.
- Notes: no live checkout registrations; stages 2–7 pending. Other local planning-ledger changes remain pending and outside the initial planning commit.
