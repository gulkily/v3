# Forte Session-Recovery Routes: Step 2 Feature Description

## Problem

Valid Forte URLs can be classified as nonexistent when the member session is absent, preventing the existing reauthentication-recovery experience. After another page restores authentication, the same Forte URL works normally.

## User stories

- As an approved member, I want a dormant Forte link to recover my session so that I can continue where I intended without seeing a false missing-route page.
- As a Forte user, I want board, directory, activity, profile, and username links to behave consistently so that authentication state does not change navigation results.
- As a site operator, I want Forte API requests to receive an authorization outcome rather than a false 404 so that clients can recover predictably.

## Core requirements

- Treat every `/forte` path and Forte API path as an application route during members-only access checks.
- Route expired-session Forte HTML navigation through the existing authentication-resume experience with its original path and query retained.
- Give expired-session Forte API requests the existing explicit authorization outcome; do not replay them automatically.
- Preserve normal authenticated Forte routing and normal post-authentication 404 behavior for genuinely invalid URLs.
- Do not alter Forte content, approval policy, or browser-key authentication.

## Shared component inventory

- **Members-only route gate** (`Application` application-route classification): extend the canonical classifier with the approved Forte-prefix rule.
- **Authentication-resume page and private-site auth**: reuse unchanged for Forte HTML GET recovery.
- **Forte HTML handlers** (board, users, activity, profiles, usernames): reuse unchanged once the gate recognizes their URL space.
- **Forte JSON APIs** (activity pagination, commit detail, content summary): reuse unchanged; extend only their access classification so they retain the canonical API authorization response.

## User flow

1. A member opens a Forte URL after their server session is unavailable.
2. The access gate recognizes it as a Forte application route.
3. A safe Forte HTML request uses existing reauthentication recovery and returns to the same URL.
4. A Forte API request reports its normal authorization outcome for the caller to handle.
5. Once authenticated, a valid URL renders normally; an invalid URL remains a normal 404.

## Success criteria

- Expired-session Forte board, directory, activity, profile, and username URLs no longer show the false missing-route page.
- Their original path and query are retained through recovery.
- Forte API calls no longer return false 404s solely because the session is absent.
- Authenticated valid and invalid Forte URLs retain their current outcomes.
