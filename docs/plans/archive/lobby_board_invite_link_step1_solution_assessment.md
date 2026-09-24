# Redeemable Board Invitations — Step 1: Solution Assessment

## Problem Statement

An approved user should be able to invite a recipient who creates a new,
disposable browser-key identity and is then automatically approved and signed
into the private board.

## Option A — Signed, single-use invitation bound to the new identity

An approved inviter creates a signed invitation containing a commitment to a
high-entropy secret. The recipient opens the Lobby, chooses a username (or a
clearly temporary display name), proves control of a browser key, and redeems
the secret; redemption consumes the invitation and creates the inviter's
attributable approval for that identity.

- Pros:
  - Implements the proposed reverse-approval experience without making a link
    itself a durable logged-in session.
  - Preserves a clear chain: inviter → invitation → verified recipient key →
    approved identity.
  - Allows one-time use, expiry, revocation, and an auditable inviter.
- Cons:
  - Adds an invitation lifecycle beyond the existing approval flow.
  - A forwarded, unredeemed secret can be claimed by the first recipient.

## Option B — Operator-issued generic redemption codes

Operators issue codes from a central tool; recipients redeem them through the
same identity-creation and authentication flow.

- Pros:
  - Simple authority model and centralized oversight.
  - Useful when ordinary members must not invite others.
- Cons:
  - Does not satisfy the approved-user-as-inviter model.
  - Loses the social attribution that the inviter's signed record provides.

## Option C — Lobby link only

Share `/lobby/` and retain the normal separate approval workflow.

- Pros:
  - Uses the existing Lobby without new invitation lifecycle rules.
- Cons:
  - Does not automatically approve or sign in the recipient.
  - Leaves the operator or inviter with a separate approval action.

## Recommendation

Choose **Option A**. The recipient must first have a distinct browser-key
identity; a shared `guest` login should not be used because it removes
accountability and prevents reliable revocation. Let the recipient choose a
username during that flow, subject to normal uniqueness rules, with a
temporary generated label only if the product needs one.

The unhashed secret is a bearer credential: it must be submitted only to the
redemption flow, never included in a public post, URL query, logs, or the
canonical approval record. The invitation must be atomic single-use, expiring
and revocable, and result in an authenticated session only after the browser
proves possession of the bound key. A stored hash/commitment is appropriate
only when the original secret has sufficient unpredictable entropy.
