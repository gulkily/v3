# Private-Window Lobby Fallback: Step 2 Feature Description

## Problem

An approved-members-only site sends a private-window visitor without a browser key to Reconnecting after a protected request, where they receive an error instead of reaching the Lobby.

## User stories

- As a new or private-window visitor, I want to reach the Lobby automatically when I have no browser key so that I can begin the permitted access flow.
- As an approved member with a saved browser key, I want protected-page recovery to continue restoring my session so that I return to my requested page.
- As an operator, I want unapproved access to remain limited to the Lobby so that private content stays protected.

## Core requirements

- A protected HTML request without a session may still attempt browser-key recovery.
- When that recovery detects no usable saved browser key, it must enter Lobby automatically and preserve the safe requested destination.
- A usable browser key must retain the current authentication and approved-return behavior.
- Authentication, key-validation, and network failures must remain visible recovery errors and must not be silently classified as an absent key.
- Lobby-only authorization and protected-route restrictions must not change.

## Shared component inventory

- Recovery page: extend the canonical Reconnecting surface; no new entry page.
- Browser identity recovery: extend its absent-key outcome; retain its existing authentication behavior and endpoints.
- Lobby: reuse as the sole anonymous and pending-access destination.
- Protected-route access gate: reuse unchanged as the canonical decision to offer session recovery.

## User flow

1. A visitor opens a protected page on an approved-members-only instance.
2. The site offers browser-key recovery.
3. With no usable saved key, the visitor enters Lobby with the requested destination retained.
4. With a usable key, the existing authentication flow returns an approved member to the requested page or shows pending access in Lobby.

## Success criteria

- A private-window protected-page visit automatically reaches Lobby rather than remaining on Reconnecting.
- The Lobby remains the only accessible content surface for the unidentified visitor.
- A saved approved key still returns the visitor to the original protected URL.
- Invalid or failed authentication still presents a recovery error rather than redirecting to Lobby.
