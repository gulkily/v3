# Post Detached Signature Day-1 Recovery Plan V1

## Goal

Restore the intended day-1 authenticity property for authored posts:

- every non-anonymous browser-authored post has an adjacent detached OpenPGP signature file;
- the signature verifies against the post author's published public key;
- the signed bytes are exactly the canonical post record bytes that are committed;
- `/posts/<id>` and `/activity/` expose the source record, commit, and signature links.

Anonymous posts may remain unsigned, but the UI and read model should make that state explicit enough that unsigned authored content is not mistaken for cryptographically verified content.

## Current Gap

The current implementation records repository provenance but not author-level cryptographic provenance.

- `public/assets/browser_signing.js` submits `/api/create_thread` and `/api/create_reply` with URL-encoded compose fields only.
- `LocalWriteService::createThread()` and `LocalWriteService::createReply()` generate `Post-ID` and `Created-At`, build the canonical post text, and write only `records/posts/<post-id>.txt`.
- `commitCanonicalWrite()` stages only the passed canonical paths, so no adjacent detached signature is committed.
- `resolveAuthorIdentityId()` confirms that `author_identity_id` names a known identity, but it does not prove that the current write was signed by that identity.
- The specs say detached signatures remain adjacent where applicable, but no post write flow actually creates or validates them.

Result: a post can be traced to the accepted git history, but not verified as "this exact canonical post record was signed by this author's key."

## Intended Record Layout

For a signed post:

```text
records/posts/<post-id>.txt
records/posts/<post-id>.txt.asc
```

Rules:

- `.txt` remains the canonical post record.
- `.txt.asc` is an ASCII-armored detached OpenPGP signature over the exact `.txt` bytes, including LF line endings and trailing LF.
- `.sig` may continue to be accepted by source display as a legacy/binary detached signature extension, but new browser writes should use `.txt.asc`.
- The signature file is committed in the same git commit as the post record.

## Core Design Decision

Use a two-phase signed compose flow for browser-authored posts.

Reason: the browser cannot sign the exact canonical record in the current one-request flow because the server generates `Post-ID` and `Created-At`. Signing a pre-submit intent payload would prove less than the user expects; it would not directly verify the final canonical record bytes.

The signed flow should be:

1. Browser normalizes compose inputs and ensures a published OpenPGP identity.
2. Browser asks the server to prepare canonical post bytes.
3. Server returns:
   - `post_id`
   - `thread_id`
   - `record_path`
   - `canonical_record`
   - optional label records that will also be written
   - an expiry or nonce for replay control
4. Browser creates a detached OpenPGP signature over `canonical_record`.
5. Browser submits the prepared canonical record plus detached signature.
6. Server re-validates the prepared record, verifies the signature against the author's public key, writes `.txt` and `.txt.asc`, and commits both in one git commit.

## New API Contract

### `POST /api/prepare_thread`

Input:

- `board_tags`
- `subject`
- `body`
- `author_identity_id`

Output:

- `status=ok`
- `post_id=<generated id>`
- `thread_id=<same id>`
- `record_path=records/posts/<post-id>.txt`
- `canonical_record=<exact LF-normalized bytes>`
- `prepare_token=<opaque token>`
- `expires_at=<RFC3339 UTC timestamp>`

### `POST /api/prepare_reply`

Input:

- `thread_id`
- `parent_id`
- `board_tags`
- `body`
- `author_identity_id`

Output:

- `status=ok`
- `post_id=<generated id>`
- `thread_id=<thread id>`
- `record_path=records/posts/<post-id>.txt`
- `canonical_record=<exact LF-normalized bytes>`
- `prepare_token=<opaque token>`
- `expires_at=<RFC3339 UTC timestamp>`

### `POST /api/create_prepared_post`

Input:

- `prepare_token`
- `post_id`
- `record_path`
- `canonical_record`
- `detached_signature`
- `author_identity_id`

Behavior:

- Reconstruct or load the prepared record server-side.
- Reject expired, mismatched, duplicate, or tampered prepared records.
- Verify `detached_signature` over `canonical_record`.
- Confirm the signer fingerprint matches `author_identity_id`.
- Parse `canonical_record` with `PostRecordParser`.
- Write:
  - `records/posts/<post-id>.txt`
  - `records/posts/<post-id>.txt.asc`
  - any derived thread-label records already implied by the canonical body
- Commit all written canonical files together.
- Run the same incremental read-model and artifact invalidation path as existing post writes.

## Backward Compatibility

Keep existing `/api/create_thread` and `/api/create_reply` behavior initially for:

- anonymous submissions;
- no-JavaScript fallback;
- internal/server-generated posts where no browser private key exists.

Add clear naming in code and tests:

- unsigned create path;
- signed prepared create path.

After the signed browser path is stable, decide whether authenticated browser posts should be required to use the signed path and whether unsigned authored posts should be rejected.

Existing historical posts without `.txt.asc` should be treated as legacy unsigned records. Do not synthesize signatures for them. Their page can continue to show source and commit only.

## Verification Rules

Server verification should be mandatory before writing a signed prepared post:

- The signature must be ASCII-armored detached OpenPGP.
- The signature must verify over the exact canonical record bytes.
- The public key must be loaded from the already-published identity/public-key record for `author_identity_id`.
- The signing key fingerprint must equal the lowercase fingerprint portion of `author_identity_id`.
- Verification failure must return a deterministic `400` or `403` response and must not write or commit either file.
- The signature file itself must be ASCII text, LF-normalized, and end with a trailing LF.

Implementation candidates:

- Add `ForumRewrite\Security\OpenPgpSignatureVerifier`.
- Prefer a small, explicit `gpg --verify <signature> <record>` invocation in a temporary homedir after importing the published public key.
- Keep all temp files under system temp directories, never under the public artifact root.
- Keep shell invocation non-interactive and fully argument-escaped, matching existing git safety standards.

## Browser Work

Update `public/assets/browser_signing.js`:

- Add helpers to sign exact text with OpenPGP.js:
  - read saved private key;
  - create message from the server-provided `canonical_record`;
  - create armored detached signature.
- Update signed compose submission:
  - call prepare endpoint;
  - sign returned canonical record;
  - submit `/api/create_prepared_post`;
  - keep existing optimistic UI behavior where possible.
- Preserve anonymous buttons as an explicit unsigned path.
- Surface verification/signing errors in the existing compose status area.

Important: optimistic cards should use the prepared `post_id` returned by the server so the pending UI matches the final permalink.

## Server Work

Add preparation and finalization methods in `LocalWriteService`:

- `prepareThread(array $input): array`
- `prepareReply(array $input): array`
- `createPreparedPost(array $input): array`

Suggested implementation:

- Extract shared canonical record construction from `createThread()` and `createReply()`.
- Persist prepared records in a small private pending-write store under `state/cache` or SQLite, keyed by `prepare_token`.
- Store enough data to re-check:
  - canonical record hash;
  - author identity id;
  - post id;
  - thread id;
  - parent id;
  - created timestamp;
  - expiry.
- On finalization, compare the submitted `canonical_record` hash to the prepared hash before verifying the signature.
- Reuse existing `synchronizePostDerivedState()` and invalidation behavior after commit.

## Read Model And Display

The source/signature display work should remain display-only:

- `Application::sourceSignatureLink()` should continue to show `records/posts/<post-id>.txt.asc` when it exists.
- Source routes should continue to serve validated adjacent detached signature files.
- The read-model builder should continue ignoring adjacent `.asc`/`.sig` files when scanning record families.

Optional follow-up:

- Add a post metadata flag such as `signature_status` for `signed`, `unsigned_legacy`, `anonymous_unsigned`, or `signature_invalid`.
- Avoid request-time signature verification on hot page loads; verify at write time and optionally during offline audit tooling.

## Implementation Slices

### Slice 1: Signature Verifier

Work:

- Add `OpenPgpSignatureVerifier`.
- Add tests with fixture public key, fixture canonical text, valid detached signature, tampered text, and wrong key.
- Return structured results rather than raw `gpg` output.

Acceptance:

- Valid detached signature verifies.
- Tampered record fails.
- Wrong key fails.
- Missing or malformed signature fails without throwing unhandled warnings.

Status: Implemented. Added `OpenPgpSignatureVerifier` with structured verification results, static public-key/text/signature fixtures, and OpenPGP security tests for valid signatures, tampered text, fingerprint mismatch, and malformed signature input.

### Slice 2: Canonical Record Builder Extraction

Work:

- Extract canonical thread/reply record construction from `createThread()` and `createReply()`.
- Preserve byte-for-byte output for current unsigned paths.
- Add unit tests that current post records remain unchanged.

Acceptance:

- Existing create-thread/create-reply tests still pass.
- Extracted builder returns the exact canonical bytes later signed by the browser.

Status: Implemented. Extracted `buildThreadPostRecord()` and `buildReplyPostRecord()` in `LocalWriteService`, kept existing unsigned write paths on those helpers, and added fixed-byte reflection tests for both canonical record builders.

### Slice 3: Prepare Endpoints

Work:

- Add application routes for `/api/prepare_thread` and `/api/prepare_reply`.
- Add `LocalWriteService::prepareThread()` and `prepareReply()`.
- Store pending prepared records with expiry and canonical hash.
- Return canonical record bytes in a response format that preserves newlines exactly; JSON is preferable for this endpoint.

Acceptance:

- Prepare endpoints return valid canonical records.
- Prepared post IDs are unique and not committed before finalization.
- Expired prepare tokens are rejected.

Status: Partially implemented for preparation. Added `/api/prepare_thread` and `/api/prepare_reply`, writer preparation methods, pending prepared-post JSON storage with expiry metadata, and smoke tests that prepared thread/reply records return exact canonical bytes without committing canonical post files. Expiry rejection is deferred to Slice 4 finalization, where prepared tokens are consumed.

### Slice 4: Signed Finalization Endpoint

Work:

- Add `/api/create_prepared_post`.
- Verify token, canonical hash, parser validity, author identity, and detached signature.
- Write `.txt` and `.txt.asc`.
- Commit both files in one git commit.
- Reuse read-model update and artifact invalidation.

Acceptance:

- Valid signed post creates both files and displays a signature link.
- Invalid signature creates no files and no commit.
- Signature file is included in the same commit as the post record.

Status: Implemented. Added `/api/create_prepared_post`, prepared-token loading and expiry enforcement, canonical hash and metadata matching, canonical author identity public-key lookup, detached OpenPGP verification, atomic post-plus-signature canonical commits, and smoke tests covering valid finalization, invalid signatures, expired tokens, committed signature paths, and the `/posts/<id>` signature link.

### Slice 5: Browser Signed Compose

Work:

- Add OpenPGP.js detached-signature helper.
- Change non-anonymous thread/reply submit paths to prepare, sign, and finalize.
- Preserve anonymous submit buttons as unsigned create calls.
- Keep draft clearing and optimistic UI behavior correct after the two-step flow.

Acceptance:

- Signed browser thread creation produces `.txt.asc`.
- Signed browser reply creation produces `.txt.asc`.
- Anonymous post creation remains possible and unsigned.
- Failure to sign or verify leaves the draft recoverable.

Status: Implemented. Added browser detached-signature helpers that use the saved private key and OpenPGP.js to sign the exact prepared canonical record, changed browser-identity thread and reply submissions to prepare/sign/finalize through `/api/create_prepared_post`, kept the anonymous submitter on the existing unsigned form path, preserved draft restore on failures, and added browser-side transport tests for signed thread and reply finalization.

### Slice 6: Audit And UI Clarity

Work:

- Add an offline audit command that scans posts and reports:
  - signed and valid;
  - missing signature;
  - invalid signature;
  - unknown author key;
  - anonymous unsigned.
- Add optional UI copy or metadata for unsigned legacy posts if product direction requires it.

Acceptance:

- Operators can audit repository authenticity without relying on page rendering.
- Historical unsigned posts are visible as legacy rather than silently considered verified.

Status: Implemented. Added `scripts/audit_post_signatures.php` to scan canonical posts and report `signed_valid`, `missing_signature`, `invalid_signature`, `unknown_author_key`, and `anonymous_unsigned` records using the same detached OpenPGP verifier as the write path. Added explicit `Signature: legacy unsigned` / `Signature: anonymous unsigned` metadata for unsigned post sources on post and activity pages, plus command and rendering smoke coverage.

### Slice 7: Verification

Run:

- `php -l src/ForumRewrite/Write/LocalWriteService.php`
- `php -l src/ForumRewrite/Application.php`
- `php -l src/ForumRewrite/Security/OpenPgpSignatureVerifier.php`
- `php -l tests/WriteApiSmokeTest.php`
- `php -l tests/OpenPgpKeyInspectorTest.php`
- `php tests/run.php`

Also manually verify:

- create a signed browser thread;
- open `/posts/<post-id>`;
- confirm `Source`, `Commit`, and `Signature` links are present;
- open `/source/current/records/posts/<post-id>.txt.asc`;
- run local signature verification over the source record and signature file.

Status: Completed with environment caveats. Syntax checks passed for `LocalWriteService.php`, `Application.php`, `OpenPgpSignatureVerifier.php`, `WriteApiSmokeTest.php`, `OpenPgpKeyInspectorTest.php`, and `browser_signing.js`. Focused signature tests passed in the full runner, including detached verifier tests, prepared-post write smoke tests, and `PostSignatureAuditCommandTest`. `php tests/run.php` did not pass overall in this shell because `gpg-agent` cannot start for pre-existing key-generation tests, and the working tree also contains an unrelated uncommitted `DownloadProdDataCommandTest.php` runner entry; the failures were not in the new detached-signature verifier, prepare/finalize, browser-signing, source-link, or audit-command coverage. Browser manual verification was not run from this non-browser shell; the automated transport and write-path tests cover the prepared canonical record, detached signature, committed `.txt.asc`, and rendered signature link path.

## Rollout Notes

- Ship as additive first: signed browser posts use the new path, existing unsigned paths remain.
- After confidence is high, make signed writes mandatory when `author_identity_id` is present.
- Keep anonymous writes unsigned unless product policy changes.
- Do not backfill fake signatures for old content. Authenticity for legacy records remains repository/git acceptance only.

## Open Questions

- Should server-side reply-agent posts be signed by the agent private key as part of this same recovery, or in a follow-up slice?
- Should thread-label and post-reaction records also require detached signatures in this pass?
- Should `/source/blob/<sha>/<signature-path>` be preferred for signatures once signature and record are committed together, or should UI continue linking current signature files?
- What cutoff date should the UI use if it labels older unsigned posts as `legacy unsigned`?
