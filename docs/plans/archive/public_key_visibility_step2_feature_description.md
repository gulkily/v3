# Public Key Visibility Step 2 Feature Description

## Problem

Approved and unapproved profile pages already expose a user’s public key under advanced technical details, but a visitor viewing the user’s bootstrap post has no equivalent way to inspect that key. The feature fills in the missing post entry point while preserving the current profile presentation.

## User Stories

- As a visitor, I want to view a user’s public key from an approved profile so that I can inspect the identity material.
- As a visitor, I want to view a user’s public key from an unapproved profile so that approval status does not prevent key inspection.
- As a visitor, I want to view a user’s public key from their bootstrap post so that I can inspect the identity without first finding the profile page.
- As a visitor, I want missing-key states to be clear so that I do not mistake unavailable key data for a rendering failure.

## Core Requirements

- Keep the public key available under advanced technical details on approved profile pages.
- Keep the same public-key access available under advanced technical details on unapproved profile pages.
- Add equivalent public-key disclosure to the bootstrap post wherever that post is viewed.
- Use the public key associated with the post author and show a clear unavailable state when no key is present.
- Keep key visibility independent from approval status; displaying a key must not imply that the user is approved or trusted.

## Shared Component Inventory

- `templates/pages/profile.php`: canonical profile identity-details presentation; reuse unchanged for approved and unapproved profiles unless a small shared presentation extension is needed.
- `templates/pages/post.php`: standalone post page; extend its author identity details to include the public-key disclosure.
- `templates/partials/post_card.php`: reusable post card for non-root posts and standalone post rendering; extend the canonical post identity disclosure here so post views remain consistent.
- `templates/partials/thread_root_card.php`: bootstrap/root post presentation in thread views; reuse the same post-key disclosure behavior so the bootstrap post is covered when opened through its thread.
- Existing profile retrieval/API surfaces: the profile data already includes the public key for HTML profile rendering; no new public-key storage or separate key destination is required for this scope.

## Simple User Flow

1. Visitor opens an approved profile, unapproved profile, or bootstrap post.
2. Visitor opens the existing advanced technical-details section when viewing a profile or post.
3. Visitor selects or reads the Public key entry.
4. If no key is associated with the author, the page states that the public key is unavailable.

## Success Criteria

- A test or manual inspection confirms the public key is visible from an approved profile, an unapproved profile, and the bootstrap post.
- The key is available in the existing advanced-details pattern without being shown in the default post body or metadata.
- A missing-key fixture produces a clear unavailable state on both profile and post surfaces.
- Approved and unapproved examples expose equivalent key access, with no approval or trust claim introduced by the disclosure.
