# Chouse Approved-Members-Only Access — Step 4: Implementation Summary

## Stage 1 - Register access feature flag
- Changes:
  - Registered `FORUM_APPROVED_MEMBERS_ONLY` with a safe default of `false`.
  - Enabled the existing environment/site-record precedence and feature-flag listing for the new flag.
  - Added default, environment-override, and registry-list coverage.
- Verification:
  - `php tests/run.php FeatureFlagEvaluatorTest` — all tests passed.
- Notes:
  - Route behavior is not changed yet; later stages consume this flag.

## Stage 2 - Authenticate browser identity
- Changes:
  - Added a short-lived server challenge endpoint and detached OpenPGP
    signature verification endpoint.
  - Added browser-side authentication using the existing local browser keypair.
  - Added the authentication endpoints to the API index.
- Verification:
  - `php -l src/ForumRewrite/Application.php` — passed.
  - `node --check public/assets/private_site_auth.js` — passed.
  - Existing OpenPGP signature tests — passed.
- Notes:
  - The access gate is not enabled yet; Stage 3 will consume the authenticated
    session and enforce the lobby allowlist.

## Stage 3 - Enforce lobby allowlist
- Changes:
  - Added the shared `FORUM_APPROVED_MEMBERS_ONLY` access decision.
  - Added the `/lobby/` page.
  - Allowed lobby users only Lobby, Account, and their authenticated own
    profile; protected other dynamic routes return 404.
  - Approved users retain the existing full-site route access.
- Verification:
  - PHP/template lint passed.
  - Flagged direct thread request returned 404.
  - Flagged Lobby request rendered successfully.
  - `php tests/run.php FeatureFlagEvaluatorTest` — all tests passed.
- Notes:
  - Static artifact serving is addressed in Stage 4.
