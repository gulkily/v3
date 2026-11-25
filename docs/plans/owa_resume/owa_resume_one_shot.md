# Installed app reopens where you were — one-shot

Cycle: `v3/owa_resume`; Mode: one-shot; Record/index: this file, [plans index](../README.md), [notifications checklist](../notifications_checklist.md) (owa follow-up)
Debug mode: enabled ([log](owa_resume_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/owa_resume`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_ledger/owa_resume.md))
Extended estimates: enabled (no alternatives or work units, so only the cycle estimate applies)

**Cycle estimate: 0.12**

Status: planned on `feature/owa-resume`. Next action: implement.

## Request and authorization

The maintainer, 2026-11-24 21:56 ET, in a Claude session: "can the web app remember what we were doing before if we like close it and reopen it, or no?" Claude answered that it does not today (iPhone reopens a closed or evicted home-screen app at `start_url`, `/`) and proposed: save the page and scroll position, and on a launch at `/` in the installed app return there if it was recent; a notification tap or shared link wins. Asked whether to do this as a one-shot, the maintainer answered "Yes please" (2026-11-25 02:00 ET). Authorizes a one-shot through an open PR; excludes merge, deployment and the phone check. The sibling one-shot is `owa_app_title` (PR #418).

Feedback item: none matched in `docs/feedback/items/` (searched "resume", "where i was", "last page", "home screen").

## Outcome and work type

Application change. In the installed app (display mode standalone, or iPhone's `navigator.standalone`): when the app goes to the background or leaves a page, it remembers the page address and scroll position on this device. When the app is launched fresh and opens at `/`, it goes to the remembered page instead, at its scroll position, if that was within the last 6 hours and was not `/`. A launch at any other address (a notification tap opens its own page) is left alone. Browser tabs are never affected.

Decisions made here, open to the maintainer's overrule:

1. **6 hours** as "recent". Longer means coming back next morning to a stale thread instead of the board.
2. **Fresh launch** = no mark in `sessionStorage`, which starts empty in a new app process and survives page changes within it. Tapping the Board/home link inside a running app is therefore never redirected.
3. **Before first paint:** an inline script in the layout `<head>` (next to the existing theme script) does the check and `location.replace()`, so the home page is not painted first (AGENTS.md, first-paint rule). Saving happens on `pagehide` and on `visibilitychange` to hidden, which also covers fast navigation and nav cached copies, since it reads `location` at the moment of leaving.
4. **What is remembered:** the address of any page the layout renders, query and `#` included. The layout never renders `/api/`, so nothing there is skipped specially.
5. **Feature flag** `FORUM_OWA_RESUME_ENABLED`, on by default and site-mutable, so the operator can turn it off from Tools if it misbehaves (v3 flags serve that purpose).

## Reuse decisions

- `templates/layout.php` already carries an inline before-paint `<script>` for theme choice; the resume snippet sits beside it as its own small inline script, since it must run before paint and an external file would add a blocking request. `standalone_layout.php` pages are not app pages (no manifest) and are left alone.
- Standalone detection matches the existing worker registration's checks (`matchMedia('(display-mode: standalone)')`, `navigator.standalone`).
- Flag: `FeatureFlagRegistry` definition and `TemplateRenderer` pass-through, like `NAV_CACHED_COPY_ENABLED`.
- Storage key namespaced per site with the `BrowserRuntimeProfile` namespace, as other per-site browser keys are.

## Risks

- A redirect loop: impossible because the snippet only redirects when the current path is `/`, the target is not `/`, and it sets the session mark before redirecting.
- Offline launch: the target is fetched through the service worker; pages it cannot answer offline fall back as any offline navigation does. Accepted.
- Scroll restore after `location.replace()` happens once the page has loaded; content that loads later (images) may shift it slightly. Accepted.
- Stale page: the remembered page is fetched fresh (or from the nav copy), not a snapshot.

## Concepts touched

None changed.

## Completion conditions

- In a standalone-emulated browser: open a thread, scroll, background the app (pagehide), start a new session at `/`: lands on the thread at the scroll position. After 6 hours, or with the flag off, stays on `/`. Launching at `/threads/x` stays there. In a normal tab nothing changes.
- Release: on the iPhone SE home-screen app, open a thread, swipe the app away, reopen: the thread. Tap a notification: its page.

## Study history

2026-11-25T07:20Z; scope v1; starting revision `eac0968f`; reference R1, hashes not re-verified. Claude, single exposed rater. Intake **0.12**.

*— Claude · 2026-11-25 UTC · Model: claude-opus-5-5*
