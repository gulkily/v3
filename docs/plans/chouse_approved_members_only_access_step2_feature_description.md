# Chouse Approved-Members-Only Access — Step 2: Feature Description

## Problem

Chouse is intended to be a private approved-members site. Until a user is
approved, the site must provide only the minimum account/profile experience
needed to identify themselves and explain how access is obtained.

## User Stories

- As an unapproved user, I want to see a lobby so that I understand why the
  site is restricted and what I need to do next.
- As an unapproved user, I want to access my Account page and linked profile
  so that I can verify the identity awaiting approval.
- As an approved user, I want the complete site to remain available so that
  approval grants membership without additional topic or label rules.
- As the site operator, I want every other route and feed blocked for
  unapproved users so that private content is not exposed through alternate
  URLs.

## Core Requirements

- A registered dedicated feature flag controls the behavior independently of
  site identity, theme, and branding; it is off by default and enabled only
  on instances intended to be private.
- A flagged instance has two access states: approved member or lobby-only
  user.
- Lobby-only users may access only the Lobby page, Account page, and their own
  linked profile; required authentication/approval actions must remain usable
  within that experience.
- Lobby-only users cannot access any other page, profile, thread, post, feed,
  API, tag, activity, tool, instance, backup, or generated content artifact.
- Approved members retain access to the complete existing site.
- Protected content must not be discoverable through direct URLs, RSS, APIs,
  static artifacts, or alternate route aliases; unauthorized content requests
  return 404.

## Shared Component Inventory

- `Application` route handling and existing approved-user resolution: extend
  the canonical access decision rather than adding per-page checks.
- `FrontController` static-artifact resolution: extend the existing request
  boundary so anonymous/static artifacts cannot bypass private access.
- Account/key page and profile rendering: reuse existing surfaces; constrain
  the profile link to the authenticated user’s own profile.
- Existing approval and pending-user flows: reuse their current identity and
  approval semantics within the lobby experience.
- Lobby page: add one new canonical page because no existing page expresses
  the private-site waiting state.

## Simple User Flow

1. A visitor arrives at chouse and identifies themselves with the supported
   browser key/account flow.
2. The site determines whether that identity is approved.
3. An unapproved identity is shown the Lobby and can open only Account and
   the linked own profile.
4. An approved identity enters the normal site and can use all existing
   pages and feeds.
5. Requests for any protected surface from a lobby-only user return 404.

## Success Criteria

- An unapproved test identity can successfully load only Lobby, Account, and
  its own profile.
- The same identity receives 404 for every protected HTML, RSS, API, backup,
  tag, activity, tool, instance, thread, post, and other-profile request.
- An approved test identity can load the normal board, threads, profiles,
  feeds, and account surfaces without regression.
- Static artifact and direct-download checks cannot reveal protected content.
- The behavior can be enabled for chouse or any future instance without
  changing theme selection or branding; zenmemes remains unchanged while the
  flag is off.
