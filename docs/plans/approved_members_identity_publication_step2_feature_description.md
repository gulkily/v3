# Approved Members Identity Publication Step 2 Feature Description

## Problem

Private approved-members-only instances currently allow a browser key to reach a prepared identity state without reliably completing profile publication. Users must be able to create a keypair and automatically share its public key, after which the existing approval state should determine lobby or full-site access.

## User Stories

- As a new user, I want my generated public key to be shared automatically so that I do not need to submit a second manual form.
- As an existing user, I want the browser to recognize my existing profile so that setup does not try to create a duplicate identity.
- As an approved user, I want to enter the full site after key setup so that approval is reflected immediately.
- As an unapproved user, I want to remain in the lobby while my profile is pending so that private content stays protected.
- As an instance operator, I want all non-allowlisted routes hidden from lobby users so that enabling private mode does not expose content through alternate views or feeds.

## Core Requirements

- Key generation or import automatically publishes the public key and completes profile creation when the identity is new.
- Existing profiles are reused without duplicate creation and can authenticate using their matching browser key.
- Existing approval records are applied to newly completed profiles without a separate user action.
- Lobby users can access only Lobby, Account, and their own profile; all other site content and feeds return 404.
- Public or client-provided identity hints must not grant access; entry to the full site requires verified key authentication and approval.

## Shared Component Inventory

- Account key page: extend the existing browser-key setup surface and status messaging; do not add a parallel identity-creation UI.
- Browser signing/publication service: reuse the existing key inspection, prepare, sign, finalize, and retry flow; make its existing-profile check compatible with private mode.
- Identity publication APIs: reuse the existing prepare/finalize contracts for automatic publication; keep the manual link form only as a recovery path.
- Approval/read-model state: reuse the existing profile approval state and canonical approval records; no new approval model is needed.
- Private access gate and lobby: extend the existing private-mode allowlist and lobby behavior to recognize the completed authenticated profile.

## Simple User Flow

1. User opens Account and generates or imports a browser keypair.
2. The browser automatically shares the public key and completes identity publication.
3. The system reuses an existing profile or creates a new one, then applies any existing approval.
4. The browser authenticates with the private key.
5. Approved users enter the full site; unapproved users remain in Lobby and can open Account and their own profile.

## Success Criteria

- A new browser keypair produces a visible profile without manual public-key submission.
- A seeded approval for that fingerprint results in full-site access after setup and authentication.
- An existing profile is reused without a duplicate identity or failed setup loop.
- An unauthenticated or unapproved request to every non-allowlisted page, API, feed, backup, or download receives 404.
- The authenticated user can open the own-profile link from Account and see the profile’s public fingerprint.
