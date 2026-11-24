> **Feature plan:** [Step 1](./push_subscribe_step1_solution_assessment.md) · [Step 2](./push_subscribe_step2_feature_description.md) · [Step 3](./push_subscribe_step3_development_plan.md) · [Step 4](./push_subscribe_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/push_subscribe`; host ledger [push_subscribe.md](../effort_ledger/push_subscribe.md). Standing P2 enrollment.

Extended estimates: enabled. Stage values are stage work only; they are not summed into the cycle estimate.

**Cycle estimate: 0.75**

Branch: `feature/push-subscribe`, from `origin/main` at `9f73088e`. Planning commit `80cadf01`.

Step 4 entry, 2026-11-24 UTC: **0.75** reaffirmed from Step 3. Claude, single exposed rater.

Dependency delta so far: zero. No package, manifest or lock is added; the sender uses PHP's bundled `openssl`.

## Stage 1 - Encryption and VAPID signing

**Original estimate: 0.15**
**Retrospective: 0.10**

- Changes:
  - `src/ForumRewrite/Push/P256Key.php`: P-256 keys moved in and out of OpenSSL as DER (public point to SPKI, scalar plus point to SEC 1), and ECDSA signatures between DER and 64-byte form.
  - `src/ForumRewrite/Push/WebPushCrypto.php`: `encrypt()` (RFC 8291 aes128gcm, one 4096-byte record, plaintext capped at 3000 bytes), `vapidToken()`, `verifyVapidToken()`, `vapidAuthorization()`, base64url helpers.
  - `tests/WebPushCryptoTest.php`, listed in `tests/run.php`.
- Verification:
  - Passed: `./v3 test WebPushCryptoTest`, 5 of 5. The Appendix A body matches byte for byte; the RFC 8292 example token verifies and a changed one fails; a fresh message decrypts the way a browser would.
  - Passed: `./v3 test --smoke WebPushCryptoTest`, 492 of 492.
  - PHP 8.1: no local binary, so pending. Every OpenSSL and hash function used dates from PHP 7.3 or older (`openssl_pkey_derive` 7.3, `hash_hkdf` 7.1.2, `aes-128-gcm` with tag 7.1, `openssl_pkey_new` with `curve_name` 7.1). Typed `OpenSSLAsymmetricKey` needs PHP 8.0.
- Notes: smaller than planned, because the probe had already settled the byte formats.
- Estimate details: original scope as in Step 3; no revisions; driver was the DER handling.

*— Claude · 2026-11-24 UTC · Model: claude-opus-5-5*

## Stage 2 - VAPID key and sender

**Original estimate: 0.10**
**Retrospective: 0.10**

- Changes:
  - `PushStorageConfig`: `state/private/push.sqlite3` (or `PUSH_DATABASE_PATH`) and `push_vapid.pem` beside it.
  - `PushVapidKeys`: makes the key pair on first use under a file lock, writes it 0600 through a temporary file, and gives the raw public key.
  - `PushSubscription` (endpoint plus the two browser keys, checked), `PushEndpointPolicy` (https on the Apple, Google, Mozilla and Microsoft push hosts only), `PushSendResult` (sent, gone for 404 and 410, refused with a short reason such as Apple's `BadJwtToken`).
  - `PushSender::send()`: token for the endpoint's origin, valid 12 hours; `TTL` 24 hours; `Urgency`; payload capped at 3000 bytes of JSON.
  - `tests/PushSenderTest.php`, listed in `tests/run.php`.
- Verification:
  - Passed: `./v3 test PushSenderTest WebPushCryptoTest`, 10 of 10. The request's token verifies with the site key and names the right audience; the body decrypts as a browser would; unknown hosts, plain http, look-alike hosts, credentials in the URL and bare addresses are refused without a request; the key survives a reload with mode 0600.
  - Passed: the stream transport posted to a local `php -S` server and read its 410 and body.
- Notes:
  - Deviation from Step 2's inventory: the plan named `curl`, but no code under `src/` uses `curl`. The sender follows `PublicKeyPublishDestination`'s PHP stream request instead (TLS verified, no redirects), so the host needs no `curl` extension.
  - The local stub stands in for a push service; Apple itself is checked only in Stage 5's iPhone replay.
- Estimate details: original scope as in Step 3; no revisions.

*— Claude · 2026-11-24 UTC · Model: claude-opus-5-5*

## Stage 3 - Store, flag, routes and the session check

**Original estimate: 0.20**
**Retrospective: 0.25**

- Changes:
  - `PushSubscriptionStore` in `state/private/push.sqlite3` (or `PUSH_DATABASE_PATH`, added to `PrivateConfigSchema`): one row per endpoint with username, key, session key and browser keys. Named-column inserts. A second member on the same device takes the row over; past 10 devices per username, the least recently refreshed go.
  - Flag `FORUM_PUSH_ENABLED` ("Notifications"), off by default, in the Forum experience group after Backlog.
  - `PushApiController`: `GET /api/push/key`, `POST /api/push/subscribe`, `POST /api/push/unsubscribe`, `POST /api/push/test`. Any signed-in member; POSTs need JSON with `X-Requested-With: ForumPush` and are refused cross-site, as `/api/inbox/catch_up` is. Unknown push services get 422. The test checks that the row's session is still signed in as its key, and deletes the row when it is not or when the push service answers 404 or 410.
  - `ViewerSessionStore::sessionKey()` and `signedInIdentityForKey()`, with `ViewerSessions` wrappers. `/api/clear_identity` deletes the session's rows after signing out.
  - `ApiRouteCatalog` entries and a "Notifications (browser push)" section in `docs/reference/agent_api.md`.
- Verification:
  - Passed: `./v3 test PushApiTest`, 7 of 7: store add, take-over, cap and delete; on, test, off; 401, 405, 403 (missing header, cross-site), 400 and 422; sign-out then a new sign-in drops the device; 403 from Apple is reported and kept, 410 deletes; all four routes 404 with the flag off; the store and key are Git-ignored and absent from a backup archive and an offline snapshot.
  - Passed: `./v3 test --smoke PushApiTest PushSenderTest WebPushCryptoTest ApiRouteCatalogTest FeatureFlagEvaluatorTest ViewerSessionStoreTest`, 504 of 504; `PrivateConfigCommandTest PrivateConfigSchemaTest DeploymentSettingsTest`, 25 of 25.
  - Not covered by a test: the `/api/clear_identity` delete runs through `Application` against the checkout's own state, which tests avoid. The send-time session check, which is tested, still stops delivery if that delete is missed.
- Notes:
  - In-scope refinement: a fourth route, `GET /api/push/key`, because the page needs the site key before the browser can subscribe.
  - Change from Step 3: no forward chain is followed. A same-key sign-in keeps its session, and any other sign-in or sign-out clears the old one, so checking the row's own session is enough.
  - Pre-existing failure fixed (maintainer rule): `PrivateConfigCommandTest::testEverySchemaKeyIsInTheTemplate…` failed on `9f73088e` too, because `FORUM_PUBLISHED_IMAGE_ROOT` (from the image-serving cycle) had no template example. Added one beside `FORUM_IMAGE_ROOT`.
- Estimate details: original scope as in Step 3; retrospective higher for the extra route and the pre-existing test fix.

*— Claude · 2026-11-24 UTC · Model: claude-opus-5-5*

## Stage 4 - Preferences row and worker

**Original estimate: 0.20**
**Retrospective: 0.20**

- Changes:
  - `templates/pages/preferences.php`: a Notifications `account-row` with a state and two actions (Send a test, Turn on or Turn off), rendered only with the flag on. The inline script shows it before paint only for a browser with a saved key and push support, with the last known state from `forum_push_state` (Blocked when permission was refused).
  - `public/assets/push_preferences.js`, loaded only with the flag on: Turn on asks permission first, inside the tap, then subscribes with the site key and saves; Turn off unsubscribes and tells the server; Send a test shows "Test sent", "Refused: <reason>", or Off when the server says the device is gone. On load, a subscription the browser still holds is saved again, so it follows the current sign-in and a changed site key.
  - `public/service_worker.js`: `push` shows every message, with "ZenMemes" / "Something new" when the payload can't be read and only same-site page addresses; `notificationclick` focuses an open window, else opens the message's page.
  - `public/assets/preferences.css`: the row's actions wrap together at the right on a phone.
  - `ToolsPageController::preferences()` passes `pushEnabled`.
  - New `tests/browser/push_preferences.mjs`.
- Verification:
  - Passed: `CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/push_preferences.mjs`: no row with the flag off; hidden without a key; nothing asked on load; on, test, reload (state drawn before paint with the page script blocked, then saved again), off; Blocked; a refusal shown; gone unsubscribes the browser; the worker's four payload cases and both tap cases.
  - Passed: `node tests/browser/preferences.mjs` (existing page unchanged with the flag off); `./v3 test --smoke PreferencesPageTest PushApiTest`, 496 of 496.
  - Looked at 375 to 380 px in Gloss, Gloss Dark and Word97: the title stays on one line and the actions sit under the state.
  - Navigation icons: none added, so `theme-nav-icons.css` is unchanged.
  - Not tested here: a real browser's push service. The stubs stand in for `PushManager` and `Notification`.
- Notes:
  - The plan said "a tap on the row"; the row has explicit Turn on and Turn off buttons instead, like the other rows' actions.
  - `npm install` of the existing `playwright-core` dev dependency was run locally to run the browser tests; no manifest or lock changed (`package-lock.json` is ignored).
- Estimate details: original scope as in Step 3; no revisions.

*— Claude · 2026-11-24 UTC · Model: claude-opus-5-5*

## Stage 5 - Concepts, checks and handoff

**Original estimate: 0.10**
**Retrospective: 0.10**

- Changes:
  - New `docs/concepts/push_subscription.md`; `viewer_session.md` gains session keys; `feature_flags.md` gains the flag as an example.
  - `docs/plans/notifications_checklist.md`: cycle 2 status, the iPhone check, and the maintainer's open feed question with what web push allows.
  - Effort ledger closeout.
- Verification:
  - Passed: `./v3 test --smoke WebPushCryptoTest PushSenderTest PushApiTest ApiRouteCatalogTest FeatureFlagEvaluatorTest ViewerSessionStoreTest PrivateConfigCommandTest PrivateConfigSchemaTest PreferencesPageTest`, 521 of 521. The full suite was not run (maintainer runs it).
  - Passed: `git diff --check origin/main..HEAD`; every source link in `push_subscription.md` resolves.
  - Pending, maintainer: merge, deploy, read `PHP_VERSION` on prod (the code needs PHP 8.0 or later and uses OpenSSL functions from 7.3), turn the flag on, and run the iPhone check below.
- iPhone check (the Known user and environment evidence from Step 2):
  1. On zenmemes.com, Tools → Feature Flags → Notifications: on.
  2. On the iPhone SE, open ZenMemes from the Home Screen, then Tools → Preferences.
  3. Notifications → Turn on → Allow. The row shows On.
  4. Send a test. The row shows "Test sent", and a ZenMemes notification arrives.
  5. Tap the notification: the app comes to the front.
  6. Turn off. The row shows Off, and Send a test disappears.
  - If step 4 shows "Refused: <reason>", that reason is Apple's (for example `BadJwtToken`); report it, and the fix is a follow-up cycle.
- Notes: Safari outside the installed app shows the row too, but iPhone allows push only in the Home Screen app; the guide for that is cycle 3 (`push_ios_guide`).

**Cycle retrospective: 0.80** (projected 0.75). Delivered the approved scope plus `GET /api/push/key` and a fix for a test that failed on `main`. Maintainer comfortable-fit judgment pending.

Dependency delta: zero. No package, manifest or lock changed; `playwright-core` was already a dev dependency.

*— Claude · 2026-11-24 UTC · Model: claude-opus-5-5*
