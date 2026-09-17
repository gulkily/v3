# Redeemable Board Invitations — Step 4: Implementation Summary

## Stage 1 - Invitation lifecycle foundation
- Changes:
  - Added canonical invitation records for issued, revoked, and redeemed events.
  - Added a ledger that rejects duplicate issuance, hash mismatches, foreign revocation, and repeated redemption.
  - Validated hash-only canonical data and optional internal destinations.
- Verification:
  - `php tests/run.php CanonicalRecordParsersTest` passed.
- Notes:
  - Invitation records intentionally contain only a SHA-256 verification hash; bearer secrets are not represented in canonical records.

## Stage 2 - Site-wide invitation issuance and destination selection
- Changes:
  - Added signed, approved-session invitation issuance and issuer-only revocation APIs.
  - Added a shared Invite navigation action that opens a generator from any board page with that page as its optional target.
  - Added a generated-link flow using a browser-only fragment secret and Activity labels for issue/revoke events containing the verification hash only.
- Verification:
  - PHP lint passed for the application, writer, and read-model updates.
  - `node --check public/assets/invite_issuance.js` and `node --check public/assets/invite_navigation.js` passed.
  - `php tests/run.php CanonicalRecordParsersTest ReadModelBuilderTimingTest LocalAppSmokeTest::testPrivateLobbyOnlyExposesLobbyAccountAndAuthenticationSurfaces` passed.
- Notes:
  - External and malformed targets are rejected before a signed invitation is created; the final target access check remains part of redemption.

## Stage 3 - Lobby identity creation and redemption
- Changes:
  - Added a Lobby redemption surface that reads the bearer secret only from a URL fragment and removes it after successful use.
  - Added signed redemption preparation/finalization bound to the recipient browser identity, with single-use, expiry, revocation, and hash checks under the write lock.
  - Kept redemption available to Lobby users while requiring a published browser-key identity before it can proceed.
- Verification:
  - PHP lint passed for application, writer, and invitation ledger updates.
  - `node --check public/assets/invite_redemption.js` passed.
  - `php tests/run.php CanonicalRecordParsersTest LocalAppSmokeTest::testPrivateLobbyOnlyExposesLobbyAccountAndAuthenticationSurfaces` passed.
- Notes:
  - The recipient is intentionally prompted to set up a fresh browser key; no key-restore path was added to the invitation surface.

## Stage 4 - Approval derivation and session transition
- Changes:
  - Extended both full and incremental approval derivation to recognize a valid issued invitation followed by its signed redemption as an inviter-attributable approval.
  - Added a redeemed Activity event linked to the same verification hash.
  - Preserved the validated optional destination through redemption and use it only after browser-key authentication succeeds.
- Verification:
  - PHP lint passed for both read-model implementations.
  - `node --check public/assets/private_site_auth.js` and `node --check public/assets/invite_redemption.js` passed.
  - `php tests/run.php ReadModelBuilderTimingTest PrivateSiteAuthTest LocalAppSmokeTest::testApprovedPrivateSessionCanViewOwnProfileAndBoard` passed.
- Notes:
  - Invalid, expired, revoked, or already redeemed invitations cannot become approval candidates.

## Stage 5 - Security regression coverage and operator guidance
- Changes:
  - Added private Lobby route-matrix coverage for the redemption preparation endpoint.
  - Documented invite generation, seven-day expiry, revocation, Activity hash visibility, disposable keys, target routing, and secret-leak response in the production runbook.
  - Made invitation redemption start the standard browser key-generation flow when the recipient has no key, and made generated links select-on-focus with an explicit Copy action.
- Verification:
  - `php tests/run.php LocalAppSmokeTest::testPrivateLobbyOnlyExposesLobbyAccountAndAuthenticationSurfaces CanonicalRecordParsersTest ReadModelBuilderTimingTest PrivateSiteAuthTest` passed.
  - `php tests/run.php BrowserSigningNormalizationTest` passed after hardening browser initializers for the minimal DOM environment used by the regression suite.
  - `php tests/run.php` passed.
  - `php tests/run.php BrowserSigningNormalizationTest LocalAppSmokeTest::testApplicationRendersCoreRoutes PrivateSiteAuthTest` passed after the invitation interaction refinement.
  - `git diff --check` passed for feature changes; the pre-existing `todo.txt` trailing-blank-line warning remains unrelated.
- Notes:
  - The bearer secret is intentionally confined to a browser fragment and the private redemption request; the canonical repository and Activity expose only its verification hash.
