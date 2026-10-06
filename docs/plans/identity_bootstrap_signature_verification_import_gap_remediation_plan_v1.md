# Identity Bootstrap GnuPG Import-Gap Remediation Plan

Date: 2026-10-06

## Finding

The reported failure is an **unusable-import** problem, not evidence of a bad browser signature.

- The final automatic attempt (`bootstrap_retry_index: 1`) imported with exit code `0`, then verification exited `2` with `ERRSIG` and `NO_PUBKEY`; GnuPG therefore could not find the signer public key in that attempt's fresh verification home.
- `VALIDSIG` and the reported signer fingerprint are absent, as expected when verification cannot load the key.
- The event contains `IMPORT_RES` but not `IMPORTED`, `IMPORT_OK`, or `KEY_CONSIDERED`. A normal local import of the test key emits all three before verification succeeds.
- `OpenPgpSignatureVerifier` currently labels import as accepted when **either** the process exits `0` **or** it emits `IMPORT_OK`. Exit `0` alone does not prove that the expected key was stored and usable, so the verifier continues and misclassifies this case as `signature_verification_failed`.

GnuPG documents `NO_PUBKEY` as an unavailable public key and `ERRSIG` as a signature it could not check; the latter can be caused by a missing key. It also documents `IMPORT_OK` as the per-key result and `IMPORT_RES` as aggregate import statistics. [GnuPG DETAILS](https://github.com/gpg/gnupg/blob/master/doc/DETAILS)

## What remains unknown

The same user action succeeding on the next Post is not yet explained. The current log does not retain the `IMPORT_RES` counters, a matching `IMPORT_OK` fingerprint, a successful-bootstrap event, or the server's GnuPG version. It therefore cannot distinguish:

1. A transient server-side GnuPG/temp-filesystem condition from
2. A browser key/runtime variant that this deployed GnuPG did not store, followed by a changed key or runtime on the later attempt.

The first operational check is to compare the successful identity fingerprint with `0D1C5215DE1FC2E9C3737ED855696BD531B2BFCB`. If it differs, the later success used a different key and is not recovery of the failed import.

## Solution plan

### 1. Make import usability an explicit verifier gate

- After import, confirm that the expected fingerprint is present in the same temporary GnuPG home (and record whether a matching `IMPORT_OK` was emitted).
- If that gate fails, return a distinct `public_key_import_unavailable` status; do not run detached-signature verification or report it as a signature failure.
- Keep raw GnuPG output, armor, signatures, canonical records, and user IDs out of logs and responses.

### 2. Capture the missing safe evidence

- Add allowlisted import counters (`count`, `imported`, `unchanged`, `not_imported`), matching-`IMPORT_OK` state, and a parsed `ERRSIG` reason code.
- Log a safe success event with the existing attempt ID, retry index, bundle version, and resulting fingerprint, so failures can be compared with the later success.
- Record the deployed GnuPG version through a cached operator/runtime diagnostic, not per user request.

### 3. Align preparation with final verification

- Require the prepare-time key check to establish the same GnuPG import usability contract as final verification; do not let the armor fallback alone authorize a bootstrap that GnuPG cannot verify.
- Show an actionable import-specific browser diagnostic with the attempt ID when this gate fails; preserve the manual-key fallback.

### 4. Reproduce before changing retry behavior

- Add fixtures for: exit `0` without matching `IMPORT_OK`, missing expected fingerprint after import, and `ERRSIG`/`NO_PUBKEY`.
- Run fresh-browser HTTP/v5 and HTTPS/v6 staging attempts against the deployed GnuPG; retain only the safe correlation events.
- Inspect temp-directory ownership, free space/inodes, and the deployed `gpg --version` if the import gate fails.
- Only if matching-key evidence shows an intermittent server condition should the product add one short delayed fresh retry. Otherwise fix the identified import/deployment incompatibility; do not mask it with retries.

## Completion criteria

- `NO_PUBKEY` after bootstrap import is reported as an import-usability failure, never as a generic signature mismatch.
- Every failed and successful bootstrap can be correlated by attempt ID and compared without cryptographic payloads.
- Staging establishes whether the failed and successful attempts used the same key and runtime path.
- No retry, identity persistence, or manual-key behavior changes until the reproduced evidence supports one.

## No implementation in this document

This plan records investigation and proposed scope only. It intentionally makes no application changes.
