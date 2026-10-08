# Identity Bootstrap Signature Verification Investigation

Date: 2026-10-06

## Symptom

While creating a post without an existing browser identity, the first automatic identity-bootstrap attempt can fail with:

```text
Could not prepare your browser identity automatically. Open /account/key/ to finish manually.
Identity bootstrap signature verification failed: signature_verification_failed
```

The failure was observed in Firefox and Chrome. In the reported case, pressing **Post** again immediately, without changing any form data, succeeded.

This is unrelated to P0 of the multi-site refactor.

## Confirmed Behavior

- The browser prepares an identity payload, signs the server-provided canonical record locally, then sends the detached signature to `/api/create_identity`.
- The client already performs one immediate recovery attempt for this exact failure. Both automatic attempts failed in the reported interaction; the next user-triggered post succeeded.
- The server compares the submitted canonical record with the prepared record before signature verification. A changed record would fail with a distinct canonical-record-mismatch error, so it does not explain this status.
- The reported status is emitted by `OpenPgpSignatureVerifier` after the public key, signature armor, expected fingerprint, and key import have passed their earlier validation paths. It represents either a non-zero GnuPG verification command or a verification result without a parsed `VALIDSIG` status line.
- Verification occurs before the identity is persisted. For this specific verification failure, retrying does not create a partial identity or post.

## Compatibility Check

Fresh browser-generated Ed25519 keys and detached signatures were exercised against the PHP verifier with local GnuPG 2.2.27. Both bundled OpenPGP.js versions verified successfully:

| Browser bundle | Result |
| --- | --- |
| `openpgp.min.js` (v6.3.0) | Verified |
| `openpgp.v5.11.3.min.js` | Verified |

This rules out a general Firefox/Chrome, Ed25519, or bundled v5/v6 interoperability failure in the local environment. It does not establish that production has identical GnuPG, deployment assets, or runtime conditions.

## Working Conclusion

The repeat-post success makes a permanent bad keypair, permanent algorithm incompatibility, and canonical-record corruption less likely. The current leading explanation is a transient first-use timing or initialization condition in the browser signing path or server-side verifier environment.

Remaining candidates include:

1. Browser WebCrypto/OpenPGP initialization or signing state on the first attempt.
2. A transient GnuPG process, temporary-directory, locking, or resource condition on the server.
3. A deployment serving stale or mixed JavaScript assets, despite the local bundles being compatible.
4. A specific GnuPG verification condition hidden by the current catch-all status, such as missing signature data or a bad-signature result.

The exact cause requires a matching server log entry from a reproduced failure.

## Current Diagnostics and Their Limit

`LocalWriteService` logs `identity_bootstrap_signature_verification_failed` with the verifier status, expected and reported public-key fingerprints, and parsed GnuPG status-code names. It intentionally omits keys, signatures, canonical records, raw GnuPG output, and user IDs.

The browser message only receives `signature_verification_failed`. It cannot distinguish whether GnuPG exited unsuccessfully, produced no `VALIDSIG`, or reported a more specific verification code. The immediate client retry also provides no delay or visible attempt correlation.

## Next Safe Diagnostic Improvements

- Record separate import and verification exit codes; record whether `VALIDSIG` was absent; retain the safe parsed GnuPG status-code names.
- Add a non-secret bootstrap attempt/correlation ID to the prepare and create requests, plus retry index and selected OpenPGP bundle version.
- Record only safe client context such as secure-context availability; never log private keys, public-key armor, detached signatures, canonical record contents, or raw GnuPG output.
- Preserve the current friendly fallback, but make it action-oriented: retrying is safe; `/account/key/` is for persistent failure. Expandable details may show a safe diagnostic code and attempt ID.
- Add a fresh browser-to-server staging smoke test that covers the HTTP/v5 and HTTPS/v6 selection paths against the deployed verifier.

## Potential Remediation (Not Implemented)

If diagnostics confirm a transient first-attempt failure, replace the current immediate retry with one short delayed, bounded fresh retry. Keep the existing manual-key fallback after the second failure. This matches the observed recovery pattern while avoiding indefinite retries or duplicate identity creation.

No application behavior is changed by this note.
