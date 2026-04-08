# Public Key Storage V1

This document defines canonical repository storage for reusable signer public keys.

## Scope

- Canonical public keys live in `records/public-keys/`.
- One file stores one ASCII-armored OpenPGP public key.
- Public-key files are canonical repository artifacts, not transient cache entries.
- These files support signature verification and identity/profile reads without requiring the original write request.

## File Naming

- The canonical filename is `records/public-keys/openpgp-<UPPERCASE-FINGERPRINT>.asc`.
- `<UPPERCASE-FINGERPRINT>` is the normalized key fingerprint rendered in uppercase ASCII hex.
- V1 reads must remain compatible with legacy lowercase filenames from earlier repository state.
- V1 writes must create or normalize to the uppercase canonical filename.

## File Contents

- The file body is exactly one ASCII-armored OpenPGP public key block.
- The file must be ASCII only.
- The file must use LF line endings and end with a trailing LF.
- No extra headers, metadata wrappers, or detached signatures are embedded in the file.

## Invariants

- The fingerprint derived from the armored key text must match the filename fingerprint.
- There is at most one canonical stored key per normalized fingerprint.
- Storing a key for an already-known fingerprint reuses the existing canonical path instead of creating duplicates.
- If a legacy lowercase filename exists and the uppercase canonical path does not, the stored key may be normalized by renaming the legacy file to the canonical uppercase path.

## Relationship To Identity Records

- Identity bootstrap records under `records/identity/` may embed the same armored key text in their body.
- Public-key storage exists so later signed records can resolve signer keys by fingerprint without reparsing unrelated records.
- Profile and account reads may surface key material from this family directly.

## Example Path

```text
records/public-keys/openpgp-0168FF20EB09C3EA6193BD3C92A73AA7D20A0954.asc
```

## Example Body

```text
-----BEGIN PGP PUBLIC KEY BLOCK-----
...
-----END PGP PUBLIC KEY BLOCK-----
```
