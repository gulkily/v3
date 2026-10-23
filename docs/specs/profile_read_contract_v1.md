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
- A later username change is available only while the `FORUM_USERNAME_CHANGES_ENABLED` flag is on (default off): a signed, same-key `set-name` record under `records/profile-names/` supplies the visible username without changing the identity, keys or approvals (see [invitation entry](../reference/invitation_entry.md#choosing-a-username-step-by-step)). Other post-bootstrap profile editing is not part of V1.

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

- `/user/<username>` resolves by normalized username token, not by a separate merge record family.
- Username matching is case-insensitive after normalization to the route token.
- If no profile currently resolves for the username token, the route returns `404 Not Found`.
- The username route is an aggregate public entrypoint for that username token, not a distinct profile type.
- All approved profiles with the matching username token are grouped together on that route.
- The route shows:
  - the count of approved profiles using that username token
  - combined visible thread and post totals across those approved profiles
  - visible threads and posts authored by any of those approved identities
  - a list of the approved profile slugs that participate in the aggregate
- Unapproved profiles with the same username token are shown separately and do not contribute to the combined totals.

## Username Collision Handling

- V1 does not merge colliding identities into one canonical identity record.
- Each profile remains anchored to its own `Identity-ID` and canonical `/profiles/<profile-slug>` route.
- The only "merge" behavior is read-side aggregation on `/user/<username>` and `/users/` for approved profiles that share the same normalized username token.
- Approved collisions are intentionally treated as one visible user bucket for browse surfaces:
  - `/user/<username>` aggregates their visible content and lists the participating approved profiles
  - `/users/` groups approved profiles by `username_token` and sums their thread/post counts
- Unapproved collisions remain separate pending profiles and are never folded into approved totals.
- Collision handling remains deterministic and fully derivable from indexed profile state plus approval state.

## Composite User

- A composite user is the set of approved profiles that share one normalized `username_token`. It is the "visible user bucket" above, and the unit that private messaging encrypts to and delivers by (`ApprovedUserKeyResolver`). "Composite identity" is not a separate term.
- Membership is derived, not recorded. Each profile keeps its own `Identity-ID`; approval follows `Identity-ID`, not the username.
- A verified `set-name` record under `records/profile-names/` changes the profile's `username_token`. The profile leaves its old composite user and joins the one for the new token, or forms a new one. This affects `/user/<username>`, `/users/` grouping, private-message recipient keys, and which approvers count as "same username" for additional keys. Its approval is unchanged.
- A rename can join an existing composite user only with consent: renaming to a token another profile uses (approved or pending, except the shared `guest`) is held as a join request until an approved holder of that token or an operator accepts it with a signed grant (`records/profile-name-grants/`). See [choosing a username](../reference/invitation_entry.md#joining-a-username-someone-else-uses). Grants are audit records; membership is still derived from the rename record.
- Stored private messages keep the sender and recipient tokens they were sent with, so earlier conversations stay under the old name after a rename.

## Derived Read-Model Requirements

The indexed profile model must materialize enough data to support:

- profile lookup by identity slug
- username lookup by normalized username token
- current visible username per identity
- approved-profile aggregation by normalized username token
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
- merge record families
- merge-management routes
- moderation-derived profile suppression
- historical username-claim browsing beyond the current visible username and deterministic collision handling
