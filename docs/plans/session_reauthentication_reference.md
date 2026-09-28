# Session Reauthentication Reference

**Status: shipped.** This reads as an open design doc with unresolved
"Session Strategy Options," but the design here was implemented and is live:

- **Persistence policy**: the "Configure PHP sessions explicitly" option
  below was the one chosen (not the app-owned opaque session or
  stateless-token options) — a persistent, long-lived PHP session cookie
  (`Application::startViewerSession()`, `PERSISTENT_VIEWER_SESSION_COOKIE_LIFETIME`),
  no idle/absolute timeout, matching "Chosen persistence policy" above.
- **Recommended Experience** (steps 1-4): shipped as
  `Application::renderAuthenticationResumePage()` /
  `ResumeTarget::fromRequestUri()` (validated same-origin `return_to`),
  `templates/pages/authentication_resume.php`, and
  `public/assets/private_site_auth.js` (silently signs `/api/auth_challenge`
  via the saved browser key, calls `/api/authenticate_identity`, then
  `location.replace()`s to the original target). Step 5 (an in-page
  navigation guard) was not found in the current codebase — appears
  unimplemented, but the doc marks it optional ("If needed").
- `Application::shouldResumeViewerSession()`/`resumeViewerSession()` are a
  separate, complementary mechanism (proactively starting/cleaning up the
  PHP session on a wider set of GET routes even when approved-members-only
  is off), not the resume-document flow above.

## Context

Approved-members-only access currently depends on PHP's file-backed session. If its server-side record has expired or been cleaned up, a request is rejected before browser JavaScript can use the browser's saved OpenPGP key to restore authentication. Board and Threads requests redirect to Lobby; other protected HTML routes receive a non-recovering access-required page. Reloading after the Lobby script finishes works because the script has recreated the session.

The immediate user goals are:

- Return an approved member to the exact original destination after silent reauthentication.
- Perform reauthentication without showing Lobby or requiring a manual reload whenever possible.
- Evaluate replacing the PHP-managed authentication session with an application-controlled alternative.

## Chosen persistence policy

- A saved browser key is the durable sign-in authority for this low-stakes social-board experience; the application imposes no idle or absolute sign-in timeout.
- The PHP viewer-session cookie is persistent across browser restarts (subject to browser cookie-retention policies). If its server-side session record is gone, the existing browser key silently restores access through the resume flow.
- No separate feature flag is needed: this behavior is part of the existing approved-members access experience.
- Removing approval is intentionally outside this cycle. Authorization continues to be checked against the current profile on each request.

## Constraints

- A cold navigation (bookmark, pasted URL, new tab, or a dormant tab's first request) reaches the server before client code can authenticate. It cannot be recovered entirely in the background.
- A protected document must not be served before the server recognizes the member.
- Automatically replaying POSTs or write/API requests is unsafe; recovery applies only to safe HTML GET navigations.
- Return targets must be same-origin relative paths. Preserve path and query string; preserve a fragment only when an existing document can capture it.
- Authentication failure, missing browser keys, network loss, pending approval, and concurrent tabs need explicit recovery behavior.

## Recommended Experience

1. An unauthenticated safe HTML GET receives a small authentication-resume document, not the Lobby page or a plain 403.
2. The resume document carries a validated `return_to` target and silently signs an authentication challenge using the existing browser key.
3. Success uses `location.replace(return_to)`, so the resume page does not remain in browser history and the original target is loaded with a valid server session.
4. If automatic recovery cannot proceed, the page offers Account/Lobby recovery while retaining the validated target.
5. On an already loaded page, a navigation guard may check session status before following same-origin protected links. If needed, it authenticates first and then follows the original link, avoiding even the resume document.

## Session Strategy Options

### Configure PHP sessions explicitly

Set a deliberate cookie lifetime and server-side retention policy instead of inheriting PHP's short file-session defaults. This is the smallest change, but it remains coupled to deployment-level cleanup and does not provide application-level session inspection or revocation.

### Application-owned opaque authentication sessions

Use a random, HttpOnly, Secure, SameSite cookie whose hash identifies a server-side session record. The record carries identity, issuance, last-use, idle and absolute expiration, rotation, and revocation data. Store authentication challenges in the same controlled persistence layer. This gives the application explicit lifecycle control without placing identity or authorization claims in the cookie.

### Full client-side/stateless credential

Avoid a server session lookup by signing or encrypting authorization claims into a token. This makes revocation, approval changes, expiry, and token theft handling materially harder. It is not recommended for this private-member flow.

## Suggested Sequencing

1. Establish a validated return-target contract and the resume document using the current PHP session.
2. Add in-page navigation recovery and complete browser/server tests for all safe protected routes.
3. Decide and document explicit idle/absolute expiration and browser-restart policy.
4. If PHP lifecycle control remains inadequate, introduce application-owned opaque auth sessions behind a feature flag, support a temporary migration path, and then retire PHP authentication state.

## Acceptance Signals

- An expired session at a Board, thread, profile, or query-string URL returns to that URL after successful automatic authentication.
- Approved members do not see a Lobby flash or need to reload.
- Invalid return targets cannot create an open redirect.
- POSTs and APIs are not silently replayed.
- Session expiry, rotation, revocation, and multi-tab recovery are covered by automated tests.
