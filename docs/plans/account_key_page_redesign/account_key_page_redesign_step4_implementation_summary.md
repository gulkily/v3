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

## Stage 2 - Status-first header
- Changes:
  - Added a header with title and one-line purpose in `templates/pages/account_key.php`.
  - Wrapped the existing status block as a bordered status card; page-scoped styles in `public/assets/identity.css` using theme tokens only.
- Verification:
  - `php tests/run.php AccountKeyPageHooksTest LocalAppSmokeTest BrowserSigningNormalizationTest`: 220 passed, 0 failed.
  - Playwright Chromium screenshots at 1100px and 375px (light theme): no horizontal overflow; status card, username, and primary action render first.
  - Other themes not individually screenshotted; styles use existing tokens only (`--line`, `--panel`, `--ink`). Full theme matrix is part of Stage 6.
- Notes:
  - Fingerprint display not added (needs script change; out of Stage 2 scope).

## Stage 3 - Prominent backup and restore
- Changes:
  - `templates/pages/account_key.php`: the collapsed "Back up or restore your key" drawer is now a visible section with heading and one-line explanation; `#key-backup`, `data-key-backup`, tabs, panels, and all warnings are unchanged.
  - `public/assets/identity.css`: section spacing and heading style.
  - `tests/browser/key_backup_ui.mjs`: removed the two clicks on the old `<summary>`, which no longer exists.
- Verification:
  - `php tests/run.php AccountKeyPageHooksTest LocalAppSmokeTest BrowserSigningNormalizationTest`: 220 passed.
  - `FORMAT=text` and `FORMAT=png node tests/browser/key_backup_ui.mjs`: both passed (download, reminder, rejection, rollback, exact restore, decryption).
  - 1100px and 375px: no horizontal overflow.
- Notes:
  - The script's `area.open = true` for `#key-backup` links is now a harmless no-op because the section is always visible.

## Stage 4 - Technical content grouped by intent
- Changes:
  - `templates/pages/account_key.php`: the technical drawer ("Under the hood: keys and storage") now has four labeled groups: Public key (viewer, copy, load, link-identity form), Private key (warning, viewer in a collapsed native disclosure, restore/copy), Storage and identity (status with undo, guest publication, hint cookie, saved identity), and Remove from this browser (clear keypair, dashed border). All hooks kept.
  - `public/assets/identity.css`: group, warning, reveal, and danger styles.
  - `tests/LocalAppSmokeTest.php`: updated the drawer label assertion.
- Verification:
  - `php tests/run.php AccountKeyPageHooksTest LocalAppSmokeTest BrowserSigningNormalizationTest`: 220 passed.
  - `FORMAT=text` and `FORMAT=png node tests/browser/key_backup_ui.mjs`: both passed.
  - Chromium at 375px: no horizontal overflow; private-key viewer not visible until "Show private key" is opened.
- Notes:
  - The private-key viewer stays in the DOM, so script updates still land.
  - The drawer wording ("Under the hood") and whether it should default to open are reviewed in Stage 5.
