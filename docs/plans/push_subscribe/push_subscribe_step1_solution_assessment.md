> **Feature plan:** [Step 1](./push_subscribe_step1_solution_assessment.md) · [Step 2](./push_subscribe_step2_feature_description.md) · [Step 3](./push_subscribe_step3_development_plan.md) · [Step 4](./push_subscribe_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/push_subscribe`; host ledger [push_subscribe.md](../effort_ledger/push_subscribe.md). Standing P2 enrollment. New cycle; the [notifications checklist](../notifications_checklist.md) gave an inherited estimate of 0.70.

Extended estimates: enabled. Each option's figure is that option's whole-cycle projection; they are alternatives, not summed.

Cycle estimate: 0.70

## Original Query

Next: start cycle 2, push_subscribe, as a staged Railspike cycle. Write Step 1 (solution assessment) only, then stop for my approval. Scope is in the checklist: a feature flag (off by default), a VAPID key pair made on first use and kept in private state, a per-device Notifications switch in Preferences that asks for permission only when tapped, subscriptions stored per username and key in a private SQLite store (never the content repo or public export), turning it off removes the subscription, and a "Send a test" button. The sender is part of this cycle because the test needs it.

Step 1 must compare:
- a sender written with PHP openssl only (RFC 8291 aes128gcm + RFC 8292 VAPID ES256, no new dependency, tested against the RFC examples) vs a library like minishlink/web-push (needs dependency approval per CONTRIBUTING.md)
- an empty "tickle" push where the worker asks the server what's new vs an encrypted payload (iOS requires showing a notification for every push)

Reuse to check: public/service_worker.js (scope /, no push listener yet), public/assets/pwa_registration.js, BrowserRuntimeAssetRenderer::manifest(), UsageReportStore and src/ForumRewrite/Inbox/ (private SQLite stores), src/ForumRewrite/TaskQueue/, templates/pages/preferences.php, FeatureFlagRegistry. DMs are end-to-end encrypted, so a notification can name who wrote but never what.

Release target: iPhone SE, app added to the home screen (iOS 16.4+). Background: https://zenmemes.com/threads/thread-20261118022806-9fecb3e4

## Problem

Nothing in v3 can reach a member whose app is closed: no device subscribes, the site has no push key pair, and no code sends to a push service.

Concepts touched: [feature flags](../../concepts/feature_flags.md), changed (one new flag); [viewer session](../../concepts/viewer_session.md), used unchanged (it names the username and key); push subscription, proposed new entry. Feedback: no matching item in `docs/feedback/items/`.

## What the reuse check found

- `public/service_worker.js` is the one worker at scope `/`. It gets the `push` and `notificationclick` listeners. `pwa_registration.js` already registers it on every page, so the switch can use `navigator.serviceWorker.ready`.
- `BrowserRuntimeAssetRenderer::manifest()` already sets `display: standalone`, which iPhone needs before it offers push. No manifest change is needed.
- `UsageReportStore` and `InboxMarkStore` show the pattern: one SQLite file under `state/private/`, created on first use. `UsageReportsExportExclusionTest` shows how to prove the export never sees it.
- The task queue stores no payload and may be off on a new install. "Send a test" makes one HTTP request to the push service, so it sends during the tap. Queued sending waits for cycle 4.
- `templates/pages/preferences.php` keeps its rows in `localStorage`, so each row is already per device. The Notifications row follows the same row markup.
- The project has no Composer, no `vendor/` and no PHP package of any kind.

## Shared by every option

- A new flag `FORUM_PUSH_ENABLED`, off by default. With it off, the row is hidden, the routes return 404, and nothing is sent. Stored subscriptions stay until a member turns them off.
- The server makes the VAPID key pair with `openssl` on the first subscribe. It keeps the private key in `state/private/`, readable only by its owner. If the key changes, the switch sees a new public key and subscribes again without asking.
- One row per device in `state/private/push.sqlite3`: username, key fingerprint, endpoint, the browser's two keys, and the times it was created and last sent to. A second member on the same device replaces the row, because a browser has one endpoint.
- The row shows only to a signed-in member, with the flag on, in a browser that supports push. A tap on On asks iPhone for permission, subscribes and stores the row. Off unsubscribes and deletes the row.
- "Send a test" sends to this device only and shows the push service's answer. A 404 or 410 deletes the row.
- The server sends only to `https` endpoints on known push hosts (Apple, Google, Mozilla, Microsoft). It refuses any other host when a device subscribes, so a member can't make the server call an arbitrary address.
- `docs/reference/agent_api.md` lists the subscription routes; agents do not receive push.

## Option A — Our own `openssl` sender with an encrypted payload

**Estimate: 0.70**
Main driver: about 60 lines of encryption and signing, plus the store, routes, switch and worker listener. Uncertainty: low, since the probe below passed.

The server encrypts a short payload (title, text, page to open, `tag`) for each device and signs a VAPID token. The worker shows the payload as it arrives. A disposable probe outside the repository, on PHP 8.3 and OpenSSL 3.0, matched the RFC 8291 Appendix A message byte for byte. It also verified the RFC 8292 example token and signed and verified a fresh one.

- Pro: no new dependency, and the RFC examples become tests in `tests/`.
- Pro: the notification shows with no network round trip, even with an expired session or a weak signal.
- Pro: Apple only sees ciphertext. For a DM the text is only who wrote, so nothing secret is in it anyway.
- Con: we own the crypto code. The fixed RFC examples and a real iPhone test are the safeguard.

## Option B — Our own sender with an empty "tickle" push

**Estimate: 0.70**
Main driver: Option A without the encryption, plus a pending-notice store and an endpoint the worker calls. Uncertainty: medium, from iPhone's rules for a push.

The server sends an empty push signed with VAPID. The worker asks the server for this device's pending notice, using the session cookie, and shows it. If that request fails, iPhone still needs a notification, so the worker shows a plain "Something new on ZenMemes".

- Pro: half the crypto code, since an empty push needs no encryption.
- Pro: even who wrote never passes through the push service.
- Con: each notification waits for a request to our server, and the generic text shows exactly when the signal is weak.
- Con: the server keeps per-device pending notices and one more endpoint.

## Option C — The `minishlink/web-push` library with an encrypted payload

**Estimate: 0.90**
Main driver: adding Composer and a vendor tree to a project that has neither, and making deploys carry it. Uncertainty: medium.

The library does the encryption and signing; our code keeps the store, routes, switch and worker. Version 11.0.0 (MIT, July 2026) needs PHP 8.2, while v3 supports PHP 8.1. It pulls about a dozen packages, among them `web-token/jwt-library`, `spomky-labs/base64url` and the PSR HTTP interfaces. It also needs an HTTP client such as Guzzle, found through `php-http/discovery`.

- Pro: a maintained library with years of use.
- Con: Deploy now runs `git pull` only, so the vendor tree is either committed or needs a new deploy step.
- Con: it raises the PHP floor, and needs dependency approval with the full inventory.

## Recommendation

Option A. The probe shows the crypto is small and checkable against fixed examples. The encrypted payload is the most reliable way to meet iPhone's rule that every push shows a notification. Option B's privacy gain is small, because a DM notification only names who wrote. Option C adds more to deploys than the sender itself would.

Vertical slice: turn the flag on at zenmemes.com. On the iPhone SE, open the home-screen app, go to Preferences and tap Notifications. Allow the prompt, then tap Send a test. A "ZenMemes" test notification appears, and a tap opens the app. Turn the switch off: the row is gone from the store, and Send a test disappears.

## Open questions for the maintainer

1. Should signing out turn this device's notifications off? This plan assumes yes, so a shared device stops getting someone else's notices.
2. May every signed-in member turn notifications on, or only approved members? This plan assumes every signed-in member, with at most 10 devices per username.
3. The VAPID token names a contact. This plan assumes the site's own `https` address rather than an operator email.

— Claude (Opus 5.5), 2026-11-23

## Study snapshot and approval state

| Field | Value |
| --- | --- |
| Checkpoint | Step 1 handoff |
| Projected total (recommended scope, Option A) | 0.70 |
| Alternatives (not summed) | A 0.70, B 0.70, C 0.90 |
| Inherited estimate | 0.70, from the notifications checklist (Claude, 2026-11-23) |
| Reference / protocol | R1 / P2 |
| Rater | Claude, single exposed rater |
| Exposure | Estimated after reading the reuse points and running the crypto probe, so not strictly prospective |
| Baseline | `9f73088e` |
| Approval | **Approved Step 1** by the maintainer, 2026-11-23, with all three open assumptions accepted |
