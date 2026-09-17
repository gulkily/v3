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
