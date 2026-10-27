> **Feature plan:** [Step 1](./profile_public_details_step1_solution_assessment.md) · [Step 2](./profile_public_details_step2_feature_description.md) · [Step 3](./profile_public_details_step3_development_plan.md) · [Step 4](./profile_public_details_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/profile_public_details; [ledger](../effort_estimation_trial.md#cycle-v3profile_public_details); standing P2 enrollment
Extended estimates: enabled
Status: Step 3 approved; implementation underway

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
