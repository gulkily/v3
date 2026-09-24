# iOS Users Page Cache Freshness Step 2 Feature Description

## Problem

Users can see stale cached content on the approved users page after user/profile state changes, with iOS reported as the affected client. The users page should preserve the static-page performance model while reliably showing fresh approved-user state for all clients after relevant changes.

## User Stories

- As a visitor on any client, I want the users page to reflect current approved-user data so that I can trust the directory.
- As an approved user, I want approval-related changes to appear on the users page without waiting on stale cached HTML so that newly visible users are discoverable.
- As a site maintainer, I want users-page freshness handled through the existing cache/version model so that the behavior remains consistent with other static pages.

## Core Requirements

- The approved users page must not serve stale approved-user listings after profile approval or visible-content changes affect the directory.
- The solution must preserve cache benefits for ordinary anonymous, queryless users-page visits.
- iOS browsers, desktop browsers, and embedded webviews must receive the same freshness behavior for the users page.
- Pending approval access and behavior must remain restricted to approved users.
- Existing version notification behavior must continue to work for cached pages.

## Shared Component Inventory

- Approved users directory (`/users/`): reuse and extend the canonical server-rendered users page and its approved-user data source.
- Pending users directory (`/users/pending/`): reuse unchanged as the authenticated approval workflow; only its impact on approved users-page freshness is in scope.
- User profile links (`/user/{username}` and `/profiles/{profile_slug}`): reuse existing profile/username surfaces; no new user-card component is needed.
- Static HTML artifacts for `/users/`: reuse the existing static artifact serving/building flow and tighten freshness around this route.
- Static artifact invalidation: extend the existing invalidation surface where users-page content changes are already implied.
- Version freshness check (`/api/version` and browser reload banner): reuse unchanged unless Step 3 confirms it is the freshness boundary for this route.

## Simple User Flow

1. A visitor opens `/users/` from any browser or webview.
2. The page loads quickly through the existing cached/static route when valid.
3. A profile approval or visible-content change updates the data that belongs in the directory.
4. The next valid users-page view reflects the changed directory state.
5. If a browser already has outdated page content open, the existing version freshness experience prompts recovery.

## Success Criteria

- After an approval or visible-content change, `/users/` no longer returns an obsolete users listing from static HTML.
- The freshness behavior is route/data driven, not dependent on user-agent-specific handling.
- Static serving for `/users/` still works when the artifact is current.
- `/users/pending/` remains uncached for anonymous static serving and remains unavailable to unapproved visitors.
- Existing smoke coverage confirms the users page, pending users page, static users artifact, and version freshness behavior still pass.
