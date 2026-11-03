# Thread Subject Record V1

This document defines the canonical append-only thread-subject record family:
one event that sets the displayed title (subject) of an existing thread without
rewriting the thread root post.

## Scope and location

- One ASCII text file represents one subject-set event.
- Files live in `records/thread-subjects/<record-id>.txt`; the filename stem must
  match `Record-ID`, which keeps the `thread-subject-` prefix.
- V1 supports `Operation: set` only. There is no clear or remove operation.

## Format

Headers in `Key: Value` form, then one blank line, then an optional (usually
empty) body.

- `Record-ID` (required)
- `Created-At` (required): RFC 3339 UTC, for example `2026-11-03T05:13:04Z`;
  authoritative canonical time for ordering
- `Thread-ID` (required): the root post ID of the target thread
- `Operation` (required): `set`
- `Subject` (required): the new title, one line, leading and trailing space trimmed
- `Author-Identity-ID` (optional): `openpgp:` and 40 lowercase hex digits; present
  on authored records, absent on system records
- `Source-Post-ID` and `Source-Post-SHA256` (optional, together): the reply whose
  `title:` line produced this record and the SHA-256 of that reply's exact
  canonical bytes; one without the other is rejected
- `Action-At` (optional): RFC 3339 UTC, milliseconds allowed
- `Intent-ID` (optional): offline signed intent that produced the record
- `Reason` (optional): short ASCII explanation

## Two kinds of record

- **System records** have no `Author-Identity-ID`. The media-embed title fetch
  writes them. They fill only an empty subject; the earliest one wins and none
  ever overrides a title a person gave.
- **Authored records** carry `Author-Identity-ID` and normally reply provenance.
  The write path produces one only when the author had authority for that thread
  at write time (see the thread title concept). The latest authored record wins
  over the root post's own `Subject` header and over system records.

An authored record whose source reply is retracted or deleted is withdrawn: readers
skip it and the previously effective title returns. A record whose `Thread-ID` is
not a known root thread is counted invalid and ignored.

## Example

```text
Record-ID: thread-subject-20261103051500-2fa9fbd6
Created-At: 2026-11-03T05:15:00Z
Thread-ID: thread-20261103051304-2fa9fbd6
Operation: set
Subject: Retitle a thread from a reply
Author-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954
Source-Post-ID: reply-20261103051500-7c1d2e3f
Source-Post-SHA256: 9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08
Reason: Title directive in reply

```

## Signatures

A detached ASCII-armored OpenPGP signature may accompany the exact record bytes
as `<record-id>.txt.asc`, verified against `Author-Identity-ID`. Unsigned records
remain valid. Signing changes neither precedence nor authority.

## Compatibility

Records written before this document (system records without provenance) remain
valid and keep their fill-empty behavior. Readers that predate the provenance
headers ignore unknown headers and still read the subject.
