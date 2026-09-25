# Public Key Visibility Step 3 Development Plan

## Stage 1
- Goal: Make the profile author’s public key available to every bootstrap-post rendering path.
- Dependencies: Approved Step 2; existing profile-to-post identity association.
- Expected changes: Extend the post read data used by standalone posts, thread posts, and thread-root posts to carry the associated public key and an explicit missing-key state; do not change persistence or add a migration.
- Verification approach: Exercise representative signed and keyless post fixtures and confirm the rendered view receives the expected key or absence state.
- Risks or open questions:
  - Posts with no linked profile must remain renderable.
  - Existing author label and approval behavior must not change.
- Canonical components/API contracts touched: Existing post/profile read model queries and `fetchPost`/thread-post retrieval paths; no public API contract change.

## Stage 2
- Goal: Display the author’s public key in advanced technical details on bootstrap posts.
- Dependencies: Stage 1 data availability.
- Expected changes: Add or extend a shared post identity-details presentation used by the standalone post page, reusable post card, and thread-root card; show the armored key when available and a clear unavailable message otherwise; retain the existing profile advanced-details presentation for approved and unapproved profiles.
- Verification approach: Inspect standalone bootstrap-post, thread bootstrap-post, and ordinary-post renderings; confirm the key is not placed in default post body/metadata and approval status remains independent.
- Risks or open questions:
  - Avoid duplicating divergent markup across post surfaces.
  - Keep large key material contained within the existing advanced-details interaction.
- Canonical components/API contracts touched: `templates/pages/post.php`, `templates/partials/post_card.php`, `templates/partials/thread_root_card.php`, and the shared partial created or extended for post identity details.

## Stage 3
- Goal: Add regression coverage for complete public-key access.
- Dependencies: Stages 1–2.
- Expected changes: Add smoke or focused rendering assertions for approved profiles, unapproved profiles, standalone bootstrap posts, thread bootstrap posts, and keyless authors; cover the public-key label/content and unavailable state.
- Verification approach: Run the targeted PHP test suite and perform a manual browser check of all three requested entry points.
- Risks or open questions:
  - Test fixtures must distinguish approval state from key presence.
- Canonical components/API contracts touched: Existing application smoke/render tests; no new API endpoint required.
