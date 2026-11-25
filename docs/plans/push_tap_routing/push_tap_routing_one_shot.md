# Notification taps open their page — one-shot

Cycle: `v3/push_tap_routing`; Mode: one-shot; Record/index: this file, [plans index](../README.md), [notifications checklist](../notifications_checklist.md) cycle 5
Debug mode: enabled ([log](push_tap_routing_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/push_tap_routing`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_ledger/push_tap_routing.md))
Extended estimates: enabled (no alternatives or work units, so only the cycle estimate applies)

**Cycle estimate: 0.15**

Status: implemented on `feature/push-tap-routing`; PR open, not merged or deployed. Next action: the maintainer merges and deploys, then runs the iPhone SE checks below and the MVP release check.

## Request and authorization

The maintainer, 2026-11-24 19:57 ET, in a Claude session: "Continue the ZenMemes notifications epic with cycle 5, push_tap_routing … Run it as a Railspike one-shot unless the offline-URL decision in the handoff needs my choice … take it through to a PR, and list the iPhone SE checks I need to run." The [handoff](../push_tap_routing_handoff.md) (PR #413) sets the outcome. Authorizes a one-shot through an open PR; excludes merge, deployment and the phone checks.

Feedback item: none matched in `docs/feedback/items/` (searched "notificationclick", "tap", "notification").

## Outcome and work type

Application change. A tap on a notice opens what it is about: a reply opens its thread at that reply, a DM opens the conversation, a folded "N new" notice opens Unread. If the app is already open, that window goes to the page rather than only coming to the front. With no network, a reply opens the thread from the offline reader; a DM or a folded notice, which have no offline page, opens the home page (the offline reader).

## Decisions (made here, open to the maintainer's overrule)

1. **Reply URL** becomes `/threads/<thread_id>#post-<post_id>` instead of `/posts/<post_id>`. Online, thread pages already give every reply `id="post-<id>"` (`templates/partials/post_card.php`) and have no pagination, so the browser lands on the reply in its thread. Offline, `/threads/…` is in `supportsOfflineNavigation()` and the offline reader renders the same `post-<id>` ids (`offline_reader.js`). The handoff thought the thread page had no anchor; it does.
2. **Offline fallback for DMs and folded notices is the home page, not Unread.** The handoff asked to check that Unread works offline; it does not (`/unread/` is not in `supportsOfflineNavigation()` and has no cached shell), and neither does a DM conversation. `/` offline is the cached reader, which is what the installed app opens offline (cycle 1). Making Unread or a conversation readable offline would be its own cycle.
3. **Open window:** `focus()`, then `WindowClient.navigate(url)`. Where `navigate` is missing or fails (it rejects for a window this worker doesn't control, and its support in iPhone home-screen apps is unconfirmed), the worker posts `{type: "open-url", url}` and a listener in `pwa_registration.js`, loaded on every layout page, calls `location.assign()` for a same-origin path. A window already at that URL is only focused. No window: `openWindow(url)`, as now.

## Approach and risks

- Risk: an open window shows its current page for a moment before it navigates. That is the app coming to the front, not a wrong page painted by the tap; a cold start opens the right page directly.
- Risk: a reply that arrived after the last offline snapshot is not in it, so offline the thread opens without that reply. Accepted: the snapshot refreshes whenever the app is opened online.
- Risk: the offline reader renders after load, so the browser's own jump to `#post-<id>` finds nothing. The reader scrolls to the hash target after it renders a thread.
- Risk: `self.navigator.onLine` can be wrong. Used only to pick the offline fallback for paths with no offline page; when it says online but the network fails, the tap behaves as any other uncached navigation does today.

## Reuse decisions

- `notificationclick` and `supportsOfflineNavigation()` in `public/service_worker.js`: extended in place; no second worker.
- `public/assets/pwa_registration.js` is the page-side script already loaded on every layout page that talks to the worker; the listener goes there rather than in a new asset.
- `PushNotices::recordReply()` already reads the post row, which carries `thread_id`; only the URL string changes. `PushNotifier` passes URLs through untouched; the folded notice's `/unread/` is unchanged.
- `renderOfflineThread()` in `offline_reader.js` gets the scroll; the `post-<id>` ids it already sets are reused.
- Tests extend `PushReplyNoticeTest` (URL), `OfflineNavigationWorkerTest` (worker tap routing under node's `vm`, like its other worker tests) and the worker block of `tests/browser/push_preferences.mjs`, which asserts the current focus-only behaviour.

## Concepts touched

`docs/concepts/push_subscription.md` (what a notice carries): the reply URL and what a tap does change; updated in place.

## Completion conditions

- A reply notice's URL is `/threads/<thread_id>#post-<post_id>`; DM and folded URLs unchanged.
- Worker: no window → opens the URL; an open window → focused and sent to the URL (navigate, else open-url message); offline with a path that has no offline page → `/`.
- Page: an `open-url` message for a same-origin path navigates; anything else is ignored.
- Offline reader: `/threads/<id>#post-<reply>` scrolls to the reply.
- Release: the handoff's iPhone SE checks (reply and DM taps with the app closed, open on another page, and in airplane mode; a folded notice opens Unread), then the MVP release check. A desktop pass does not count.

## Verification and recovery plan

`./v3 test --smoke PushReplyNoticeTest PushNotifierTest OfflineNavigationWorkerTest`; `node tests/browser/push_preferences.mjs` with the local Chrome; a by-hand browser check of the offline reader scroll if a local fixture allows it. The full suite is not run (AGENTS.md). Recovery for a member: a tap that lands on the wrong page leaves the app usable; nothing is stored.

Starting revision `87dd42d6` on `main`; branch `feature/push-tap-routing`.
Planning commit: `dab9f54` (confirmed with Git before any implementation edit).

## Execution history

- 2026-11-24: plan written. Decisions 1–3 above taken under the handoff's one-shot instruction.
- 2026-11-25: implemented. `service_worker.js`: `noticeTapUrl()` (same-site only; offline, paths with no offline page become `/`) and `openNoticePage()` (no window → `openWindow`; a window already on the URL → focus only; else focus, `navigate()`, else `open-url` message). `pwa_registration.js`: the `open-url` listener, same-site paths only, plus `startMessages()` so a message sent while the page loads is not held. `offline_reader.js`: `renderOfflineThread()` scrolls to `#post-<id>`. `PushNotices::recordReply()`: the thread URL. `docs/concepts/push_subscription.md`: taps described; the "proposed" line for this cycle removed.
- 2026-11-25: correction to the plan's test list: `tests/browser/push_preferences.mjs` asserted that a tap with an open window only focuses it; updated to expect focus plus `navigate()`.

## Outcome

Delivered as planned. Limitations: an open window shows its current page for a moment before it moves; offline, a reply newer than the device's snapshot is not in the thread the reader shows; DMs and Unread offline open the home page. Not verified: any real phone, and which of `navigate()` or the `open-url` message runs in an iPhone home-screen app.

## Verification

- `./v3 test --smoke OfflineNavigationWorkerTest PushReplyNoticeTest PushNotifierTest`: 515 run, 515 passed. New: `OfflineNavigationWorkerTest::testNotificationTapOpensItsPageInTheOpenWindowOrANewOne` (closed app, navigate, navigate failing, no navigate, window already there, another site's URL, offline DM/Unread/thread) and `testPageFollowsOnlySameSiteOpenUrlMessages`; `PushReplyNoticeTest` asserts the thread URL. The full suite was not run.
- `node tests/browser/push_preferences.mjs` (local Chrome, `playwright-core` from outside the repository): ok.
- Local replay (a disposable Playwright script outside the repository, `parity_minimal_v1` fixture, zenmemes profile, 375 by 260 px so the reply is below the fold): online, `/threads/root-001#post-reply-001` lands on the reply. Offline after the reader is cached, the same address shows the offline reader with the reply at the top (scrollY 310); with this cycle's `offline_reader.js` change removed it stayed at the top of the page (scrollY 0). No page errors.
- Not run: `notificationclick` in a real browser (headless Chrome cannot tap a notification); the iPhone SE checks.

## Final review

Diff checked: the worker changes stay in the existing listener and reuse `supportsOfflineNavigation()`; the page listener sits in the script that already registers the worker; no new asset, dependency, UI text, flag or navigation destination. Files are within the plan's scope plus this record, the debug log, the ledger, the checklist, the plans index and the concept page.

## Commit, merge and publication

Planning commit `dab9f54`; completion commit `7e47b05`; [PR #415](https://github.com/gulkily/v3/pull/415) open. Not merged, not deployed.

## Study history

2026-11-25T01:20Z; scope v1; starting revision `87dd42d6`; reference R1, hashes not re-verified. Claude, single exposed rater (saw the checklist's 0.15). Intake **0.15**.

2026-11-25T01:55Z; closeout; scope v1 unchanged. Retrospective **0.13**: the anchor already existed, so the URL question took less than the handoff expected; the offline reader scroll was a small addition.

*— Claude · 2026-11-25 UTC · Model: claude-opus-5-5*
