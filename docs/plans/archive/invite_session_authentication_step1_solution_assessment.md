# Invite Session Authentication: Step 1 Solution Assessment

## Problem statement

On a public instance, the Account page can show an approved browser identity and the Invite link, while the invitation route cannot read that authenticated session and rejects the same user.

## Option A — Treat invitation flow as session-bound

**In plain language:** When someone opens or uses invitations, the site checks their normal signed-in state first and, if needed, sends them through the existing browser-key sign-in process.

Pros:
- Preserves proof-of-key-possession as the authorization boundary for issuing invitations.
- Limits session initialization and reauthentication recovery to invitation pages and invitation actions.
- Aligns the Invite link, page, and write actions around one authentication state.

Cons:
- Requires coordinated changes across the invitation routes and their navigation/recovery behavior.
- An expired session still requires the existing browser-key authentication step.

## Option B — Restore signed-in state across public routes

**In plain language:** When a browser already has a session, recognize it on every page. When its session is absent but its local browser key is available, the next page automatically proves key possession and creates a new session. Anonymous visitors do not receive a session merely for reading the site.

Pros:
- Makes server-side authentication state consistently available for signed-in members on public routes.
- Lets public navigation consistently reflect a member's authenticated state.
- Restores ordinary navigation after a browser restart or expired session without sending a member to Account first.
- Reduces route-specific session omissions in the future without creating routine anonymous sessions.

Cons:
- Broadens a targeted invitation fix into a site-wide authentication-restoration behavior change.
- Requires the browser-key authentication support to be safely available on public pages and careful testing of expired or invalid sessions.

## Option C — Start viewer sessions for all public visitors

**In plain language:** Give every visitor—including anonymous readers—a server-side session, so every page can check whether that visitor has signed in.

Pros:
- Makes server-side authentication state consistently available to all public routes.
- Simplifies the distinction between first-time and returning browsers.

Cons:
- Creates unnecessary session state and cookies for anonymous readers.
- Broadens the change beyond the invitation problem without a corresponding user benefit.

## Option D — Authorize invitation issuance from the identity-hint cookie

**In plain language:** Use the browser's saved “this is probably Alice” marker to open and prepare invitations, then rely on the existing signature check only when publishing one.

Pros:
- Makes the existing Account-page recognition immediately available to invitations.
- Requires little session-routing work.
- Retains final browser signing and server-side signature verification before publication.

Cons:
- The identity hint identifies a browser preference; it is not proof of private-key possession.
- Lets an unproven browser begin a privileged workflow and makes authorization depend on a weaker signal than publication.

## Recommendation

Choose **Option B**. It restores a returning approved member's signed-in state on ordinary public pages by proving possession of the existing browser key, while leaving anonymous visitors session-free. This fixes invitations and makes authenticated navigation consistent without trusting the identity-hint cookie.
