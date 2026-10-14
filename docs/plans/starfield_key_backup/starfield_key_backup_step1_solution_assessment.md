> **Feature plan:** [Step 1](./starfield_key_backup_step1_solution_assessment.md) · [Step 2](./starfield_key_backup_step2_feature_description.md) · [Step 3](./starfield_key_backup_step3_development_plan.md) · [Step 4](./starfield_key_backup_step4_implementation_summary.md)

Debug mode: disabled

## Original Query
now that we have a private messages feature, it becomes more relevant for users to back up their keys
    consider how they can be backed up and how to encourage users to do it
        backup options:
            text file
            image file
            zip file
            what else?
        arguments for:
            ???

consider the starfield format we have described but not implemented?
write step 1 of railspike

## Understood Intent
Decide how users should back up their browser-held private keys now that private messages depend on them, with the unimplemented Starfield (SFENC, `sfenc.md`) PNG format considered as the image-file option, and how to motivate users to actually do it.

## Problem Statement
Private messages are unreadable if a browser-held key is lost, and users have no durable, low-friction way to back it up or reason to do so.

## Current Context
- Keys live only in the browser; the server never sees private keys. `/account/key/` already supports local raw-key import (see `browser_private_key_restore_step1_solution_assessment.md`), but there is no export or backup flow.
- `sfenc.md` specifies binary → starfield PNG (lossless, gzip, CRC, seeded xoshiro256\*\*). No encoder or decoder exists.
- The spec has defects that block implementing it as written: the brightness range (180 + 7 bits) overflows 255 and overlaps the size bits; decoding reads only `R`, which differs from brightness for the blue-white hue; the header's "64 bytes in 16 pixels" does not fit RGB-only pixels with fixed alpha.
- An SFENC image is not encrypted; anyone holding the PNG holds the key unless the key is passphrase-protected first.

## Arguments for Backing Up
- Losing browser storage (cleared data, new device, reinstall) permanently loses identity, history, and the ability to read private messages.
- Private messages raise the cost of loss from "re-register" to "irrecoverable conversations".
- Local backup preserves the no-server-custody model; the alternative is escrow.

## Option A: Plain text file (armored key) only
Offer a "Download backup" that saves the armored private key as a `.txt` file, restorable through the existing import path.

- **Pros:** Smallest slice; no new format; works with password managers and any file store.
- **Cons:** Unencrypted unless passphrase support is added; easy to ignore or leak; no emotional hook for users to keep it.

## Option B: Text file plus Starfield PNG, passphrase-protected
Offer both text and a Starfield PNG from one "Download backup" action, encrypting the key with a user passphrase first, and accept either file on restore. Revise `sfenc.md` to a conforming v0.2 and build a client-side encoder/decoder.

- **Pros:** Image is easy to store and recognize, and doubles as a memorable artifact; uses an already-designed format; one restore path for both files; passphrase makes a leaked image harmless.
- **Cons:** Requires spec fixes and a JS PRNG implementation (64-bit math); lossless-only means screenshots or chat apps that recompress will silently break the backup (needs a verify step); passphrase loss is a new failure mode.

## Option C: Multi-format bundle (text, PNG, zip, print/QR) with reminders
Offer every format, plus a zip of key and metadata, printable/QR sheet, and recurring reminders tied to private-message use.

- **Pros:** Covers most user preferences; reminders directly address low adoption.
- **Cons:** Large surface before any format is proven; more formats to specify, test, and keep restorable; risk of choice paralysis.

## Recommendation
Choose **Option B**, sequenced so the text file ships first within the same slice and the Starfield PNG follows once `sfenc.md` is corrected. Defer zip, QR/print, and recurring reminders to follow-ups; include one light prompt on the private-messages surface when no backup has been made.

The viable slice: on `/account/key/`, a user sets a passphrase, downloads a text backup and a Starfield PNG, and the page decodes the file back locally to confirm it restores the same fingerprint before saving. Nothing leaves the browser. Open decisions for Step 2: passphrase optional vs. required, and whether `sfenc.md` is revised in place or versioned as v0.2.
