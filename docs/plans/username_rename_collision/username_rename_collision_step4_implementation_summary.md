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

## Stage 2 - Store held renames as join requests
- Changes:
  - Finalizing a held name verifies the signature, then stores a join request (signed record, signature, 7-day expiry) in private state at `state/.../profile-name-join-requests/<identity>.json`, one per key, and returns `status: pending`. The prepared token is consumed.
  - A successful free-name rename deletes any pending request for that key.
  - `LocalWriteService::profileNameJoinRequest()` (public fields only; prunes an expired request on read) and `cancelProfileNameJoinRequest()` (under the write lock).
  - New `ProfileNameJoinRequestApiController` with `GET /api/profile_name_request` and `POST /api/cancel_profile_name_request`, using the signed-in session key (401 otherwise). Routes are added to the session-start list, the members-only allowlist, the route list and `ApiRouteCatalog`.
  - Stage 1 test renamed to `testRenameIntoHeldUsernameBecomesJoinRequest`; new `testJoinRequestIsReplacedCancelledAndExpires` (replace, endpoint status and cancel, 401, expiry pruning, private fields not exposed).
- Verification:
  - Both new tests, the two existing profile-name tests and `ApiRouteCatalogTest`: 7/7 passed.
- Notes:
  - Deviation from the plan wording: requests are stored in their own directory beside `prepared-posts`, not as a kind inside it, so one-per-key lookup and listing don't need to scan short-lived prepared posts. Same private, non-canonical state as planned.
  - Status and cancel use the signed-in session rather than a signature, the same way the private-message endpoints work.

## Stage 3 - Audit-only grant record family
- Changes:
  - `ProfileNameGrantRecordParser`: `Record-ID: profile-name-grant-<32 hex>`, `Created-At`, `Operation: grant-name`, `Author-Identity-ID` (grantor), `Grantee-Identity-ID` (must differ), `Username`, `Profile-Name-Record-ID` (the held `set-name` record), empty body.
  - `CanonicalPathResolver::profileNameGrant()` gives `records/profile-name-grants/<id>.txt`. `SourcePathValidator` accepts the record and its `.asc`.
  - `ActivityService` labels the path "profile name grant record" and attributes it to the grantor in commit manifests.
  - New test `testProfileNameGrantRecordIsAuditOnly`: parser valid/invalid cases, path validation, and identical `profiles` rows (name, token, approval) from a full rebuild before and after committing a signed grant.
- Verification:
  - New test plus `CanonicalRecordParsersTest`, `ContentImportPlannerTest` and the Stage 2 test: 59/59 passed.
- Notes:
  - Incremental update check: not applicable. `IncrementalReadModelUpdater` only handles post, label, subject, reaction, identity and approval writes. Rename writes, and therefore grant writes, use a full rebuild (`refreshDerivedStateAfterCommit`), which the test covers.
  - Imports: `ArchiveRecordCatalog` already doesn't recognize `profile-names`, so grants are likewise reported as unsupported; no import change.
