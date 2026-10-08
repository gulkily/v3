# Private-Window Lobby Fallback: Step 1 Solution Assessment

## Problem statement

On an approved-members-only instance, a visitor without a saved browser key reaches a Reconnecting page from a protected URL instead of automatically entering the Lobby.

## Option A — Redirect missing-key recovery clients to Lobby

Pros:
- Preserves silent reauthentication and original-destination recovery for visitors with a usable saved key.
- Sends a genuinely unidentified private-window visitor to the only permitted entry surface.
- Keeps the existing server-side access gate and Lobby as the canonical access boundary.

Cons:
- The initial protected request still briefly renders the recovery page before client-side navigation.

## Option B — Redirect every unauthenticated protected request to Lobby on the server

Pros:
- Avoids rendering the recovery page for visitors without a session.
- Simplifies the first response for new visitors.

Cons:
- Prevents the established automatic recovery path from restoring approved members with saved browser keys.
- Would require a separate mechanism to distinguish a missing browser key, which the server cannot observe directly.

## Option C — Add a separate anonymous-entry route or page

Pros:
- Could provide dedicated first-visit guidance.
- Leaves the existing recovery page unchanged.

Cons:
- Duplicates the Lobby's role and navigation.
- Broadens a narrow routing defect into a new access surface.

## Recommendation

Choose **Option A**. Make the existing recovery client redirect a missing-key visitor to Lobby while retaining the validated requested destination for later approved recovery; keep authentication failures visible rather than treating them as a missing key.
