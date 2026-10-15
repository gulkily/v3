> **Feature plan:** [Step 1](./phone_site_development_step1_solution_assessment.md) · [Step 2](./phone_site_development_step2_feature_description.md) · [Step 3](./phone_site_development_step3_development_plan.md) · [Step 4](./phone_site_development_step4_implementation_summary.md)

Debug mode: enabled

Implementation in progress. Live server acceptance is pending.

## Stage 1 - Private authorized lifecycle
- Changes: additive private workflow/action/instance records; signed, instance-scoped expiring actions; authenticated operator enforcement; immutable request/thread snapshot; deduplicated starts and version checks.
- Verification: `php tests/run.php RailspikeWorkflowTest` — 2 passed, covering invalid signatures, hints, non-operators, expired/foreign actions, repeats, eligibility, and stale versions. `git diff --check` passed.
- Notes: records will share the private queue database; legacy/public read-model schema is unchanged. Later actions deliberately unavailable until their stages.

## Stage 2 - Queue and bounded Codex assessment
- Changes: allowlisted assessment task; atomic request/task insertion in the private queue database; CLI worker wiring; read-only isolated checkout runner with stdin prompts, explicit model, structured output, 90-second maximum and output cap; validated private revision persistence.
- Verification: `php tests/run.php RailspikeWorkflowTest TaskQueueWorkerTest TaskQueueStoreTest` — 28 passed. PHP syntax checks and diff whitespace checks passed.
- Notes: missing executable/workspace/model/home yields visible failure. Actual zenmemes credentials and sandbox support remain external acceptance requirements. No real model call was made during unit verification.

## Stage 3 - Phone request and status controls
- Changes: shared public-safe mount in root/reply cards; authenticated operator-only no-store workflow API; same-origin JSON and signature enforcement; shared browser menu/status/history renderer, explicit refresh and bounded polling; standalone post and thread script wiring.
- Verification: PHP/JavaScript syntax checks passed; `php tests/run.php RailspikeWorkflowTest CodexHandoffDraftServiceTest` — 7 passed; diff whitespace checks passed. Full browser journey is scheduled in Stage 8.
- Notes: no workflow data is embedded in public HTML; private data is fetched after operator authentication. Legacy endpoints and preview contracts remain separate and unchanged.
