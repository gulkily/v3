> **Feature plan:** [Step 1](./account_key_page_simplification_step1_solution_assessment.md) · [Step 2](./account_key_page_simplification_step2_feature_description.md) · [Step 3](./account_key_page_simplification_step3_development_plan.md) · [Step 4](./account_key_page_simplification_step4_implementation_summary.md)

Debug mode: enabled

Branch: `account-key-page-simplification` (stacked on the unmerged `account-key-page-redesign`). Planning commit: `docs: add account key page simplification plans (steps 1-3)`.

## Stage 1 - Hook contract update
- Changes:
  - `tests/AccountKeyPageHooksTest.php`: added a check that every `data-*` hook selector in `key_backup_ui.js` exists in the template, and a `RETIRED_HOOKS` list (empty) so any removal must be explicit. No page change.
  - New-structure hooks (fingerprint, expando IDs) are asserted in the stages that add them.
- Verification:
  - `php tests/run.php AccountKeyPageHooksTest LocalAppSmokeTest BrowserSigningNormalizationTest`: 222 passed, 0 failed.
- Notes:
  - Hook list derived from the current template, `browser_signing.js`, `key_backup_ui.js`, and `key_backup_ui.mjs`.
