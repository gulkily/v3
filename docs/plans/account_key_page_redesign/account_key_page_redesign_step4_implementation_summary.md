> **Feature plan:** [Step 1](./account_key_page_redesign_step1_solution_assessment.md) · [Step 2](./account_key_page_redesign_step2_feature_description.md) · [Step 3](./account_key_page_redesign_step3_development_plan.md) · [Step 4](./account_key_page_redesign_step4_implementation_summary.md)

Debug mode: enabled

Branch: `account-key-page-redesign`. Planning commit: `docs: add account key page redesign plans (steps 1-3)`.

## Stage 1 - Hook contract test
- Changes:
  - Added `tests/AccountKeyPageHooksTest.php` asserting the template keeps every hook the page script, `browser_signing.js`, and browser tests use, and that the script still targets them.
  - Registered it in `tests/run.php`. No page or style change.
- Verification:
  - `php tests/run.php AccountKeyPageHooksTest`: 2 passed.
  - `php tests/run.php BrowserSigningNormalizationTest LocalAppSmokeTest`: 218 passed, 0 failed.
- Notes:
  - `tests/browser/key_backup_ui.mjs` asserts the backup password field is hidden initially; Stage 3 (promoting backup) must update that assertion deliberately.
