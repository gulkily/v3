> **Feature plan:** [Step 1](./invite_qr_code_step1_solution_assessment.md) · [Step 2](./invite_qr_code_step2_feature_description.md) · [Step 3](./invite_qr_code_step3_development_plan.md) · [Step 4](./invite_qr_code_step4_implementation_summary.md)

Debug mode: enabled

## Status

Implementation in progress on `feature/invite-qr-code`. Approved planning commit: `ac22088d`. Real-phone acceptance remains required before completion.

## Stage 1 - Inline QR and copy fallback

- Changes: Extended the canonical invitation result with a labeled canvas and accessible link/status. Bundled unmodified MIT-licensed qrcode-generator 2.0.4 with integrity/provenance; loads only on Invite through existing asset fingerprinting. Fixed black/white rendering includes four-module margins. Missing encoder or canvas failure preserves the new copyable link and successful-issuance feedback.
- Verification: `php tests/run.php InvitationIssuanceTest LocalAppSmokeTest` ran 144 tests: 142 passed. The core-route toolbar assertion also fails on untouched `ac22088d`; the unrelated feature-flag tooltip assertion passes with `FORUM_SECRETS_PATH` pointing to an absent file, identifying local private configuration dependence. All eight invitation tests passed. A separate clean baseline worktree with this patch passed 10 focused tests including both invitation wiring and the flag test. PHP/JS syntax and `git diff --check` passed.
- Verification: Chrome loaded the page through Board → Invite, with issuance/signing mocked for presentation isolation. Independently decoded actual canvas pixels with jsQR 1.4.0 to the exact displayed link; checked all 12 themes at 320/1280 pixels (24 checks), square sizing, white background, no external requests or browser errors. Reviewed screenshot. Encoder also independently decoded 65-, 81-, and 259-byte URLs. Temporary evidence: `/tmp/invite-qr-acceptance/preview.json`, `preview.png`; regression output: `/tmp/invite-qr-stage1-tests.log`.
- Notes: Real issuance/redemption and physical-phone scanning are not claimed by this preview. Existing flat asset packaging dictated keeping the vendored file directly under `public/assets/`. Database migration and production deployment are not applicable.
