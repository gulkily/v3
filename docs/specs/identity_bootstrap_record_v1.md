# Identity Bootstrap Record V1

This document defines the canonical identity bootstrap record required for the retained PHP rewrite scope.

## Scope

- One ASCII text file represents one bootstrapped identity.
- Canonical identity bootstrap files live in `records/identity/`.
- The record captures the first accepted key-linked identity for a signer.
- The public-key body stored here is canonical content, not a derived cache.

## File Naming

- The canonical filename is `records/identity/identity-openpgp-<lowercase-fingerprint>.txt`.
- The filename stem must exactly match the `Post-ID` header.
- The `<lowercase-fingerprint>` portion is the normalized signer fingerprint rendered in lowercase ASCII hex.

## File Structure

An identity bootstrap file has two parts:

1. A contiguous header block at the top of the file
2. A body separated from the headers by one blank line

Headers use this form:

```text
Key: Value
```

The body is the ASCII-armored public key text tied to the identity.

## Required Headers

- `Post-ID`: canonical record ID, using the form `identity-openpgp-<lowercase-fingerprint>`
- `Board-Tags`: must be `identity`
- `Subject`: must be `identity bootstrap`
- `Username`: the visible bootstrap username derived from the linked public key user ID
- `Identity-ID`: canonical identity ID, using the form `openpgp:<lowercase-fingerprint>`
- `Signer-Fingerprint`: normalized signer fingerprint in uppercase ASCII hex
- `Bootstrap-By-Post`: post ID that created or first linked this identity
- `Bootstrap-By-Thread`: root thread ID for the bootstrap flow

## Body Rules

- The body must contain one non-empty ASCII-armored OpenPGP public key block.
- The public key text must be ASCII only.
- The file uses LF line endings and ends with a trailing LF.
- Detached signatures, if present for related write flows, live beside the signed write record and are not embedded in the identity bootstrap record body.

## Invariants

- `Identity-ID` must be derived from `Signer-Fingerprint`.
- `Post-ID` must be derived from `Identity-ID`.
- `Username` should match the normalized visible username extracted from the stored public key user ID for newly written records.
- The fingerprint derived from the public key body must match `Signer-Fingerprint`.
- `Bootstrap-By-Thread` identifies the root thread for the bootstrap event.
- `Bootstrap-By-Post` must refer to a post that belongs to `Bootstrap-By-Thread`.
- The identity bootstrap record is append-only for V1. Post-bootstrap profile updates remain out of scope.

## Legacy Compatibility

- Older bootstrap records may omit `Username`.
- When a legacy record omits `Username`, V1 readers should fall back to `guest`.

## Relationship To Public-Key Storage

- The same armored key text must also be storable under `records/public-keys/`.
- The identity bootstrap record keeps the bootstrap event and bootstrap references.
- The public-key storage file keeps the reusable signer-key artifact addressed by fingerprint.

## Example

```text
Post-ID: identity-openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954
Board-Tags: identity
Subject: identity bootstrap
Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954
Signer-Fingerprint: 0168FF20EB09C3EA6193BD3C92A73AA7D20A0954
Bootstrap-By-Post: thread-20260321175707-asdf-7197c7be
Bootstrap-By-Thread: thread-20260321175707-asdf-7197c7be

-----BEGIN PGP PUBLIC KEY BLOCK-----
...
-----END PGP PUBLIC KEY BLOCK-----
```
