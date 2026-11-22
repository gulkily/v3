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

## Stage 2 - Roll back over SSH

**Original estimate: 0.20**

**Retrospective: 0.20**

- Changes:
  - `AppUpdate::rollback()` resets `main` to an earlier deployed commit under the deploy lock, keeps local edits, runs the read-model check, and logs an attempt (`rolled_back`) and a history entry (`Action: rollback`). It never fetches.
  - Refusals, each leaving the checkout as it was: `not_deployed_here`, `already_running`, `not_behind`, `wrong_branch`, `busy`, `local_changes_conflict`.
  - `deploy()` and `rollback()` share `record()` for the read-model check and logging, and `moveKeepingLocalChanges()` for the set-aside and put-back of local edits. The deploy's messages and reasons are unchanged.
  - `deployedCommits()` lists the allowed targets: every old or new commit in the history, so the version before the first recorded deploy is included.
  - `./v3 deploy --rollback=COMMIT` (7 to 64 hex digits).
- Verification:
  - Passed: `./v3 test --smoke AppUpdateTest DeployPageTest`, with five new tests: rollback then deploy forward; a kept local edit; a conflicting local edit leaves `HEAD`, the edit and the stash list unchanged; every refusal; the CLI.
  - The first High risk from Step 3 (a local edit that does not apply on the older commit) is covered by the conflicting-edit test.
- Notes:
  - Defect found and fixed from Stage 1: a first deploy on a checkout with no history file was recorded twice, because the seed read the attempt just logged. `record()` now seeds before logging; the rollback tests cover it.
- Estimate details (extended mode only): original scope as in Step 3 Stage 2; uncertainty medium; no revision.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*

## Stage 3 - Preview and warnings

**Original estimate: 0.10**

**Retrospective: 0.10**

- Changes:
  - `AppUpdate::rollbackPreview()` returns the commits a rollback undoes (same shape and cap as incoming commits) and up to three warnings: a read-model version change, changes under `src/ForumRewrite/Canonical/`, and changes under `docs/specs/`. It changes nothing.
  - `incoming()` and the preview share `commitList()`; `rollback()` and the preview share `rollbackRefusal()`, so both judge a target the same way.
  - `./v3 deploy --rollback=COMMIT` prints `Undoes-Commit:` and `Warning:` lines before the result.
- Verification:
  - Passed: `./v3 test AppUpdateTest DeployPageTest` (49 run, 0 failed), with two new tests: the commit list and refusals; each warning off and on, including a `SCHEMA_VERSION` change from 55 to 56, and a rollback that goes through despite three warnings.
  - The second High risk from Step 3 (older code misreads newer records) has its early validation here; the mitigation is the warning plus Deploy now.
- Notes:
  - Over SSH the warnings print and the rollback proceeds; the operator already chose the commit. The page (Stage 4) asks for a confirmation.
- Estimate details (extended mode only): original scope as in Step 3 Stage 3; uncertainty low; no revision.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*
