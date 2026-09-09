# Post Public Key Link Step 4 Implementation Summary

## Stage 1 - Add conditional public-key link metadata
- Changes:
  - Added canonical public-key path and `/source/current/` link metadata during post hydration when the author identity and stored key resolve successfully.
  - Reused `post_identity_details.php` for thread-root, thread-post, and single-post rendering.
  - Preserved the existing bootstrap-post public-key details display and keyless-post behavior.
- Verification:
  - `php -l src/ForumRewrite/Application.php`
  - `php -l templates/partials/post_identity_details.php`
  - `php tests/run.php LocalAppSmokeTest::testPostAndActivityLinkAdjacentSignatureFiles`
  - Reflection smoke check confirmed the expected uppercase fixture key path and href.
  - `git diff --check` passed for the stage files.
- Notes:
  - The repository already validates and serves canonical public-key files through the existing source routes; no new route or schema change was needed.

## Stage 2 - Add signed and keyless rendering coverage
- Changes:
  - Added a smoke test that verifies no public-key link is shown for a keyless post.
  - Added assertions that the same canonical public-key link appears in both thread and single-post views when the author key is available.
- Verification:
  - `php -l tests/LocalAppSmokeTest.php`
  - `php tests/run.php LocalAppSmokeTest::testPostPagesExposeAvailableAuthorPublicKeyLink`
  - `php tests/run.php LocalAppSmokeTest::testPostAndActivityLinkAdjacentSignatureFiles`
  - `git diff --check` passed for the stage files.
- Notes:
  - Coverage uses the existing parity fixture and read-model profile data; no fixture or schema changes were required.

## Stage 3 - Move link to post details page
- Changes:
  - Removed the public-key link from shared thread and post-card identity details.
  - Added the conditional link to the individual post details header at `/posts/{post_id}`.
  - Kept the existing bootstrap-post technical key display unchanged.
- Verification:
  - Focused rendering coverage confirms the link is absent from the thread view and present once on the individual post view.
  - Existing keyless-post and adjacent-signature behavior remain covered.
- Notes:
  - This follows the clarified placement requirement without adding a new route or data contract.
