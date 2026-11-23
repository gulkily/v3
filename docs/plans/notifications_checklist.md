# Notifications checklist

Working checklist for notifications in the offline web app (owa): the installed ZenMemes app on a phone tells a member about a DM or a reply when the app is closed. It is not a Railspike artifact and approves no implementation. Each item is its own Railspike cycle, one-shot or staged as marked, following AGENTS.md. Record progress, checks and limitations here.

Requested by the maintainer on 2026-11-23. Background: [Web push notifications for v3: an outline](https://zenmemes.com/threads/thread-20261118022806-9fecb3e4) (2026-11-18), written for a small group of local vending operators who would use a v3 instance instead of a group text (two on Android, the rest on iPhone). Feedback items: [PF-20261118-F03](../feedback/items/PF-20261118-F03.txt) (icons, cycle 0). Mentions, a later trigger, are [PF-20261111-F45](../feedback/items/PF-20261111-F45.txt).

Everything ships behind one site flag, added in cycle 2 and off by default. Web push on iPhone works only from an app added to the home screen, on iOS 16.4 or later; the release check for every cycle is the maintainer's iPhone SE, installed from the home screen, plus one Android phone where one is at hand.

The MVP is cycles 0 to 5: a member turns notifications on, and gets a DM or a reply as a notification that opens the right page. Everything after that waits for the MVP to be used.

## Reuse inventory

Found on a first read at `93295c82`. Each cycle re-checks its own part before planning.

- `public/service_worker.js` (scope `/`, registered by `public/assets/pwa_registration.js`) has a `message` listener and no `push` or `notificationclick` listener. Push goes in this worker; no second worker.
- `BrowserRuntimeAssetRenderer::manifest()` builds the manifest: `start_url` `/offline/`, name from `manifestName` ("Zenmemes Offline Reading"), icons from `FaviconRegistry::manifestIcons()`. `public/assets/app-icon-180.png`, `-192.png` and `-512.png` exist (cycle 0).
- `/api/inbox/unread` (used by `public/assets/inbox_unread.js`) gives the unread count; `PrivateMessageReadState::unreadConversations()` lists who wrote and how many; `src/ForumRewrite/Inbox/` holds the caught-up marks (`InboxMarkStore`, `InboxFeed`). Unread is behind `FORUM_INBOX_ENABLED`.
- Private SQLite stores kept apart from content and the public export: `UsageReportStore` (`state/private/usage_reports.sqlite3`) and the Inbox marks. Push subscriptions belong in a store like these, never in the content repository.
- `src/ForumRewrite/TaskQueue/` (`SqliteTaskQueueStore`, `TaskQueueWorker`, `DetachedTaskQueueLauncher`) for sending off the request path. The worker may be off on a new install (`import_without_worker`), so sending needs a fallback.
- `templates/pages/preferences.php` for the per-device switch. `FeatureFlagRegistry` for the flag.
- PHP has `openssl` and `sodium`. Web push encryption (RFC 8291, aes128gcm) and VAPID signing (RFC 8292, ES256) can be written with `openssl` alone; a library such as `minishlink/web-push` needs dependency approval under CONTRIBUTING.md.
- DMs are end-to-end encrypted, so the server can say who wrote and never what they wrote.

## Cycles

- [x] **0. `app_icons`** (one-shot) — 180, 192 and 512 px icons; the manifest lists them; iPhone's Add to Home Screen gets the Z instead of a screenshot. [PR #332](https://github.com/gulkily/v3/pull/332) merged 2026-11-18. [Record](./app_icons/app_icons_one_shot.md)
  - [ ] Real-phone check: add to home screen on the iPhone SE and see the icon.
- [x] **1. `owa_start_url`** (one-shot; estimate 0.08) — the installed app opens the home page `/` (offline: the cached reader) and is named "ZenMemes"; the manifest `id` is unchanged so installs update in place. [PR #392](https://github.com/gulkily/v3/pull/392) open. [Record](./owa_start_url/owa_start_url_one_shot.md)
  - The maintainer, 2026-11-23: on his home screen it showed "Zenmemes" and he wanted "ZenMemes".
  - [ ] Merged and deployed.
  - [ ] Real-phone check: remove and re-add the app on the iPhone SE (iPhone reads the manifest only when adding), see "ZenMemes", open it online and in airplane mode.
- [ ] **2. `push_subscribe`** (staged; estimate 0.70) — a member turns notifications on for this device and gets a test notification. The flag (off by default); a VAPID key pair made on first use and kept in private state; a "Notifications" switch in Preferences that asks for permission only when tapped; the subscription stored under username and key in a private store; turning it off removes it; "Send a test" sends one. Includes the sender itself, since the test needs it.
  - Step 1 compares: a sender written with `openssl` (no new dependency, tested against the RFC 8291 and 8292 examples) against a library (needs dependency approval); and an empty "tickle" push the worker answers by asking the server what is new, against an encrypted payload.
  - Endpoints the push service reports gone (404, 410) are deleted when a send hits them.
  - The agent API gets the subscription routes in `docs/reference/agent_api.md`; agents do not receive push.
- [ ] **3. `push_ios_guide`** (one-shot; estimate 0.10) — on iPhone in Safari, outside the installed app, the Notifications switch is replaced by a one-screen "Add to Home Screen" guide. In the installed app, the switch shows. Copy kept short, per the UI copy rules.
- [ ] **4. `push_on_events`** (staged; estimate 0.60) — a DM or a reply to your post sends a notification to each of your devices. A DM says who wrote; a reply says who replied and its first line. Sent from the task queue, with a fallback when the worker is off. One notification per conversation or thread, replaced by the next (`tag`), not one per post. Your own posts never notify you.
  - Step 1 settles: what a reply notification shows for an unsigned post; whether a post already read on another device still notifies.
- [ ] **5. `push_tap_routing`** (one-shot; estimate 0.15) — a tap opens the DM conversation or the reply, focusing an open app window rather than a new one; with no network, the cached copy or Unread. Cycle 5 ends the MVP.
- [ ] **MVP release check** — the maintainer, on the iPhone SE from the home screen: turn on, get a DM and a reply from a second account with the app closed, tap each, turn off. Then the same with one vending operator if the trial goes ahead (question 4).

## After the MVP

Not planned until the MVP is in use. Each is a candidate, not a commitment.

- [ ] **6. `unread_app_badge`** (one-shot; estimate 0.10) — the app icon shows the unread count (`setAppBadge`), set by each push and by opening the app, cleared when caught up.
- [ ] **7. `push_choices`** (staged; estimate 0.40) — per-member choices: DMs and replies (on), new threads on the board or in a watched thread (off).
- [ ] **8. `push_mentions`** (one-shot) — @mentions notify, once [PF-20261111-F45](../feedback/items/PF-20261111-F45.txt) exists.
- [ ] **9. `outbox_sent_notice`** (one-shot) — the owa says "3 posts sent" when the Outbox sends what was written offline. Local only, no server push.
- Not planned: email fallback (question 3), quiet hours, notifying the operator that a deploy is waiting.

## Open questions

1. ~~Where should the installed app open, and under what name?~~ Answered in cycle 1: the home page, "ZenMemes" (name confirmed by the maintainer; the start page is open to his overrule).
2. Which events notify by default? The outline's lean: DMs and replies on, new posts off. (cycles 4 and 7)
3. Is an email fallback worth it for members who never install the app?
4. zenmemes.com first, or a small private instance for the vending operators?
