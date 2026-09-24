# Session Reauthentication: Step 2 Feature Description

## Problem

When the server loses an approved member's PHP session, protected navigation sends them to Lobby or an unrecoverable access page before the browser can silently prove its saved identity. The original destination is lost, and recovery may require a reload.

## User stories

- As an approved member, I want an expired session to restore silently so that I can keep using the site without being diverted to Lobby.
- As an approved member, I want to return to the exact page and query I requested so that reauthentication does not interrupt my task.
- As a member without a usable browser key, I want a clear recovery path so that I understand how to regain access.
- As a site operator, I want safe recovery boundaries so that expired authentication never replays writes or creates an open redirect.

## Core requirements

- Safe protected HTML GET navigation must retain a validated, same-site return destination, including its query string.
- Successful browser-key reauthentication must resume the destination without a manual reload or visible Lobby detour.
- Recovery must work consistently for Board, Threads, profiles, and other protected HTML routes.
- Existing account setup, pending-approval, authentication failure, and offline states must remain clear and actionable.
- POSTs, downloads, and API/write requests must not be automatically resumed or replayed; unsafe return targets must fall back safely.

## Shared component inventory

- **Member access gate** (`Application` members-only routing): extend the canonical gate so all protected HTML routes use one recovery outcome; retain distinct API and write-request handling.
- **Private-site authentication** (`private_site_auth.js`): extend the existing challenge/signature authentication surface to resume a supplied destination rather than defaulting to Board.
- **Lobby and Account Key pages** (`lobby.php`, `account_key.php`): reuse their existing authentication-status surface for setup and failure recovery; Lobby remains the destination for genuinely pending/unconfigured members, not normal approved-member recovery.
- **Access-required message page** (`message.php`): replace its non-recovering approved-member experience with the shared recovery experience; retain it for states that cannot recover automatically.
- **Authentication APIs** (`/api/auth_challenge`, `/api/authenticate_identity`, `/api/clear_identity`): reuse the established identity proof and sign-out contract; no new authentication proof mechanism is introduced in this feature.

## User flow

1. A member follows a protected link after their server session has expired.
2. The site retains the safe original destination and begins silent browser-key authentication.
3. If authentication succeeds, the member arrives at the original destination with an active session.
4. If the key is unavailable, approval is pending, or authentication fails, the member sees an actionable recovery state that retains the destination where safe.
5. Write and API requests receive their normal explicit failure response and require intentional retry after authentication.

## Success criteria

- Automated coverage proves exact return for expired-session Board, thread, profile, and query-string navigation.
- An approved member with a usable saved key neither sees Lobby nor needs a manual reload during recovery.
- Invalid return destinations cannot navigate off-site, and no POST/API request is automatically replayed.
- Existing pending, unconfigured, failed, and offline recovery states remain understandable and reachable.
- Reauthentication latency and outcome can be observed without recording keys, signatures, identity values, or session tokens.
