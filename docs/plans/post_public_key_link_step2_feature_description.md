# Post Public Key Link Step 2 Feature Description

## Problem

Users viewing a signed post need a direct way to inspect the author’s public key file. The link should appear when a public key is available and remain absent for anonymous, unsigned, or legacy posts without one.

## User stories

- As a reader, I want to open the author’s public key from a post so that I can independently inspect the signing key.
- As a reader of an unsigned or legacy post, I want no misleading key link so that the post’s verification state remains clear.
- As a maintainer, I want the link rendered through the existing post identity component so that thread and single-post views stay consistent.

## Core requirements

- Show a public-key link in post metadata when the post has an available author public key.
- Use the canonical public-key file as the link target and preserve existing escaping and access behavior.
- Do not show the link when the author key is unavailable or the post has no resolved author identity.
- Keep the change limited to a link; do not add verification panels, trust judgments, or new key-management workflows.

## Shared component inventory

- `templates/partials/post_card.php` — canonical post rendering surface used by thread and single-post views; reuse its existing identity-details inclusion.
- `templates/partials/post_identity_details.php` — current shared identity-details component; extend it to expose the conditional link rather than creating a parallel post-specific component.
- `templates/partials/thread_root_card.php` — thread-root rendering surface that already reuses the identity-details component; preserve the same behavior there.
- `templates/pages/profile.php` — existing full public-key display; leave unchanged because it serves profile inspection rather than post-level discovery.

## Simple user flow

1. A reader opens a thread or individual post page.
2. The page shows the author’s public-key link when a key is available.
3. The reader follows the link to inspect the canonical public-key file.
4. Posts without an available key show no link.

## Success criteria

- Signed fixture posts expose one working public-key link in both thread and single-post views.
- Unsigned or keyless posts expose no public-key link.
- The link resolves to the expected canonical public-key file without changing post content or profile behavior.
- Existing post and identity rendering tests remain passing.
