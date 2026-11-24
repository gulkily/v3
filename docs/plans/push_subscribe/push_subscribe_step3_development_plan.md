> **Feature plan:** [Step 1](./push_subscribe_step1_solution_assessment.md) · [Step 2](./push_subscribe_step2_feature_description.md) · [Step 3](./push_subscribe_step3_development_plan.md) · [Step 4](./push_subscribe_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/push_subscribe`; host ledger [push_subscribe.md](../effort_ledger/push_subscribe.md). Standing P2 enrollment.

Extended estimates: enabled. Stage values are stage work only; they are not summed into the cycle estimate.

**Cycle estimate: 0.75**

Concepts touched: [feature flags](../../concepts/feature_flags.md) (changed in Stage 3); [viewer session](../../concepts/viewer_session.md) (changed in Stage 3: a subscription follows its session); push subscription (new entry `docs/concepts/push_subscription.md`, Stage 5). Feedback: none.

Change since Step 2 approval: none in scope. One detail found while planning: a new sign-in replaces the session row (`replaced_by_hash`), so a subscription follows that chain to the current session instead of holding one fixed session.

## Completion Contract

- Normal entry: Preferences → Notifications in the installed app at zenmemes.com.
- End-to-end outcome: On asks permission once, stores the subscription, and shows Send a test. The test shows a "ZenMemes" notification on that device; a tap opens the app.
- Recovery: blocked permission shows Off and "Blocked", and a tap tries again. A 404 or 410 from the push service deletes the row, and the switch shows Off. Off and sign-out stop all sending to the device.
- Flag off: the row is hidden, the three routes return 404, and nothing is sent.
- Deployment: a normal deploy. No migration, no rebuild; `state/private/push.sqlite3` and the VAPID key appear on first use.
- Release: merged and deployed with the flag off. The maintainer turns it on and runs Stage 5's iPhone check; until then the outcome on iPhone is pending.

## Key Risks

- **High risk:** Apple refuses our token or message. Impact: nothing arrives on iPhone. Early validation: Stage 2 runs the full send against a local stub push service, and Stage 5 against Apple. Mitigation: Send a test shows the push service's status and reason word, so a refusal is visible on the phone.
- **High risk:** a member gets another member's notices on a shared device. Early validation: Stage 3 tests sign-out and a new sign-in. Mitigation: each send checks that the row's session, followed forward, is still signed in as that username; otherwise it deletes the row.
- The sender fails on PHP 8.1, which no local binary covers. Mitigation: build every key from DER bytes, using only functions from PHP 7.3 or older. Stage 1 records the function list, and Stage 5 reads `PHP_VERSION` on prod.
- iPhone drops a subscription after a push without a notification. Mitigation: Stage 4's worker always shows one, with a plain fallback.

## Stage 1

**Estimate: 0.15**

- Goal: the server can encrypt a push message and sign a VAPID token, proven by the RFC examples.
- Dependencies: none.
- Expected changes: new `src/ForumRewrite/Push/WebPushCrypto.php` with `encrypt(string $plaintext, string $userPublicKey, string $authSecret): string` (RFC 8291 aes128gcm, one record) and `vapidToken(string $audience, string $subject, int $expiresAt, string $privateKeyPem): string` (RFC 8292 ES256). Test seams take the salt and the sender key. A key helper turns raw P-256 points into DER and back, and turns a DER signature into the 64-byte form.
- Verification approach: `WebPushCryptoTest`: the Appendix A message byte for byte; the RFC 8292 example token verifies with its key; a fresh token verifies; bad key lengths are refused.
- Estimate details (extended mode only): driver: byte formats (DER, raw points, signatures); uncertainty: low, since the probe passed.
- Risks or open questions:
  - Impact: a PHP 8.1 gap. Early warning: the recorded function list. Mitigation: DER keys only.
- Canonical components/API contracts touched: PHP `openssl` and `hash_hkdf`; no new dependency.

## Stage 2

**Estimate: 0.10**

- Goal: the server sends one message to one endpoint and reports what happened.
- Dependencies: Stage 1.
- Expected changes: `PushVapidKeys` makes the key pair on first use in `state/private/push_vapid.pem` (mode 0600) and gives the public key in raw form. `PushSender::send(PushSubscription $subscription, array $payload): PushSendResult` refuses non-`https` or unknown hosts, posts with `TTL`, `Urgency` and the VAPID header through `curl`, and maps 201 to sent, 404 and 410 to gone, and other codes to refused with the reason.
- Verification approach: `PushSenderTest` against a local stub server: headers present, the body decrypts with the test browser key, host refusal, and the gone and refused mappings.
- Estimate details (extended mode only): driver: the stub server; uncertainty: low.
- Risks or open questions:
  - Impact: the first High risk. Early warning: decrypting the stub's body. Mitigation: the status on Send a test.
- Canonical components/API contracts touched: the `curl` options used by `InstanceArchiveDownloader`; `state/private/` as in `InboxDatabaseConfig`.

## Stage 3

**Estimate: 0.20**

- Goal: a member's device subscribes, unsubscribes and gets a test, behind the flag.
- Dependencies: Stage 2.
- Expected changes: `PushSubscriptionStore` in `state/private/push.sqlite3`, one row per endpoint: username, key fingerprint, session hash, browser keys, created and last-sent times. Named-column inserts; at most 10 rows per username, oldest dropped. Flag `FORUM_PUSH_ENABLED`, off. Member routes: `POST /api/push/subscribe`, `POST /api/push/unsubscribe`, `POST /api/push/test`, same-origin only, in `ApiRouteCatalog` and `agent_api.md`. The test reads the session forward and deletes the row when it no longer matches. `ViewerSessions::signOut()` deletes the session's rows.
- Verification approach: `PushSubscriptionStoreTest` (add, replace, cap, delete) and `PushApiTest` (flag-off 404, non-member 403, cross-site 403, unknown host refused, sign-out and new sign-in delete, gone deletes). An export test in the shape of `UsageReportsExportExclusionTest`.
- Estimate details (extended mode only): driver: the session check and the route set; uncertainty: medium.
- Risks or open questions:
  - Impact: the second High risk. Early warning: the sign-in-as-someone-else test. Mitigation: the send-time session check.
- Canonical components/API contracts touched: `ApiRouteCatalog`, `FeatureFlagRegistry`, `ViewerSessions`, the `UsageReportStore` store pattern.

## Stage 4

**Estimate: 0.20**

- Goal: the Notifications row and the worker work in a browser.
- Dependencies: Stage 3.
- Expected changes: in `preferences.php`, one `account-row`, rendered hidden and shown before paint only for a signed-in member, with the flag on and push support. A tap asks permission, subscribes with the server key, and posts the subscription. The row reads On, Off or Blocked, and Send a test shows the result in a few words. If the server key changed, the row subscribes again quietly. `service_worker.js` gets `push` (show the payload, or a plain "ZenMemes" notice if it can't be read) and `notificationclick` (focus an open app window, else open `/`).
- Verification approach: a new `tests/browser/push_preferences.mjs` with stubbed permission and push manager: hidden with the flag off, Off to On to Off, Blocked, test result shown. A worker test for the fallback notice. Gloss, Gloss Dark and Word97 at 380px. Navigation icons: none added.
- Estimate details (extended mode only): driver: browser API stubs; uncertainty: medium.
- Risks or open questions:
  - Impact: a silent push drops the subscription on iPhone. Early warning: the fallback test. Mitigation: every push shows a notification.
- Canonical components/API contracts touched: the Preferences row markup and its `localStorage` helpers, `public/service_worker.js` (no second worker), `pwa_registration.js` unchanged.

## Stage 5

**Estimate: 0.10**

- Goal: the concepts are recorded, and the iPhone check is ready for the maintainer.
- Dependencies: Stage 4.
- Expected changes: `docs/concepts/push_subscription.md`; updates to `feature_flags.md` and `viewer_session.md`; the checklist and ledger. The Step 4 summary lists the iPhone replay steps.
- Verification approach: smoke set plus every test named above; `git diff --check`; concept paths checked against code. Pending until the maintainer runs it: on prod, read `PHP_VERSION`, turn the flag on, then on the iPhone SE do On, Send a test, tap, Off, and Send a test again, which the server refuses.
- Estimate details (extended mode only): driver: documentation; uncertainty: low for the docs, high for Apple until the replay.
- Risks or open questions:
  - Impact: Apple refuses. Early warning: the status on Send a test. Mitigation: a follow-up fix cycle with Apple's reason.
- Canonical components/API contracts touched: the concept inventory.

## Study snapshot

- 2026-11-24 UTC, Step 3 handoff: **0.75** projected total, unchanged. Five stages, stage work 0.15, 0.10, 0.20, 0.20, 0.10 (not summed). Claude, single exposed rater. Scope v1, reference R1, baseline `9f73088e`. Awaiting `Approved Step 3`.

*— Claude · 2026-11-24 UTC · Model: claude-opus-5-5*
