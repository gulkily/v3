# Redeemable Board Invitations — Step 2: Feature Description

## Problem

Private boards require a recipient to have an identity before an approved user
can approve them. An approved member needs a safe, attributable invitation
available from any board page that lets a recipient establish their own
identity and enter a chosen board destination without manual approval.

## User Stories

- As an approved member, I want to create and revoke a one-time invitation so
  that I can vouch for a specific new participant.
- As an approved member, I want to generate an invitation from any board page
  and optionally set its destination so that I can invite someone into the
  relevant conversation without navigating away.
- As an invitee, I want an invitation to take me to the Lobby and guide me
  through choosing a username and creating a disposable key so that I can
  enter the board in one flow.
- As an invitee, I want my session to be tied to my own key-backed identity so
  that I do not share a generic guest account.
- As an operator, I want invitation redemption and resulting approval to show
  who issued it so that member access remains auditable and revocable.

## Core Requirements

- Only an authenticated, approved identity may issue an invitation from any
  full-board page; possession of an invitation alone never creates a session
  or grants access.
- An invitation is redeemable only once, can expire or be revoked, and is
  bound atomically to one verified recipient identity.
- Redemption creates the inviter-attributable approval through the existing
  canonical approval model; the recipient then authenticates with their key
  and is sent to the optional approved internal destination or the board.
- The invitation secret is handled only by the private redemption flow and is
  never exposed as board content or a canonical approval value; Activity shows
  lifecycle events linked by verification hash only.
- Until redemption and key authentication complete, the existing lobby-only
  boundary continues to protect all non-allowlisted board surfaces.

## Shared Component Inventory

- Lobby and private access gate: extend the canonical entry and restriction
  surfaces to recognize invitation redemption; do not create a parallel entry
  path.
- Account key page and browser identity-publication service: reuse for
  disposable key generation, identity publication, and recipient status.
- Identity publication and authentication APIs: reuse their verified
  browser-key lifecycle; invitation redemption must require that identity.
- Signed approval records and approval-derived state: extend the existing
  inviter-attributable approval lifecycle rather than introduce a second
  membership model.
- Shared approved-member navigation/action: extend the canonical site-wide
  control so invitation generation is available from every board page rather
  than duplicating page-specific controls.
- Invitation issuance and redemption: add dedicated surfaces because no
  existing component owns an invitation's secret, lifecycle, audit state, or
  post-redemption destination.

## Simple User Flow

1. An approved member generates an invitation from any board page, optionally
   uses the current or another internal board destination, and shares its
   private secret or invitation link with a recipient.
2. The recipient arrives in Lobby, selects an available username, and creates
   a new disposable browser-key identity.
3. The recipient proves control of that identity and redeems the invitation.
4. The invitation is consumed and creates the inviter's approval for that
   identity.
5. The recipient authenticates with their key and enters the selected allowed
   destination, or the board when none was selected.

## Success Criteria

- An approved member can issue an invitation from every board page, and its
  resulting approval is attributable to that member.
- A new recipient can complete key setup, choose a valid unique username,
  redeem a valid invitation, and reach the board in one flow.
- A valid optional internal destination opens only after successful redemption
  and authentication; an absent or invalid destination falls back safely to
  the board.
- An expired, revoked, reused, malformed, or leaked-only invitation cannot
  create access or a session.
- An unapproved identity cannot issue invitations, and an unresolved invitee
  cannot access any protected board route, feed, API, download, or artifact.
- An interrupted, unredeemed invitation does not create access; the recipient
  can start again with a newly generated disposable key.
