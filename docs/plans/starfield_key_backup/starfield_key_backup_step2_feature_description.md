> **Feature plan:** [Step 1](./starfield_key_backup_step1_solution_assessment.md) · [Step 2](./starfield_key_backup_step2_feature_description.md) · [Step 3](./starfield_key_backup_step3_development_plan.md) · [Step 4](./starfield_key_backup_step4_implementation_summary.md)

Debug mode: disabled

## Problem
Private messages are unreadable if the browser-held private key is lost, and users today can only copy raw key text from `/account/key/`, with no durable backup file and no prompt to make one.

## User Stories
- As a user with a browser key, I want to download a passphrase-protected backup so that I can recover my identity and private messages if this browser's storage is lost.
- As a user, I want a Starfield PNG backup so that I have a compact image file that is easy to store and recognize.
- As a user on a new browser, I want to restore from a backup file so that I regain my identity without pasting key text.
- As a user who has not backed up, I want a gentle prompt where I read private messages so that I remember to do it.

## Core Requirements
- Backup creation and restore happen entirely in the browser; private key material and passphrases never reach the server.
- The key is encrypted with a user passphrase before it is written to any backup file, including the PNG.
- Each backup is decoded locally and checked against the account fingerprint before it is offered for download.
- A text backup and a Starfield PNG are both downloadable, and either restores through one restore path.
- The Starfield format is corrected in `sfenc.md` so independent implementations decode identically.

## Delivery Scope
- **Work type:** application change (plus the `sfenc.md` spec revision it depends on).

## Completion Boundary
- **Normal entry:** a signed-in user opens `/account/key/` (or follows the prompt from the private messages pages) with a key set up in the browser.
- **End-to-end outcome:** the user enters a passphrase, downloads text and PNG backups, and sees confirmation that each was verified; on a clean browser they restore from either file with the passphrase and the same fingerprint is linked.
- **Recovery:** wrong passphrase, corrupt or recompressed PNG, and fingerprint mismatch each produce a clear message and leave the existing key untouched; the existing raw copy/restore buttons remain available.
- **Release condition:** round-trip backup and restore works in the supported browsers with no server-side key handling, and `sfenc.md` matches the shipped encoder.

## Risks
- **Spec defects in `sfenc.md`** (brightness overflow, `R`-only decode, header size): wrong images or failed decodes. Earliest validation: a spec-only encode/decode test vector in Step 3. Mitigation: revise the spec and fix test vectors before building the UI.
- **Lossy handling of the PNG** (screenshots, chat apps, image optimizers): silent corruption. Mitigation: verify at creation, warn on restore failure, and always offer the text file.
- **Forgotten passphrase:** backup becomes useless. Mitigation: explain once at creation that it cannot be recovered; do not weaken protection.
- **Cross-browser 64-bit PRNG and PNG canvas behavior** (color management, alpha premultiplication): non-identical pixels. Earliest validation: byte-exact test vectors in at least two browsers.

## Shared Component Inventory
- `/account/key/` (`templates/pages/account_key.php`): existing key status, Copy/Restore Private Key, and viewer — **extend** with backup download and file restore, reusing the existing validation and fingerprint derivation.
- `browser_signing.js` and `openpgp.min.js`: **reuse** for key handling and passphrase encryption; encoder/decoder is a **new** client module since no shared binary-to-image component exists.
- Private messages inbox/conversation pages: **reuse** the existing page layout; add one prompt only (no new surface).
- `sfenc.md`: **extend** as the single source of truth for the image format.

## Simple User Flow
1. User opens `/account/key/` and chooses "Back up key."
2. User sets a passphrase and confirms it.
3. Browser encrypts the key, produces the text file and Starfield PNG, and decodes each back to verify.
4. User downloads one or both files; the page records that a backup was made.
5. Later, on a new browser, user chooses "Restore from backup," selects a file, and enters the passphrase.
6. Browser decrypts, confirms the fingerprint, and sets up the identity.

## Success Criteria
- A key backed up to PNG restores byte-identical to the original in a fresh browser profile.
- A PNG recompressed to JPEG is rejected with a clear message, never restored incorrectly.
- Network inspection during backup and restore shows no request carrying key or passphrase data.
- The same payload and seed yield bit-identical PNG bodies from the encoder and a second reference decoder built from `sfenc.md` alone.
- A user with no backup sees the prompt on private messages pages, and it disappears after a verified backup.
