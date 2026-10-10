# Feature Flag Change Record V1

This document defines the immutable, signed audit record for one site-level
feature-flag change.

## Scope

- One ASCII text file represents one successful feature-flag mutation.
- Files live in `records/feature-flag-changes/`.
- The record is signed with an adjacent ASCII-armored detached OpenPGP
  signature and committed with the updated `records/instance/feature-flags.txt`
  snapshot.

## Required Headers

- `Record-ID`: `feature-flag-change-` followed by an ASCII record token
- `Created-At`: RFC 3339 UTC timestamp
- `Flag-Key`: registered uppercase feature-flag key
- `Value`: exactly `true` or `false`
- `Operator-Identity-ID`: lowercase canonical OpenPGP identity ID

The record has no body. It must use LF line endings and end in one trailing LF.

## Canonical Path Rule

- Record: `records/feature-flag-changes/<record-id>.txt`
- Signature: `records/feature-flag-changes/<record-id>.txt.asc`
- The filename stem must exactly match `Record-ID`.

## Example

```text
Record-ID: feature-flag-change-20261009120000-ab12cd34
Created-At: 2026-10-09T12:00:00Z
Flag-Key: FORUM_APP_VERSION_NOTIFICATION
Value: false
Operator-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954

```
