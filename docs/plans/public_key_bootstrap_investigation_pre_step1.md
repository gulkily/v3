# Public-Key Bootstrap Investigation — Pre-Step 1 Findings

## Confirmed Problems

- Automatically created bootstrap posts are unsigned. The account flow creates a generic bootstrap anchor before linking the public key, but does not create an adjacent `.txt.asc` signature.
- The resulting post is reported as `anonymous unsigned`, even though it is later associated with the user’s profile and public key in the read model. This makes the status easy to misinterpret.
- The bootstrap post itself does not contain the user’s OpenPGP identity ID or fingerprint. The association exists in the identity record’s `Bootstrap-By-Post` field rather than in the post content.
- A public key and a bootstrap-post association can therefore be mistaken for proof that the user authored the bootstrap post. They are not cryptographic proof of that post’s authorship.
- Bootstrap posts are marked `identity internal` and hidden from normal feeds/counts. A profile can consequently show zero posts/threads while still linking to a real bootstrap post, making the account appear incomplete.
- The account page’s advanced `Link identity` action returns `Identity already exists for this fingerprint` when the user resubmits the key for an already-created account. The message is technically correct but does not clearly guide the user to the existing profile or explain that setup is already complete.

## Existing Behavior That Works

- Account creation does create a canonical bootstrap post when no post ID is supplied.
- The identity record stores the bootstrap post/thread IDs and the armored public key.
- The profile page exposes the public key for both approved and unapproved profiles.
- The bootstrap post can now expose its associated public key under advanced details, but this does not add a signature.
- Ordinary signed-post verification remains possible when a post has an adjacent `.txt.asc` signature and the exact canonical post bytes are available.

## Scope for a Future Step 1

- Decide whether the bootstrap anchor should remain an unsigned structural record or become a signed identity statement.
- Decide how the UI should distinguish identity association, key availability, approval state, and cryptographic authorship verification.
- Decide whether duplicate identity-link submissions should become an idempotent success, a clearer recovery message, or remain an error.
- Decide how hidden bootstrap posts should be discoverable without presenting them as ordinary forum content.
