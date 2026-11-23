# Tagsets Change Record V1

This document defines the signed record for one change to a site's tagsets, and the
site tagsets file that the change sets or removes.

## Scope

- One text file represents one saved change: set the site tagsets to a text, or
  reset them to the shipped `resources/tagsets.txt`.
- Files live in `records/tagsets-changes/`.
- The record is signed with an adjacent ASCII-armored detached OpenPGP signature
  by an active operator. One commit holds the record, its signature, and the new
  or removed `records/instance/tagsets.txt`.

## Required Headers

- `Record-ID`: `tagsets-change-` followed by an ASCII record token
- `Created-At`: RFC 3339 UTC timestamp
- `Operation`: exactly `set` or `reset`
- `Operator-Identity-ID`: lowercase canonical OpenPGP identity ID

No other headers are allowed.

## Body

- `set`: the full tagsets text, in the `resources/tagsets.txt` format. It must
  pass `Tagsets::check()`. Line endings are LF, lines have no trailing spaces,
  and the text ends in one LF (empty text is allowed and unlocks nothing).
- `reset`: empty.

The signature covers the whole record, so it covers the rules themselves.

## Site Tagsets File

`records/instance/tagsets.txt` holds the body of the latest `set` change, byte for
byte. When it exists and passes `Tagsets::check()`, it replaces the shipped file
entirely. When it is missing, the shipped file applies. When it fails the check, the
shipped file applies and the Tag Unlocks page shows the first error.

## Canonical Path Rule

- Record: `records/tagsets-changes/<record-id>.txt`
- Signature: `records/tagsets-changes/<record-id>.txt.asc`
- The filename stem must exactly match `Record-ID`.

## Example

```text
Record-ID: tagsets-change-20261122230000-ab12cd34
Created-At: 2026-11-22T23:00:00Z
Operation: set
Operator-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954

Parent: issue
Target: reply, root
Unlocks: claimed, resolved, closed, open
Exclusive: yes

Parent: event
Unlocks: attend, interested
```
