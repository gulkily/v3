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

## Stage 2 - Fingerprint on the status card
- Changes:
  - `templates/pages/account_key.php`: added a Fingerprint field and Copy action to the status card; the page script fills it from the stored fingerprint on the existing sync events, shows "No key yet" when absent, and copies the lowercase value (same form as the profile URL).
  - `public/assets/identity.css`: fingerprint row styles.
  - `tests/AccountKeyPageHooksTest.php`: asserts the new hooks.
- Verification:
  - `php tests/run.php AccountKeyPageHooksTest LocalAppSmokeTest BrowserSigningNormalizationTest`: passed.
  - Chromium run: empty state initially with Copy hidden; after set up the card showed `2e8112...731b`, matching the profile link's `openpgp-` fingerprint and the clipboard after Copy; after clear it returned to "No key yet".
  - `FORMAT=text node tests/browser/key_backup_ui.mjs`: passed.
- Notes:
  - Restore is covered by the backup browser test; the card relies on the same identity-change events.

## Stage 3 - Backup and restore as a collapsed expando
- Changes:
  - `templates/pages/account_key.php`: the visible backup section is again a native `<details id="key-backup" data-key-backup>` labeled "Back up or restore", collapsed by default; tabs, panels, and warnings kept; the redundant intro paragraph and heading removed.
  - `tests/browser/key_backup_ui.mjs`: restored the two clicks that open the expando on a direct page visit.
- Verification:
  - `php tests/run.php AccountKeyPageHooksTest LocalAppSmokeTest BrowserSigningNormalizationTest`: 222 passed.
  - `FORMAT=text` and `FORMAT=png node tests/browser/key_backup_ui.mjs`: both passed, including the "Back up your key" reminder link, which opens the collapsed expando through the existing hash handling in `key_backup_ui.js` (no script change needed).
- Notes:
  - Opening from `#key-backup` is covered by the reminder step in the browser test; direct visits stay collapsed.
