# Public-Key Bootstrap Integrity Step 3 Development Plan

## Stage 1
- Goal: Define and expose a signed automatic-bootstrap account-creation boundary.
- Dependencies: Approved Step 2; existing detached-signature verification and prepared-post contracts.
- Expected changes: Adapt the account setup flow so a newly generated bootstrap statement is prepared as canonical content, receives a detached signature from the browser key, and is committed with the identity and public-key records only after verification; preserve explicit handling for supplied/legacy anchors.
- Verification approach: Confirm success, signature failure, and repository-write failure do not leave a falsely verified or partially linked automatic bootstrap.
- Risks or open questions:
  - The existing public-key-only manual link action cannot sign a new bootstrap statement.
  - The identity, post, signature, and key must not be committed inconsistently.
- Canonical components/API contracts touched: `LocalWriteService::linkIdentity()`, existing prepare/finalize post-signing contract, `OpenPgpSignatureVerifier`, and identity/public-key write paths; no schema change.

## Stage 2
- Goal: Connect normal browser account setup to the signed-bootstrap boundary.
- Dependencies: Stage 1; existing browser key generation, private-key storage, and OpenPGP signing helpers.
- Expected changes: Have the browser sign the exact prepared bootstrap statement with the newly generated private key, submit the detached signature, handle retries/failures, and preserve the existing username/profile redirect flow.
- Verification approach: Exercise a new browser setup with a generated key, confirm the committed bootstrap signature verifies, and confirm failed signing does not report setup as complete.
- Risks or open questions:
  - Existing browsers or manual callers may not support the new two-phase flow.
  - The private key must remain local and never enter repository or server-persisted public records.
- Canonical components/API contracts touched: `account_key.php`, browser signing assets, account setup transport, and the existing signed-post client/server contract.

## Stage 3
- Goal: Make identity, approval, key, and bootstrap-signature states unambiguous and recoverable.
- Dependencies: Stages 1–2.
- Expected changes: Extend profile, bootstrap-post, source metadata, and account feedback to distinguish association, key availability, approval, verified signature, unsigned legacy anchor, and duplicate fingerprint recovery; keep internal bootstrap posts linked but excluded from ordinary feeds/counts.
- Verification approach: Inspect approved/unapproved profiles, new signed bootstrap posts, legacy unsigned anchors, hidden-post links, and repeated setup with the same fingerprint.
- Risks or open questions:
  - Status wording must not imply that approval is cryptographic verification.
  - Duplicate recovery must not create or overwrite identity records.
- Canonical components/API contracts touched: `profile.php`, `post.php`, post identity-details partials, source signature metadata, account feedback, and profile-link behavior.

## Stage 4
- Goal: Lock in regression coverage for the signed-bootstrap lifecycle.
- Dependencies: Stages 1–3.
- Expected changes: Add tests for valid signed bootstrap creation and verification, invalid/tampered signatures, atomic failure behavior, duplicate identity recovery, hidden-post discoverability, approved/unapproved states, and legacy unsigned anchors.
- Verification approach: Run focused OpenPGP/account/rendering tests, then the full PHP smoke suite and manual account/profile/post flows.
- Risks or open questions:
  - Fixtures must cover both browser-signed automatic anchors and existing manually supplied anchors.
- Canonical components/API contracts touched: Existing account, write, OpenPGP, rendering, and read-model smoke tests; no new public key storage format.
