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

The receipt uses the shared [removal acceptance v1 contract](removal_acceptance_record_v1.md),
with `Format: removal-acceptance-v1`, `Operation: delete` and
`Authority: signed-retraction`. It retains post/conversation structure, accepted
actor/time, source hash when available, request/signature hashes and retraction
class/display attribution. Signed receipts have no label blocks; separate local
operator receipts retain label bindings and the common reader combines them.
Request target, actor, author, class and acceptance time must match the receipt.
No deleted prose or cleanup state is retained. Invalid or unknown fields and
mismatched signed evidence are rejected.

A receipt is trusted local acceptance, not a transferable authorization claim.
Hashes bind evidence but do not confer authority. The writer verifies the signed
request and action-time permissions; imports cannot manufacture acceptance by
supplying a receipt. Missing or mismatched companion evidence is not an accepted
retraction. Existing accepted deletions prevent imported old content reappearing.

Previous retraction formats, including the separate Version: 1 text receipt, are unsupported and are not migrated. Do not
restore deleted prose to upgrade them. Retry/recovery for new records must retain
a coherent evidence set; a post-commit refresh failure does not undo deletion.
Previous application versions cannot read this shared format.

Finalize returns `record_path`, `signature_path` and `acceptance_path`. An exact
signed-action replay remains retryable after prepared-token cleanup; changed
requests or signatures cannot reuse that acceptance.
