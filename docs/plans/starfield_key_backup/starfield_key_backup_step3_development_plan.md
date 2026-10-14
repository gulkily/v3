> **Feature plan:** [Step 1](./starfield_key_backup_step1_solution_assessment.md) · [Step 2](./starfield_key_backup_step2_feature_description.md) · [Step 3](./starfield_key_backup_step3_development_plan.md) · [Step 4](./starfield_key_backup_step4_implementation_summary.md)

Debug mode: disabled

## Completion Contract
- Entry/outcome: `/account/key/`, directly or from private messages, offers required-passphrase text and PNG backups; each downloaded file is decoded, decrypted, and fingerprint-verified locally. Either restores the original key bytes and links the same identity in a clean browser.
- Recovery: reject wrong passwords, corrupt/unsupported/oversized files and fingerprint mismatches before mutation; restore prior local identity on storage/publication failure. Retain raw copy/restore. A clean browser compares the derived fingerprint with encrypted backup metadata; an existing identity must also match.
- Release: verify normal UI and existing-message decryption in Chromium and Firefox, including both bundled OpenPGP loader versions; inspect requests for secret leakage. Ship through existing asset fingerprinting; no database migration or production writes. Missing browser evidence blocks release. Seven stages, approximately one hour each; replan before exceeding one day.

## Key Risks
- **High risk:** v0.1 loses bytes through overflow, header packing, star overlap and inconsistent PRNG consumption. Planning vector below checks the proposed byte mapping; Stage 1 must freeze complete independent vectors before codec work, including deterministic compression/PNG serialization and header-safe placement.
- **High risk:** encryption/reserialization or failed import could change/lose keys. Encrypt an envelope containing exact original UTF-8 key text, version and fingerprint using bundled OpenPGP password encryption; verify byte equality and complete rollback before UI integration.
- **High risk:** forgotten passwords and altered images make recovery impossible. Require confirmation, explain password loss and keeping the original PNG, offer text alongside it; test errors and bounded decoding before release. Download initiation cannot prove durable storage.

## Stage 1
- Goal: freeze corrected SFENC v0.2 and conformance fixtures.
- Dependencies: approved Step 3.
- Expected changes: revise `sfenc.md`; specify 22 RGB header pixels for 64 bytes, zero padding, reversible colors, nonoverlapping reserved star cells, exact PRNG arithmetic/draw order, capacity, resource limits, canonical gzip/PNG bytes and rejection rules.
- Verification approach: independent reference codec and fixed seed/payload fixtures, all 256 byte values, empty/full capacity, header exclusion and overflow rejection. Proposed spec-only mapping: brightness `192 + (b & 63)`, hue `b >> 6`, existing hue ratios; bytes `0,63,64,127,128,191,192,255` map to RGB `192/192/192,255/255/255,163/172/192,216/229/255,192/192/134,255/255/178,192/134/115,255/178/153`; hue plus maximum channel recovers each byte.
- Risks or open questions: floating-point/background and serializer differences break exact bodies; pin deterministic rules and full vectors before dependents. The mapping vector alone does not establish format conformance.
- Canonical components/API contracts touched: `sfenc.md`; test-only independent reference and fixtures.

## Stage 2
- Goal: verified encrypted text backup core.
- Dependencies: Stage 1.
- Expected changes: new browser backup module; `createBackup(privateKeyText, passphrase)` and `readBackup(text, passphrase)` return verified envelope data without storage/network effects.
- Verification approach: real bundled OpenPGP v5/v6 round trips, exact UTF-8 bytes, fingerprint mismatch, tampering and wrong password.
- Risks or open questions: loader incompatibility or secret exposure; test both APIs, never log/persist passwords, and release transient references.
- Canonical components/API contracts touched: OpenPGP loader, new `public/assets/key_backup.js`, identity derivation.

## Stage 3
- Goal: usable text download and file restore on Account Key.
- Dependencies: Stage 2.
- Expected changes: passphrase/confirmation and file controls, verified Blob downloads, shared restore transaction, public-key linking and asset wiring.
- Verification approach: clean-browser text restore through UI; storage/publication failure rollback; existing raw restore regression.
- Risks or open questions: stale identity during async operations; recheck fingerprint before download/import, serialize actions, snapshot every modified storage entry including cleared-key recovery state.
- Canonical components/API contracts touched: `account_key.php`, `browser_signing.js`, `ComposeAndAccountKeyController`, existing asset resolver.

## Stage 4
- Goal: browser SFENC codec matching independent fixtures.
- Dependencies: Stages 1–2.
- Expected changes: `encodeStarfield(bytes, seed)` / `decodeStarfield(pngBytes)` in new `public/assets/starfield.js`; encode the same encrypted text payload.
- Verification approach: reference cross-decode, exact canonical PNG body comparison, collision/capacity boundaries and malformed dimensions/lengths/CRC/compression.
- Risks or open questions: canvas color changes or decompression exhaustion; bound file, pixel and inflated sizes, verify CRC before decompression, and compare actual saved PNG bytes across browsers.
- Canonical components/API contracts touched: SFENC v0.2, browser backup module.

## Stage 5
- Goal: PNG download and restore through the same Account Key flow.
- Dependencies: Stages 3–4.
- Expected changes: PNG choice, shared file detection/restore, verified-download status and original-file guidance; retain text access on PNG failure.
- Verification approach: download actual PNG, restore in fresh profile, reject JPEG/recompressed corruption and preserve existing identity.
- Risks or open questions: premature success; decode/decrypt final Blob and compare original bytes/fingerprint before offering download.
- Canonical components/API contracts touched: Account Key UI, backup module, SFENC codec and asset loading.

## Stage 6
- Goal: gentle private-message backup reminder.
- Dependencies: Stage 5.
- Expected changes: shared prompt linking Account Key; browser-local marker scoped to fingerprint, written only after verified download initiation.
- Verification approach: inbox/conversation prompt, reload, changed key, failed/cancelled preparation and unavailable storage.
- Risks or open questions: false reassurance; say download started, ask users to retain the file, never mark preparation alone as backed up.
- Canonical components/API contracts touched: private-message templates/scripts and shared browser identity storage.

## Stage 7
- Goal: establish release evidence and recovery guidance.
- Dependencies: Stages 1–6.
- Expected changes: focused browser fixtures and usage documentation; verify deployed asset resolution locally.
- Verification approach: Chromium/Firefox text/PNG cross-restores, original-message decryption, public/member-only entry, request inspection, raw restore regression and affected PHP/browser checks.
- Risks or open questions: unavailable engines or failed checks leave interoperability unproven; record blockers and withhold completion until resolved.
- Canonical components/API contracts touched: browser tests, `BrowserSigningNormalizationTest.php`, asset delivery, Step 4 summary.
