# Paste Private Key From Clipboard Step 2 Feature Description

## Problem

Users who have copied a browser posting private key need to restore that identity on `/account/key/` without exposing private key material to the server. The restore path should validate the key locally, recreate the matching browser-held identity state, and preserve the existing public-key publishing model.

## User Stories

- As a returning user, I want to paste my private key from the clipboard so that I can restore posting from a browser that lost its saved identity.
- As a user handling key material, I want validation before the browser saves anything so that a malformed or unrelated key does not replace my working identity.
- As a privacy-conscious user, I want private key import to stay local to my browser so that the server only receives the public key.
- As a user with an imported identity, I want the restored public key to link or re-link normally so that posting works like a freshly generated browser identity.

## Core Requirements

- Add a private-key restore action to the existing account key experience, scoped to local browser storage.
- Accept armored OpenPGP private key material from the clipboard and reject empty, malformed, encrypted/passphrase-blocked, or fingerprint-unreadable values with clear feedback.
- Derive the public key and fingerprint from the imported private key locally, then save the same browser identity fields used by generated keypairs.
- Publish or re-link only the derived public key through the existing identity link flow; never send the private key to an API, URL, log, or hidden form field.
- Preserve existing generate, copy, clear, undo-clear, saved-key display, identity hint, and posting behavior.

## Shared Component Inventory

- Account key page (`/account/key/`): extend as the canonical browser identity management surface rather than adding a separate import page.
- Account key advanced controls: extend with the restore action because private key paste/import is a technical identity-management workflow.
- Existing browser signing local storage fields: reuse for imported identity state so compose/signing paths do not need a separate identity source.
- Existing OpenPGP browser API loading: reuse for parsing, validating, deriving the public key, and reading the fingerprint locally.
- Existing public-key link API: reuse for publishing the derived public key; no new server API should receive private key material.
- Existing status and simple-account UI mirroring: extend messages so imported identities show the same ready/failed state as generated identities.

## Simple User Flow

1. User opens `/account/key/`.
2. User chooses the restore/import private key action from the account key controls.
3. Browser reads or accepts the clipboard private key locally.
4. Browser validates the private key and derives its public key and fingerprint.
5. Browser saves the restored identity in local storage and updates the account key display.
6. Browser publishes or re-links the derived public key through the existing identity link flow.
7. User can post with the restored identity, or sees clear local validation feedback if restore fails.

## Success Criteria

- A valid armored private key can restore a browser identity without generating a new keypair.
- Invalid, empty, encrypted, or unreadable private key input does not overwrite an existing saved keypair.
- Browser storage after import contains username, public key, private key, fingerprint, and published-fingerprint state consistent with generated identities.
- Network requests during import contain the public key only, with no private key material in request URL, body, or form fields.
- The account key page shows the restored identity as ready and existing signed posting flows work with it.
