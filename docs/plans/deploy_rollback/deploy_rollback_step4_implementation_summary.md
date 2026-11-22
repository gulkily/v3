> **Feature plan:** [Step 1](./deploy_rollback_step1_solution_assessment.md) · [Step 2](./deploy_rollback_step2_feature_description.md) · [Step 3](./deploy_rollback_step3_development_plan.md) · [Step 4](./deploy_rollback_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/deploy_rollback`; host ledger [deploy_rollback.md](../effort_ledger/deploy_rollback.md). Standing P2 enrollment.

Extended estimates: enabled. Stage values are stage work only; they are not summed into the cycle estimate.

**Cycle estimate: 0.65**

Step 4 entry, 2026-11-22 UTC: the Step 3 projection of **0.65** is reaffirmed unchanged. Planning commit `a12fc0da` on `feature/deploy-rollback`, from `57e7b934`. Stage estimates below are copied from Step 3, not rated again.

## Stage 1 - Record deploys

**Original estimate: 0.10**

**Retrospective: 0.10**

- Changes:
  - `AppUpdate` appends one block to `state/logs/deploy_history.txt` for each deploy that changes the commit, and `history()` reads it newest first (default 20).
  - The first use seeds the file from the `deployed` entries in `app_update.log.1` and `app_update.log`.
  - `latestAttempt()` and the history share one block parser, `blocks()`.
  - `./v3 deploy --history` prints the running commit and every recorded version.
- Verification:
  - Passed: `./v3 test AppUpdateTest DeployPageTest` (36 and 6 run, 0 failed), with three new tests: entries per deploy and none for an up-to-date attempt; seeding from an old log that has a refusal, an unreadable block and an entry without `Read-Model`; the CLI output.
  - Not run in this stage: the smoke set (run at Stage 2).
- Notes:
  - Reading the history creates the file when it is missing, so a page view writes one small file under `state/logs/`.
  - Tests run the real `scripts/deploy.php` against the fixture checkout through a new `cli()` helper.
- Estimate details (extended mode only): original scope as in Step 3 Stage 1; uncertainty low; no revision.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*
