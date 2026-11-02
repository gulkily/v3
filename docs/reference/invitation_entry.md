# Invitation entry

Click Invite from a page to generate an invitation. **Include destination URL**
starts unchecked. Enabling it prefills the originating page's internal path,
query, and fragment; edit it as needed. Toggling preserves edits. Leaving it
unchecked generates an invitation without a target. The copied link and QR code
represent the same invitation.

Opening a targeted invitation automatically prepares a browser-owned key if
needed, redeems the invitation, verifies member access, and opens the destination.
No username prompt or Redeem click is required. A fresh key receives the username
`guest`. Existing browser keys and names are preserved. JavaScript and
working browser key storage are required.

Opening the link intentionally attempts redemption, including in a
JavaScript-capable link preview. Share it with its intended recipient. An already
approved member follows a valid target without consuming an unused invitation.
An untargeted invitation shows a Username field above Redeem invitation when this browser needs an initial name. Enter a name and redeem once; an existing guest key is reused, and a named identity keeps its name.

Choose a username later through **Account → Name → Change**. The change is
signed by the same key, retains approval and content ownership, and updates profile,
username-route, author, and activity labels after rebuilding derived state.
The original key's OpenPGP user ID remains its original name; the forum's signed
name update determines the current display. Duplicate-name routing follows the
existing policy; a chosen name does not prove a unique real-world identity.

If a response is lost, reload or retry the original invitation in the same browser.
A signed same-recipient retry resumes verified access without another redemption.
Only the same accepted key can recover its grant after capacity is exhausted,
expiry, or revocation. Other keys need an available slot or a new invitation.
An invalid invitation requires help from the sender. Storage failures require fixing
browser storage or restoring a key through Account. Do not generate another key
to recover an invitation already redeemed by the current key.

Targets must be supported internal URLs. Member approval unlocks the members-only
site gate; it does not bypass independent resource restrictions. A deleted target
shows the normal Not Found page; use site navigation to continue. The approved
membership remains available.

## Capped invitations through the API

Browser issuance remains single-use with a 30-day expiry. API clients may send
`max_redemptions` (integer 1–1000) to the existing authenticated
`POST /api/prepare_invitation`. Omission means one. The returned canonical record
includes the signed `Max-Redemptions` header when requested. Sign its exact bytes
and finalize with `POST /api/create_prepared_invitation` as usual.

Example prepare fields (form encoded on the wire):

```text
verification_hash: sha256:<SHA-256 of the bearer token>
expires_at: <future RFC 3339 UTC timestamp>
max_redemptions: 2
destination: /threads/root-001
```

Keep the bearer secret and send recipients `/lobby/#invite=<token>`. Two distinct
keys can accept this example; a third is refused. Inspection/preparation,
failed writes, already-approved members and same-key retries consume no slots.
The limit counts keys, not people. Existing Lobby clients need no changes.

To close unused capacity, prepare `action=revoke`, `invitation_id` and
`verification_hash` as the issuer, then sign/finalize the returned record. Omit
`max_redemptions` when revoking. Earlier accepted grants remain valid. Recovery
never restores membership withdrawn independently of the invitation.

Inspection retains the `targeted` and `available`/`used` response fields. A closed
link with prior acceptance remains inspectable so its accepted keys can reach
signed recovery; inspection alone does not grant access or reveal recipients.
See the [record and upgrade contract](../specs/invitation_record_v1.md).

### Upgrade, backup and recovery

Upgrade every application writer and replay reader before issuing a capped link.
Older releases assume one redemption. Once multi-use records exist, use compatible
software for recovery; an old-reader rollback is unsupported. Existing single-use
records and signatures need no migration.

Keep the canonical invitation posts, signatures and approval-acceptance receipts
together in trusted backups. The public repository exporter produces a committed
snapshot under `repository/`; the existing `v3 import-repository` command accepts
`records/` or `local_repository/records/`. Extract the snapshot and repack its
`repository/records/` as top-level `records/` before using that CLI. This existing
layout mismatch is not changed by capped invitations. Keep a full Git backup as
well when preserving history is required.

Restore into an isolated repository with compatible code, run
`./v3 import-repository <archive.tar.gz> <repository> <database>` and verify the
accepted keys and closed links before switching traffic. Full rebuild and trusted
restore retain accepted counts and grants. Missing multi-use acceptance receipts
cause rebuild and invitation operations to fail; restore the matching evidence
from backup rather than deleting redemption records or reopening capacity.
Ordinary public content import deliberately excludes invitation posts and receipts
and cannot transfer invitation authority.

After a committed grant's refresh fails, retry the same signed request or the
original link with the same key. Recovery repairs derived state and consumes no
extra slot. Resolve persistent repository/refresh failures before enabling new
writes. Retry a preparation if server clock changes cause an acceptance-time
rejection; never edit signed timestamps or acceptance receipts to force acceptance.

## Choosing a username, step by step

The **Account → Name → Change** button changes the forum name attached to an
existing browser key. It can be used again later; member approval is not required
to change a name, but ownership of the key must be proven by its signature.

Later username changes are off by default. An operator enables them with the
**Username changes** feature flag (`FORUM_USERNAME_CHANGES_ENABLED`) at
`/tools/feature-flags/`. Initial naming is available even with this flag off:
the canonical original name must be `guest` (including legacy records without a
Username header) and there must be no verified name update for that key. The
same key signs its initial name; it does not need membership approval. A key that
has been renamed back to guest does not regain this exception. Choosing `guest`
again does not complete initial naming.

When the flag is off, established names cannot be changed, and name-change
Activity entries remain hidden. Existing names, keys, posts and approvals are
preserved. Account uses locally saved state to show the naming control before
paint; the signed API independently enforces eligibility. Stale browser hints
may expose a control whose request the server refuses; never replace the key
as a workaround.

1. **Check for an existing keypair.** If this browser has no saved public/private
   keypair, Account displays “Set up this browser first.” The rename action does
   not generate a replacement key.
2. **Enter a name.** Select Name → Change and edit the inline field. Cancel
   closes it without sending; Save submits using the existing key. No native
   prompt is used. Untargeted invitations provide the same choice beside Redeem.
3. **Prepare the existing identity.** The shared identity helper ensures the
   saved key is ready for signing and publishes its public identity if needed.
4. **Normalize the input.** The browser trims whitespace, lowercases the name,
   replaces runs of characters outside `a–z`, `0–9`, `.`, `_`, and `-` with a
   hyphen, and removes leading/trailing hyphens. Empty input or a result that would only fall back to `guest` is rejected.
   For example, `Alice Smith` becomes `alice-smith`. The server requires the
   normalized name to contain 1–80 characters from that same character set;
   overly long names are rejected rather than truncated. Names need not be unique.
5. **Prepare an unsigned record.** The browser sends the identity ID and normalized
   name to `POST /api/prepare_profile_name`. The server checks that the identity
   exists and is eligible under the live naming policy, then prepares a record with a unique ID, UTC timestamp, `set-name`
   operation, author identity ID, and username. It returns the exact record, its
   intended path, and a preparation token that expires after ten minutes.
   Preparation alone does not change the displayed name.
6. **Sign in the browser.** The existing private key signs those exact record
   bytes. The private key stays in the browser; only the record and detached
   signature are submitted with the preparation token, path, and identity ID to
   `POST /api/finalize_profile_name`.
7. **Verify before accepting.** Under the repository write lock, the server checks
   preparation type/expiry, the record hash, identity/path binding, record format,
   and the signature against the identity's public key, and rechecks naming eligibility. It rejects a prepared
   change whose timestamp is no newer than the latest verified name change,
   preventing stale requests from overwriting a newer saved name.
8. **Persist the signed change.** The server writes
   `records/profile-names/profile-name-<32 hex>.txt` and its `.txt.asc` signature
   companion, then commits both to the canonical repository. It preserves the
   original key, fingerprint, identity bootstrap record, approval, and content
   ownership. The key's embedded OpenPGP user ID remains its original name.
9. **Refresh derived presentation.** The server rebuilds the read model and
   invalidates generated presentation. The latest verified update, ordered by
   `(Created-At, Record-ID)`, supplies the effective profile name, username route,
   and author/activity labels. Each verified change is also listed in Activity (kind `profile_name_change`, “Changed username from X to Y”) while the flag is on. Invalid or unsigned name updates are ignored on
   replay. Historical signed content bytes remain unchanged. Existing duplicate
   username routing rules still apply.
10. **Refresh this browser.** After server success, the browser confirms that its
    current identity still matches, saves the returned username locally, refreshes
    Account, and dispatches `forum:identity-changed` for listening UI components.
    Account displays “Username saved. Your key and approval are unchanged.”
    Authenticated Account renders also reconcile the local name with server state.

### Naming errors and recovery

Errors appear in Account's existing key-status area, and the button is re-enabled.
A rejected signature, invalid name, expired preparation, or stale request does not
save a new name record. Retry to prepare a fresh request after addressing the
reported problem. If the browser identity changed during the operation, reload
Account to inspect the name for the current identity.

A lost response or derived-state failure can occur **after** the canonical change
has committed. The browser retains the signed naming request per key before
sending it. Retry (including after reload) recovers that exact committed request
without consuming initial naming twice, repairs stale derived state, and returns
the latest verified name if another update has superseded it. Keep the original
browser key; a different key cannot recover this action. A definitively expired
uncommitted preparation is cleared so the next retry can prepare afresh.

Invitation drafts remain in this tab through reload. Naming may succeed before
redemption fails; retry then uses the saved name and key. A lost acceptance
response is recovered with the existing invitation retry path, without another
slot. Persistent storage or rebuild failures require fixing the reported issue;
no successful membership is claimed until access is verified.

Implementation references: [Account action and normalization](../../public/assets/browser_signing.js),
[prepared naming writes](../../src/ForumRewrite/Write/LocalWriteService.php),
[record validation](../../src/ForumRewrite/Canonical/ProfileNameRecordParser.php),
and [verified replay](../../src/ForumRewrite/ReadModel/ProfileNames.php).

## Verification and rollback

Run `node tests/browser/invite_username.mjs`, `node tests/browser/account_initial_name.mjs`,
and `node tests/browser/invite_username_layout.mjs` for flag-off initial naming,
retry and phone/desktop theme checks. Replay the user report by opening an
untargeted invitation with Username changes disabled, entering a name, redeeming,
and checking Account after reload; repeat with an automatic guest key already
present. Existing accepted guests should name themselves in Account with the
same fingerprint. Local tests do not establish recovery on an unspecified live site.

Run `node tests/browser/invite_zero_click.mjs` with Playwright Core and Chrome
(default `/opt/google/chrome/chrome`, override with `CHROMIUM_PATH`). It creates
an isolated repository/server and tests public and members-only normal entry,
source prefilling, QR output, fresh/pending/approved identities, later naming and
CLI rebuild, interrupted response recovery, invalid/expired/revoked/foreign-used
invites, storage denial, missing targets, and untargeted manual entry.

Read-model version 20 rebuilds older caches on normal startup. Signed name updates
live in `records/profile-names/` with signature companions; no canonical data or
keys are migrated. A code rollback preserves those records but older versions
show bootstrap names after rebuilding. Preserve canonical records and browser
keys; do not delete them to roll back presentation. Production deployment is a
separate operator action.
