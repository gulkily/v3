# Post Public Key Link Step 3 Development Plan

## Stage 1
- Goal: Expose the author’s canonical public-key file as a conditional link in shared post identity details.
- Dependencies: Approved Step 2; existing `author_public_key`, `author_identity_id`, canonical source-path validation, and `/source/current/` or `/source/blob/` routes.
- Expected changes: Derive the public-key source path for posts with a resolved author key; pass the link data through the existing post rendering context; extend the shared identity-details partial without changing profile rendering.
- Verification approach: Render a signed fixture post in a thread and single-post view; confirm the link target uses the expected canonical public-key path and remains escaped.
- Risks or open questions:
  - Key filename casing must match the repository’s existing uppercase/lowercase fallback behavior.
  - Posts without a resolvable key must remain unchanged.
- Canonical components/API contracts touched: `Application` post hydration; `templates/partials/post_card.php`; `templates/partials/thread_root_card.php`; `templates/partials/post_identity_details.php`; existing source-file routes.

## Stage 2
- Goal: Lock down presence and absence behavior with focused regression coverage.
- Dependencies: Stage 1 link data and shared partial behavior.
- Expected changes: Add assertions for the link in thread and single-post renders, plus assertions that unsigned/keyless posts do not expose a public-key link; retain existing profile and source-link expectations.
- Verification approach: Run the focused post-rendering tests, relevant application smoke tests, PHP syntax checks, and `git diff --check`.
- Risks or open questions:
  - Fixture coverage may require using the existing parity public-key record rather than creating new identity data.
- Canonical components/API contracts touched: `LocalAppSmokeTest` post-rendering coverage; no database schema or public API changes.
