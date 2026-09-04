# Public Key Visibility Step 1 Solution Assessment

## Problem Statement

Visitors should be able to view a user’s public key(s) from an approved profile, an unapproved profile, or that profile’s bootstrap post.

## Current Availability

- Approved profile: available under **Advanced / technical details → Public key**.
- Unapproved profile: available in the same shared profile template and location.
- Bootstrap post: not currently available from the post view; the post exposes author identity/approval context but not the associated profile key.

## Option A: Add the existing advanced-details disclosure to bootstrap posts

Pros:
- Completes the requested access path with the smallest user-facing change.
- Preserves the existing approved/unapproved profile behavior and placement.
- Keeps the full armored key out of the default post presentation.

Cons:
- Repeats technical identity details on the bootstrap post.
- Requires a clear empty state for posts whose author has no linked public key.

## Option B: Add a dedicated public-key page and link to it everywhere

Pros:
- Creates one canonical place for one or more public keys.
- Keeps both profiles and posts compact.

Cons:
- Adds a new navigation destination for a gap that is currently limited to bootstrap posts.
- Requires deciding how the destination represents approval and missing/legacy keys.

## Option C: Leave profiles unchanged and add only a fingerprint/link on bootstrap posts

Pros:
- Keeps bootstrap posts compact.
- Provides a quick identity reference in post context.

Cons:
- Does not satisfy the request to view the public key from the bootstrap post itself.
- Creates different disclosure behavior between profiles and posts.

## Recommendation

Recommend Option A: retain the public key under advanced details on both approved and unapproved profiles, and add the same kind of advanced-details disclosure to the bootstrap post view.

Brief justification:
- The profile requirement is already satisfied. Extending the existing presentation to bootstrap posts completes the missing entry point with minimal scope, no need to redesign profile navigation, and no implication that approval itself validates the cryptographic key.
