# Profile Read Contract V1

This document defines the retained profile and username-read behavior for the PHP rewrite.

## Scope

V1 profile reads cover:

- public profile pages at `/profiles/<profile-slug>`
- self-profile reads at `/profiles/<profile-slug>?self=1`
- username routes at `/user/<username>`
- account-key page linkage and identity-hint-aware navigation as supporting behavior

Separate post-bootstrap profile editing remains out of scope.

## Canonical Inputs

Profile reads may derive state only from in-scope retained record families:

- `records/identity/`
- `records/public-keys/`
- `records/posts/`
- `records/instance/` where needed for shared shell or project-level facts

V1 does not require `records/profile-updates/`, merge records, moderation records, or thread-title updates.

## Identity Model

- Each public profile is anchored by one canonical `Identity-ID`.
- The public profile slug uses the identity slug form `<scheme>-<value>`.
- For retained scope, the canonical slug for an OpenPGP identity is `openpgp-<lowercase-fingerprint>`.
- The identity bootstrap record is the canonical source for:
  - identity ID
  - signer fingerprint
  - bootstrap post/thread references
  - bootstrap public key text

## Visible Profile Fields

Every resolved profile read must be able to surface at least:

- `Identity-ID`
- current visible username
- fallback display label
- bootstrap record ID
- bootstrap post ID
- bootstrap thread ID
- public key material
- post history summary
- thread history summary

## Username Rules

- Username capture happens once during browser key generation/bootstrap.
- If browser `prompt()` is unavailable, dismissed, blank, or unusable, the bootstrap flow falls back to `guest`.
- The chosen bootstrap username becomes the initial visible username for the identity.
- V1 has no separate username-change or profile-update flow after bootstrap.

## Public Profile Resolution

- `/profiles/<profile-slug>` resolves one profile by canonical identity slug.
- If the slug does not resolve to a known identity, the route returns `404 Not Found`.
- Public profile reads must be renderable from indexed profile state without request-time full-repo scans.

## Self Profile Resolution

- `/profiles/<profile-slug>?self=1` is the self-profile variant of the same identity route.
- Self-profile reads may use dynamic logic initially if needed, but the route contract remains owned by PHP.
- If the identity is not yet publicly materialized but the request is explicitly self-marked, the route may render an empty-state bootstrap/account view instead of `404`.
- Self-profile rendering must keep the nav/account context distinct from the public-profile view.

## Username Route Resolution

- `/user/<username>` resolves to the canonical profile currently associated with that username token.
- Username matching is case-insensitive after normalization to the route token.
- If no profile currently resolves for the username token, the route returns `404 Not Found`.
- The username route is an alternate public entrypoint, not a distinct profile type.

## Username Collision Handling

- V1 must define one deterministic canonical profile for a colliding username token.
- The selected canonical profile should be stable for a given indexed state.
- The username route should render the canonical profile and may list other profiles with the same visible username.
- Collision handling must remain deterministic and fully derivable from indexed profile state.

## Derived Read-Model Requirements

The indexed profile model must materialize enough data to support:

- profile lookup by identity slug
- username lookup by normalized username token
- current visible username per identity
- fallback display label per identity
- post and thread counts or equivalent summaries
- public-key display data
- self-profile/account affordances

## Rendering Requirements

Profile pages must:

- use the shared page shell
- preserve stable links to `/profiles/<profile-slug>` and `/user/<username>` where available
- surface enough technical detail for key-linked identity inspection
- keep public and self-profile states visually distinguishable

## Out Of Scope

V1 profile reads do not require:

- profile-update routes
- merge-management routes
- moderation-derived profile suppression
- historical username-claim browsing beyond the current visible username and deterministic collision handling
