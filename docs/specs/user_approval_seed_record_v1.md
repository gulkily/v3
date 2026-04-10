# User Approval Seed Record V1

This document defines the canonical seed record used to bootstrap the first approved users in the retained PHP forum rewrite.

## Scope

- One ASCII text file represents one explicitly seeded approved identity.
- Canonical approval seed files live in `records/approval-seeds/`.
- These records exist only to bootstrap initial trust without depending on git history.
- Later user approvals are recorded as canonical reply posts on bootstrap threads.

## File Naming

- The canonical filename is `records/approval-seeds/openpgp-<lowercase-fingerprint>.txt`.
- The `<lowercase-fingerprint>` portion must match the identity fingerprint encoded in `Approved-Identity-ID`.

## File Structure

An approval seed file has two parts:

1. A contiguous header block at the top of the file
2. A body separated from the headers by one blank line

Headers use this form:

```text
Key: Value
```

## Required Headers

- `Approved-Identity-ID`: canonical identity ID, using the form `openpgp:<lowercase-fingerprint>`
- `Seed-Reason`: short operator-readable reason for the seed

## Body Rules

- The body is plain ASCII text.
- The body may be a short explanation of why the identity was seeded.
- The file uses LF line endings and ends with a trailing LF.

## Invariants

- `Approved-Identity-ID` must use the retained identity form `openpgp:<lowercase-fingerprint>`.
- The filename fingerprint must match the fingerprint encoded in `Approved-Identity-ID`.
- Seed records are additive in V1.
- Seed records do not replace approval replies; they only bootstrap initial trust roots.

## Example

```text
Approved-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954
Seed-Reason: initial approved fixture user

Seed this identity as approved so later user-to-user approval chains can begin from canonical data.
```
