> **Feature plan:** [Step 1](./image_upload_enhancements_step1_solution_assessment.md) · [Step 2](./image_upload_enhancements_step2_feature_description.md) · [Step 3](./image_upload_enhancements_step3_development_plan.md) · [Step 4](./image_upload_enhancements_step4_implementation_summary.md)

Debug mode: enabled

## Stage 1 - Server decoding readiness
- Changes: Added bounded new-upload JPEG/PNG decoding, CRC/truncation checks and APNG rejection; documented serving-runtime GD requirements. Installed matching php8.1-gd locally.
- Verification: `php tests/run.php ImageUploadValidatorTest` (2/2 passed); valid JPEG/PNG, damage, animation, limits and missing GD are covered.
- Notes: No upload wiring yet; historical reads/restores remain independent of GD.

## Stage 2 - Upload policy and legacy compatibility
- Changes: New signed publications undergo strict decoding before persistence; exact committed-post retries/repair retain historical limits. Multipart MIME must match inspected contents.
- Verification: `php tests/run.php ImageUploadValidatorTest ImageContentTest WriteApiSmokeTest::testImageSignedPublicationRejectsTamperingAndRetriesSafely` — 7/7 passed, including signed oversized rejection with no persisted object/post and exact legacy repair.
- Notes: Replaced the historical test PNG with a valid CRC fixture; the old sample passed header checks but failed strict decoding. Global ImageReference limits remain unchanged.

## Stage 3 - Client preparation
- Changes: Added shared local JPEG/PNG/WebP/GIF/BMP preparation, header-based predecode bounds (32 MiB, 40 MP, 16384 edge), 2048-edge output, lossless alpha preservation, JPEG orientation and byte-bound shrinking. Decode timeout and resource cleanup included.
- Verification: `node tests/browser/image_prepare.mjs` passed in Chromium with touch/mobile viewport: >5 MiB PNG, >4096-edge JPEG, transparent WebP/PNG, GIF, rotated EXIF JPEG, no upscaling and invalid/oversized inputs.
- Notes: Formats requiring unavailable decoders (including HEIC/AVIF) receive export guidance. Actual handheld-device testing is not represented by viewport emulation.

## Stage 4 - Prepared-image signing and recovery
- Changes: Loaded shared preparation/state modules through both canonical layouts; signing/hash/multipart consume prepared bytes only. Selection clears original file input, supersedes stale preparation, clears stale signed attempts, and retains prepared copies across retries. Busy locking, reset cleanup and Outbox exclusion use the shared attachment state.
- Verification: `node tests/browser/image_compose_transport.cjs`, `node tests/browser/image_prepare.mjs`, JavaScript syntax and PHP renderer lint passed. Tests cover reuse of signed retries, text changes, offline rejection, rapid replacement, lock and source-input clearing.
- Notes: Preview controls and permission-aware visibility follow in Stages 5–6. Original files remain untouched on the user's device.
