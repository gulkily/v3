# Viewer session

Purpose: Remember which identity a browser signed in as, so every request from it is recognized without signing again.
Scenario: a browser with a saved key fetches a single-use challenge, signs it and posts it to `/api/authenticate_identity`; the server verifies the signature against the published key and sets a session cookie. Every later request with that cookie is recognized on any route, for 400 days, until the member signs out (`/api/clear_identity`), which ends the session on the server and keeps the identity only as the Lobby's weaker hint. Approval is checked against the current profile on each request; a session is never a substitute for signing an action.
Sources: [store](../../src/ForumRewrite/Session/ViewerSessionStore.php), [per-request resolver](../../src/ForumRewrite/Session/ViewerSessions.php), [cookies](../../src/ForumRewrite/Session/ViewerSessionCookie.php), [sign-in API](../../src/ForumRewrite/Http/AuthApiController.php), [sign-out](../../src/ForumRewrite/Http/IdentityHintController.php), [browser sign-in](../../public/assets/private_site_auth.js), [reference](../reference/authentication_modes.md#session-persistence-and-recovery).

Member-only tools given without a signature (agent reply and expansion requests, the full post analysis, LLM Exchanges) ask for this session and an approved profile, through `RouteServices::signedInApproved`; the identity hint cookie alone never passes. On a public site, a page whose viewer is known only from the hint asks the browser to sign in, as a page with no viewer does ([browser check](../../tests/browser/hint_only_resume.mjs)).

Cookies are per scheme (`__Host-v3_session` over https, `v3_session` over http) so neither blocks the other, and the store keeps only token hashes in `state/private/`, outside exports. Challenges are not tied to a browser: a captured challenge and signature can be used once by whoever submits first, which over http is no worse than taking the cookie. Losing the store or a cookie signs browsers out; those with a saved key sign back in on their next page load.

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*

*— Revised by Claude · 2026-11-15 UTC · Model: claude-fable-5-2*

Key bootstrap: each session also keeps the coarse [device fingerprint](../../src/ForumRewrite/Session/DeviceFingerprint.php) of the browser that signed in, and whether it already vouched for a new key. A browser that lost its key but kept its session asks the session for a new key under the same username; a matching device makes it approved, once per session. Browsers with a key re-sign once so older sessions gain a description. Sources: [bootstrap API](../../src/ForumRewrite/Http/KeyBootstrapApiController.php), [browser script](../../public/assets/key_bootstrap.js), [record spec](../specs/key_bootstrap_record_v1.md).

*— Revised by Claude · 2026-11-16 UTC · Model: claude-opus-5-5*
