> **Feature plan:** [Step 1](./account_key_page_simplification_step1_solution_assessment.md) · [Step 2](./account_key_page_simplification_step2_feature_description.md) · [Step 3](./account_key_page_simplification_step3_development_plan.md) · [Step 4](./account_key_page_simplification_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract
- **Entry:** `/account/key/` opened directly, from nav, or via a backup-reminder or resume link (`#key-backup`).
- **Outcome:** the default page is the status card (state, username, copyable fingerprint, primary action, clear identity, profile link) plus three collapsed sibling expandos: Back up or restore, Keys, Storage and privacy. Every prior capability is reachable within one expando click.
- **Recovery:** clear-with-undo, restore from backup, and status/error messages unchanged; the fingerprint shows an empty state with no key.
- **Deployment/external:** none; no server, storage, or signing changes.
- **Release:** full PHP suite and `tests/browser/key_backup_ui.mjs` (text and PNG) pass; theme check at desktop and 375px.

## Key Risks
- **High risk:** a removed or hidden control breaks `browser_signing.js` or tests. Early validation: Stage 1 updates the hook contract first. Mitigation: hide rather than delete duplicate controls and keep their hooks; the viewer copy buttons already click them.
- **High risk:** fingerprint on the card is stale or wrong after setup, restore, or clear. Early validation: Stage 2 browser check across those actions. Mitigation: refresh from the existing identity-change events and `storage`/`pageshow` hooks; empty state when absent.
- `#key-backup` deep link fails to open the expando. Early validation: Stage 3 reminder-link run in the browser test. Mitigation: use a native `<details>` with the anchor ID and open it from the hash in the page script.
- Technical users miss keys or storage. Early validation: Stage 5 label review. Mitigation: parallel plain labels at one level.

## Stage 1
- Goal: update the hook contract to the target structure before moving markup.
- Dependencies: none.
- Expected changes: extend `AccountKeyPageHooksTest` with the new hooks (fingerprint value and its copy action, the three expando IDs) and mark which existing hooks must remain; no page change, so new assertions are added in Stage 2 alongside the markup.
- Verification approach: existing tests pass; the list of hooks to keep is derived by grep of `browser_signing.js`, `key_backup_ui.js`, and `key_backup_ui.mjs`.
- Risks or open questions:
  - Impact: a needed hook is omitted.
  - Early warning / validation: grep against the three files.
  - Mitigation: keep all current hooks unless Stage 4 explicitly retires one.
- Canonical components/API contracts touched: `tests/AccountKeyPageHooksTest.php`.

## Stage 2
- Goal: fingerprint on the status card, visible and copyable without any click.
- Dependencies: Stage 1.
- Expected changes: add a labeled fingerprint field with a copy action to the card; fill it in the page's inline script from the stored fingerprint and refresh it on the existing identity events; empty state when no key; add its hooks to the contract test.
- Verification approach: PHP tests; browser check of set up, restore, and clear updating the value; copy yields the full value.
- Risks or open questions:
  - Impact: value differs in case or format from the profile link.
  - Early warning / validation: compare with the profile URL's fingerprint.
  - Mitigation: reuse the existing stored-fingerprint read.
- Canonical components/API contracts touched: `account_key.php` (markup and inline script), `identity.css`, `forum_pki_fingerprint` (read only).

## Stage 3
- Goal: backup and restore becomes a collapsed sibling expando that still opens from `#key-backup`.
- Dependencies: Stage 2.
- Expected changes: convert the visible backup section to a native collapsed `<details id="key-backup" data-key-backup>`; keep tabs, panels, and warnings; trim copy; update the browser test only where it assumed it was already open.
- Verification approach: both browser test formats; reminder link opens the expando.
- Risks or open questions:
  - Impact: hash opening races page load.
  - Early warning / validation: reload with `#key-backup`.
  - Mitigation: the backup script already opens the area from the hash; verify and extend it only if needed.
- Canonical components/API contracts touched: `account_key.php`, `key_backup_ui.js` (verify), `key_backup_ui.mjs`, `key_backup_reminder.php` (verify).

## Stage 4
- Goal: Keys expando with consolidated controls.
- Dependencies: Stage 3.
- Expected changes: one "Keys" expando holding the public key viewer with copy, the private key behind its existing nested reveal and warning, the load/restore actions, and the link-a-key form; hide the separate Copy Public Key and Copy Private Key buttons because the viewer copy buttons trigger them; remove the intro lines that restate the obvious.
- Verification approach: PHP tests; manual copy/load/restore/link; private viewer still updates after set up.
- Risks or open questions:
  - Impact: hidden buttons stop working or become reachable by keyboard.
  - Early warning / validation: viewer copy test and tab-order check.
  - Mitigation: use the `hidden` attribute, which removes them from focus order but leaves them clickable by script.
- Canonical components/API contracts touched: `account_key.php`, `identity.css`, `browser_signing.js` (read only).

## Stage 5
- Goal: Storage and privacy expando; remove the old drawer and groups.
- Dependencies: Stage 4.
- Expected changes: move browser key status with undo, guest publication setting, identity hint, and saved identity into one "Storage and privacy" expando with the short where-things-live note; move clear-saved-keypair here; delete the replaced group/section styles and the old drawer wrapper; update the label assertion in `LocalAppSmokeTest`.
- Verification approach: PHP tests; clear then undo; guest publication toggle persists; status text still mirrors to the card.
- Risks or open questions:
  - Impact: status mirroring depends on the status node being in the DOM.
  - Early warning / validation: set up and clear with the expando collapsed.
  - Mitigation: collapsed `<details>` content stays in the DOM.
- Canonical components/API contracts touched: `account_key.php`, `identity.css`, `LocalAppSmokeTest.php`.

## Stage 6
- Goal: polish and regression pass.
- Dependencies: Stages 1–5.
- Expected changes: spacing, contrast, and focus fixes found in review; remove dead styles.
- Verification approach: full suite; both browser backup formats; theme matrix at desktop and 375px with all expandos open; keyboard walkthrough; record in the Step 4 summary.
- Risks or open questions:
  - Impact: late fixes touch shared styles.
  - Early warning / validation: diff `identity.css` for non-`.account-key` selectors.
  - Mitigation: keep changes page-scoped.
- Canonical components/API contracts touched: `identity.css`, `account_key.php`.
