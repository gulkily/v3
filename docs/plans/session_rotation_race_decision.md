# Decision: signing in must not quietly sign the browser out

Status: decided 2026-10-30 — option A with a 30-second grace period; implemented on `fix/session-rotation-race` (see Implementation).
Date: 2026-10-30.
Found while fixing testing Approve (merged in `3c404b302`); see the [UI copy checklist entry](ui_copy_audit_checklist.md) "Testing Approve works after Remove and Continue".

## Problem

Signing in replaces the session ID and deletes the old session. A request that is still using the old ID loses the session:

1. Sign-in (`/api/authenticate_identity`, `AuthApiController`) calls `session_regenerate_id(true)`. The browser gets a new session ID, and the old session is deleted at once.
2. A request already in flight still carries the old ID. Sessions run in strict mode, so the server rejects the unknown ID, starts a new empty session and sends its cookie. That cookie replaces the signed-in one.
3. A page load that still carries the old ID goes through `resumeViewerSession` in `Application.php`, which deletes the session cookie outright.

Either way the browser ends up signed out, and nothing tells the person. Sessions otherwise last a long time (the cookie lifetime is 400 days), so the loss persists until something signs in again.

What it affects: anything that sends a request while a sign-in is finishing. Account signs in on every visit (in place, without navigating), and now after Continue too, so the window comes up in ordinary use. Losing the session also loses anything else stored in it, such as the testing Approve token before it moved to its own cookie.

## Evidence

Session log from the testing Approve browser test (2026-10-29). Each line is a request, the session ID the browser sent ("in") and the one the server used ("sid"):

```
POST /api/authenticate_identity   in=v7h11d  sid=v7h11d  signed in
POST /api/clear_identity          in=v7h11d  sid=oe95ts  token lost, new empty session
GET  /api/auth_challenge          in=oe95ts  sid=oe95ts
```

Sign-in moved the session off `v7h11d`. Remove (`clear_identity`) was already on its way with `v7h11d`, got a new empty session `oe95ts`, and the browser then used `oe95ts` from there on. Testing Approve now recovers from this by signing in again and retrying, but posting, Messages and other signed-in actions do not.

## Options

### A. Forward the old ID for a short grace period (recommended)

This is the pattern the PHP manual recommends for replacing a session ID at sign-in.

1. At sign-in, keep the old session instead of deleting it. Mark it with the time it was replaced and the new session's ID.
2. A request that arrives with the old ID during the grace period is moved to the new session. It acts as signed in, and its response sets the new cookie again, so nothing gets overwritten. A Remove sent just as sign-in finishes therefore still signs out the right session.
3. After the grace period the old ID is dead: it is discarded and treated like any unknown session, as now.
4. Both session-start paths (API requests and page loads) do the forwarding, so the cookie-deleting path no longer fires during the grace period.

Proposed grace period: 30 seconds. An in-flight request takes seconds.

Security trade-off: for the grace period, the old ID also opens the signed-in session. That matters only if an attacker already knows the victim's pre-sign-in session ID (session fixation).
- The server rejects session IDs it did not issue, so an attacker cannot simply pick one. They would need to plant a real ID in the victim's cookie, which takes something like cross-site scripting or control of a subdomain.
- The check that an old ID only forwards inside its grace period happens on the server.
- An attacker who can plant cookies or run script on the site already has easier attacks than a 30-second window.

### B. Keep the old session without forwarding

Keep the old session (unchanged, still signed out) for the grace period but never move its requests to the new session. In-flight requests stop creating empty sessions, so the cookie is no longer overwritten, and the fixation trade-off disappears.

Cost: requests on the old ID act on a stale session. A Remove sent during sign-in clears the old session, not the new one, so the browser stays signed in with a key the person just removed. Remove means "sign out", so this is wrong in the case that started this.

### C. Make the browser wait for sign-in

The browser holds other requests until a sign-in finishes. No server change.

Cost: it only covers requests from the same page. Other tabs, the Outbox, service workers and requests that start before the page knows a sign-in is under way still race. It also adds delay to every action during sign-in.

### D. Stop replacing the session ID at sign-in

Removes the race entirely.

Cost: replacing the ID at sign-in is the standard defence against session fixation. Without it a planted pre-sign-in ID stays valid for the whole 400-day session, not 30 seconds. Rejected.

## Recommendation

Option A with a 30-second grace period. It is the only option that keeps both the cookie and the meaning of Remove correct, and its exposure is a short window that needs an attacker who can already plant cookies.

## Tests

- PHP: sign in, then send a request with the old ID inside the grace period: it lands in the signed-in session and gets the new cookie. After the grace period, the old ID gets a fresh, signed-out session. A page load with the old ID inside the grace period keeps the session instead of deleting the cookie.
- Browser: Remove sent while a sign-in is in flight ends signed out; the testing Approve token survives.
- The full suite at the end.

## Decision

Option A, 30-second grace period (maintainer, 2026-10-30).

## Implementation

- `src/ForumRewrite/Http/SessionRotation.php`: `rotate()` moves the session's data to a new ID and leaves the old ID holding only the time and the new ID, never signed-in data. `follow()` runs after every session start: inside the grace period it moves the request to the new ID (following up to three sign-ins in a row); after it, the old ID is deleted and the request starts a new empty session, as an unknown ID did before.
- `AuthApiController` sign-in calls `SessionRotation::rotate()` instead of `session_regenerate_id(true)`.
- `Application::startViewerSession` calls `follow()`; `resumeViewerSession` no longer deletes the cookie of a request it forwarded.
- `SessionRotation::rotate()` turns strict mode off for the rest of the sign-in request so the new ID it created is accepted; PHP can't switch it back while the session is open, and it applies only when a session starts.

## Worked example: Remove during a sign-in

From the testing Approve browser test, before the fix. In that run the sign-in was started because the test re-seeded the operator's key on every page load (since fixed), but any sign-in on page load does the same.

1. Account loads with session A signed in with a different key from the one saved in the browser, so it signs in again in place.
2. The server accepts the sign-in, moves the data to session B, deletes A and sends B's cookie.
3. Remove is pressed before that response arrives, so its request carries A.
4. A no longer exists, so the server starts a new empty session C and sends its cookie, which replaces B's. Everything in the session is gone.

With the fix, step 4 finds A's forwarding marker, acts in B and sends B's cookie again, so Remove signs out the session the browser actually holds.

## Verification

- `SessionRotationTest` (separate PHP processes per request): the old ID lands in the signed-in session and its writes reach it; the old ID keeps no signed-in data; after the grace period the old ID gets a new empty session and its marker is deleted; chains of sign-ins forward.
- `tests/browser/session_rotation_http.mjs` (real server, cookies by hand): a page load and a Messages request on the old ID stay signed in and get the current cookie back, never a deletion or a new session; two sign-ins in a row forward; Remove on an old ID signs out the current session; an unknown ID still gets nothing. It fails without the fix.
- `testing_self_approval.mjs` now seeds keys once, as a real browser keeps them.
