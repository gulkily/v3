# Unintended One-Time Lobby Redirect Investigation

## Report

### Summary

After clearing a browser identity and setting up a new one, the first navbar navigation can redirect to `/lobby/` even when approved-members-only mode is disabled. The page says the new identity is awaiting approval. Repeating the same navigation works normally.

### Reproduction

1. Open the Account page and use **Clear identity**, when available.
2. Use **Set up this browser** and choose a username.
3. Click **About** (or another navbar link).

### Expected result

The requested page opens. A server with approved-members-only mode disabled must not send a visitor to the Lobby.

### Actual result

The first navbar navigation opens the Lobby with:

> Your identity is set up, but approval is still pending.

> Use Account to review your identity and profile.

Subsequent navigation works normally.

### Scope and constraints

- Investigate and plan only; do not alter application behavior in this work item.
- Preserve the distinction between the always-public server case and approved-members-only lobby enforcement.
- Keep the eventual change narrowly targeted at the stale one-time navigation state.

## Investigation log

### 2026-10-06: routing and client-flow review

#### What is not causing the redirect

`Application::handle()` can only redirect a request to `/lobby/` through
`membersOnlyLobbyRedirect()` when `FORUM_APPROVED_MEMBERS_ONLY` is enabled.
That condition is false for the reported server, so the PHP lobby gate cannot
be the source of this redirect.

`FrontController` also declines static artifacts when any cookie is supplied,
including `identity_hint`; cached static output is therefore not the expected
normal request path after `syncIdentityHint()` completes. It remains useful to
check the first affected navigation's request cookies and response markup if
the issue survives the client fix, but static routing is not needed to explain
the redirect that occurs.

#### Relevant public authentication flow

For a public page whose server render cannot resolve a viewer profile,
`RouteServices::renderPageTemplate()` sets `publicAuthenticationResume`. The
layout emits `data-private-site-auth-state data-public-auth-resume="true"`,
and `TemplateRenderer` loads `private_site_auth.js` with the browser signing
assets. This is intended to opportunistically authenticate an existing key on
a public server; it is not a members-only access check.

After browser identity setup, a newly created profile is normally unapproved.
`/api/authenticate_identity` correctly returns a successful identity proof
with `approved=0`.

#### Root cause

`public/assets/private_site_auth.js` handles every `approved=0` response the
same way, regardless of deployment mode:

1. It labels the identity as pending approval.
2. If the public-resume request supplied a return target, it calls
   `window.location.replace('/lobby/?return_to=...')`.

That branch is correct only for approved-members-only mode. On a public
server, “unapproved” is not an access restriction, so the client must return
to the requested page after successful key verification instead of going to
the Lobby.

This explains the one-time nature: that first public-resume navigation
creates an authenticated session for the new identity. Later server renders
resolve a viewer profile from that session or `identity_hint`; they no longer
emit the anonymous public-resume marker, so the offending branch does not
run again.

#### Confidence and remaining observation

The unconditional client redirect is sufficient to cause the reported
behavior and is the root cause to fix. The exact reason the first destination
render carries the public-resume marker is not material to correctness—the
marker intentionally exists for any temporarily anonymous public render.

During implementation verification, inspect the first affected destination
in browser DevTools to confirm it contains `data-public-auth-resume="true"`.
If it does not, investigate another caller of `PrivateSiteAuth.authenticate()`
before expanding scope.

## Fix plan

### Goal

Keep opportunistic authentication on public pages, but make approval state
control navigation only when approved-members-only mode is explicitly enabled.

### Proposed change

1. In `public/assets/private_site_auth.js`, read the existing
   `document.documentElement.dataset.approvedMembersOnly` flag.
2. On a successful `/api/authenticate_identity` response with `approved=0`:
   - when members-only mode is enabled, preserve the existing pending-approval
     status and Lobby redirect;
   - when members-only mode is disabled, report successful identity
     verification and use the requested return destination (with history
     replacement where the public-resume flow already requests it), rather
     than redirecting to `/lobby/`.
3. Do not alter `Application` lobby-gate rules, identity creation,
   `identity_hint`, approval records, or session-auth cryptography.

### Regression coverage

Add focused cases to `tests/PrivateSiteAuthTest.php`:

- public-auth-resume + valid browser key + `approved=0` returns to the
  requested page and never references `/lobby/`;
- members-only + the same successful-but-unapproved result still redirects to
  `/lobby/?return_to=...`;
- existing approved and missing-key behavior remains unchanged.

Keep the existing `AuthNavigationTest` behavior intact: it only delegates to
`PrivateSiteAuth`; the mode-specific destination decision belongs in that
shared authentication module.

### Manual verification

1. With approved-members-only disabled, clear an identity, set up a browser
   identity, and click About immediately. The first click must land on About.
2. Repeat with another navbar destination and a browser refresh; neither may
   display the Lobby pending-approval page.
3. With approved-members-only enabled, repeat as an unapproved identity. The
   Lobby pending-approval behavior must remain intact.

### Risks

- The fix must use the server-rendered feature-flag data attribute, not an
  inference from approval state, so public servers retain their normal
  unapproved identities.
- Treating `approved=0` as a public success must not be confused with
  accepting failed signature authentication; this branch is reached only
  after the authenticated API response succeeds.
- Do not remove public authentication resume wholesale: it restores an
  existing browser identity on public pages and has separate coverage.

## Status

Resolved 2026-10-06. `private_site_auth.js` now reads the server-rendered
`data-approved-members-only` flag before deciding how to handle a successful
but unapproved identity authentication. Public deployments treat that outcome
as successful identity verification and return to the requested destination;
members-only deployments preserve the pending-approval Lobby redirect.

`PrivateSiteAuthTest` covers both modes, including the public-resume history
replacement path. Ready for UAT.
