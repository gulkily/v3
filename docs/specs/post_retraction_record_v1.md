# Post retraction records v1

A newly accepted retraction retains three files in the same commit that deletes
the target post and its signature:

- `records/post-retractions/post-retraction-<post-id>.txt`: the exact signed request.
- The request path plus `.asc`: its exact detached ASCII-armored OpenPGP signature.
- `records/post-retraction-acceptances/post-retraction-<post-id>.txt`: local acceptance.

The request uses the existing `Record-ID`, `Created-At`, `Post-ID`, `Operation:
retract`, and `Author-Identity-ID` headers, with `Actor-Identity-ID` and
`Retraction-Class: operator` for an operator action. Its body is empty. Acceptance
never rewrites these signed bytes or adds unsigned headers to them.

The receipt uses UTF-8, LF, `Key: value` headers, a blank separator and an empty
body. All fields below are required; empty values mean absent for Source-SHA256,
Parent-ID and Thread-Type. Writers include Source-SHA256 when accepting a live
post. Unknown/duplicate headers, bodies, invalid identifiers/dates, unsupported
versions and inconsistent evidence are errors. IDs and timestamps follow the
existing post/request conventions. Thread-Type is empty or `task`, the supported canonical root type.

```text
Version: 1
Record-ID: post-retraction-example
Post-ID: example
Accepted-At: 2026-10-25T13:00:00Z
Actor-Identity-ID: openpgp:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
Retraction-Class: author
Retraction-Attribution: author
Source-SHA256: <64 lowercase hex characters>
Request-SHA256: <SHA-256 of exact request bytes>
Signature-SHA256: <SHA-256 of exact signature bytes>
Thread-ID: example
Parent-ID: <empty>
Post-Created-At: 2026-10-25T12:00:00Z
Author-Identity-ID: openpgp:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
Thread-Type: <empty>

```

Angle-bracket placeholders above are explanatory, not literal field values;
`<empty>` means nothing after the header's separating space. Class records
who authorized the action; attribution records its existing display treatment.
Request target, actor, author, class and acceptance time must match the receipt.
The receipt stores structural metadata only, never deleted prose or cleanup state.

A receipt is trusted local acceptance, not a transferable authorization claim.
Hashes bind evidence but do not confer authority. The writer verifies the signed
request and action-time permissions; imports cannot manufacture acceptance by
supplying a receipt. Missing or mismatched companion evidence is not an accepted
retraction. Existing accepted deletions prevent imported old content reappearing.

Previous retraction formats have no compatibility or migration guarantee. Do not
restore deleted prose to upgrade them. Retry/recovery for new records must retain
a coherent evidence set; a post-commit refresh failure does not undo deletion.
Older JSON-only application versions cannot read this format.

Finalize returns `record_path`, `signature_path` and `acceptance_path`. An exact
signed-action replay remains retryable after prepared-token cleanup; changed
requests or signatures cannot reuse that acceptance.
