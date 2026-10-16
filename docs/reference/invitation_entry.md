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

Opening the link intentionally attempts single-use redemption, including in a
JavaScript-capable link preview. Share it with its intended recipient. An already
approved member follows a valid target without consuming an unused invitation.
An untargeted invitation retains the explicit Redeem button and normal setup.

Choose a username later through **Account → Choose username**. The change is
signed by the same key, retains approval and content ownership, and updates profile,
username-route, author, and activity labels after rebuilding derived state.
The original key's OpenPGP user ID remains its original name; the forum's signed
name update determines the current display. Duplicate-name routing follows the
existing policy; a chosen name does not prove a unique real-world identity.

If a response is lost, reload or retry the original invitation in the same browser.
A signed same-recipient retry resumes verified access without another redemption.
A used invitation cannot be recovered by another identity. Expired, revoked, or
invalid invitations require help from the sender. Storage failures require fixing
browser storage or restoring a key through Account. Do not generate another key
to recover an invitation already redeemed by the current key.

Targets must be supported internal URLs. Member approval unlocks the members-only
site gate; it does not bypass independent resource restrictions. A deleted target
shows the normal Not Found page; use site navigation to continue. The approved
membership remains available.

## Choosing a username, step by step

The **Account → Choose username** button changes the forum name attached to an
existing browser key. It can be used again later; member approval is not required
to change a name, but ownership of the key must be proven by its signature.

1. **Check for an existing keypair.** If this browser has no saved public/private
   keypair, Account displays “Set up this browser first.” The rename action does
   not generate a replacement key.
2. **Ask for a name.** A browser prompt is prefilled with the locally saved name,
   or `guest`. Cancel stops the action without sending a name-change request.
   Confirming disables the button until the operation finishes.
3. **Prepare the existing identity.** The shared identity helper ensures the
   saved key is ready for signing and publishes its public identity if needed.
4. **Normalize the input.** The browser trims whitespace, lowercases the name,
   replaces runs of characters outside `a–z`, `0–9`, `.`, `_`, and `-` with a
   hyphen, and removes leading/trailing hyphens. An empty result becomes `guest`.
   For example, `Alice Smith` becomes `alice-smith`. The server requires the
   normalized name to contain 1–80 characters from that same character set;
   overly long names are rejected rather than truncated. Names need not be unique.
5. **Prepare an unsigned record.** The browser sends the identity ID and normalized
   name to `POST /api/prepare_profile_name`. The server checks that the identity
   exists and prepares a record with a unique ID, UTC timestamp, `set-name`
   operation, author identity ID, and username. It returns the exact record, its
   intended path, and a preparation token that expires after ten minutes.
   Preparation alone does not change the displayed name.
6. **Sign in the browser.** The existing private key signs those exact record
   bytes. The private key stays in the browser; only the record and detached
   signature are submitted with the preparation token, path, and identity ID to
   `POST /api/finalize_profile_name`.
7. **Verify before accepting.** Under the repository write lock, the server checks
   preparation type/expiry, the record hash, identity/path binding, record format,
   and the signature against the identity's public key. It rejects a prepared
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
   and author/activity labels. Invalid or unsigned name updates are ignored on
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
has committed. An error therefore does not always mean the server saved nothing.
Reload Account to check the authoritative name before retrying; do not replace or
regenerate the key. A read-model failure may require operator recovery/rebuild.

Implementation references: [Account action and normalization](../../public/assets/browser_signing.js),
[prepared naming writes](../../src/ForumRewrite/Write/LocalWriteService.php),
[record validation](../../src/ForumRewrite/Canonical/ProfileNameRecordParser.php),
and [verified replay](../../src/ForumRewrite/ReadModel/ProfileNames.php).

## Verification and rollback

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
