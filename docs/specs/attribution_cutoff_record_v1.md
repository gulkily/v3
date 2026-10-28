# Attribution Cutoff Record V1

This document defines the immutable, signed record an operator writes to move
the verified attribution cutoff.

## Scope

- Under the `FORUM_VERIFIED_ATTRIBUTION` site feature flag, unsigned posts and
  votes created before the cutoff keep counting; those created after it are
  unverified.
- Without any cutoff record, the cutoff is the `Created-At` of the earliest
  operator-signed [feature flag change](feature_flag_change_record_v1.md) that
  sets `FORUM_VERIFIED_ATTRIBUTION` to `true`. Turning the flag off and on
  again keeps it.
- An operator moves the cutoff with one ASCII text file in
  `records/attribution-cutoffs/`, signed with an adjacent ASCII-armored
  detached OpenPGP signature. The latest valid record by `Created-At` (then
  path) wins.
- A record counts only when its signature verifies against the named
  operator's key and that identity has an approval seed. A cutoff in the
  future is refused when written.
- With no switch-on and no cutoff record, there is no cutoff and every
  unsigned item counts.

## Required Headers

- `Record-ID`: `attribution-cutoff-` followed by an ASCII record token
- `Created-At`: RFC 3339 UTC timestamp
- `Cutoff-At`: RFC 3339 UTC timestamp
- `Operator-Identity-ID`: lowercase canonical OpenPGP identity ID

The record has no body. It must use LF line endings and end in one trailing LF.

## Canonical Path Rule

- Record: `records/attribution-cutoffs/<record-id>.txt`
- Signature: `records/attribution-cutoffs/<record-id>.txt.asc`
- The filename stem must exactly match `Record-ID`.

## Example

```text
Record-ID: attribution-cutoff-20261023150000-ab12cd34
Created-At: 2026-10-23T15:00:00Z
Cutoff-At: 2026-10-01T00:00:00Z
Operator-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954

```
