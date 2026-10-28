# Outbox and browser identity: findings to untangle

Status: backlog note, not planned work. Found 2026-10-28 while fixing the composer's Change in [ui_first_post_identity](../ui_first_post_identity/ui_first_post_identity_step4_implementation_summary.md#fix---change-on-sites-with-renaming-off).

## What we found

- **Outbox items are tied to one key, but nothing tracks that.** Two kinds of item are signed before they are sent:
  - a reaction signed offline (`outbox_intent.js`, `createSignedIntent`) stores `intent.authorIdentityId`, the canonical record (`Author-Identity-ID: …`) and a detached signature;
  - a post whose delivery was prepared (`outbox_sender.js`) stores `delivery.fields.author_identity_id` and the server's prepared record.

  Either can only be sent by that same key. Every other item (drafts, queued posts not yet prepared, unsigned reactions) is signed when it is sent, by whatever key the browser holds then.
- **The Outbox is per browser, not per key.** One IndexedDB database (`forum-offline-outbox-v1`, store `items`) holds every item regardless of signer. Accepted, rejected and cancelled items stay as history indefinitely.
- **Identity code has to read the Outbox from outside.** Before replacing a key, the composer's Change must know whether unsent signed work exists. `browser_signing.js` (`outboxHasSignedWork`) therefore opens the database directly and repeats the Outbox's schema: the database and store names, the final states `accepted` / `rejected` / `cancelled`, and the `intent.detachedSignature` and `delivery` shapes. If the Outbox changes any of these, this check silently breaks.
- **Opening the database from outside can break it.** `outbox_storage.js` creates the `items` store only in `onupgradeneeded` at version 1. Code that opens the database first creates an empty version 1 database, and the Outbox then never gets its store. The identity check avoids this by aborting the upgrade. Any other reader must do the same.
- **That check is broader than it needs to be.** It counts signed work by *any* key, not only items whose `authorIdentityId` / `author_identity_id` matches the current key. This is safe (it never orphans work) but can refuse a replacement unnecessarily.
- **The first version of the check counted every item**, including history and drafts. That is what blocked Change with "Names can't be changed on this site." Fixed in `a332f69f5`.
- **Sending registers the current key, not the item's signer.** `outbox_sender.js` calls `ensureActionIdentity` before `apply_signed_reaction` (Stage 6). If the key changed since the item was signed, the current key is registered and the old signature still can't be verified. The item then fails as "needs attention", with no path back to the key that signed it.
- **Where renaming is off, a local key with unsent signed work is stuck.** It can't be replaced (that would orphan the work) and can't be renamed on the server. The composer says "Your Outbox has items signed under this name. Send or discard them first."
- **Not checked:** Account's "Remove this key" (`clearBrowserIdentity`) does not look at the Outbox. Removing a key with unsent signed items presumably orphans them the same way, unless Undo restores the key first. This has not been reproduced.

## Directions to consider when this is picked up

- Give the Outbox a small API the identity code can call, e.g. `forumOutboxStorage.signedWorkFor(identityId)`, so identity code stops reading the database itself. It needs to load without the rest of the Outbox UI.
- Count only unsent items signed by the current key; consider showing them by key in the Outbox.
- Before Remove this key, or a replacement, offer the outcome: send these first, discard them, or keep the key.
- Decide what an item signed by a key that is gone should become: re-sign with the current key (needs user confirmation, because the signer changes), or stay as an explicit dead item.
- Decide whether accepted, rejected and cancelled history should expire or be cleared.

## Evidence

- Code: `public/assets/outbox_intent.js` (`createSignedIntent`), `outbox_storage.js` (`openDatabase`), `outbox_sender.js` (`sendReaction`, prepared delivery), `outbox_store.js` (states), `browser_signing.js` (`outboxHasSignedWork`, `changeIdentityName`, `clearBrowserIdentity`).
- Tests: `tests/browser/first_post_identity.mjs` (Outbox history doesn't block Change where renaming is off), `tests/browser/compose_identity_row.mjs` (a queued reaction signed offline: the key is registered and renamed, not replaced), `tests/OfflineOutboxSendTest.php` (a signed reaction registers before sending).
- Earlier design: [offline Outbox cycle](../offline_outbox/offline_outbox_step4_implementation_summary.md), [Offline Support Roadmap](offline_support_roadmap.md).
