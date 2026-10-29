# Unexpected page reload: handoff

Update: 2026-10-29 UTC — Public background sign-in now completes in place on `feat/background-sign-in`; see the [implementation and verification record](../background_sign_in/background_sign_in_step4_implementation_summary.md). The compose test now asserts continuity instead of waiting out a reload. Account's testing-only sign-in reload was removed too; its session-bound testing approval control appears on the next intentional visit. Version-check navigation and production session-retention investigation remain open. Merge, deployment and physical-phone verification are pending. The original diagnosis below is retained as historical evidence.


Status: handoff, not planned work. Written 2026-10-28 at the close of [ui_first_post_identity](../ui_first_post_identity/ui_first_post_identity_step4_implementation_summary.md). The maintainer has seen the page reload "from under the user" occasionally over time; the cause had not been identified.

Principle at stake: `CONTRIBUTING.md`: "Pages must look final on first paint … An in-place update after paint is acceptable only when the client learns of a real state change". A full reload while someone is reading, typing or tapping also risks losing what they were doing.

## Most likely cause: public-site sign-in resume

This is confirmed in code and seen in a test: `tests/browser/compose_identity_row.mjs` failed once when the thread page reloaded in the middle of a name change.

1. **The server marks the page.** On a public site (`FORUM_APPROVED_MEMBERS_ONLY=false`), `RouteServices::renderPageTemplate` sets `publicAuthenticationResume` when the page is rendered with no viewer profile and the route allows it (only `message.php` pages opt out). `layout.php` then renders `data-private-site-auth-state data-public-auth-resume="true"` on `<body>`, and `TemplateRenderer` (around line 159) adds `openpgp_loader.js`, `browser_signing.js` and `private_site_auth.js` to the page.
2. **When there is no viewer profile.** `Application::resolveViewerProfileFromIdentityHint` returns `null` when both of these hold:
   - there is no `authenticated_identity_id` in the PHP session;
   - the `identity_hint` cookie is missing, or doesn't resolve to a profile (no matching `username_routes` token, `openpgp:` id or profile slug).
3. **The client signs in when the page loads.** `private_site_auth.js` (DOMContentLoaded handler, around line 294) does nothing on static shared pages (`meta[name="v3-generated-at"]`). Otherwise it calls `authenticate({ returnTo: pathname + search, replaceHistory: true })`. If the browser has a saved key, `authenticateOnce`:
   - loads OpenPGP;
   - runs `ensureReadyIdentity({ verifyPublishedIdentity: true })`, which can call `/api/get_profile` and even re-register the key;
   - fetches `/api/auth_challenge`, signs it, and posts to `/api/authenticate_identity`.
4. **Then it navigates.** On a public site, every success path ends in a navigation:
   - approved (`approved=1`) → `navigateAfterApproval` → `location.replace(returnTo)`, which is the same URL, so a full reload;
   - not approved, with `returnTo` set (always, here) → `navigateAfterApproval` → the same `replace`;
   - not approved, with no `returnTo` → `location.reload()`.
5. **Timing.** The reload comes after OpenPGP loads, two or three requests and a signature. That is a fraction of a second on desktop and seconds on a slow phone or network, long enough for someone to have started scrolling, tapping Reply, typing, or opening a menu or the composer's name editor.

### Why it seems occasional

It needs a saved key, no live server session, and a missing or unusable hint cookie. Contributing conditions, checked against code but not observed in production:

- **Server session lifetime.** The session cookie lasts about 400 days (`PERSISTENT_VIEWER_SESSION_COOKIE_LIFETIME = 34560000`), but the code sets no `session.gc_maxlifetime`. The server's php.ini decides when session data is deleted. This machine has the Debian defaults: `gc_maxlifetime = 1440` seconds (24 minutes) with `gc_probability = 0`, so cleanup is by cron. Production is **unverified**; if it matches, sessions are lost after about 24 idle minutes.
- **Identity hint cookie.** `IdentityHintController` sets it for 30 days, refreshed when the browser syncs the hint (page-load prewarm on signed-action pages, after registration, on Account). It is missing after 30 days without such a visit, after cookies are cleared, or in a new browser profile with an imported key. Since `ui_first_post_identity`, the hint is not synced for a key that isn't registered yet, so it stays absent until the first signed action. That case does not reload, because `private_site_auth.js` now skips unregistered keys on public sites.
- **A hint that doesn't resolve.** For example, a key whose profile is not in the read model yet, or `guest` with no matching username route. This is **unverified**: it follows from the lookup order in code but hasn't been reproduced.
- **Unverified:** whether Safari's tracking prevention shortens these first-party cookies.

### What it can destroy

- **Text being typed.** Compose drafts are saved to browser storage and restored on load (`browser_signing.js` compose draft keys), so a draft probably survives. That is unverified for inline replies mid-keystroke and for the composer's name editor.
- **Scroll position and focus.** Lost; `location.replace` starts a fresh load.
- **Open menus, the name editor, and pending optimistic state** (a Like in flight, a reply card being posted). The in-flight request may finish server-side while the page reloads. That is unverified; the reply continuation should recover a pending reply on the next load.

### Already avoided elsewhere

- Account signs in **in place** (`data-auth-in-place`, cycle 4 `ui_account_states`): `authenticate({ navigate: false })`, then the page updates itself through `forum:authenticated-in-place`. Cycle 4 recorded "sign-in reload elsewhere" as a follow-up; this is that follow-up.
- `railspike_workflow.js` calls `authenticate({ navigate: false })`.

### Directions to consider

- Sign in in place everywhere on public sites, as Account does. A public page only needs the session for viewer-specific parts: show the result in place, or on the next navigation.
- If a refresh is truly needed, never do it while the user is interacting (focus inside a form, text being typed, a dialog open). Defer it to the next navigation.
- Decide whether public pages need the session at all before the first signed action, since signed actions verify signatures themselves.
- Check production session settings (`session.gc_maxlifetime`, cleanup) against the 400-day session cookie.

## Second candidate: new-version check

`version_check.js` runs early on every page:
- When a poll finds a new deploy, it shows a banner, and reloads only if the user taps it (`reloadForNewVersion`).
- On load, `ensurePendingVersionSatisfied` immediately runs `location.replace(… __v=<pending>)` when this tab's `sessionStorage` holds a pending version different from the page's. In practice: the tab saw the banner, the user didn't reload, then navigated to a page still served at the old version (cached or static).

This happens just after the page parses, so it reads as "the page loaded twice" rather than a reload mid-typing. That is **unverified** as a source of the reports. The `__v=` parameter in the URL after a reload would point to this path rather than the sign-in one.

## Other reloads found, unlikely to be the report

- `private_message_list.js`: reloads on `pageshow` from the back/forward cache (messages list only).
- `post_retraction.js` and `fastmod.js` (operator tools), `testing_self_approval.js` (testing only): after explicit actions.
- `auth_navigation.js` (members-only links), `invite_navigation.js`, `invite_redemption.js`: navigation after explicit actions.

## How to tell which one happened

- A URL gaining `__v=<version>` → the version check.
- The URL unchanged, and `/api/auth_challenge` plus `/api/authenticate_identity` in the network log right before the reload → sign-in resume.

To reproduce the sign-in resume: on a public site with a registered key in browser storage, delete the session cookie (`PHPSESSID`) and the `identity_hint` cookie, then load a thread page and start typing. It reloads once sign-in finishes. The test at `tests/browser/compose_identity_row.mjs` (search "signs in on load") waits this reload out.

## Evidence

- `public/assets/private_site_auth.js`: `authenticateOnce`, `navigateAfterApproval`, the DOMContentLoaded handler.
- `src/ForumRewrite/Http/RouteServices.php` `renderPageTemplate`; `src/ForumRewrite/View/TemplateRenderer.php` (script inclusion); `templates/layout.php` (`data-public-auth-resume`).
- `src/ForumRewrite/Application.php`: `resolveViewerProfileFromIdentityHint`, `startViewerSession` (cookie lifetime), `shouldResumeViewerSession`.
- `src/ForumRewrite/Http/IdentityHintController.php`: 30-day hint cookie.
- `public/assets/version_check.js`: `ensurePendingVersionSatisfied`, `reloadForNewVersion`.
