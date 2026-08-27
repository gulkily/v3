# Paste Private Key From Clipboard Step 3 Development Plan

## Stage 1
- Goal: Add the account-key restore affordance without changing behavior.
- Dependencies: Approved Step 2; existing `/account/key/` advanced controls and simple status mirror.
- Expected changes: Extend the account key template with a restore/private-key paste control and any small supporting copy needed for status feedback.
- Verification approach: Run `php -l templates/pages/account_key.php`; inspect rendered markup expectations through existing smoke coverage if touched.
- Risks or open questions:
  - Clipboard read permissions can fail in some browsers, so the UI must still allow user-driven paste fallback if implementation discovers it is needed.
- Canonical components/API contracts touched: Account key page advanced controls; account key simple status mirroring.

## Stage 2
- Goal: Add local private-key import validation and identity derivation helpers.
- Dependencies: Stage 1; existing OpenPGP loader/API contract.
- Expected changes: Add focused browser-signing helpers with planned contracts like `readClipboardText()`, `deriveIdentityFromPrivateKey(armoredPrivateKey)`, and `saveImportedBrowserIdentity(identity)`.
- Verification approach: Run `node --check public/assets/browser_signing.js`; add focused helper coverage in `BrowserSigningNormalizationTest` for valid import, invalid import, encrypted/unusable import, and no-overwrite failure behavior.
- Risks or open questions:
  - OpenPGP.js private-key-to-public-key API details must be confirmed during implementation against the bundled version.
  - Encrypted or passphrase-protected private keys should fail clearly unless existing signing flows already support decrypting them.
- Canonical components/API contracts touched: Browser signing OpenPGP loader contract; local storage fields for username, public key, private key, fingerprint, and published fingerprint.

## Stage 3
- Goal: Wire the restore action into account-key identity publishing.
- Dependencies: Stage 2.
- Expected changes: Bind the restore control in `bindAccountKeyPage()`, keep the trigger disabled during work, save imported identity only after validation succeeds, update saved-key viewers/public-key field, sync identity hint, and publish or re-link the derived public key through the existing link flow.
- Verification approach: Add focused `BrowserSigningNormalizationTest` account-page script coverage for successful restore, public-key-only network request body, status text, field updates, and button re-enable behavior.
- Risks or open questions:
  - Existing saved identity must remain intact when clipboard read, key parse, public-key derivation, or link publishing fails.
  - Status messages must not include private key material or raw pasted content.
- Canonical components/API contracts touched: `bindAccountKeyPage()` account actions; `publishPublicKeyWithRetry()`; `syncIdentityHint()`; saved-state rendering.

## Stage 4
- Goal: Complete regression verification for identity management and posting compatibility.
- Dependencies: Stage 3.
- Expected changes: Extend or adjust tests only where needed; no new server API or database changes expected.
- Verification approach: Run `node --check public/assets/browser_signing.js`; `php -l templates/pages/account_key.php`; `php tests/run.php BrowserSigningNormalizationTest`; run full `php tests/run.php` if the focused suite passes and the local environment allows.
- Risks or open questions:
  - Full-suite failures may be pre-existing or environment-specific; record any unrelated failures clearly in the Step 4 summary.
- Canonical components/API contracts touched: Browser identity signing readiness; account-key smoke coverage; no database schema or server API contract changes.
