# Account key revocation record v1

Status: implemented and locally verified; production deployment is instance-specific.

An operator-signed `records/key-revocations/key-revocation-<32 lowercase hex>.txt`
contains only Record-ID, Operation (`revoke-key`), Author-Identity-ID,
Target-Identity-ID, Username, Revocation-Cutoff and Review-Context headers, then
an empty body. The JSON/API field `revocation_cutoff` maps to the signed
`Revocation-Cutoff` header. Identity values are canonical lowercase OpenPGP identity IDs;
Username is the account claim reviewed at acceptance. Review-Context is a
SHA-256 digest of complete prepared target/operator/account state. Detached
operator signature is stored alongside the record. Root-seeded targets and
self-revocation are rejected.

Revocation-Cutoff is explicitly signed UTC `YYYY-MM-DDTHH:MM:SSZ`, defaulted
at preparation, reviewed and immutable. No commit/submission-time fallback,
custom date selector or historical invalidation is defined. A separate
repository-owned acceptance receipt binds exact record bytes and server
acceptance time; it is committed with the signed record. Receipt time must
not precede the cutoff. Access denial begins on server acceptance under the
write lock. Delayed acceptance does not invalidate previously accepted posts
or grants. Same-second concurrency is ordered by locked acceptance, not by
member timestamps or lexicographic random IDs.

## Historical authority and account ownership

Derive historical approvals including revoked sponsors before computing current
access. Never erase approval attribution or recompute old post/reaction ownership
from a graph with the revoked sponsor removed. Overlay revocation only on current
access, future grant eligibility and current message-recipient key sets. Preserve
legacy unknown acceptance times; do not claim that a signature proves when a
record was accepted. Existing historical approval signatures/receipts remain
unchanged. New grants/redemptions relying on a revoked identity are denied by the
writer; remote content import cannot introduce authority families. Trusted full
repository restore is administration and must preserve current revocation records.

A revocation durably retains the reviewed Username claim even when no active key
remains. The revoked key cannot rename away from that claim, be reapproved or be
restored through invitation redemption. Ordinary sponsorship must not classify a
reserved account as new merely because active-key count became zero. Replacement
requires a different key and explicit root-approved operator authorization.
An already approved unrelated key must not join the reserved username by renaming.
Existing accepted sponsorship does not cascade or become a violation when its
issuer is later revoked. Root rotation and owner/non-operator revocation are outside
this contract.

## Enforcement inventory

- User Access operator entry, prepare/finalize and root seed/manual administration.
- Profile naming, all direct/manual approvals, invitation issue/redeem/revoke and
  pending/exact prepared-write retries.
- Auth challenge/status/login, members-only HTTP gate, operator guards and hints.
- Signed/unsigned attributed posts, thread labels, reactions, retractions, flags,
  moderation, agent/operator actions and every canonical write entry in LocalWriteService.
- Private mailbox reads/writes, session/message actions and approved recipient keys.
- Profile/username/account history APIs, operator Activity/access history, CLI audit,
  evidence source routes, static artifacts, offline snapshots and conditional responses.
- Full/incremental projection, startup schema recovery, archive validation/restore,
  remote content import and cache/refresh failure after committed writes.

This inventory is a verification obligation, not a claim that enforcement exists.
Pre-revocation backups and older application versions cannot be treated as a safe
security rollback without restoring current revocation evidence before reopening
writers or private access. Already downloaded data cannot be recalled.

## Acceptance and projection

`records/key-revocation-acceptances/<Record-ID>.json` stores version 1, record_id,
sha256 of exact signed request bytes, and server-owned accepted_at. Signed request,
detached `.asc` signature and receipt commit together. Reader validation requires
matching IDs/hash, valid UTC times, a verified root-operator signature and an
ordinary target. Incomplete or invalid bundles fail closed. Receipt time is trusted
repository-administration evidence; it is not a second member signature. Exact
signed retries compare committed blobs and recover without duplicate events.

Schema 26 separates historical approval (`was_approved`) from current `is_approved`
and terminal `is_revoked`. Full/incremental projections derive historical facts
before applying revocation. The retained Username must still match the target's
canonical name; conflicting imported renames fail projection instead of releasing
the claim. Account history preserves approval facts alongside revocation facts.
Operator-only Activity rows use stable `key-revocation:<Record-ID>` action keys;
ordinary views/detail access and offline operational-table publication exclude them.

Prepare/finalize endpoints are `/api/prepare_key_revocation` and
`/api/create_prepared_key_revocation`; both require an authenticated approved root
operator and return no-store JSON. Preparation accepts target_identity_id and
rejects custom revocation_cutoff input. Finalization accepts prepare_token,
record_path, canonical_record and detached_signature. The reviewed cutoff is
already inside canonical_record; it is never recalculated during finalization.
A fresh login must prove key possession before receiving revoked-key status.

See [operation/restore/rollback](../runbooks/account_key_revocation.md).
