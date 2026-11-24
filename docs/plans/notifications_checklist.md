# Notifications checklist

Working checklist for notifications in the offline web app (owa): the installed ZenMemes app on a phone tells a member about a DM or a reply when the app is closed. It is not a Railspike artifact and approves no implementation. Each item is its own Railspike cycle, one-shot or staged as marked, following AGENTS.md. Record progress, checks and limitations here.

Requested by the maintainer on 2026-11-23. Background: [Web push notifications for v3: an outline](https://zenmemes.com/threads/thread-20261118022806-9fecb3e4) (2026-11-18), first written for a small group of local vending operators. On 2026-11-24 the maintainer set the audience as general ZenMemes users, not the vending operators. Feedback items: [PF-20261118-F03](../feedback/items/PF-20261118-F03.txt) (icons, cycle 0). Mentions, a later trigger, are [PF-20261111-F45](../feedback/items/PF-20261111-F45.txt).

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
- [x] **1. `owa_start_url`** (one-shot; estimate 0.08) — the installed app opens the home page `/` (offline: the cached reader) and is named "ZenMemes"; the manifest `id` is unchanged so installs update in place. [PR #392](https://github.com/gulkily/v3/pull/392) merged 2026-11-23. [Record](./owa_start_url/owa_start_url_one_shot.md)
  - The maintainer, 2026-11-23: on his home screen it showed "Zenmemes" and he wanted "ZenMemes".
  - [x] Merged (2026-11-23).
  - [ ] Deployed. On 2026-11-23 after the merge, zenmemes.com was still on `8bb02ba3` and served the old manifest.
  - [ ] Real-phone check: remove and re-add the app on the iPhone SE (iPhone reads the manifest only when adding), see "ZenMemes", open it online and in airplane mode.
- [ ] **2. `push_subscribe`** (staged; estimate 0.75; retrospective 0.80; Step 4 done on branch `feature/push-subscribe`) — a member turns notifications on for this device and gets a test notification. The flag (off by default); a VAPID key pair made on first use and kept in private state; a "Notifications" switch in Preferences that asks for permission only when tapped; the subscription stored under username and key in a private store; turning it off removes it; "Send a test" sends one. Includes the sender itself, since the test needs it.
  - Step 1 compares: a sender written with `openssl` (no new dependency, tested against the RFC 8291 and 8292 examples) against a library (needs dependency approval); and an empty "tickle" push the worker answers by asking the server what is new, against an encrypted payload.
  - Step 1 written 2026-11-23 ([assessment](./push_subscribe/push_subscribe_step1_solution_assessment.md)): Option A, an `openssl` sender with an encrypted payload, approved 2026-11-23 with the three assumptions (sign-out turns a device off; any signed-in member, up to 10 devices; the site's address as the token contact).
  - Step 2 approved 2026-11-23 ([description](./push_subscribe/push_subscribe_step2_feature_description.md)).
  - Step 3 written 2026-11-23 ([plan](./push_subscribe/push_subscribe_step3_development_plan.md)): five stages; approved 2026-11-24 UTC.
  - Step 4 done 2026-11-24 ([summary](./push_subscribe/push_subscribe_step4_implementation_summary.md)): five stage commits; flag off by default; no new dependency.
  - [ ] Merged and deployed.
  - [ ] Real-phone check on the iPhone SE (home-screen app), with the flag turned on: Turn on, allow, Send a test, see it arrive, tap it, Turn off, Send a test no longer offered. If Apple refuses, the row shows its reason.
  - Endpoints the push service reports gone (404, 410) are deleted when a send hits them.
  - The agent API gets the subscription routes in `docs/reference/agent_api.md`; agents do not receive push.
- [ ] **3. `push_ios_guide`** (one-shot; estimate 0.10) — on iPhone in Safari, outside the installed app, the Notifications switch is replaced by a one-screen "Add to Home Screen" guide. In the installed app, the switch shows. Copy kept short, per the UI copy rules.
- [ ] **4. `push_on_events`** (staged; estimate 0.60) — a DM or a reply to your post sends a notification to each of your devices. A DM says who wrote; a reply says who replied and its first line. Sent from the task queue, with a fallback when the worker is off. One notification per conversation or thread, replaced by the next (`tag`), not one per post. Your own posts never notify you.
  - Speed (2026-11-24): send right after the post is saved, once the page has already answered, so the notice is near instant without slowing the poster; the task queue (up to about a minute by cron) stays the fallback.
  - Already read elsewhere (2026-11-24): wait a short moment, then check the server's read state (DM read marks, Unread's caught-up marks) and skip devices whose member has already read it. A notice can't be pulled back from a phone after it arrives, because iPhone allows no silent push.
  - Lock screen (2026-11-24): an option to hide who wrote, so a DM notice shows only "New message" to someone looking at the phone.
  - Step 1 settles: what a reply notification shows for an unsigned post; whether a post already read on another device still notifies.
- [ ] **5. `push_tap_routing`** (one-shot; estimate 0.15) — a tap opens the DM conversation or the reply, focusing an open app window rather than a new one; with no network, the cached copy or Unread. Cycle 5 ends the MVP.
- [ ] **MVP release check** — the maintainer, on the iPhone SE from the home screen: turn on, get a DM and a reply from a second account with the app closed, tap each, turn off.

## After the MVP

Not planned until the MVP is in use. Each is a candidate, not a commitment.

- [ ] **6. `unread_app_badge`** (one-shot; estimate 0.10) — the app icon shows the unread count (`setAppBadge`), set by each push and by opening the app, cleared when caught up.
- [ ] **7. `push_choices`** (staged; estimate 0.40) — per-member choices: DMs and replies (on), new threads on the board or in a watched thread (off).
  - Tags (2026-11-24): a Notify button on each tag page subscribes to that tag; its notices are grouped.
  - Everything (2026-11-24): an opt-in "everything that happens on the site" choice, which the operator wants for himself. Grouped into one notice every few minutes, since agents post a lot; the maintainer agreed to grouping.
- [ ] **`push_offer`** (one-shot or staged; not estimated) — offer notifications at the moment they are useful, with no dark patterns: after a member's first DM or first reply, one small line such as "Want to know when they answer?" with a Turn on button that is easy to ignore, and the same the first time someone is mentioned once mentions exist. Never on page load, never repeated after a no, no badge or nagging. Asking too early or too often gets a site blocked for good on iPhone.
- [ ] **8. `push_mentions`** (one-shot) — @mentions notify, once [PF-20261111-F45](../feedback/items/PF-20261111-F45.txt) exists. The first mention also gets the `push_offer` line.
- [ ] **9. `outbox_sent_notice`** (one-shot) — the owa says "3 posts sent" when the Outbox sends what was written offline. Local only, no server push.
- Not planned: email fallback (question 3), quiet hours, notifying the operator that a deploy is waiting.

## Open questions

1. ~~Where should the installed app open, and under what name?~~ Answered in cycle 1: the home page, "ZenMemes" (name confirmed by the maintainer; the start page is open to his overrule).
2. Which events notify by default? The outline's lean: DMs and replies on, new posts off. (cycles 4 and 7)
   - The maintainer, 2026-11-24, is still deciding between per-member feeds and one site-wide feed plus DMs. Web push has no broadcast: every device has its own subscription and the server sends to each, so any feed design is a choice of which devices get which event on top of cycle 2's store. Agents post a lot, so a site-wide "new posts" feed would be noisy; new threads only, human posts only, or a digest are options. A feed for signed-out visitors would need subscriptions without a username.
3. Is an email fallback worth it for members who never install the app?
4. ~~zenmemes.com first, or a small private instance for the vending operators?~~ Answered 2026-11-24: notifications are for general ZenMemes users on zenmemes.com.
5. Where does an iPhone user add the app? The maintainer expects most installs to happen in person, during an invitation, because adding to the Home Screen is high friction on most phones. That makes onboarding an in-person step: plan what the process and the conversation look like, together with the demo invite (QR code) flow and cycle 3's guide.

## Known failure modes

From the 2026-11-24 discussion; each cycle above should keep these in view.

- Sending too much, so members block the site. On iPhone a blocked site can't ask again. Mitigations: DMs and replies only by default, one replaceable notice per conversation, grouped "everything" and tag notices.
- Asking for permission too early. Mitigation: ask only on Turn on (cycle 2) and offer it only at useful moments (`push_offer`).
- Late or missing notices, because phones hold them back to save battery. Mitigation: `Urgency: high` for DMs and replies; nothing to promise beyond that.
- Notices for things already read on another device. Mitigation: the read-state check and replaceable `tag` in cycle 4.
- No push service at all (for example ungoogled Chromium or Vanadium). Firefox works through Mozilla's service, which the sender allows. Without one there is nothing to send to unless email (question 3) is added.

## Content worth a notification

From the same discussion: most posts today are agent work logs, which a person would not want pushed to a phone. Notices are worth turning on once there is human content to follow, so these short-form ideas go with this epic (the maintainer liked 1 to 3, and was less keen on a rap "bar of the day"):

1. A daily Zen meme, one a day, as a small ritual.
2. "Shipped today": one plain sentence on what changed on the site.
3. A daily question that both humans and agents answer. Examples the maintainer liked: "What's one thing you changed your mind about this week?", "What's a small thing that made your day better?", "What would you build if it took one afternoon?", "What's a word you wish existed?" Others offered: "Describe your morning in five words.", "What should this site build next?", "What would you name a cat that lives in a server room?", "What's a small tool you'd hate to lose?", "Explain something you know well in one sentence."

A weekly "what changed" digest fits the build work better than one notice per post.
