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

## Stage 4 - Keys expando
- Changes:
  - `templates/pages/account_key.php`: new collapsed sibling expando "Keys" (`#key-keys`) holding the public key viewer with Copy, the private key behind a nested "Private key" disclosure with a one-line warning, Restore Private Key, Load Saved Public Key, and the link-a-public-key form. The public/private group boxes and their intro lines were removed from the old drawer.
  - The separate Copy Public Key and Copy Private Key buttons are kept in a `hidden` row; the viewer Copy buttons trigger them as before.
- Verification:
  - `php tests/run.php AccountKeyPageHooksTest LocalAppSmokeTest BrowserSigningNormalizationTest`: 222 passed.
  - Chromium run after set up: public viewer filled; private viewer hidden until its disclosure opens; public Copy yielded a PGP PUBLIC KEY BLOCK and private Copy a PGP PRIVATE KEY BLOCK from the clipboard.
  - `FORMAT=text node tests/browser/key_backup_ui.mjs`: passed.
- Notes:
  - Hidden buttons are removed from the focus order; the old drawer still holds storage and clear until Stage 5.

## Stage 5 - Storage and privacy expando
- Changes:
  - `templates/pages/account_key.php`: the old "Under the hood" drawer is now the flat sibling expando "Storage and privacy" (`#key-technical`): a three-line where-things-live note, browser key status with Undo, the guest-publication setting, identity hint and saved identity, and Clear Saved Keypair. Group boxes and headings removed.
  - `public/assets/identity.css`: deleted the `.account-key-group*` and `.account-key-section*` styles that no longer apply.
  - `tests/LocalAppSmokeTest.php`: drawer label assertion now "Storage and privacy".
- Verification:
  - `php tests/run.php AccountKeyPageHooksTest LocalAppSmokeTest BrowserSigningNormalizationTest`: 222 passed; existing assertions on the guest-publication and "Saved browser identity" copy were kept intact by preserving that text.
  - Chromium run with the expando collapsed: card status read "All set! Posting as alice."; Clear removed the key (fingerprint "No key yet", status "Removed this browser keypair.", Undo visible); Undo restored it with the fingerprint back; the guest-publication setting persisted across reload.
  - `FORMAT=text node tests/browser/key_backup_ui.mjs`: passed.
- Notes:
  - Collapsed `<details>` content stays in the DOM, so status mirroring to the card works without opening it.

## Stage 6 - Polish and regression pass
- Changes:
  - Removed the non-essential lead paragraph under the title and its style; no other defects found in review.
- Verification:
  - Theme matrix: all 15 theme stylesheets at 1100px and 375px with all expandos and the private-key reveal open: 30 combinations, no horizontal overflow and no text/background pair below 4.5:1 (username, fingerprint, labels, warning, where-things-live, expando summaries, status badge).
  - Screenshot at 375px: card with state, name, fingerprint and Copy, profile link, actions, then three collapsed sibling expandos.
  - `php tests/run.php` (full suite): 1190 run, 1190 passed, 0 failed.
  - `FORMAT=text` and `FORMAT=png node tests/browser/key_backup_ui.mjs`: passed (reminder deep link, restore, privacy).
  - `identity.css` changes in this cycle are `.account-key-*` only.
- Notes:
  - Not covered: a real-device mobile browser and a screen-reader pass; no deployment, migration, or server changes, so those checks are not applicable.

## Final verification
- All six planned stages are complete, each with a stage-scoped commit that includes its summary update.
- The first Step 4 commit (`0dbe5bbe`) contains only the plans and debug log; the summary appears afterward.
- Commits on this branch for this cycle: planning, six stages, plus the earlier Step 1 commit and the debug report: above the minimum of 7.
- Contract: default view is the status card with copyable fingerprint and three collapsed sibling expandos (Back up or restore, Keys, Storage and privacy); every prior capability is reachable within one expando click; clear/undo, restore, and the `#key-backup` deep link work.
- Not applicable: deployment, migration, and server checks (UI-only change).

## Follow-up - Fingerprint in site-wide form
- Changes:
  - `templates/pages/account_key.php`: the card now shows, and Copy yields, the fingerprint as `openpgp-<lowercase fingerprint>`, the same form as profile slugs and `/profiles/` URLs, instead of the bare hex value from Stage 2.
- Verification:
  - `php tests/run.php AccountKeyPageHooksTest LocalAppSmokeTest BrowserSigningNormalizationTest`: passed.
  - Chromium run: card text, clipboard, and the profile link slug were identical (`openpgp-502cfc...1f44`); empty state unchanged after clear.
- Notes:
  - Stage 2's earlier note about matching the profile link's fingerprint is superseded by this.
