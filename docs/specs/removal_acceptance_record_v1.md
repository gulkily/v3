# Removal acceptance record v1

New signed retractions, local operator deletions and label resolutions share one
`Key: value` receipt. Previous F01 text and F02 JSON formats are unsupported;
there is no migration, historical replay or old-state recovery. Signed request
and detached signature files stay separate and retain their exact bytes.

The example displays empty values without trailing spaces; actual encoded headers include the required space.
All headers are required exactly once. Empty optional values have one space after
the colon. UTF-8, LF and a final newline are required; maximum size is 1 MiB.
Unknown/duplicate fields, contradictory profiles, invalid IDs/dates/hashes and
unrecognized body text are rejected. The body contains zero or more label blocks,
separated and terminated by a blank line; duplicate label paths are rejected.

```text
Format: removal-acceptance-v1
Post-ID: root
Operation: resolve
Authority: local-operator
Accepted-At: 2026-10-29T00:00:00Z
Actor: local-operator
Source-SHA256: aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
Thread-ID: root
Parent-ID:
Post-Created-At: 2026-10-28T00:00:00Z
Author-Identity-ID:
Thread-Type:
Retraction-Class:
Retraction-Attribution:
Request-SHA256:
Signature-SHA256:

Label-Path: records/thread-labels/label-a.txt
Label-SHA256: bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb

```

Local receipts use `records/post-removals/<post-id>.txt`; operation is `delete` or
`resolve`, actor is `local-operator`, source hash is required, and signature/class/
attribution fields are empty. Anonymous authors and empty parent/thread type are
valid. Resolution binds ownership without deleting or suppressing a live source.

Signed receipts retain `records/post-retraction-acceptances/post-retraction-<id>.txt`.
Authority is `signed-retraction`, operation `delete`, actor and author are OpenPGP
identities, class/attribution are `author` or `operator`. Request/signature hashes
are mandatory and bind the separate evidence. Source hash is optional for signed
requests: the signed target/author binding remains required; ordinary deletion
captures the exact source hash when available. No hash may be invented.

The receipt is local acceptance, not a signature or authority transferable by
archive import. Entry points authenticate before acceptance. Same-post evidence
must agree on conversation structure and nonempty source hashes; compatible
label bindings are combined, deletion dominates resolution, and signed evidence
is preserved. Conflicting bindings fail explicitly. New-format retries retain
transaction recovery; unsupported old formats cannot authorize an action.
