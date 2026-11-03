# Viewer session

Purpose: Remember which identity a browser signed in as, so every request from it is recognized without signing again.
Scenario: a browser with a saved key fetches a single-use challenge, signs it and posts it to `/api/authenticate_identity`; the server verifies the signature against the published key and sets a session cookie. Every later request with that cookie is recognized on any route, for 400 days, until the member signs out (`/api/clear_identity`), which ends the session on the server and keeps the identity only as the Lobby's weaker hint. Approval is checked against the current profile on each request; a session is never a substitute for signing an action.
Sources: [store](../../src/ForumRewrite/Session/ViewerSessionStore.php), [per-request resolver](../../src/ForumRewrite/Session/ViewerSessions.php), [cookies](../../src/ForumRewrite/Session/ViewerSessionCookie.php), [sign-in API](../../src/ForumRewrite/Http/AuthApiController.php), [sign-out](../../src/ForumRewrite/Http/IdentityHintController.php), [browser sign-in](../../public/assets/private_site_auth.js), [reference](../reference/authentication_modes.md#session-persistence-and-recovery).

Cookies are per scheme (`__Host-v3_session` over https, `v3_session` over http) so neither blocks the other, and the store keeps only token hashes in `state/private/`, outside exports. Challenges are not tied to a browser: a captured challenge and signature can be used once by whoever submits first, which over http is no worse than taking the cookie. Losing the store or a cookie signs browsers out; those with a saved key sign back in on their next page load.

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*
