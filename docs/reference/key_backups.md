# Browser key backups

Expand **Account Key → Back up or restore your key**, or follow **Back up your key** from Messages to open it automatically. Enter and confirm a strong, unique passphrase, then download a text backup or Starfield PNG. Each file is encrypted and decoded/decrypted locally to verify the original key and fingerprint before download starts. Keep the file and passphrase somewhere safe; the site cannot recover either one. You can download both formats, entering your passphrase again for each download.

The PNG is an image containing the encrypted backup. Keep the **original downloaded file**. Lossless PNG recompression that preserves the exact pixels is supported, including conversion from opaque RGBA to RGB. Screenshots, resizing, and conversion to JPEG are not supported. Keep the original even when using an image host: a visually similar image can still contain altered data. The text file provides an alternative.

To recover, open **Account Key** in the new browser, choose the original file under **Restore from backup**, enter its passphrase, and select **Restore backup**. Your public key is linked through the existing identity flow. The account status updates immediately, and a new session is authenticated automatically; no manual reload is needed. Your private key and passphrase stay in the browser. Restoring the key lets you decrypt retained messages encrypted to that key; it does not back up messages or recover deleted server history.

Wrong passphrases, damaged files, or fingerprint mismatches leave the existing browser identity in place. A backup for another identity must be restored in a separate browser profile. If linking fails, retry after connectivity returns. The existing advanced raw Copy/Restore Private Key controls remain available, but raw copied keys are not passphrase-protected backups.

The Messages reminder is local to this browser and key. It clears when a verified download starts, which cannot confirm that the file was saved permanently. It can return after clearing browser data, changing keys, or when browser storage is unavailable. Restoring a key does not mark a new backup as downloaded.

## Format and validation

- Text is a password-encrypted OpenPGP message containing a versioned envelope with the exact UTF-8 armored key text and fingerprint. Both bundled OpenPGP versions are supported.
- PNG follows [SFENC v0.2](../../sfenc.md). It wraps that same encrypted text. SFENC itself provides encoding and corruption detection, not encryption.
- Browser keys up to 32 KiB are supported. SFENC payloads are limited to 128 KiB and PNG inputs to 20 MiB. Payload decoding uses bounded stored DEFLATE; PNG decoding accepts bounded standard DEFLATE and all five PNG row filters without canvas color conversion.
- No database migration or server-side private-key handling is required. New scripts use the existing asset fingerprint resolver. Deployment uses the normal application release process; this feature does not need a production canary that writes identities.

Focused local checks (browser fixtures use disposable temporary repositories):

```sh
python3 tests/Support/starfield_reference.py --test
node tests/browser/starfield.cjs
node tests/browser/starfield_browsers.mjs
node tests/browser/key_backup.cjs
node tests/browser/key_backup.cjs openpgp.v5.11.3.min.js
FORMAT=png REENCODE=1 RESTORE_BUNDLE=v5 node tests/browser/key_backup_ui.mjs
BROWSER=firefox BUNDLE=v5 MEMBERS_ONLY=true node tests/browser/key_backup_ui.mjs
```

The browser tests require the repository's `playwright-core` dependency, Chromium (default `/opt/google/chrome/chrome`, override `CHROMIUM_PATH`), and Playwright Firefox. They exercise actual download files, cross-browser/bundle restore, original-message decryption, reminder state, rejection and rollback, and network-secret checks.
