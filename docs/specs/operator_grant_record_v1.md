# Operator Grant Record V1

This document defines the immutable, signed record of one operator making
another approved key an operator.

## Scope

- One ASCII text file represents one successful grant.
- Files live in `records/operator-grants/`.
- The record is signed by the granting operator with an adjacent
  ASCII-armored detached OpenPGP signature. It is committed together with the
  target's approval seed, which carries `Operator-Grant-ID` naming this record.
- The record is evidence. Operator authority comes from the approval seed
  alone; a reader that ignores grant records still decides operators
  correctly.
- Instance import excludes these records, like approval seeds.

## Required Headers

- `Record-ID`: `operator-grant-` followed by an ASCII record token
- `Created-At`: RFC 3339 UTC timestamp
- `Operator-Identity-ID`: lowercase canonical OpenPGP identity ID of the granting operator
- `Target-Identity-ID`: lowercase canonical OpenPGP identity ID of the key made an operator; it differs from `Operator-Identity-ID`
- `Target-Username`: the target's username as the confirmation showed it

The record has no body. It must use LF line endings and end in one trailing LF.

## Canonical Path Rule

- Record: `records/operator-grants/<record-id>.txt`
- Signature: `records/operator-grants/<record-id>.txt.asc`
- The filename stem must exactly match `Record-ID`.

## Example

```text
Record-ID: operator-grant-20261109060000-ab12cd34
Created-At: 2026-11-09T06:00:00Z
Operator-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954
Target-Identity-ID: openpgp:1fc2edb074108568ad28fec8748e323e033d32b4
Target-Username: ilyag

```
