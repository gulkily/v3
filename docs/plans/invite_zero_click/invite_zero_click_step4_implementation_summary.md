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
