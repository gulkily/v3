# Signed reaction submission

Explicit online votes use the existing `/api/apply_thread_tag` and
`/api/apply_post_tag` endpoints. Thread likes and QDB captions remain thread-label
records; post/reply likes and all Flags remain post-reaction records. Both share
browser signing and server verification. See [Reaction Record V1](reaction_record_v1.md).

## Request and fallback

Alongside the existing target ID and `tag`, signed clients submit
`canonical_record` and `detached_signature`. The record has an empty body, one
label/tag, LF endings, and these ordered headers: Record-ID, Created-At,
Target-Kind, Target-ID, Operation, Tags, Author-Identity-ID. Target-Kind is
`thread` or `post` for the endpoint used; Operation is `add`. The server also
accepts, byte-exact and unrewritten, the deprecated legacy layout (Thread-ID/
Post-ID and Labels/Tags in place of the Target and Tags headers) still signed by
cached older clients; see `LegacyReactionHeaders`.
The browser uses 128 random bits in the family-prefixed identifier. The server
accepts a bounded safe identifier, requires exact correspondence with the
requested action and resolved viewer identity, and verifies the detached
signature before considering duplicate suppression. It never normalizes signed
bytes or silently falls back to unsigned after a signing/verification error.

The server accepts creation times within 300 seconds of its clock. Otherwise,
it returns HTTP 409 with a JSON `error_code` of `clock_skew`, `canonical_record`,
`prepare_token`, `canonical_sha256`, and `expires_at`. The browser checks that
only the identifier/time changed, signs the replacement, and resubmits to the
same endpoint with the token. The prepared record keeps the layout the client
submitted. There is one automatic fallback. Tokens expire
in ten server-clock minutes and bind exact bytes, author, and record family.
Normal success retains the existing text response and score/moderation fields.

Legacy requests without signing fields retain unsigned behavior and existing
eligibility rules. Automatic guest identity setup can produce signed votes.
Hashtag-generated labels and offline signed-intent replays are written in the
unified format without a record signature; an intent signature is not presented
as a signature over the final reaction record. Historical import/rebuild verification has no live clock limit.

## Evidence and recovery

Records and `.txt.asc` companions are committed together. Both canonical loaders
verify any supplied companion against the author's public key; invalid signatures
or missing verification keys exclude the record from scores, labels, and
moderation. Unsigned records remain valid. Import planning can resolve retained
local keys when an incoming archive omits them. Signature verification does not
change author approval policy or make aggregate scores universally signed.

Schema 17's `reaction_signatures` table records per-file verified/unsigned/invalid
status, including labels omitted from deduplicated activity. Classic and Forte
activity details show record status and signature links. Current raw-source
responses expose `X-Forum-Signature-Status` while preserving exact content bytes.
Whole-repository backups and thread archives preserve signature companions.
Compatibility cannot detect intentional removal of a signature from a record.

Accepted signed writes first save a journal under the database directory in
`signed-reaction-writes-<repository-hash>/`. Atomic file publication writes the
signature before the record, preventing an interrupted new write from appearing
unsigned. The journal remains until commit, derived-state refresh, and cache
invalidation finish. The next normal locked write recovers pending pairs and
refreshes presentation before processing the new request. Retrying a successful
or recovered vote does not count twice. Do not delete journals to bypass a
recovery conflict: restore the referenced key or reconcile conflicting files,
then retry. Recovery refuses to overwrite differing bytes.

A schema upgrade rebuilds the disposable read model on normal startup. Operators
may explicitly run `./v3 rebuild <repository> <database>` after restoring records.
A rollback to code that ignores reaction signatures loses invalid-signature
filtering: retain canonical pairs and suspend writes until compatible verification
is restored. No production deployment is performed by this feature's tests.

## Verification

Run `php tests/run.php` for the PHP and Node-backed regression suite. The
standalone real-browser matrix uses the project's `playwright-core` dependency:

```sh
node tests/browser/signed_reactions.mjs
```

Set `CHROMIUM_PATH` if Chrome is not at `/opt/google/chrome/chrome`, or
`PLAYWRIGHT_MODULE` to an external Playwright module path. The matrix creates and
removes disposable repositories, serves only localhost, and covers classic,
Forte, QDB up/down votes and Flags, skew fallback, guest setup, legacy unsigned
clients, signing-error recovery, raw evidence, and reload behavior.
