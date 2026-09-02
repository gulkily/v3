# Public-Key Bootstrap Integrity Step 4 Implementation Summary

## Stage 1 - Prepare and finalize signed identity bootstraps
- Changes:
  - Added a prepared automatic-identity bootstrap flow that returns canonical post bytes for browser signing.
  - Added finalization that verifies the detached signature against the submitted public key before writing identity, public-key, bootstrap-post, and signature records.
  - Added `/api/prepare_identity` and `/api/create_identity` JSON endpoints and documented them in the API index.
  - Preserved the existing public-key-only `link_identity` path for legacy/manual callers.
- Verification:
  - `php -l src/ForumRewrite/Write/LocalWriteService.php` — passed.
  - `php -l src/ForumRewrite/Application.php` — passed.
  - `php tests/run.php` — all tests passed.
- Notes:
  - Browser setup is not yet connected to these endpoints; that is Stage 2.
