# Invitation entry

Click Invite from a page to generate an invitation. **Include destination URL**
starts unchecked. Enabling it prefills the originating page's internal path,
query, and fragment; edit it as needed. Toggling preserves edits. Leaving it
unchecked generates an invitation without a target. The copied link and QR code
represent the same invitation.

Opening a targeted invitation automatically prepares a browser-owned key if
needed, redeems the invitation, verifies member access, and opens the destination.
No username prompt or Redeem click is required. A fresh key receives a random
`reader-…` name. Existing browser keys and names are preserved. JavaScript and
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
