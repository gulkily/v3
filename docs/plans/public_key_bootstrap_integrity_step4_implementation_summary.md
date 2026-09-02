# Public-Key Bootstrap Integrity Step 4 Implementation Summary

## Stage 1 - Prepare and finalize signed identity bootstraps
- Changes:
  - Added a prepared automatic-identity bootstrap flow that returns canonical post bytes for browser signing.
  - Added finalization that verifies the detached signature against the submitted public key before writing identity, public-key, bootstrap-post, and signature records.
  - Added `/api/prepare_identity` and `/api/create_identity` JSON endpoints and documented them in the API index.
  - Preserved the existing `link_identity` path for legacy/manual callers; its public-key-only bootstrap remains explicitly unsigned.
- Verification:
  - `php -l src/ForumRewrite/Write/LocalWriteService.php` — passed.
  - `php -l src/ForumRewrite/Application.php` — passed.
  - `php tests/run.php` — all tests passed.
- Notes:
  - Browser setup is not yet connected to these endpoints; that is Stage 2.

## Stage 2 - Sign automatic bootstrap statements in the browser
- Changes:
  - Changed normal browser identity publication to prepare an automatic bootstrap statement, sign it locally, and finalize it through the new identity endpoint.
  - Reused the existing local OpenPGP signing helper and server-known-identity check for duplicate/recovery behavior.
  - Kept private-key material in browser storage and sent only the detached signature to the server.
- Verification:
  - `node --check public/assets/browser_signing.js` — passed.
- Notes:
  - Existing browser test fixtures still mock the legacy link transport and will be updated with the signed lifecycle in Stage 4.

## Stage 3 - Make status and recovery understandable
- Changes:
  - Changed duplicate manual identity linking to redirect to the existing profile when the submitted public key can be inspected.
  - Clarified on profile pages that the bootstrap post is an internal setup artifact and is excluded from normal feeds and counts.
  - Kept an ordinary form error fallback when duplicate-key recovery cannot identify the submitted key.
- Verification:
  - `php -l src/ForumRewrite/Application.php` — pending final verification.
- Notes:
  - Stage 4 will update the browser fixtures and add regression coverage for the signed lifecycle and duplicate recovery.

## Stage 4 - Regression coverage and handoff
- Changes:
  - Updated browser identity lifecycle fixtures for profile inspection, signed-endpoint preparation, and legacy transport fallback.
  - Added coverage that the browser retries transient legacy publication failures without exposing private-key material in the request.
  - Added compatibility handling for older installations that do not yet expose the signed identity endpoints.
- Verification:
  - `node --check public/assets/browser_signing.js` — passed.
  - Signed identity preparation/finalization tests in `php tests/run.php` — passed.
  - Browser signing tests in `php tests/run.php` — passed.
  - Full `php tests/run.php` reached only two unrelated pre-existing fixture failures: an approval-script fixture missing `pages/profile.php`, and a bootstrap-public-key fixture using a SQLite database without `profiles`.
- Handoff notes:
  - New account setup uses `/api/prepare_identity`, local detached signing, and `/api/create_identity`.
  - The old `/api/link_identity` path remains available for legacy/manual unsigned anchors and should be treated as explicitly unsigned.
  - No migration or new role dependency is required.
