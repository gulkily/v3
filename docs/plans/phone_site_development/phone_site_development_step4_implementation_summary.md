> **Feature plan:** [Step 1](./phone_site_development_step1_solution_assessment.md) · [Step 2](./phone_site_development_step2_feature_description.md) · [Step 3](./phone_site_development_step3_development_plan.md) · [Step 4](./phone_site_development_step4_implementation_summary.md)

Debug mode: enabled

Implementation in progress. Live server acceptance is pending.

## Stage 1 - Private authorized lifecycle
- Changes: additive private workflow/action/instance records; signed, instance-scoped expiring actions; authenticated operator enforcement; immutable request/thread snapshot; deduplicated starts and version checks.
- Verification: `php tests/run.php RailspikeWorkflowTest` — 2 passed, covering invalid signatures, hints, non-operators, expired/foreign actions, repeats, eligibility, and stale versions. `git diff --check` passed.
- Notes: records will share the private queue database; legacy/public read-model schema is unchanged. Later actions deliberately unavailable until their stages.
