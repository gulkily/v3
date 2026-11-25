> **Feature plan:** [Step 1](./push_subscribe_step1_solution_assessment.md) · [Step 2](./push_subscribe_step2_feature_description.md) · [Step 3](./push_subscribe_step3_development_plan.md) · [Step 4](./push_subscribe_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/push_subscribe`; host ledger [push_subscribe.md](../effort_ledger/push_subscribe.md). Standing P2 enrollment.

Extended estimates: enabled.

**Cycle estimate: 0.75**

## Problem

The installed ZenMemes app can't reach a member while it is closed. A member has no way to turn notifications on for a device, and the server has no way to send one.

## Approved direction

Step 1 was approved on 2026-11-23 with Option A: our own `openssl` sender with an encrypted payload, and no new dependency. The maintainer accepted all three assumptions: signing out turns the device's notifications off; any signed-in member may turn them on, up to 10 devices per username; the push token names the site's `https` address as its contact.

Concepts touched: [feature flags](../../concepts/feature_flags.md), changed (`FORUM_PUSH_ENABLED`); [viewer session](../../concepts/viewer_session.md), changed (sign-out also removes the device's subscription); push subscription, proposed new entry `docs/concepts/push_subscription.md`. Feedback: no matching item in `docs/feedback/items/`.

## User stories

- As a member on my iPhone, I want to turn notifications on for this device in Preferences, so that the app can reach me when it is closed.
- As a member, I want a test notification on demand, so that I know this device really works.
- As a member, I want Off, or signing out, to stop notifications on this device, so that a shared or old device stops getting my notices.
- As an operator, I want all of this behind one flag that is off by default, so that I choose when my site offers it.

## Core requirements

- With `FORUM_PUSH_ENABLED` off, the Notifications row is hidden, the subscription routes return 404, and nothing is sent.
- The browser asks for permission only after the member taps On. The site never asks on page load.
- Subscriptions live only in `state/private/push.sqlite3`, and the VAPID private key only in `state/private/`. Neither reaches the content repository, a backup archive or the public export.
- The server accepts and sends only to `https` endpoints on the Apple, Google, Mozilla and Microsoft push hosts. A push service answer of 404 or 410 deletes that device's row.
- The sender passes the RFC 8291 Appendix A and RFC 8292 examples as tests, using only PHP's bundled `openssl`.

## Delivery scope

- Work type: application change. It covers the service worker, the Preferences page, a new private store and sender under `src/ForumRewrite/`, new routes, the flag, tests, the concept entry and `docs/reference/agent_api.md`.
- Out of scope: notices for DMs and replies (cycle 4), the iPhone "Add to Home Screen" guide (cycle 3), tap routing beyond opening the app (cycle 5), and the task queue.

## Completion boundary

- Normal entry: Preferences → Notifications in the installed app.
- End-to-end outcome: On, then Send a test, shows a "ZenMemes" notification on that device. A tap on it opens the app.
- Recovery: a refused permission leaves the row Off and says it was blocked, so the member can change it in iPhone Settings and tap again. A gone endpoint removes the row, and the switch shows Off.
- Release: merged, deployed, the flag turned on at zenmemes.com, and the maintainer's iPhone check passed.

## Known user and environment

- The maintainer on an iPhone SE (3rd gen), iOS 16.4 or later, in ZenMemes added to the home screen, at zenmemes.com.
- Failures to handle: the permission prompt never appears, the test never arrives, or Apple refuses the token. The row then shows the reason in a few words, and Send a test can be tried again.
- Evidence: the test notification on that iPhone, then Off, then a second test that the server refuses because the row is gone. A desktop browser or Android test does not replace this.

## Risks

- **Apple refuses our token or our message.** Nothing arrives, with no clear error. Validate first by sending one test from a script to the maintainer's iPhone subscription. Mitigate by showing Apple's status and reason on Send a test.
- **PHP 8.1 lacks a feature the sender uses.** Production may fail where 8.3 passed. Validate by running the sender tests on PHP 8.1 early. Mitigate by building keys from DER bytes, which every supported version reads.
- **iPhone drops a subscription after a push without a notification.** Notifications stop for good. Mitigate by having the worker always show a notification, with a plain fallback if the payload can't be read.
- **The session cookie and the subscription drift apart.** A member who signs in as someone else could get the first member's notices. Mitigate by tying each row to the session that created it, so sign-out and a new sign-in remove it.

## Shared component inventory

- `public/service_worker.js`: add `push` and `notificationclick` listeners to the existing worker; no second worker. `pwa_registration.js` stays as is.
- `BrowserRuntimeAssetRenderer::manifest()`: used unchanged; it already sets `display: standalone`.
- `templates/pages/preferences.php`: one more `account-row` with the same markup. Send a test appears only while the row is On.
- `UsageReportStore` and `InboxDatabaseConfig`: the pattern for a private SQLite file created on first use. `UsageReportsExportExclusionTest` is the pattern for proving the export and backups leave it out.
- `ApiRouteCatalog`: registers the subscribe, unsubscribe and test routes as member routes, which feeds `agent_api.md`.
- `ViewerSessions::signOut()`: the hook that removes the device's row.
- `FeatureFlagRegistry`: the new flag, off by default, in the existing group order.
- HTTP: the `curl` requests already used by `InstanceArchiveDownloader` and the LLM providers. No new client.

## User flow

1. A signed-in member opens Preferences in the installed app and sees Notifications: Off.
2. They tap it; iPhone asks for permission, and they allow it.
3. The row shows On, and a Send a test button appears.
4. They tap Send a test; a ZenMemes notification appears, and a tap opens the app.
5. Later they tap the row again, or sign out; the row shows Off, and the server deletes the subscription.

## Success criteria

- Tests cover the RFC examples, the store (add, replace, delete, the 10-device cap), host refusal, 404/410 deletion, sign-out deletion, the flag-off 404s and the export exclusion.
- A browser test shows the row hidden with the flag off and Off-then-On with a stubbed permission.
- The maintainer's iPhone check passes the evidence in Known user and environment.

## Enhancement requests

- None yet.

## Study snapshots and approval

- 2026-11-23 UTC, Step 2 handoff: **0.75** projected total, up from 0.70. New understanding: the sign-out hook tied to the session, the 10-device cap, and the PHP 8.1 check on key import. Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified this session. Baseline `9f73088e`. **Approved Step 2** by the maintainer, 2026-11-23.

*— Claude · 2026-11-23 UTC · Model: claude-opus-5-5*
