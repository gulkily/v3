> **Feature plan:** [Step 1](./offline_compose_step1_solution_assessment.md) · [Step 2](./offline_compose_step2_feature_description.md) · [Step 3](./offline_compose_step3_development_plan.md) · [Step 4](./offline_compose_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./offline_compose_debug_log.md))

Effort study: `railspike-effort` / `v3/offline_compose`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 4 in progress on `feat/offline-compose`.

Cycle estimate: 0.80

# Step 4: Implementation Summary — Offline compose

## Step 4 Entry

2026-11-11T13:45:00Z. Step 3 explicitly approved. Branch `feat/offline-compose` from `a8436cb5` (main moved from `f3abd425` while planning; the new commits are DM instant load and do not touch the Outbox, worker or composer paths except unrelated parts of `browser_signing.js`). Planning commit `5520b837`. Whole-cycle **0.80** reaffirmed. Stage originals 0.05/0.12/0.10/0.10/0.10/0.15/0.10/0.06 from the [plan](./offline_compose_step3_development_plan.md), copied, not re-rated. Same R1/P2, single exposed Claude rater.

## Stage 1 - Board and thread composers save to the Outbox

**Original estimate: 0.05**
**Retrospective: 0.05**

- Changes:
  - The board, board list and thread pages load `outbox_store.js`, `outbox_storage.js` and `outbox_compose.js`, and Forte loads `outbox_compose.js`. Before, these composers rendered Save to Outbox but left it disabled.
  - `outbox_compose.js` binds when it runs, even after the page has finished parsing, and binds each form once. The same late-load trap broke Ctrl+Enter for approved members in PF-20261108-B02.
- Verification:
  - Browser replay against a local server with the worker active: load the board and a thread online, go offline, type, Save to Outbox. Both now show "draft saved" and the item appears in Tools → Outbox. Before: the button stayed disabled. `/compose/thread` and `/compose/reply` still work.
  - New `OfflineOutboxComposeTest::testScriptLoadedAfterParsingBindsEachComposerOnce`: fails on the previous script, passes now.
  - `LocalAppSmokeTest` asserts board and thread load `outbox_compose.js`.
  - `php tests/run.php OfflineOutboxComposeTest LocalAppSmokeTest`: 170 run, 170 passed.
  - `./v3 test --smoke OfflineOutboxComposeTest OfflineOutboxStateTest` (before the new test): 453 run, 453 passed. Full suite not run.
  - Forte composer: not replayed in a browser; covered only by the script list. Pending for Stage 7.
- Notes:
  - Pressing Post offline still shows the account error on the board. That is Stage 2.
- Estimate details: original scope unchanged; no revision.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*

## Stage 2 - Post without a connection saves to the Outbox

**Original estimate: 0.12**
**Retrospective: 0.10**

- Changes:
  - `outbox_compose.js`: `itemFromForm` builds an Outbox item from any composer (the two Save to Outbox handlers now share it), and `queueFromForm` saves it as queued.
  - `browser_signing.js`: when the browser is offline, Post queues the text, clears the composer and its saved draft, and says "Saved on this device, not sent. It will be sent when you're back online." with an Outbox link. Anonymous posts and images can't go through the Outbox, so they stay in the composer with "You're offline. Your text stays here; post it once you're back online."
  - A request that fails to reach the server while the browser thinks it is online is never queued, since the server may have accepted it. The composer keeps the text and says "Couldn't reach the server. Your text is still here: try again, or Save to Outbox to send it later." instead of "Couldn't create your account". The account failure now keeps its cause so this can be told apart.
- Verification:
  - Browser replay, local server with the worker: Post offline on a thread, the board (lazily loaded signing), `/compose/reply` and `/compose/thread`. Each showed the "not sent" message, cleared the text and added a queued item. Opening Tools → Outbox online sent all four, each "accepted".
  - Anonymous Post offline on the board: text kept, offline message, nothing queued.
  - Online with every POST refused: refusal shown, text kept, nothing queued. Online with every POST dropped: "Couldn't reach the server", text kept, nothing queued.
  - New `OfflineOutboxComposeTest::testQueueFromFormSavesAQueuedItemAndRefusesImages`.
  - `./v3 test --smoke OfflineOutboxComposeTest OfflineOutboxSendTest OfflineOutboxStateTest BrowserSigningNormalizationTest AccountKeyPageHooksTest LocalAppSmokeTest`: 555 run, 555 passed. Full suite not run.
  - `tests/browser/compose_attempt_replay.mjs` (duplicate-post protection): desktop and phone pass.
  - Signed-in member queueing and reloading offline: not yet replayed; the run used a fresh guest key. Pending for Stage 7.
- Notes:
  - Plan said to classify by the fetch failure, never by `navigator.onLine` alone. In practice `navigator.onLine === false` is the only case where a request certainly never left, so it is the only one that queues. A dropped request while "online" is reported, not queued, which is safer against duplicates.
- Estimate details: original scope unchanged; anonymous and image limits were already in the plan's risks.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*

## Stage 3 - Waiting posts send on reconnect; threads list their unsent replies

**Original estimate: 0.10**
**Retrospective: 0.10**

- Changes:
  - `outbox_compose.js` sends posts waiting in the Outbox when the connection returns, and when any page with a composer loads online with waiting posts. It loads the board's lazy signing scripts first when needed, and uses the existing `processQueuedOutbox` with its cross-tab lock.
  - A thread page lists its own replies still on this device above its reply composer, with a dashed border and "Saved on this device, not sent. Outbox". The list refreshes after Post offline, Save to Outbox and each send, and disappears once they are accepted.
  - The board loads `outbox_sender.js`; small styles for the list in `site.css`.
- Verification:
  - Browser replay, local server with the worker: a reply posted offline on a thread appears in the unsent list; on reconnect it is published once and shows in the thread, and the list is gone.
  - Ten replies queued offline, then sent with every first `create_prepared_post` response dropped after the server handled it: one published copy each, all ten "accepted" in the Outbox. One send was cut off by a page navigation and went out after the sender's existing two-minute requeue, still once.
  - `./v3 test --smoke OfflineOutboxComposeTest OfflineOutboxSendTest OfflineOutboxStateTest LocalAppSmokeTest ReactionOutboxTest`: 464 run, 464 passed. Full suite not run.
  - `tests/browser/composers.mjs`: pass.
  - Two tabs sending at once: relies on the existing `navigator.locks` delivery lock; not replayed separately.
- Notes:
  - A post interrupted mid-send waits up to two minutes before retrying. That is the sender's existing stale-send window, left unchanged.
- Estimate details: original scope unchanged.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*

## Stage 4 - New post and Reply open the cached compose pages offline

**Original estimate: 0.10**
**Retrospective: 0.08**

- Changes:
  - The worker caches `/compose/thread` and `/compose/reply` as they render with no target, alongside the reader shell, and finds their scripts and styles the same way. Both pages count toward the revision check, so a deploy that changes the composer refreshes them.
  - Offline, a GET navigation to either page gets the cached copy at the usual address. For a reply, the worker fills `thread_id` and `parent_id` from the address (escaped), as the server would. Form posts are never answered from the cache.
  - Offline cache version 15 → 16, so existing installs fetch the new pages.
- Verification:
  - Browser replay, local server with the worker: online board and thread, go offline, click New post (from both) and a Reply link. Each opens the compose page at its usual address. The reply page carries `root-001`/`reply-001`, Save to Outbox is enabled, and Post queues with "Saved on this device, not sent". On reconnect the Outbox sent them.
  - Deploy simulation: edited the New post template on disk and browsed three pages online; the cached page picked up the change. Template restored.
  - New `OfflineNavigationWorkerTest::testComposeNavigationServesTheCachedPageWithTheReplyTargetFromTheAddress` (target fill, escaping, POST not served). The refresh test also checks both pages are cached.
  - `./v3 test --smoke OfflineNavigationWorkerTest LocalAppSmokeTest OfflineOutboxComposeTest OfflineSnapshotBootstrapTest`: 466 run, 466 passed. Full suite not run.
  - `SiteProfileRegistryTest` failed after the stage commit: it still expected cache version 15. Fixed in a follow-up commit; 9 run, 9 passed.
- Notes:
  - The Step 3 risk about one visitor's details in a cached page: the bare pages hold no per-visitor content (two fetches were byte-identical), and the cache belongs to that browser profile.
  - Offline, the reply page shows only "Reply" with no quoted post yet. That is Stage 5.
- Estimate details: original scope unchanged.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*

## Stage 5 - The offline Reply page shows the post being answered

**Original estimate: 0.10**
**Retrospective: 0.08**

- Changes:
  - New `offline_reply_context.js` on the reply page. On the copy the worker serves offline, it reads the post being answered and its thread from the saved snapshot. It fills the same heading and quote the server renders: "Reply to <author>", author · date · in <thread title>, and the body, clamped when long. When the post isn't saved: "This thread isn't saved on this device. You can still write your reply and save it to send later."
  - The worker marks compose pages it serves from the device with `data-offline-copy`, so the script never touches a page the server rendered.
  - The page's asset paths now include the SQLite runtime, which the reader already caches.
- Verification:
  - Browser replay, offline: a reply to a reply and a reply to the thread root both show the same heading and quote as the online page. An unsaved thread shows the "isn't saved" note, and the composer still works. Text typed offline is still there after an offline reload, with the quote. Online, a reply page with no parent is left alone. Screenshot compared with the online page.
  - `OfflineNavigationWorkerTest` checks the `data-offline-copy` mark on both pages.
  - `./v3 test --smoke OfflineNavigationWorkerTest LocalAppSmokeTest OfflineOutboxComposeTest SiteProfileRegistryTest`: 472 run, 472 passed. `LazyComposeSigningTest OpenPgpAssetSmokeProbeTest OpenPgpLoaderTest`: 12 passed. `tests/browser/composers.mjs`: pass. Full suite not run.
- Notes:
  - Dates use the month and day in UTC, like the online page; the online page also adds a full-date tooltip, which the offline quote leaves out.
- Estimate details: original scope unchanged.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*

## Stage 6 - The offline board and thread show the same composers as online

**Original estimate: 0.15**
**Revised estimate (2026-11-11, new understanding): 0.18** — interrupted sends waited two minutes, which the reader replay exposed.
**Retrospective: 0.18**

- Changes:
  - The reader page carries the board's compact New post composer and the thread's reply composer as templates, from the same partials as online. The reader mounts them where the online pages have them: New post under the board toolbar, the reply composer under the thread. It sets the thread target and binds signing, inline expand and Outbox the same way.
  - Offline posts get Reply links before Like, as online, pointing at the reply page the worker serves offline.
  - The reader page loads `compose.css`, `inline_reply_form.js` and `outbox_compose.js`. `inline_reply_form.js` exposes a bind for composers added later.
  - Outbox delivery: while the cross-tab delivery lock is held, an item left "sending" by a closed or navigated page is retried at once instead of after two minutes. A manual Send in the Outbox now takes the same lock and rereads the item first, so an item another tab already sent is not sent again.
  - The composer templates render only when the page renderer is present, so browser tests that include the reader template bare keep working.
- Verification:
  - Browser replay at 390px and 1280px: type online on the board and a thread, go offline, open them in the reader. One composer each, with the text typed online restored, Save to Outbox enabled and Post queueing. Unsent replies are listed under the thread, and offline posts carry Reply links. After reconnecting on the same page, all four items were accepted within 20 seconds and the unsent list cleared.
  - Online and offline screenshots compared side by side: same composer markup and controls. The reader's own differences (date format, no intro banner) predate this cycle.
  - `OfflineSnapshotThreadPresentationTest` now finds Like after Reply and checks the Reply links.
  - `./v3 test --smoke OfflineOutboxSendTest ReactionOutboxTest OfflineOutboxPresentationTest OfflineSnapshotPresentationTest OfflineSnapshotThreadPresentationTest LocalAppSmokeTest OfflineNavigationWorkerTest OfflineOutboxComposeTest`: 485 run, 485 passed. `tests/browser/composers.mjs`: pass. Full suite not run.
  - `tests/browser/offline_board_toolbar.cjs`: fails at "word97 390px: Tags at the right". It fails the same way on `origin/main` (`a8436cb5`), so it is a baseline failure, not from this cycle.
- Notes:
  - The offline board composer starts collapsed when it restores a draft, as the online one does after a reload.
- Estimate details: original scope plus the interrupted-send fix, found by this stage's replay and needed for "sent once on reconnect" in the approved outcome.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*
