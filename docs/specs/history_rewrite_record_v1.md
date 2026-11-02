# History rewrite record v1

A history purge rewrites every commit after the first one that touched a purged
path, so each of those commits gets a new identity. This record keeps the old
and new identities together so `/source/blob/<commit>/...` and
`/source/commits/<commit>` links written before the purge keep resolving. It is
written once per purge, committed with the purge, and never edited. It is
instance-local: archives and imports neither export nor accept it.

## Canonical path rule

- `records/history-rewrites/history-rewrite-<YYYYMMDD>T<HHMMSS>Z.txt`, where the
  timestamp is `Rewritten-At` with punctuation removed.
- A run with more than 100,000 mappings continues in
  `records/history-rewrites/history-rewrite-<timestamp>-<part>.txt` for parts 2
  and up. All parts of one run share the header values except `Part`.
- Two runs cannot share a second; the writer refuses to overwrite.

## Headers

All headers are required exactly once, in this order. UTF-8, LF and a final
newline are required; each file is at most 16 MiB.

- `Format`: `history-rewrite-v1`
- `Rewritten-At`: RFC 3339 UTC timestamp of the rewrite, matching the filename
- `Reason`: `purge`
- `Receipt-Count`: number of accepted `delete` receipts that drove the run, a
  non-negative integer without sign or leading zeros
- `Part`: 1-based part number
- `Parts`: total parts in the run; `Part` must not exceed it

## Body

Zero or more mapping blocks, each separated and terminated by a blank line:

- `Old-Commit`: the commit identity before the rewrite, 40 lowercase hex
- `New-Commit`: the identity after the rewrite, 40 lowercase hex

Within one file `Old-Commit` values are strictly ascending, no two blocks share a
`New-Commit`, and `Old-Commit` never equals `New-Commit`: commits whose identity
did not change (everything before the first affected commit) are omitted. A file
holds at most 100,000 blocks. Unknown or duplicate headers, header order
changes, unknown block fields, uppercase hex, an identity mapping, a repeated
`Old-Commit` or `New-Commit`, out-of-order blocks, or a filename that disagrees
with `Rewritten-At` and `Part` are rejected.

## Resolution

To resolve an identity, apply every record in ascending `Rewritten-At` then
`Part` order: whenever the current identity appears as an `Old-Commit`, continue
with its `New-Commit`. The result is the newest identity; an identity that no
record names resolves to itself. Routes consult the map only after Git reports
the commit unknown, so a live identity is never redirected.

## Example

```text
Format: history-rewrite-v1
Rewritten-At: 2026-11-01T10:15:00Z
Reason: purge
Receipt-Count: 2
Part: 1
Parts: 1

Old-Commit: 1111111111111111111111111111111111111111
New-Commit: aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa

Old-Commit: 2222222222222222222222222222222222222222
New-Commit: bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb

```

Stored at `records/history-rewrites/history-rewrite-20261101T101500Z.txt`.

## Existing records

There are none; this format is introduced with the first purge. A later
format version adds a new `Format` value and a resolver that reads both.
