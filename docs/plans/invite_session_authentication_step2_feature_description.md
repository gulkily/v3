# Invite Session Authentication: Step 2 Feature Description

## Problem

On a public instance, an approved member with a saved browser key is not consistently recognized after their server session expires. The Account page can identify them, but invitations and ordinary navigation cannot reliably use their authenticated state.

## User stories

- As an approved member, I want my saved browser key to restore my sign-in on my next normal page visit so that I can continue using the site after a browser restart or expired session.
- As an approved member, I want Invite to appear and work consistently so that I can issue invitations without first visiting Account.
- As an anonymous reader, I want to browse the public site without receiving an unnecessary sign-in session or cookie.
- As a member whose key is unavailable or whose approval is pending, I want a clear recovery path so that I know how to proceed.
- As a site operator, I want invitation authorization to remain based on cryptographic proof and server-side session state so that a browser identity hint cannot grant invitation authority.

## Core requirements

- Existing authenticated sessions are recognized across public application pages without creating sessions for visitors who have no session.
- A safe public-page visit by an approved member with a usable saved browser key restores authentication automatically when no valid session exists.
- Invitation pages and invitation actions use the restored authenticated session and retain their existing browser-signing and server-side signature verification before publication.
- Invite navigation is consistent with authenticated state; direct invitation URLs provide an actionable recovery outcome when authentication cannot be restored.
- Automatic restoration must not replay writes, API requests, downloads, or form submissions; those actions require an intentional retry after authentication.

## Shared component inventory

- **Viewer session and request boundary** (`Application`): extend the canonical session lifecycle and authenticated-viewer resolution for public requests; do not create a parallel authentication state.
- **Browser-key authentication** (`private_site_auth.js`, `/api/auth_challenge`, `/api/authenticate_identity`, `/api/auth_status`): reuse the existing proof-of-key-possession flow to restore a session; no new credential type is introduced.
- **Public navigation** (`TemplateRenderer` navigation and `auth_navigation.js`): extend the canonical navigation state and recovery behavior so Invite accurately reflects authentication.
- **Account and recovery surfaces** (`account_key.php`, existing feedback/recovery rendering): reuse the established key-setup and authentication-failure guidance when automatic restoration is unavailable.
- **Invitation flow** (`/invites/`, invitation APIs, `invite_navigation.js`, `invite_issuance.js`): reuse the existing signed invitation workflow; make it consume the shared authenticated state rather than identity-hint recognition.

## Simple user flow

1. A returning approved member opens an ordinary public page.
2. If a valid session exists, the site recognizes it; if not, the browser automatically proves possession of its saved key and receives a new session.
3. The page and navigation reflect the restored approved-member state, including Invite.
4. The member opens Invite and creates a signed invitation through the existing verification flow.
5. An anonymous, unconfigured, pending, or failed-authentication browser remains safe and receives the appropriate public or recovery experience.

## Success criteria

- Automated coverage proves that a valid existing session is recognized on ordinary public pages and invitations.
- An approved browser with a usable saved key regains a session on a normal page visit and can subsequently issue an invitation without visiting Account.
- Anonymous public visitors receive no new session solely from reading public pages.
- A missing, invalid, pending, or unusable key cannot issue an invitation and receives a clear recovery path.
- Invitation records remain signed in the browser and verified by the server before publication; no automatic recovery replays a write or API request.
