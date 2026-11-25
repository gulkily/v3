# Handoff: `push_tap_routing` (notifications cycle 5)

Written 2026-11-24 by Claude for the next session. Cycle 5 of the [notifications checklist](./notifications_checklist.md), the last MVP cycle. Cycle 3 (`push_ios_guide`) stays skipped for now.

## Where things stand

- Cycle 4 (`push_on_events`, PR #411) is merged: a DM or a reply to your post records a notice, and it is sent to each of your devices. Plans and the Step 4 summary: [push_on_events/](./push_on_events/push_on_events_step4_implementation_summary.md). Its deploy with cron running and its iPhone check are still open in the checklist; if the maintainer hasn't run that check, ask before relying on it.
- zenmemes.com runs PHP as FastCGI (DreamHost), not php-fpm, so notices go out through the detached one-task worker, with cron as the fallback.
- A tap on a notice only focuses the app. `notificationclick` in `public/service_worker.js` focuses **any** open window on the site and ignores the notice's URL; it opens the URL only when no window is open.

## What cycle 5 delivers

A tap opens what the notice is about: the DM conversation, or the reply. If the app is already open, that window goes to the page instead of a new window opening. With no network, the tap shows the cached copy of that page, or Unread when there is none. A folded "N new replies" notice opens Unread.

Run it as a one-shot Railspike cycle (estimate 0.15 in the checklist). Switch to staged only if the URL question below needs the maintainer's choice; say so in the first reply.

## What the URLs are now

Set in `src/ForumRewrite/Push/PushNotices.php` and `PushNotifier::overflowPayload()`; `pushNotice()` in the worker keeps only same-origin paths in `data.url`:

- Reply: `/posts/<post_id>`.
- DM: `/messages/conversation/<sender>`.
- Folded notice: `/unread/`.

## Decide early

1. **Navigating an open window.** `WindowClient.navigate()` may not work for an installed iPhone web app (check Safari's support on iOS 16.4 and later). The fallback is `focus()` plus `postMessage({type: "open-url", url})` to the page, which then calls `location.assign()`, with a small listener loaded on every page. A plain `openWindow()` when no window is open stays as it is.
2. **Offline.** `supportsOfflineNavigation()` covers `/`, `/threads/…` and `/tags/…` only, so `/posts/<id>` and `/messages/conversation/<name>` get no offline page today. Options: point reply notices at the thread instead (`/threads/<thread_id>`, plus a way to land on the reply, which the thread page has no anchor for yet), or add these paths to the worker's cached-copy lookup (`NAV_COPY_CACHE`, see `nav_cached_copy.js`) with Unread as the fallback when nothing is cached. Unread itself must work offline for the fallback to mean anything; check it.
3. **DMs.** The conversation page decrypts in the browser with the key in localStorage. Check that a tap on a cold start lands on a readable conversation, not on a sign-in or key step.

## Code to start from

- `public/service_worker.js`: `notificationclick`, `pushNotice()`, `supportsOfflineNavigation()`, the nav-copy cache (`NAV_COPY_CACHE`, `navCopyRefreshes`) and the existing `message` listener.
- `public/assets/pwa_registration.js` and `nav_cached_copy.js`: page-side code that already talks to the worker.
- `src/ForumRewrite/Push/PushNotices.php` (reply and DM URLs) and `PushNotifier::overflowPayload()` (`/unread/`).
- `docs/concepts/push_subscription.md` (what a notice carries) and the checklist's cycle 5 entry.
- Tests: `PushNotifierTest` and `PushReplyNoticeTest` assert today's URLs. Worker behaviour has no PHP test, so check it by hand in a browser, then on the phone.

## Done means

- On the iPhone SE home-screen app: tapping a reply notice and a DM notice each opens that page, (a) with the app closed, (b) with it open on another page, (c) in airplane mode. A folded notice opens Unread. A desktop pass does not count as the phone check; record it as pending if it can't be run.
- Then the checklist's **MVP release check**: turn on, get a DM and a reply from a second account with the app closed, tap each, turn off.

## Keep in view

- `PRINCIPLES.md`; `AGENTS.md` (feedback items, smoke tests only, no new dependency without approval, UI copy rules); the Railspike effort study enrollment.
- Pages must look final on first paint. A tap that lands on a page must not flash another page first.
- Follow-ups go on a new branch from a fresh `origin/main`.

*— Claude · 2026-11-24 UTC · Model: claude-opus-5-5*
