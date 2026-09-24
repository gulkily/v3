# Post Public Key Link Step 1 Solution Assessment

## Problem Statement

Users need post pages to show a link to the author's public key file when available. Source: `thread-20260826052458-b25eef3d`, submitted 2026-08-26T05:24:58Z.

## Option A: Add a public-key link to post metadata

Pros:
- Directly visible where the user expects it.
- Helps verify signed authorship.
- Small user-facing addition.

Cons:
- Could clutter compact post metadata.
- Needs clear absence behavior for anonymous or legacy unsigned posts.

## Option B: Link only from profile pages

Pros:
- Keeps post pages cleaner.
- Groups identity details in one place.

Cons:
- Adds an extra click from the post.
- Does not satisfy the requested page-level visibility.

## Option C: Add a verification details panel

Pros:
- Can include signature, key, and trust status together.
- More complete identity audit surface.

Cons:
- Larger UI scope than a link.
- Needs more product decisions.

## Recommendation

Recommend Option A.

Brief justification:
- A direct public-key link on signed post pages solves the immediate verification need without building a larger identity panel first.
