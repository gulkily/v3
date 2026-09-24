# Public Key Visibility Step 4 Implementation Summary

## Stage 1 - Expose author public keys to post views
- Changes:
  - Added the associated profile public key to standalone post and thread-post read paths.
  - Preserved nullable behavior for posts without a linked profile or public key.
  - Made no persistence, schema, or API endpoint changes.
- Verification:
  - `php -l src/ForumRewrite/Application.php` — passed.
  - `php tests/run.php` — all tests passed.
- Notes:
  - The data is now available to standalone post, thread-root, and reusable post-card presentation paths for Stage 2.

## Stage 2 - Add bootstrap-post key disclosure
- Changes:
  - Added a shared advanced technical-details partial for bootstrap-post public keys.
  - Integrated the disclosure into standalone post cards and thread-root cards.
  - Displays the armored key when present and `Public key unavailable.` when absent.
  - Limits the disclosure to root/bootstrap posts so ordinary replies are unchanged.
- Verification:
  - `php -l templates/partials/post_identity_details.php` — passed.
  - `php -l templates/partials/post_card.php` — passed.
  - `php -l templates/partials/thread_root_card.php` — passed.
  - `php tests/run.php` — all tests passed.
- Notes:
  - Profile rendering remains unchanged and continues to expose keys under advanced technical details for approved and unapproved profiles.

## Stage 3 - Verify public-key visibility
- Changes:
  - Added rendering assertions for the approved profile and linked bootstrap post in standalone and thread views.
  - Added approved and unapproved profile assertions around the approval transition.
  - Added a keyless-profile regression case for the bootstrap-post unavailable state.
- Verification:
  - `php -l tests/LocalAppSmokeTest.php` — passed.
  - `php tests/run.php` — all tests passed.
- Notes:
  - Coverage confirms approval state does not control public-key visibility.
