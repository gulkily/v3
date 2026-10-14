> **Feature plan:** [Step 1](./image_upload_enhancements_step1_solution_assessment.md) · [Step 2](./image_upload_enhancements_step2_feature_description.md) · [Step 3](./image_upload_enhancements_step3_development_plan.md) · [Step 4](./image_upload_enhancements_step4_implementation_summary.md)

Debug mode: enabled

## Stage 1 - Server decoding readiness
- Changes: Added bounded new-upload JPEG/PNG decoding, CRC/truncation checks and APNG rejection; documented serving-runtime GD requirements. Installed matching php8.1-gd locally.
- Verification: `php tests/run.php ImageUploadValidatorTest` (2/2 passed); valid JPEG/PNG, damage, animation, limits and missing GD are covered.
- Notes: No upload wiring yet; historical reads/restores remain independent of GD.
