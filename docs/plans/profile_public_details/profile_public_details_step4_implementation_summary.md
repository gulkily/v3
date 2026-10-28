> **Feature plan:** [Step 1](./profile_public_details_step1_solution_assessment.md) · [Step 2](./profile_public_details_step2_feature_description.md) · [Step 3](./profile_public_details_step3_development_plan.md) · [Step 4](./profile_public_details_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/profile_public_details; [ledger](../effort_estimation_trial.md#cycle-v3profile_public_details); standing P2 enrollment
Extended estimates: enabled
Status: Five stages locally complete; production acceptance pending

**Cycle estimate: 0.60**

## Entry checkpoint

Date: 2026-10-27 UTC
Scope: v1/A; original baseline 93c3ea8132df45fd5b334f05758a144fdb90e78e; R1/P2; Codex single exposed rater; prior history/source/live-page and reference exposure retained.
Projection: Step 3 total 0.60 reaffirmed; earlier 0.50 checkpoints preserved. No measured labor or accepted outcome.
Planning commit: 81e1d51b7 on feat/profile-public-details, created before this summary or application changes.
Stage originals: S1 eligibility/counts 0.18; S2 key display 0.12; S3 history/recovery 0.22; S4 host parity 0.14; S5 acceptance/docs 0.16. Scopes/drivers/uncertainties inherited from approved Step 3, not independent ratings; non-additive.
Approval: User explicitly approved Step 3. Deployment, push and merge remain outside scope.
Dependencies: No additions or updates authorized or planned.

*— Codex · 2026-10-27 UTC*

## Stage 1 - Eligible identity contributions and totals

**Original estimate: 0.18**
**Retrospective stage work: 0.18**

- Changes: Shared exact-identity eligibility and count query; hidden/retracted/bootstrap/scripture-expansion exclusions, authored visible-root restriction for threads. Existing aggregate interfaces unchanged. Schema 31 and documented query contract.
- Verification: ProfilePublicDetailsTest 2/2, including same-name/unapproved fixtures and version-30 normal profile startup rebuild; isolated CLI rebuild passed.
- Notes: SQL bootstrap exclusion follows existing `identity` board-tag semantics (not merely `internal`). No dependencies. Original stage scope/estimate unchanged; no labor measurement.

*— Codex · 2026-10-27 UTC*

## Stage 2 - Public key on the normal profile

**Original estimate: 0.12**
**Retrospective stage work: 0.12**

- Changes: Reusable escaped public-key disclosure using existing themed viewer styles; independent public-only copy script with manual-selection recovery. Normal public profile loads it without browser identity/private-key dependencies. Updated obsolete key-absence tests.
- Verification: ProfilePublicDetailsTest and UserAccessTest 8/8; browser profile_public_details.mjs passed collapsed default, keyboard expansion, exact clipboard text, denied clipboard recovery, no-JS key availability and phone overflow. PHP lint passed.
- Notes: Stage 1 commit 7cbdd8163. Full theme acceptance remains Stage 5. Original scope/estimate unchanged; no dependencies or measured labor.

*— Codex · 2026-10-27 UTC*

## Stage 3 - Bounded profile history and recovery

**Original estimate: 0.22**
**Retrospective stage work: 0.22**

- Changes: Bounded exact-identity histories, full matching totals, stable creation-time/post-ID order and independent thread/post positions. ProfileHistoryView composes existing PageNavigation with optional position parameter names; shared navigation gains optional accessible labels. Invalid positions and empty histories offer recovery. Existing aggregate interfaces preserved.
- Verification: ProfilePublicDetailsTest 5/5, including tied-time traversal, arrivals/deletions, backward traversal, limit cap, unapproved normal-route counts, separate list navigation, malformed/cross-identity positions and deleted-page recovery. Earlier shared Activity/UserAccess checks 19/19 passed after shared pagination changes.
- Notes: Stage 2 commit 820fed58d. No new table/index; query changes covered by schema 31. Host/static query handling is next. Original estimate/scope unchanged, no dependencies or measured labor.

*— Codex · 2026-10-27 UTC*

## Stage 4 - Host/static delivery compatibility

**Original estimate: 0.14**
**Retrospective stage work: 0.10**

- Changes: Added actual active-release/static-versus-position regression and HTTP browser traversal with independent lists; expanded offline policy checks to positioned profile URLs. Existing host code already bypasses static artifacts for queries and excludes profiles from high-traffic caches; no routing mutation was necessary.
- Verification: Static/live regression plus OfflineNavigationWorkerTest 11/11; profile_public_details.mjs passed actual HTTP static first page, live older pages, independent positions, reload/backward/invalid-position recovery, JavaScript on/off and key behavior.
- Notes: Stage 3 commit 628fe999a. Corrected test setup to activate its generated release and use workspace destructor cleanup; browser static evidence checks response bytes because document serialization omits trailing comments. These were harness corrections, not production failures. Offline profile reads remain network-only with normal Reload/Back; no new shell fallback or freshness policy. No dependencies/measured labor.

*— Codex · 2026-10-27 UTC*

## Stage 5 - Acceptance and documentation

**Original estimate: 0.16**
**Retrospective stage work: 0.16**

- Changes: Updated profile/read-model contracts and copy audit; extended browser acceptance across all 13 zenmemes themes at phone/desktop widths, empty/key-unavailable/not-found states, and same-URL retry after an HTTP 503. Updated messaging's direct template fixture for the new history data contract.
- Verification: Focused profile/UserAccess/Activity/theme/matrix/offline/core-route/source checks 42/42; messaging composer/release-isolation checks 5/5 without warnings. Chromium profile_public_details.mjs passed with and without JavaScript, actual HTTP static/live routing, independent positions, keyboard, reload/previous/First page, exact-key clipboard and denied-copy recovery. All 13 themes at 390/1280 widths passed overflow and loaded/expanded/empty/unavailable checks; Word97/Chicago/Midnight screenshots visually inspected. Isolated final CLI rebuild, PHP/JS syntax checks and diff whitespace checks passed.
- Notes: Stage 4 commit 9dffe6c44. Theme screenshots are disposable local `/tmp/profile-public-<theme>-<width>.png` evidence, not committed assets. No runtime code changed during acceptance; corrected a legacy direct-render test fixture that otherwise emitted undefined-history warnings. No dependency delta, navigation destination/icon change, canonical format change or measured labor. Full suite and physical devices unverified.

## Production replay and recovery handoff

Status: Local acceptance complete; zenmemes.com deployment and named-profile acceptance pending. No live content or approvals changed.

1. After separately authorized deployment, allow normal schema-31 startup rebuild or run the installation's normal `./v3 rebuild` with its configured repository/database. Regenerate and activate static artifacts through the existing deployment procedure; old profile HTML does not acquire the feature merely because application files changed. Do not reuse the isolated test database.
2. Open `https://zenmemes.com/profiles/openpgp-e4299fae842c36f1404a693215130d67723b8cc1` anonymously through an author link and directly, on phone and desktop. Confirm the Public key disclosure starts closed; expand and compare/copy the full key against its Tools record. Clipboard denial must leave selectable text and manual-copy recovery.
3. Identify the records behind this profile's earlier 1-thread/1-post counts and compare the new totals with eligible authored rows. Hidden bootstrap records do not become public contributions; zero is legitimate if no eligible content exists. Follow a displayed contribution. Its unapproved status must not force activity browsing through `/user/testing1`.
4. For a profile with over 50 eligible entries, follow each list's Next/Previous links, reload an older-page URL, and confirm the other list's position remains intact. An invalid position must show First page recovery, not first-page content under that URL. Retry a failed request with Reload or return with Back. Empty histories and absent keys must be usable without misleading success feedback.
5. If results still show the old summary-only profile, verify the deployed revision and regenerate/activate the installation's current artifacts. Record actual same-host results before marking production acceptance complete. Local fixtures do not establish the exact live profile's final counts.

## Study closeout and release limits

**Original Step 3/entry cycle projection: 0.60**
**Retrospective cycle scope: 0.60**

Scope: v1/A, stable v3/profile_public_details, original baseline 93c3ea8132df45fd5b334f05758a144fdb90e78e, R1/P2; Codex single exposed rater. Earlier 0.50 intake/Step 1/Step 2 and all original stage values preserved; no stage sum or independent-rater claim. Exact frozen hashes verified. Five planned/completed stages; planning 81e1d51b7, stages 7cbdd8163 / 820fed58d / 628fe999a / 9dffe6c44 plus this closeout stage. Final commit is discoverable with `git log --oneline -- docs/plans/profile_public_details/`.
Outcome: Direct collapsed public key and identity-specific bounded histories with visible-content totals and normal recovery, locally verified. Maintainer comfortable-fit/study acceptance pending; accepted-outcome count unchanged. No measured labor, child observation or scope split. Production deployment/replay, physical devices, full suite, merge and push remain unperformed. No external dependencies added or updated. Guidance commit d28ea9165 predates this separately approved implementation.

*— Codex · 2026-10-27 UTC*

Acceptance follow-up: Stage 5 closeout commit 20b7ce6f2. Strengthened the HTTP browser check to open an authored contribution from the profile and return through its author profile link, with JavaScript both off and on. Corrected synthetic root fixture parent/author attribution and selected the synthetic contribution explicitly rather than assuming it sorts ahead of the retained root fixture. The full 13-theme browser check passed again. No production changes or estimate revision.

## Approved navigation refinement — 2026-10-27

Request: User accepted the approval-summary mockups after identifying the username and User Access links as confusing.
Disposition: Apply the accepted presentation as an in-scope profile refinement: remove supplementary username navigation and place Approval details beside Approved/Not approved. Keep the Tools destination and legacy account-keys anchor; no access policy or navigation destination change. Application of the previously optional username link does not change username aggregation.
Status: Applied and locally verified. Mockup images were review-only; removed from the project and commit history at the user’s request.
Verification: Focused PHP checks 18/18; updated HTTP browser checks passed for all 13 themes at 390/1280 widths, JavaScript on/off, independent histories and key recovery. Reviewed the updated phone rendering. No new dependencies, schema/query change, access-policy change or production deployment. Existing projected/retrospective cycle estimates retained; this is a small presentation refinement, not a new study outcome.

*— Codex · 2026-10-27 UTC*

## Main integration — 2026-10-27

Authorization: User explicitly requested merge into main and push, without further UI changes.
Integration: Retained upstream main through 085c47701 and local progressive-disclosure guidance. Resolved additive conflicts in the effort ledger, copy audit and test registration by preserving both sides. No application conflicts or further presentation changes. Mockup images and their former commit remain absent from branch/reflog history.
Verification: Merged profile/access/messaging/core-route and backup-policy checks 22/22; actual HTTP browser profile suite passed JavaScript on/off and all 13 themes at phone/desktop sizes. Diff checks passed. Production deployment and named-host acceptance remain separate from Git integration.
