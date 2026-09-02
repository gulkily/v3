# Public-Key Bootstrap Integrity Step 1 Solution Assessment

## Problem Statement

Automatically created bootstrap posts are unsigned and hidden, which makes identity association, authorship verification, account completeness, and repeated setup attempts unclear to users.

## Option A: Make the bootstrap post a signed identity statement

Pros:
- Gives the bootstrap post direct cryptographic authorship evidence.
- Makes the post itself a meaningful identity artifact rather than an opaque anchor.
- Provides a clear basis for displaying “signed and verified” status.

Cons:
- Expands account creation into a coordinated key-and-post signing flow.
- Requires careful handling of exact canonical post content and failure recovery.
- Still needs separate UX for hidden/internal visibility and duplicate submissions.

## Option B: Keep the bootstrap post unsigned and label it explicitly as an internal anchor

Pros:
- Preserves the simple account-creation flow.
- Accurately separates profile/key association from post authorship verification.
- Allows focused UX improvements for hidden-post discovery and duplicate identity linking.

Cons:
- The bootstrap post can never provide cryptographic proof of authorship.
- “Anonymous unsigned” remains potentially confusing unless the status vocabulary changes.
- Visitors must understand that the identity record, not the post, carries the key association.

## Option C: Remove the automatic bootstrap post and use the identity record as the sole bootstrap artifact

Pros:
- Eliminates an unsigned post that resembles user content.
- Simplifies the distinction between identity data and authored posts.
- Avoids hidden-post counts and discoverability confusion.

Cons:
- Removes the stable post/thread target used by current profile and approval flows.
- Requires revisiting existing repository and read-model contracts.
- Does not by itself improve verification of the user’s later posts.

## Recommendation

Recommend Option A, paired with explicit UI states for identity association, key availability, approval, and signature verification; make repeat submissions of an existing fingerprint recoverable by linking to the existing profile.

Brief justification:
- The bootstrap post is already exposed as a profile-linked artifact, so signing it gives that artifact an honest, verifiable purpose. Clear labeling and a recoverable duplicate flow address the remaining confusion around hidden posts and account setup without conflating approval with cryptographic authorship.
