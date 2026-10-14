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

## Stage 6 - Private-message reminder
- Changes: shared reminder partial on private-message surfaces links directly to Account Key backup controls. A fingerprint-scoped local marker records only verified download initiation; storage failure keeps a useful prompt and reports that its marker could not be saved.
- Verification: Chromium text/PNG UI runs passed normal Messages-to-Account-Key navigation, initial prompt, failed confirmation without a marker, successful-download suppression on list/conversation, changed-key prompt and storage-unavailable behavior; PHP lint and diff checks passed.
- Notes: wording promises only download initiation, not durable storage. Restoring or merely preparing a backup does not suppress the reminder.

## Stage 7 - Release verification and recovery documentation
- Changes: added user recovery/format guidance and README entry; expanded cross-browser/bundle tests, canonical members-only identity fixtures, original-message decryption, fingerprint mismatch, publication failure and concurrent-tab recovery. Bounded streamed OpenPGP plaintext decoding rejects compressed oversized envelopes; Account Key ignores a cancelled best-effort identity-hint request during navigation. Restore rollback preserves a newer identity installed by another tab.
- Verification: `FORMAT=png RESTORE_BUNDLE=v5 node tests/browser/key_backup_ui.mjs` passed Chromium/v6 export to Firefox/v5 restore on a public site; `BROWSER=firefox BUNDLE=v5 MEMBERS_ONLY=true node tests/browser/key_backup_ui.mjs` passed Firefox/v5 text export to Chromium/v6 restore on a members-only site. Both exercised normal Messages navigation, reminder state, downloaded bytes, wrong password, storage/publication failures, concurrent identity preservation, exact key recovery, mismatched identity rejection, retained-message decryption and no secret-bearing requests. PNG additionally rejected an actual JPEG conversion and corruption.
- Verification: both `key_backup.cjs` bundle runs passed, including a compressed oversized plaintext envelope; `starfield.cjs` passed independent vectors/max-size round trips plus checksum-repaired invalid dimensions/header/version/length/compression/padding tests. The earlier Chromium/Firefox codec Blob conformance runs remain valid (codec unchanged).
- Verification: 92 affected PHP/JS regression cases passed across BrowserSigningNormalizationTest, PrivateMessagePageControllerTest, PrivateMessageListTest, PrivateMessageReaderTest and PrivateMessageComposerTest. After final changes, 14 focused raw restore/authentication/page-controller cases passed, followed by both raw restore cases after the concurrent-tab guard. Affected PHP lint, JavaScript syntax, `git diff --check`, feature-document links, and all four new fingerprinted asset-to-source resolutions passed.
- Notes: initial members-only fixture lacked a canonical identity, causing synthetic approval to be replaced on publication; fixed the test fixture and reran successfully. Fingerprinted assets are served by the existing dynamic source resolver; physical hashed copies are not required in this checkout. No database migration, production writes, deployment or external service changes were needed or performed. Maximum key text is 32 KiB; original PNGs only, as documented.

## Completion
- All seven stages are complete on `feature/starfield-key-backup`, with the approved planning commit followed by one summary-bearing commit per stage.
- Normal entry, both download/restore formats, required failure recovery, retained-message access, and local asset delivery are verified. No release verification blockers remain for the tested Chromium/Firefox and bundled OpenPGP v5/v6 combinations.
- Ready for review; deployment remains the normal repository release process.

## Follow-up - Restore state and lossless PNG compatibility
- Report: backup restore left Account Key state stale until manual reload; a PNG uploaded to and downloaded from Imgur was rejected.
- Findings: file restore did not notify same-tab account/toolbar listeners or resume a public-site session. The supplied original and Imgur images have identical 256×256 RGBA pixels (zero changed channel values); Imgur removed opaque alpha and recompressed RGB data. Strict container equality incorrectly rejected intact backup data.
- Changes: emit a same-tab identity-change event, refresh simple status/toolbar/reminder state, set the existing restored-key status, and authenticate file restores on public as well as members-only sites. Decode bounded standard PNG DEFLATE, all five row filters, split IDAT, ancillary metadata and opaque RGB/RGBA. Compare exact normalized RGBA pixels against the canonical rendered starfield, retaining payload CRC/encryption checks. Canonical encoder bytes and version 2 remain unchanged. Updated independent reference, specification and recovery guidance; this supersedes the earlier original-container-only decoder restriction.
- Verification: both supplied samples recover identical 1,595-byte encrypted payloads in Chromium, Firefox, Node and the independent Python decoder; no passphrase was requested or used. `node tests/browser/starfield.cjs` and `node tests/browser/starfield_browsers.mjs` passed existing encoder vectors, RGB/RGBA lossless recompression with every filter, split IDAT/metadata, independent decoding, pixel-corruption rejection and inflated-size limits. `python3 tests/Support/starfield_reference.py --test` passed.
- Verification: `FORMAT=png REENCODE=1 RESTORE_BUNDLE=v5 node tests/browser/key_backup_ui.mjs` and `BROWSER=firefox BUNDLE=v5 MEMBERS_ONLY=true node tests/browser/key_backup_ui.mjs` passed cross-browser/bundle recovery, session access without manual reload, same-page badge/status/control refresh without navigation, rollback, mismatches, JPEG rejection, retained-message decryption and network privacy. `php tests/run.php BrowserSigningNormalizationTest PrivateSiteAuthTest` passed all 89 cases; PHP lint, JS syntax and diff checks passed.
- Notes: supplied files in `starfield_samples/` remain untouched and uncommitted. Pixel-changing transformations (including JPEG and resizing) remain unsupported; acceptance of these exact samples is not a guarantee about every image-host transformation. No deployment performed.

## Follow-up - Collapsible backup interface
- Changes: moved backup and restore controls into a native, initially collapsed “Back up or restore your key” expando, reusing Account Key disclosure styling. The Messages reminder's `#key-backup` link opens it automatically, including same-page hash navigation.
- Verification: PHP lint, JS syntax and diff checks passed. The existing Chromium-to-Firefox UI test passed with collapsed initial state, reminder-link expansion, manual expansion and the full backup/restore flow.
