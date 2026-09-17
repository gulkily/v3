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
