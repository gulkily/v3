# Approval acceptance receipt v1

A receipt records server-observed facts when an account key grant is accepted. It is repository-owned evidence, not a member signature or a replacement for validating the signed grant. Repository imports remain trusted administration.

## Storage and binding

Receipts live at `records/approval-acceptances/<grant-post-id>.json`. The writer commits a new grant, its detached signature when present, and its receipt together. Failure before commit removes incomplete files. Failure after commit must preserve the grant and report that acceptance committed even when projection or cache refresh failed.

Version 1 has these fields:

| Field | Meaning |
| --- | --- |
| `version` | Integer `1` |
| `post_id` | Exact grant post ID; must match the receipt filename |
| `record_sha256` | SHA-256 of the unchanged grant bytes |
| `accepted_at` | Server acceptance time, RFC 3339 UTC with second precision |
| `kind` | `direct` or `invitation` |
| `context` | Acceptance-time approval-policy evaluation described below |

Context includes `actor_identity_id`, `actor_username`, `target_identity_id`, `target_username`, `target_profile_slug`, `target_thread_id`, `target_post_id`, `existing_key_count`, `classification`, `actor_is_operator`, `cross_account_allowed`, and `allowed` (`true`). Identity IDs are canonical lowercase OpenPGP fingerprints. The usernames, counts, authority, and effective policy are snapshots, not values to recompute from today's profiles or settings.

Classifications are `new_account` (no existing approved keys), `own_account_key` (additional key for the authorizer's username), and `cross_account_key` (additional key for another username). Operator authority is recorded separately; an operator-authorized cross-account grant is shown explicitly as an override. A root seed itself has no synthetic receipt.

Signed direct approvals include `Approval-Context-SHA256` in their body. It hashes the server's JSON encoding of the complete reviewed context. The server stores the reviewed context with preparation metadata and compares it with live context under the write lock before acceptance. Changed context requires fresh preparation and review. Receipts validate this digest when present, as well as exact grant bytes, identities, bootstrap thread/parent, classification, count, booleans, and acceptance time. Older/manual grants without the digest do not acquire a member-signed review claim.

An invitation redemption is signed by its recipient. Its receipt identifies the invitation issuer as the authorizing actor and the recipient as the target key. Replay also checks that this authorizer matches issuance. The receipt does not change or replace the issuance/redemption signatures.

## Replay and uncertainty

Full and incremental invitation projections use the same replay implementation. A receipt's acceptance time is compared with invitation expiry; the current wall clock cannot remove an accepted grant. Later revocation prevents future redemption and does not revoke an accepted key. For legacy redemptions without receipts, the recorded creation date is used only to replay eligibility; actual acceptance time, count, classification, authority, and policy remain unknown. Historical review candidates are not confirmed violations.

Activity uses receipt acceptance time for new grants. Signed policy-change records retain their recorded event time and source commits. Second-resolution timestamps alone may not establish order for simultaneous events; inspect commits and acceptance-policy snapshots. Environment-only changes have no invented signed change history.

Invalid receipts fail validation rather than being silently treated as known evidence. Missing receipts leave legacy facts unknown. Receipts do not retroactively apply the current policy to old grants and do not implement revocation, notifications, device pairing, or account recovery.

## Publication and restore

Account history and receipt source links follow existing profile/source membership boundaries. They contain no IP or device details. `view=security` on classic/Forte activity is a separate correlation view requiring an authenticated, currently approved, canonically root-seeded operator. It uses no-store responses, including its API entry points; RSS is unavailable. Its correlation fields are not added to public offline snapshot tables.

Trusted `import-repository` restores recognize and validate the receipt family, preserving grant bytes and receipt facts across a rebuild. Remote `import-instance` content imports exclude approvals, invitations, seeds, and acceptance receipts as authority records. Preserve receipts and adjacent grant signatures with repository backups.

Implementation: [ApprovalAcceptance](../../src/ForumRewrite/Security/ApprovalAcceptance.php), [InvitationApprovalReplay](../../src/ForumRewrite/Invitation/InvitationApprovalReplay.php), and [ApprovalHistory](../../src/ForumRewrite/ReadModel/ApprovalHistory.php). See the [operator runbook](../runbooks/account_key_approval_safety.md).

## Readable storage v2

The v2 codec represents the same logical v1 acceptance facts using text. New-write
integration is tracked in the F03 implementation summary. Storage has no body;
exactly one of each header below is required, LF-terminated, at most 16 KiB.
Unknown/duplicate headers, control characters, invalid UTF-8, missing fields,
noncanonical integers and booleans are rejected. Scalar strings are at most
4096 bytes. Header names are case-sensitive. Example (hash is illustrative):

```text
Format: approval-acceptance-v2
Post-Id: grant-example
Record-Sha256: aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
Accepted-At: 2026-01-01T00:05:00Z
Kind: direct
Context-Actor-Identity-Id: openpgp:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
Context-Actor-Username: alice
Context-Target-Identity-Id: openpgp:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb
Context-Target-Username: bob
Context-Target-Profile-Slug: bob-key
Context-Target-Thread-Id: bootstrap
Context-Target-Post-Id: bootstrap
Context-Existing-Key-Count: 0
Context-Classification: new_account
Context-Actor-Is-Operator: false
Context-Cross-Account-Allowed: false
Context-Allowed: true
```

The order of `Context-` headers is the original context insertion order. Preserve
it during conversion: `Approval-Context-SHA256` still hashes the existing PHP JSON
encoding with `JSON_THROW_ON_ERROR`, including key order, scalar types and default
Unicode/slash escaping. This private digest preimage is not the receipt storage
format. Do not sort fields, rewrite signed grants or compute the digest from text
bytes. New grants use the same digest algorithm. Legacy decoding is isolated in
`LegacyApprovalReceipt`; `ApprovalReceiptText` and shared semantic validation do
not depend on it. Unknown historical fields or values that cannot be represented
losslessly block conversion and leave original evidence intact.
