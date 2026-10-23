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

## Stage 4 - Accept and decline join requests
- Changes:
  - `ApprovalPolicy::mayGrantUsername()`: an operator, or an approved key whose current token is the requested name. Names held only by pending profiles therefore need an operator. It deliberately ignores `ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL`, because joining a name requires that name's consent.
  - `LocalWriteService`:
    - `profileNameJoinRequestsFor()` lists answerable requests, oldest first, with the requester's current name and slug, and skips entries for unknown identities.
    - `declineProfileNameJoinRequest()`.
    - `prepareProfileNameGrant()` builds a grant naming the held record.
    - `finalizePreparedProfileNameGrant()` verifies the grantor's signature, then under the write lock re-checks the request, the grantor's authority, the requester's latest name and the requester's signature. It commits the grant and the rename (plus their `.asc` files) in one commit, rebuilds, and clears the request.
  - Endpoints:
    - `GET /api/profile_name_join_requests` and `POST /api/decline_profile_name_request` (session).
    - `POST /api/prepare_profile_name_grant` and `POST /api/finalize_profile_name_grant` (signed).
    - Added to the routing, session-start and allowlists, and `ApiRouteCatalog`.
  - Tests:
    - `testHolderAcceptsJoinRequestAndOthersCannot`: stranger can't list, prepare or decline; holder lists over HTTP; forged grant signature refused; accept keeps approval; one commit holds the grant; replay refused.
    - `testJoinRequestDeclineStaleStateAndPendingOnlyNames`: decline; requester renamed since asking; holder renamed away between prepare and finalize; pending-only name answered by an operator only.
- Verification:
  - Six username tests plus `ApprovalPolicyTest` and `ApiRouteCatalogTest`: 15/15 passed.
- Notes:
  - Found while testing: join requests are stored next to the database, which in tests is the shared temp directory, so files from other runs can appear. Listing now skips identities that aren't in this repository. Prepared posts already share this layout; in production the directory is per instance (`state/cache`).
  - The Stage 2 endpoint test only passed because of the order tests ran in (once output has been sent, `session_start()` doesn't run). Both endpoint tests now start the session explicitly through an `actAs()` helper.
  - Removed leftover request files from earlier failed runs in `/tmp/profile-name-join-requests/`. (Correction, recorded in Stage 5: there were five files, not three; passing runs leave none.)

## Stage 5 - Requester UI
- Changes:
  - `account_key.php`: a hidden pending-request line ("Waiting for <name> to accept.") with a Cancel button, inside the status card under the actions.
  - `browser_signing.js`: Choose username accepts a `pending` result and shows the line instead of a status message. On load (flag on and signed in), the page fetches `/api/profile_name_request` to restore the line. Cancel posts `/api/cancel_profile_name_request`. The expiry is shown only within the last 24 hours. A free-name save hides the line.
  - New browser regression `tests/browser/username_join_request.mjs`, with fixture `tests/Support/username_join_browser_fixture.php` (an operator approves an `ilya` holder and a `guest` requester). It reuses `invite_browser_router.php`. Screenshots are written when `SHOT_DIR` is set.
- Verification:
  - `node tests/browser/username_join_request.mjs`: passed at desktop (1280) and phone (390). Covers no line initially, request, line text, no duplicate status message, name unchanged, survives reload, no horizontal overflow, cancel, and stays cleared after reload.
  - Screenshots reviewed in the light, word97, chicago and midnight themes at both widths. The line wraps with its button on phone, and Cancel matches the existing small-button style. The Word 97/Chicago fixed status bar shows over content in full-page screenshots; that is existing theme chrome, unrelated to this change.
  - `AccountKeyPageHooksTest` and `BrowserSigningNormalizationTest`: 88/88 passed. `tests/browser/invite_zero_click.mjs` (includes Choose username to a free name): all three scenarios passed.
- Notes: no navigation destination changed, so `theme-nav-icons.css` is unaffected. No CSS added; the line uses existing `meta` and `account-key-viewer-copy` styles.

## Stage 6 - Holder UI
- Changes:
  - `account_key.php`: a "Username requests" section below the status card, hidden when empty. Each request comes from a `<template>` and shows "<requester> asks to use <name>. This key will be able to act as <name>." (the consequence beside the decision, matching approval wording), with Accept, Decline, and a status line for that request.
  - `browser_signing.js`: on load (flag on and signed in), lists `/api/profile_name_join_requests`.
    - Accept signs the grant (prepare → `signCanonicalRecord` → finalize).
    - Decline posts the decline.
    - Success removes the request and hides the section when empty; errors show inside that request and re-enable its buttons.
  - `identity.css`: list reset, separators between requests, wrapping for long names. Buttons reuse `account-key-simple-actions` and `account-key-secondary-button`.
  - `tests/browser/username_join_request.mjs` extended for the holder side.
- Verification:
  - Browser test passed at desktop and phone. Covers: hidden when empty; two requests (one from an operator key); 77-character requester name with no overflow; operator cancels first, then the holder's Decline shows "This request is no longer pending." in that request; decline leaves the requester's name and clears their pending line; accept hides the section, the requester's Account shows `ilya`, and `ilya`'s recipient keys are exactly the holder's and requester's keys.
  - Screenshots reviewed (light, word97, chicago, midnight; desktop and phone). The first pass showed full-width Accept/Decline and the error at the bottom of the section; both fixed before commit.
  - `AccountKeyPageHooksTest`: 6/6 passed.
- Notes: `/user/ilya` doesn't print profile fingerprints in its main text, so the browser test checks composite membership through the recipient-keys API instead.

## Stage 7 - Documentation
- Changes:
  - `docs/specs/profile_read_contract_v1.md`: commits the "Composite User" section drafted before this cycle (definition, "composite identity" is not a separate term, effect of a rename, private-message history) and adds the consent-to-join rule.
  - `docs/reference/invitation_entry.md`: step 4 no longer says "Names need not be unique"; step 7 says a held name stops at a join request; new "Joining a username someone else uses" section (request, answer, save, endpoints, flag).
  - `docs/research/username_change_report.md`: follow-up section recording what was traced, defined and changed, and what remains open.
- Verification:
  - Relative link and anchor check across the three docs and this cycle's plans: all targets exist. The only flagged anchor (`#cycle-v3username_rename_collision`) is a false positive from the checker stripping underscores; GitHub keeps them.
  - `git diff --check`: clean.
  - Behaviour described matches the Stage 1–6 tests: held includes pending holders; `guest` is exempt; one request per key; 7-day expiry; operator-only acceptance for pending-only names; one combined commit.
- Notes: the report's two earlier "Unverified" bullets are left as written (historical) and answered by the new follow-up section.
