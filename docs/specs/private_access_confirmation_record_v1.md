# Private access confirmation record v1

Status: Implemented (signed API and acceptance). Enforcement and UI follow in [private_key_access](../plans/private_key_access/private_key_access_step3_development_plan.md).

Approval lets a key post as its username. Reading that username's private messages needs private access ([concept](../concepts/private_key_access.md)). A key has it when it is the username's first key or a root seed, was approved by a same-username key with access, or was approved by an operator, as its approval receipt records. Every other approved key is public only until a confirmation record names it.

A confirmation never changes approval, posting or retirement. It is permanent: to withdraw a confirmed key, retire it.

## Signed record and acceptance

Path: `records/private-access-confirmations/openpgp-<lowercase fingerprint>.txt`, with a detached signature at the same path plus `.asc`. One target has at most one accepted confirmation. The signed canonical record has these ordered headers and an empty body:

    Version: 1
    Action-ID: private-access-confirmation-<32 lowercase hexadecimal digits>
    Created-At: <RFC 3339 UTC, second precision>
    Actor-Identity-ID: openpgp:<40 lowercase hexadecimal digits>
    Target-Identity-ID: openpgp:<40 lowercase hexadecimal digits>
    Account: <target's normalized username at preparation>
    Authority: member|operator
    Context-SHA256: <64 lowercase hexadecimal digits>

`Context-SHA256` hashes the server's JSON encoding of the reviewed context: `actor_identity_id`, `target_identity_id`, `account` and `authority`.

The server's receipt lives at `records/private-access-confirmation-acceptances/openpgp-<fingerprint>.txt`:

    Version: 1
    Action-ID: <the record's Action-ID>
    Record-SHA256: <SHA-256 of the record bytes>
    Signature-SHA256: <SHA-256 of the detached signature bytes>
    Accepted-At: <RFC 3339 UTC>

The record, signature and receipt are committed together.

## Authority

- **member:** the actor is an active approved key of the target's username that itself has private access.
- **operator:** the actor is an active root-approved operator, for any username.

The actor and target differ. The target is active, approved and public only. Readers check authority again when they count the record. A record whose actor has since lost private access, outside operator authority, gives no access.

## API

- `POST /api/prepare_private_access_confirmation` with `actor_identity_id` and `target_identity_id`. Returns `prepare_token`, `canonical_record`, `record_path` and `confirmation_context`, or an error naming who can confirm.
- `POST /api/confirm_private_access` with `prepare_token`, `canonical_record` and `detached_signature` (the actor's detached OpenPGP signature of the exact record bytes). Repeating the accepted request returns the same result with `recovered: true`. A different confirm for a confirmed target is refused.
- `GET /api/private_access_status?target_identity_id=…` returns `has_access`, `reason` (`root`, `first`, `own_account`, `operator`, `confirmed` or `public_only`) and, when confirmed, `confirmed_at`, `actor_identity_id` and `record_path`.

## Export and import

Both directories are archive record families, so public archives carry them. `./v3 import-instance` leaves them out as instance authority, like key retirements. An imported site's keys keep their receipts' first-key and operator evidence, but not confirmations.
