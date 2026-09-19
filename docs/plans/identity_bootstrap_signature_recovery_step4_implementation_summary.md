# Identity Bootstrap Signature Recovery: Step 4 Implementation Summary

## Stage 1 - Bounded bootstrap recovery

- Changes:
  - Preserved identity-bootstrap API failures as safe technical details.
  - Added one fresh retry for `signature_verification_failed`, alongside the existing transient key-inspection retry.
  - Kept the existing Account recovery message after a second failure.
- Verification:
  - `node --check public/assets/browser_signing.js` — passed.
  - `php tests/run.php BrowserSigningNormalizationTest::testReadyIdentityRetriesBootstrapSignatureVerificationFailure BrowserSigningNormalizationTest::testReadyIdentityStopsAfterSecondBootstrapSignatureVerificationFailure` — passed.
- Notes:
  - The retry occurs before any identity publication marker is stored.

## Stage 2 - Shared protected-action readiness

- Changes:
  - Added the canonical `ensureActionIdentity` readiness contract over the existing browser identity flow.
  - Routed compose identity preparation through that contract and exposed it to other protected-action surfaces.
- Verification:
  - `node --check public/assets/browser_signing.js` — passed.
  - `php tests/run.php BrowserSigningNormalizationTest::testActionIdentityReadinessUsesTheSharedRecoveryPath BrowserSigningNormalizationTest::testReadyIdentityRetriesBootstrapSignatureVerificationFailure BrowserSigningNormalizationTest::testReadyIdentityStopsAfterSecondBootstrapSignatureVerificationFailure` — passed.
- Notes:
  - The default preserves the established lightweight action-readiness behavior; callers can explicitly request full publication verification when needed.
