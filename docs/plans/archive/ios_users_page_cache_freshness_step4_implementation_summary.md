# iOS Users Page Cache Freshness Step 4 Implementation Summary

## Stage 1 - Users Artifact Invalidation
- Changes:
  - Extended `StaticArtifactInvalidator` so users-page artifacts are deleted for board-thread, reply, profile, identity-link, and approval invalidations.
  - Added smoke coverage that each directory-affecting invalidation removes `/users.html` and `/users/index.html` from both public and alternate static roots.
- Verification:
  - `php -l src/ForumRewrite/Write/StaticArtifactInvalidator.php` passed.
  - `php -l tests/LocalAppSmokeTest.php` passed.
  - `php tests/run.php --filter LocalAppSmokeTest` passed.
- Notes:
  - The invalidation remains route/data driven and does not add user-agent-specific behavior.

## Stage 2 - Static Users Route Preservation
- Changes:
  - Added regression coverage that both `/users/` and `/users` serve the same current static HTML artifact.
  - Left front-controller and artifact-builder route eligibility unchanged.
- Verification:
  - `php -l tests/LocalAppSmokeTest.php` passed.
  - `php tests/run.php --filter LocalAppSmokeTest` passed.
- Notes:
  - `/users/` remains eligible for anonymous, queryless static serving when the artifact is current.

## Stage 3 - Client-Agnostic Freshness Verification
- Changes:
  - Completed verification-only stage; no additional production code was needed.
- Verification:
  - `php tests/run.php --filter LocalAppSmokeTest` passed.
  - `php tests/run.php --filter VersionCheckBehaviorTest` passed.
  - `git diff b43ca9b..HEAD -- src tests | rg -n "iOS|iPhone|HTTP_USER_AGENT|User-Agent|user_agent|navigator\\.userAgent"` returned no matches.
- Notes:
  - Pending users behavior remains covered by the local smoke test.
  - The implementation is route/data driven and does not branch on client type.
