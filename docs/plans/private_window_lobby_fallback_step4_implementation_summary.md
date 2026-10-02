# Private-Window Lobby Fallback: Step 4 Implementation Summary

## Stage 1 - Route a missing browser key to Lobby
- Changes:
  - Extended the existing browser identity recovery absent-key outcome to replace a protected-page recovery view with Lobby.
  - Preserved the validated requested destination in Lobby's existing `return_to` contract.
- Verification:
  - Ran `node --check public/assets/private_site_auth.js` successfully.
  - Ran a Node browser-context smoke check with no saved key and `/threads/root-001?view=full`; it returned `not-configured`, made no authentication request, and replaced the view with `/lobby/?return_to=%2Fthreads%2Froot-001%3Fview%3Dfull`.
- Notes:
  - The fallback applies only when no usable key is available; real authentication errors remain on the existing visible-error path.
