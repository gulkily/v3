> **Feature plan:** [Step 1](./image_content_step1_solution_assessment.md) · [Step 2](./image_content_step2_feature_description.md) · [Step 3](./image_content_step3_development_plan.md) · [Step 4](./image_content_step4_implementation_summary.md)

Debug mode: disabled

## Stage 1 - Portable storage and feature flag
- Changes: Added strict versioned body references, immutable private filesystem store, upload/store limits, and default-off registered image flag. Documented the canonical text contract; no database migration.
- Verification: `php tests/run.php ImageContentTest FeatureFlagEvaluatorTest` — 18 passed; reference round-trip, corruption, traversal/symlink roots, dimensions, idempotent storage and flag precedence checked. Updated the registry enumeration expectation for the new flag.
- Notes: Original bytes are retained; future bucket storage can preserve digest identity. Store mutations will use the existing instance write lock. UI and serving arrive in later stages.

## Stage 2 - Signed attachment publication
- Changes: Prepared posts bind portable image metadata in their signed body. Finalization accepts a server-verified multipart upload, verifies signature/approval/flag/checksum/limits under the instance write lock, and saves retained bytes only after the canonical commit. Unsigned thread/reply/quote writes reject image references. Image finalization retains its expiring preparation token for safe retries.
- Verification: `php tests/run.php WriteApiSmokeTest::testImageSignedPublicationRejectsTamperingAndRetriesSafely` passed with a real GPG signature, tampered bytes, disabled flag, unsigned bypass and duplicate retry checks; changed PHP files pass lint.
- Notes: A post committed before a failed image save is explicitly reported; retrying the same signed submission repairs the image without a duplicate post. Failed pre-commit uploads remain PHP temporary files, outside retained backups. Preparation-token expiry still applies.
