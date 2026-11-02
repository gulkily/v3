# Key retirement record v1

Status: Implementation contract; no writable retirement route until all authorization/history gates pass.

A retirement permanently withdraws one fingerprint's future account privileges.
It retains the public key, original signed records, historical approval grants and
private encrypted transfers. It is not OpenPGP revocation, account deletion or
browser-local key removal. Public reading and keyless posting remain available.

## Signed record and acceptance

Path: `records/key-retirements/openpgp-<lowercase fingerprint>.txt`, with detached
signature at the same path plus `.asc`. One target has one immutable retirement.
The signed canonical record uses these ordered headers and an empty body:

    Version: 1
    Action-ID: key-retirement-<32 lowercase hexadecimal digits>
    Created-At: <RFC 3339 UTC>
    Actor-Identity-ID: openpgp:<40 lowercase hexadecimal digits>
    Target-Identity-ID: openpgp:<40 lowercase hexadecimal digits>
    Account: <target current normalized username>
    Authority: member|operator
    Replacement-Identity-ID: openpgp:<40 lowercase hexadecimal digits>
    Context-SHA256: <64 lowercase hexadecimal digits>

The record has a trailing blank line. No private keys, plaintext, session keys,
message identifiers or private sync state belong in it. Limits: 16 KiB record,
64 KiB signature; strict headers, identities and UTC date, no extra body.

Acceptance path: `records/key-retirement-acceptances/openpgp-<fingerprint>.txt`.
Repository-owned plain-text acceptance binds Version, Action-ID,
Record-SHA256, Signature-SHA256 and Accepted-At to the exact signed record.
It is a server acceptance fact, not a member signature. Record, signature and
acceptance commit together using existing recoverable canonical transactions.
Imports remain trusted administration, as with approval acceptance: imported
acceptance does not substitute for cryptographic signature verification.
Missing, malformed, mismatched or conflicting evidence never grants a retirement;
normal startup fails explicitly rather than silently restoring a target's access.

Prepared internal state retains reviewed context, including actor/target/replacement
identity, active approval, immutable bootstrap names, verified name history,
operator authority and effective rename flag. Signatures bind its hash. Recheck
under the existing write lock before acceptance. A changed context requires new
review; exact accepted resubmission returns the existing outcome. Client dates do
not establish past authority. A valid accepted retirement survives the actor's
later retirement; never require today's actor eligibility to replay past acceptance.

## Authority and final-key protection

Member: active approved actor distinct from target; rename flag disabled; equal
current and bootstrap usernames for actor and target; neither has any verified
name-change history. A verified rename back or imported rename still counts.
Operator: active approved root-seeded actor distinct from target; may retire a
renamed target and does not depend on the rename flag. Cross-account approval
flag never grants ordinary members retirement authority.

Both paths require a distinct active approved replacement whose current name
matches the target's current account and its own bootstrap name, with no verified
name history. The actor may be that replacement. Otherwise approve and verify a
new replacement first. Serialize the final check with retirement and other
canonical writes; concurrent reciprocal retirements cannot consume the last key.
The feature does not migrate old approval receipts or redefine general accounts.

## Historical versus current eligibility

Derive the approval graph from retained grants exactly as before. Retired keys
continue to support old grants and their beneficiaries. Apply effective retirement
after historical approval/attribution derivation; never delete the original grants.
Preserve historical approval status separately from the public active membership
value where required by history presentation and message verification.

New sign-in, protected actions, member/operator signing, message sends and new
recovery donations require active eligibility. Recheck canonical retirement on
mutation acceptance, including when derived refresh failed; a stale PDO snapshot
or retained seed cannot restore access. Existing/forwarded sessions are checked
on each protected request. A retired signature remains cryptographically valid
for old records; it is not present-day authority to submit a new protected action.

Historical original-signer lookup and retained donor transfers may use approved
retired keys; new send/target/donor lookup may not. Existing private transfers have
no acceptance timestamp. Preserve those already in the trusted private store;
do not invent dates or promote fresh uploads to historical evidence. Serialize new
transfer/send acceptance with retirement, rechecking source and destination under
the lock. A response already delivered cannot be recalled. Scheduling/continuation
must invalidate on retirement; no full-account public-key materialization.

## Recovery and deployment

Rebuild/incremental updates must agree. Schema changes require the version bump,
schema specification, prior-version startup test and isolated rebuild/UI checks.
Ordinary canonical archives include all three public retirement files. Message
recovery additionally requires retained private message/sync stores; missing stores
do not erase retirement and do not imply recoverable plaintext.

Use a retirement-aware application for restore/rollback. Older code may ignore
retirement and is not safe to serve against retired accounts. No deployment,
history rewrite or remote configuration change is part of local implementation.
