# Session Reauthentication Reference

**Status: implemented behavior with historical design context.** For current settings, access exceptions, recovery instructions, and source/test evidence, use [Authentication Modes and Access](../reference/authentication_modes.md). This document preserves the earlier problem and alternatives; it is not a list of supported authentication modes or an assertion of production verification.

## Current Implementation — Reviewed 2026-10-13

- **PHP persistence:** `Application::startViewerSession()` configures a persistent 400-day cookie. The application imposes no separate idle/absolute session timeout, but cookie lifetime does not guarantee host-side session retention. Approval remains a separate check against the current profile.
- **Resume document:** `Application::renderAuthenticationResumePage()`, `ResumeTarget::fromRequestUri()`, `templates/pages/authentication_resume.php`, and `public/assets/private_site_auth.js` implement challenge-signature recovery for eligible protected HTML GETs without an authenticated profile. Missing keys and pending approval take recovery paths; they do not receive member content.
- **Navigation guard is implemented:** `public/assets/auth_navigation.js` checks status and reauthenticates before eligible same-origin link navigation. `TemplateRenderer` installs it for approved viewers with members-only access. `tests/AuthNavigationTest.php` covers active/expired sessions and the dedicated invitation-link exception. Its coverage is not a claim that all browser/tab combinations were tested.
- **Public-session resumption:** `Application::shouldResumeViewerSession()`/`resumeViewerSession()` resume existing PHP sessions on eligible public requests, including invitation preparation. Separately, configured public pages can opportunistically authenticate a saved browser key; keyless public browsing does not require login.
- **Historical alternatives:** application-owned opaque sessions and stateless login tokens below were not selected. No migration to them is part of the current behavior.

## Original Problem Context — Historical

The original report concerned requests arriving after a PHP session record expired or was cleaned up, before browser JavaScript could restore authentication. It described Board/Threads redirects to Lobby and non-recovering access-required pages elsewhere, with access restored only after Lobby authentication and a reload. This describes the earlier problem, not the implemented recovery above.

The original goals were:

- Return an approved member to the exact original destination after silent reauthentication.
- Perform reauthentication without showing Lobby or requiring a manual reload whenever possible.
- Evaluate replacing the PHP-managed authentication session with an application-controlled alternative.

## Chosen Persistence Policy

- A saved browser key is the durable sign-in authority for this low-stakes social-board experience; the application imposes no idle or absolute sign-in timeout.
- The PHP viewer-session cookie is persistent across browser restarts (subject to browser cookie-retention policies). If its server-side session record is gone, a usable saved browser key can restore the session through the resume flow; member content still requires approval.
- No separate feature flag is needed: this behavior is part of the existing approved-members access experience.
- Approval-removal design was outside the original cycle. Access checks use the current profile rather than treating the cookie as a permanent approval grant.

## Recovery Constraints

- A cold navigation (bookmark, pasted URL, new tab, or a dormant tab's first request) reaches the server before client code can authenticate. It cannot be recovered entirely in the background.
- A protected document must not be served before the server recognizes the member.
- Automatically replaying POSTs or write/API requests is unsafe; recovery applies only to safe HTML GET navigations.
- Return targets must be same-origin relative paths. Preserve path and query string; preserve a fragment only when an existing document can capture it.
- Authentication failure, missing browser keys, network loss, pending approval, and concurrent tabs need explicit recovery behavior.

## Implemented Recovery Experience

1. An eligible protected HTML GET without an authenticated profile receives a small authentication-resume document. API/download/RSS requests are excluded; an authenticated but unapproved viewer follows access-required/Lobby rules instead.
2. The resume document carries a validated `return_to` target and silently signs an authentication challenge using the existing browser key.
3. Successful authentication of an approved member uses `location.replace(return_to)`, so the resume page does not remain in browser history and the original target loads with a valid session.
4. If automatic recovery cannot proceed, the page offers Account/Lobby recovery while retaining the validated target.
5. On an approved members-only page, the installed navigation guard checks session status before eligible same-origin link navigation. If needed, it authenticates before proceeding. Guard exceptions and failure recovery are documented in the current reference.

## Session Strategy Options — Historical Assessment

The following options record the earlier tradeoff discussion, not selectable runtime modes. PHP sessions were retained; the application does not establish its own server-side retention guarantee.

### Configure PHP sessions explicitly

Set a deliberate cookie lifetime and server-side retention policy instead of inheriting PHP's short file-session defaults. This is the smallest change, but it remains coupled to deployment-level cleanup and does not provide application-level session inspection or revocation.

### Application-owned opaque authentication sessions

Use a random, HttpOnly, Secure, SameSite cookie whose hash identifies a server-side session record. The record carries identity, issuance, last-use, idle and absolute expiration, rotation, and revocation data. Store authentication challenges in the same controlled persistence layer. This gives the application explicit lifecycle control without placing identity or authorization claims in the cookie.

### Full client-side/stateless credential

Avoid a server session lookup by signing or encrypting authorization claims into a token. This makes revocation, approval changes, expiry, and token theft handling materially harder. It is not recommended for this private-member flow.

## Suggested Sequencing — Historical Proposal

Retained for design history, not as a current implementation backlog. The resume document, navigation guard, and explicit cookie policy now exist; the conditional session replacement remains an unselected alternative.

1. Establish a validated return-target contract and the resume document using the current PHP session.
2. Add in-page navigation recovery and complete browser/server tests for all safe protected routes.
3. Decide and document explicit idle/absolute expiration and browser-restart policy.
4. If PHP lifecycle control remains inadequate, introduce application-owned opaque auth sessions behind a feature flag, support a temporary migration path, and then retire PHP authentication state.

## Acceptance Signals — Original Targets

These were the intended outcomes, not a record that every scenario passed. Current executed checks and remaining evidence limits are recorded in the [documentation implementation summary](./authentication_modes_documentation/authentication_modes_documentation_step4_implementation_summary.md).

- An expired session at a Board, thread, profile, or query-string URL returns to that URL after successful automatic authentication.
- Approved members do not see a Lobby flash or need to reload.
- Invalid return targets cannot create an open redirect.
- POSTs and APIs are not silently replayed.
- Desired coverage included session expiry, rotation, revocation, and multi-tab recovery; this historical target does not establish complete automated coverage.
