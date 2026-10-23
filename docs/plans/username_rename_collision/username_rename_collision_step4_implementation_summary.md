> **Feature plan:** [Step 1](./username_rename_collision_step1_solution_assessment.md) · [Step 2](./username_rename_collision_step2_feature_description.md) · [Step 3](./username_rename_collision_step3_development_plan.md) · [Step 4](./username_rename_collision_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort`, cycle `v3/username_rename_collision`; standing P2 enrollment; [ledger](../effort_estimation_trial.md#cycle-v3username_rename_collision).

## Step 4 entry

- Branch `feat/username-rename-collision` from `main` at `8a2d59c1`; planning commit `077877c5` (Steps 1–3, debug log, plans index).
- Study checkpoint (2026-10-23 UTC, Claude Opus 5.5, single exposed rater, R1/P2, scope v3): Step 3 projection of **0.70 reaffirmed** before implementation.
- Carried into this branch uncommitted from the earlier session: the "Composite User" section in `docs/specs/profile_read_contract_v1.md` (committed with Stage 7) and the cycle's ledger rows in `effort_estimation_trial.md` (committed at closeout).
- An untracked `docs/research/effort_estimation/` folder of unknown origin appeared during setup; it is not part of this cycle and is left untouched.

## Stage 1 - Refuse direct saves into a held name
- Changes:
  - `LocalWriteService::isUsernameHeld()`: a name is held when any other profile, approved or pending, uses its token; `guest` is never held.
  - `prepareProfileName` returns `held`; `finalizePreparedProfileName` refuses a held name under the write lock with "That username is already in use." and writes nothing.
  - New test `WriteApiSmokeTest::testRenameIntoHeldUsernameIsNeverSavedDirectly` (case-insensitive held name, `guest`, own name, free name).
- Verification:
  - `php tests/run.php` for the new test plus the five existing profile-name tests in `WriteApiSmokeTest`: 6/6 passed.
  - `tests/browser/invite_zero_click.mjs` renames only to free names; deferred to the Stage 8 run.
- Notes: the refusal is temporary; Stage 2 turns a held-name finalize into a pending join request.
