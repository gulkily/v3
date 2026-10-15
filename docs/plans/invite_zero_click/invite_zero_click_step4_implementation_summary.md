> **Feature plan:** [Step 1](./invite_zero_click_step1_solution_assessment.md) · [Step 2](./invite_zero_click_step2_feature_description.md) · [Step 3](./invite_zero_click_step3_development_plan.md) · [Step 4](./invite_zero_click_step4_implementation_summary.md)

Debug mode: enabled

# Implementation summary

## Stage 1 - Opt-in destination
- Changes: Invite captures path/query/fragment; checking destination prefills the source once and preserves edits. Shared server validation accepts fragments and rejects off-site/control/backslash escapes; client validation agrees.
- Verification: `php tests/run.php InvitationIssuanceTest CanonicalRecordParsersTest` — 53 passed; `git diff --check` passed.
- Notes: Existing fragment-rejection expectation updated for the approved behavior; browser acceptance remains Stage 7.

## Stage 2 - Signed naming records
- Changes: Immutable signed profile-name records with validated timestamps/names, prepare/finalize API routing, signer verification and prepared-record binding. Existing keys and bootstrap records are untouched.
- Verification: `php tests/run.php WriteApiSmokeTest::testSignedProfileNamePreservesIdentityAndRejectsForgery` passed, including foreign signature, tampered payload, invalid name and byte preservation checks; PHP lint and diff checks passed.
- Notes: Name syntax follows existing normalized username characters, bounded to 80 characters. Existing duplicate-name routing policy is retained; projection support follows in Stage 3.

## Stage 3 - Naming projections
- Changes: Verified name updates replay deterministically into canonical profile/username and author projections, including incremental identity insertion. Sequential name writes use increasing per-identity timestamps. Name writes use the existing full rebuild and invalidate presentation. Read-model version 20 documents the new projection semantics without adding tables.
- Verification: Signed naming smoke test passes immediate projection, version-19 normal-startup recovery, subsequent incremental identity write, repeated naming, and forged-update rejection on rebuild. Prior-version startup regression and ReadModelSchemaTest passed; diff check passed.
- Notes: Full rebuild after a name change favors consistent authors/activity and approval retention over adding another partial update path. CLI rebuild is included in Stage 7.

## Stage 4 - Browser identity and Account naming
- Changes: Identity preparation accepts a temporary name without prompting, preserving existing keys. Account offers Choose username through signed preparation/finalization, updates local labels after successful save, and reconciles authenticated server names on render.
- Verification: BrowserSigningNormalizationTest — 81 passed, including concurrent noninteractive temporary-key generation; JavaScript syntax check passed. Backend naming/continuity regression from Stage 3 remains passing.
- Notes: No arrival-time prompt; Account naming uses existing browser status and signing components. Full browser rename acceptance follows in Stage 7.

## Stage 5 - Invitation inspection and recovery
- Changes: Added nonmutating no-store bearer inspection, signed same-recipient recovery under the write lock, destination in final results, and already-approved access without consuming unused invites. Different recipients cannot recover a consumed invite.
- Verification: Signed invitation policy/recovery smoke test and PrivateSiteAuthTest — 10 passed. Tests cover nonconsumption on inspection/approved entry, lost-response recovery without extra canonical writes, forged ownership, foreign recipient, exact destination, and policy recheck at acceptance; diff check passed.
- Notes: Inspection reveals only targeted/available-or-used state, never recipient identity or a successful recovery claim. Recovery requires a new signed prepared operation and retained member approval.

## Stage 6 - Automatic authenticated arrival
- Changes: Targeted invites inspect before key creation, silently prepare/reuse identity, redeem/sign, force fresh approval verification without auth navigation, and replace Lobby with the validated target. Untargeted invites stay manual. Invite pages suppress competing auth/guest startup; errors retain the bearer for explicit retry and expose Account/board recovery links.
- Verification: 90 existing auth/identity tests passed; expanded auth/issuance suite — 19 passed. New redemption harness covers automatic/manual separation, exact target, fresh auth contract, network retry, storage/auth/unsafe-target failures, and invalid tokens without key creation.
- Notes: Target handoff no longer depends on sessionStorage or an intermediate reload. Missing resources retain the normal authenticated Not Found page and site navigation. Repeated clicks are serialized; retries are user-triggered after failure.

## Stage 7 - Acceptance and release checks
- Changes: Added isolated Chrome acceptance fixture/server and public/private UI matrix; documented invitation entry, naming, recovery, preview consumption, and rollback; updated authentication reference and completed todo entry. Acceptance review aligned encoded-space URL validation and rejects stale concurrent name updates. Successful entry clears legacy destination storage when available.
- Verification: `node tests/browser/invite_zero_click.mjs` passed on public and members-only servers: UI-generated link/QR, source opt-in/edit retention, fresh/pending/approved keys, exact query/fragment, same-key naming, isolated `./v3 rebuild`, same-recipient reuse, lost final response followed by reload, blocked storage without consumption, missing-resource navigation, invalid/expired/revoked/foreign-used invites, and manual untargeted flow. No targeted-flow dialogs occurred.
- Verification: Affected PHP/JS suite (`WriteApiSmokeTest BrowserSigningNormalizationTest InvitationIssuanceTest PrivateSiteAuthTest AuthNavigationTest ReadModelSchemaTest`) — 267 passed. Additional parser/metadata/candidate/startup regressions — 63 passed. Final validation/naming/issuance checks — 55 passed, plus stale-name ordering recheck passed. PHP/JS syntax and `git diff --check` passed.
- Notes: CLI rebuild and real-browser roles verified only against temporary local repositories. No production deployment/external service mutation was authorized or performed. Read-model caches rebuild automatically at version 20; canonical records and keys need no migration. Rollback preserves signed name records; older code may display bootstrap names. Preview tools executing JavaScript can consume targeted invites, as documented. The full unrelated repository suite was not required or run.

## Completion
- All seven planned stages are implemented and verified; planning and stage commits are separate.
- Browser acceptance command: `node tests/browser/invite_zero_click.mjs` (Chrome path can be set with `CHROMIUM_PATH`).
- Operator documentation: [Invitation entry](../../reference/invitation_entry.md).

## Follow-up - Default autogenerated username to guest
- Changes: Per user request, fresh targeted-invite keys now use `guest`; existing keys/names remain preserved and later Account naming still works. Updated operator documentation and browser assertions.
- Verification: InvitationIssuanceTest — 9 passed; real-browser public and members-only acceptance matrices passed, explicitly asserting the initial guest username and exercising repeated fresh guest redemptions, recovery, and later naming. JavaScript syntax and diff checks passed.
- Notes: Existing approval policy remains in force; only the autogenerated username changed.
