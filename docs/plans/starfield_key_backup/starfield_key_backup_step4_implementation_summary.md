> **Feature plan:** [Step 1](./starfield_key_backup_step1_solution_assessment.md) · [Step 2](./starfield_key_backup_step2_feature_description.md) · [Step 3](./starfield_key_backup_step3_development_plan.md) · [Step 4](./starfield_key_backup_step4_implementation_summary.md)

Debug mode: disabled

## Stage 1 - SFENC v0.2 conformance
- Changes: corrected header, reversible byte colors, isolated cells, exact integer PRNG/background, bounded canonical gzip and PNG; independent Python reference and fixed SHA256 fixtures.
- Verification: `python3 tests/Support/starfield_reference.py --test` passed empty/all-byte/text/capacity-boundary round trips, all 256 colors, collision-free placement through maximum capacity, overflow and truncation rejection; `git diff --check` passed.
- Notes: canonical stored DEFLATE intentionally trades compression for deterministic cross-browser bytes; re-encoded PNGs are rejected. No production or database changes.

## Stage 2 - Encrypted backup core
- Changes: browser-only password-encrypted OpenPGP envelope preserves exact original UTF-8 key text and validates its fingerprint; creation decrypts its own result before returning.
- Verification: `node tests/browser/key_backup.cjs` and the same command with `openpgp.v5.11.3.min.js` passed real-key exact-byte restoration, wrong/empty password, corrupt/oversized file and fingerprint mismatch checks.
- Notes: uses the existing loader and both bundled versions; no new cryptography implementation or secret storage/network effects.

## Stage 3 - Account Key text backup and restore
- Changes: added passphrase/download/file controls, final-Blob verification, serialized UI actions and identity-change checks; extracted the shared raw/file import transaction with exact-text preservation and cleared-key undo rollback. Account Key assets use the existing fingerprint resolver.
- Verification: `node tests/browser/key_backup_ui.mjs` passed actual Chromium download, fresh-profile restore, wrong password, simulated storage failure rollback, exact bytes and request privacy; two existing raw import/publication rollback tests passed with `php tests/run.php BrowserSigningNormalizationTest::testAccountRestorePrivateKeyPublishesDerivedPublicKeyOnly BrowserSigningNormalizationTest::testAccountRestorePrivateKeyRollsBackWhenPublishFails`; affected PHP lint and JS syntax checks passed.
- Notes: test waits for existing automatic authentication/reload before filling password controls; no production state used. Browser test fixtures live in isolated temporary directories.

## Stage 4 - Browser SFENC codec
- Changes: canvas-free bounded PNG/gzip codec with deterministic background, shuffled nonoverlapping cells and whole-file canonical validation.
- Verification: `node tests/browser/starfield.cjs` passed exact SHA256 matches to independent Python vectors, Python cross-decoding of JS output, empty/all-byte/capacity/max-128-KiB round trips, corruption/truncation/oversize rejection. `node tests/browser/starfield_browsers.mjs` passed exact PNG Blob hashes and restores in Chromium and Firefox.
- Notes: original PNG is required; lossless re-encoding is deliberately rejected as noncanonical. Container checks and bounded stored-block parsing avoid canvas color transforms and decompression bombs.

## Stage 5 - PNG download and unified restore
- Changes: added Starfield download, actual-Blob decode/decrypt verification, content-based file detection and original-image guidance; text stays independently available.
- Verification: `FORMAT=png node tests/browser/key_backup_ui.mjs` and the default text run passed real downloads and fresh-profile exact-key restoration. PNG run also converted the actual PNG to JPEG and rejected it, rejected a corrupted PNG, and checked unchanged storage; PHP lint/JS syntax passed.
- Notes: no canvas is used in production; the browser test uses canvas solely to simulate destructive JPEG conversion.
